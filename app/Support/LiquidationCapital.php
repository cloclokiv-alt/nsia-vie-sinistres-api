<?php

namespace App\Support;

use App\Enums\StatutBeneficiaire;
use App\Enums\TypeEvenement;
use App\Exceptions\TransitionInterdite;
use App\Models\Beneficiaire;
use App\Models\DossierSinistre;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Arrête le capital dû et le ventile entre les bénéficiaires.
 *
 * Le montant proposé vient du contrat, mais le gestionnaire peut l'arrêter à une
 * autre valeur : sur un contrat emprunteur, c'est le capital restant dû figurant
 * au décompte bancaire qui fait foi, pas le capital garanti d'origine.
 */
final class LiquidationCapital
{
    /**
     * Montant théorique proposé au gestionnaire avant arbitrage.
     */
    public static function montantPropose(DossierSinistre $dossier): int
    {
        $dossier->loadMissing('contrat');

        return $dossier->contrat->capitalMobilisable();
    }

    /**
     * Arrête le capital et ventile les montants dus. Rejoue sans effet de bord :
     * relancer une liquidation écrase proprement la précédente tant que le
     * dossier n'est pas validé.
     *
     * @return array<array-key, int> montants dus par identifiant de bénéficiaire
     */
    public static function appliquer(DossierSinistre $dossier, ?int $capitalXaf, User $auteur): array
    {
        $capital = $capitalXaf ?? self::montantPropose($dossier);

        if ($capital <= 0) {
            throw TransitionInterdite::parce('Le capital à liquider doit être strictement positif.');
        }

        $beneficiaires = $dossier->beneficiairesRetenus();

        if ($beneficiaires->isEmpty()) {
            throw TransitionInterdite::parce('Aucun bénéficiaire retenu : impossible de liquider.');
        }

        $centiemes = $beneficiaires
            ->mapWithKeys(fn (Beneficiaire $b) => [$b->id => $b->quotePartEnCentiemes()])
            ->all();

        // Lève si la somme des quotes-parts ne fait pas exactement 100 %.
        $montants = RepartitionCapital::repartir($capital, $centiemes);

        DB::transaction(function () use ($dossier, $beneficiaires, $montants, $capital, $auteur): void {
            foreach ($beneficiaires as $beneficiaire) {
                $beneficiaire->forceFill([
                    'montant_du_xaf' => $montants[$beneficiaire->id],
                    'statut' => $beneficiaire->statut === StatutBeneficiaire::Regle
                        ? StatutBeneficiaire::Regle
                        : StatutBeneficiaire::Valide,
                ])->save();
            }

            $dossier->forceFill(['capital_liquide_xaf' => $capital])->save();

            JournalDossier::enregistrer(
                $dossier,
                TypeEvenement::Liquidation,
                sprintf(
                    'Capital arrêté à %s FCFA, réparti entre %d bénéficiaire(s).',
                    number_format($capital, 0, ',', ' '),
                    $beneficiaires->count(),
                ),
                $auteur,
                ['capital_xaf' => $capital, 'repartition' => $montants],
            );
        });

        return $montants;
    }
}
