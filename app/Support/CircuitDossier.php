<?php

namespace App\Support;

use App\Enums\MotifRejet;
use App\Enums\StatutBeneficiaire;
use App\Enums\StatutDossier;
use App\Enums\TypeEvenement;
use App\Exceptions\TransitionInterdite;
use App\Models\Beneficiaire;
use App\Models\DossierSinistre;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Le circuit du dossier sinistre : seule porte d'entrée pour changer un statut.
 *
 * Écrire `$dossier->statut = ...` court-circuiterait à la fois les contrôles
 * métier et le journal de traçabilité. Tout passe par `transiterVers()`.
 */
final class CircuitDossier
{
    /**
     * Fait passer le dossier au statut demandé, après vérification de la
     * transition et des conditions propres au statut visé.
     *
     * @param  array{motif?: MotifRejet|null, motif_detail?: string|null, commentaire?: string|null}  $options
     */
    public static function transiterVers(
        DossierSinistre $dossier,
        StatutDossier $cible,
        User $auteur,
        array $options = [],
    ): DossierSinistre {
        $depuis = $dossier->statut;

        if ($depuis === $cible) {
            return $dossier;
        }

        if (! $depuis->peutAllerVers($cible)) {
            throw TransitionInterdite::entre($depuis, $cible);
        }

        self::verifierConditions($dossier, $cible, $options);

        return DB::transaction(function () use ($dossier, $depuis, $cible, $auteur, $options): DossierSinistre {
            $champs = ['statut' => $cible];

            if ($cible->estDecide() && ! $depuis->estDecide()) {
                $champs['decide_le'] = now();
                $champs['decide_par_id'] = $auteur->id;
            }

            if ($cible->exigeMotif()) {
                $champs['motif_rejet'] = $options['motif'] ?? null;
                $champs['motif_rejet_detail'] = $options['motif_detail'] ?? null;
            }

            if ($cible === StatutDossier::Clos) {
                $champs['clos_le'] = now();
            }

            // Réouverture : le dossier repart en instruction, la décision précédente
            // est effacée mais reste lisible dans le journal.
            if ($depuis->estDecide() && $cible === StatutDossier::EnInstruction) {
                $champs['clos_le'] = null;
                $champs['decide_le'] = null;
                $champs['decide_par_id'] = null;
                $champs['motif_rejet'] = null;
                $champs['motif_rejet_detail'] = null;
            }

            $dossier->forceFill($champs)->save();

            JournalDossier::enregistrer(
                $dossier,
                self::typeEvenement($depuis, $cible),
                self::description($depuis, $cible, $options),
                $auteur,
                array_filter([
                    'depuis' => $depuis->value,
                    'vers' => $cible->value,
                    'motif' => ($options['motif'] ?? null)?->value,
                    'commentaire' => $options['commentaire'] ?? null,
                ], fn ($valeur) => $valeur !== null),
            );

            return $dossier;
        });
    }

    /**
     * Affecte le dossier à un gestionnaire (ou le désaffecte avec null).
     */
    public static function affecter(DossierSinistre $dossier, ?User $gestionnaire, User $auteur): DossierSinistre
    {
        if ($dossier->statut->estFige()) {
            throw TransitionInterdite::parce('Un dossier réglé ou clos ne peut plus être réaffecté.');
        }

        $dossier->forceFill(['gestionnaire_id' => $gestionnaire?->id])->save();

        JournalDossier::enregistrer(
            $dossier,
            TypeEvenement::Affectation,
            $gestionnaire === null
                ? 'Dossier retiré de son gestionnaire.'
                : sprintf('Dossier affecté à %s.', $gestionnaire->name),
            $auteur,
            ['gestionnaire_id' => $gestionnaire?->id],
        );

        return $dossier;
    }

    /**
     * Les conditions métier propres au statut visé, au-delà de la simple
     * existence de la transition.
     *
     * @param  array<string, mixed>  $options
     */
    private static function verifierConditions(DossierSinistre $dossier, StatutDossier $cible, array $options): void
    {
        match ($cible) {
            StatutDossier::ControleMedical => self::exigerControleMedicalPertinent($dossier),
            StatutDossier::EnLiquidation => self::exigerDossierComplet($dossier),
            StatutDossier::Valide => self::exigerLiquidation($dossier),
            StatutDossier::EnReglement => self::exigerMontantsVentiles($dossier),
            StatutDossier::Regle => self::exigerTousLesReglementsPayes($dossier),
            StatutDossier::Rejete, StatutDossier::SansSuite => self::exigerMotif($options),
            default => null,
        };
    }

    private static function exigerControleMedicalPertinent(DossierSinistre $dossier): void
    {
        if (! $dossier->nature->exigeControleMedical()) {
            throw TransitionInterdite::parce(sprintf(
                'Un sinistre « %s » ne relève pas du contrôle médical.',
                $dossier->nature->label(),
            ));
        }
    }

    private static function exigerDossierComplet(DossierSinistre $dossier): void
    {
        $manquantes = $dossier->piecesManquantes();

        if ($manquantes->isNotEmpty()) {
            throw TransitionInterdite::parce(sprintf(
                'Il manque %d pièce(s) obligatoire(s) : %s.',
                $manquantes->count(),
                $manquantes->take(5)->map->libelleAffiche()->join(', '),
            ));
        }

        if (! $dossier->repartitionEstComplete()) {
            throw TransitionInterdite::parce(sprintf(
                'La répartition entre bénéficiaires totalise %s %% au lieu de 100 %%.',
                number_format($dossier->quotePartTotale() / 100, 2, ',', ' '),
            ));
        }
    }

    private static function exigerLiquidation(DossierSinistre $dossier): void
    {
        if ($dossier->capital_liquide_xaf <= 0) {
            throw TransitionInterdite::parce('Le capital doit être liquidé avant de valider la prise en charge.');
        }

        if (! $dossier->repartitionEstComplete()) {
            throw TransitionInterdite::parce('La répartition entre bénéficiaires doit totaliser 100 %.');
        }
    }

    private static function exigerMontantsVentiles(DossierSinistre $dossier): void
    {
        $sansMontant = $dossier->beneficiairesRetenus()
            ->filter(fn (Beneficiaire $b) => $b->montant_du_xaf <= 0);

        if ($sansMontant->isNotEmpty()) {
            throw TransitionInterdite::parce(sprintf(
                '%d bénéficiaire(s) sans montant arrêté : relancez la liquidation.',
                $sansMontant->count(),
            ));
        }
    }

    private static function exigerTousLesReglementsPayes(DossierSinistre $dossier): void
    {
        $impayes = $dossier->beneficiairesRetenus()
            ->filter(fn (Beneficiaire $b) => $b->statut !== StatutBeneficiaire::Regle);

        if ($impayes->isNotEmpty()) {
            throw TransitionInterdite::parce(sprintf(
                '%d bénéficiaire(s) n\'ont pas encore été réglés.',
                $impayes->count(),
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function exigerMotif(array $options): void
    {
        if (! ($options['motif'] ?? null) instanceof MotifRejet) {
            throw TransitionInterdite::parce('Un rejet ou un classement sans suite doit être motivé.');
        }
    }

    private static function typeEvenement(StatutDossier $depuis, StatutDossier $cible): TypeEvenement
    {
        return match (true) {
            $cible === StatutDossier::Clos => TypeEvenement::DossierClos,
            $depuis->estDecide() && $cible === StatutDossier::EnInstruction => TypeEvenement::DossierReouvert,
            $cible->exigeMotif() || $cible === StatutDossier::Valide => TypeEvenement::Decision,
            default => TypeEvenement::ChangementStatut,
        };
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function description(StatutDossier $depuis, StatutDossier $cible, array $options): string
    {
        $base = sprintf('Statut : « %s » → « %s ».', $depuis->label(), $cible->label());

        $motif = $options['motif'] ?? null;

        if ($motif instanceof MotifRejet) {
            $base .= ' Motif : '.$motif->label().'.';
        }

        return $base;
    }
}
