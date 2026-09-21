<?php

namespace App\Workflow;

use App\Enum\MotifRejet;
use App\Enum\StatutDossier;

/**
 * Circuit du dossier sinistre : seule porte d'entree pour changer un statut.
 *
 * Ecrire le statut directement court-circuiterait a la fois les controles metier et le
 * journal de tracabilite. La table des transitions, elle, vit dans StatutDossier.
 *
 * La verification est separee de l'application : « verifier » ne touche a rien et se
 * teste seule, « appliquer » revient au service qui tient la transaction et le journal.
 */
final class CircuitDossier
{
    /**
     * Le dossier peut-il prendre ce statut ?
     *
     * @throws TransitionRefusee avec le motif exact, destine a l'ecran
     */
    public static function verifier(EtatDossier $etat, StatutDossier $cible, ?MotifRejet $motif = null): void
    {
        if ($etat->statut === $cible) {
            return;
        }

        if (!$etat->statut->peutAllerVers($cible)) {
            throw TransitionRefusee::entre($etat->statut, $cible);
        }

        match ($cible) {
            StatutDossier::ControleMedical => self::exigerControleMedicalPertinent($etat),
            StatutDossier::EnLiquidation => self::exigerDossierComplet($etat),
            StatutDossier::Valide => self::exigerLiquidation($etat),
            StatutDossier::EnReglement => self::exigerMontantsVentiles($etat),
            StatutDossier::Regle => self::exigerTousLesReglementsPayes($etat),
            StatutDossier::Rejete, StatutDossier::SansSuite => self::exigerMotif($motif),
            default => null,
        };
    }

    /** Variante sans exception, pour n'afficher a l'ecran que les boutons praticables. */
    public static function estPossible(EtatDossier $etat, StatutDossier $cible, ?MotifRejet $motif = null): bool
    {
        try {
            self::verifier($etat, $cible, $motif);

            return true;
        } catch (TransitionRefusee) {
            return false;
        }
    }

    private static function exigerControleMedicalPertinent(EtatDossier $etat): void
    {
        if (!$etat->nature->exigeControleMedical()) {
            throw TransitionRefusee::parce(sprintf(
                'Un sinistre « %s » ne relève pas du contrôle médical.',
                $etat->nature->value,
            ));
        }
    }

    private static function exigerDossierComplet(EtatDossier $etat): void
    {
        if ([] !== $etat->piecesManquantes) {
            throw TransitionRefusee::parce(sprintf(
                'Il manque %d pièce(s) obligatoire(s) : %s.',
                count($etat->piecesManquantes),
                implode(', ', array_slice($etat->piecesManquantes, 0, 5)),
            ));
        }

        if (!$etat->repartitionEstComplete()) {
            throw TransitionRefusee::parce(sprintf(
                'La répartition entre bénéficiaires totalise %s %% au lieu de 100 %%.',
                number_format($etat->quotePartTotaleCentiemes / 100, 2, ',', ' '),
            ));
        }
    }

    private static function exigerLiquidation(EtatDossier $etat): void
    {
        if ($etat->capitalLiquideXaf <= 0) {
            throw TransitionRefusee::parce(
                'Le capital doit être liquidé avant de valider la prise en charge.',
            );
        }

        if (!$etat->repartitionEstComplete()) {
            throw TransitionRefusee::parce(
                'La répartition entre bénéficiaires doit totaliser 100 %.',
            );
        }
    }

    private static function exigerMontantsVentiles(EtatDossier $etat): void
    {
        if ($etat->beneficiairesSansMontant > 0) {
            throw TransitionRefusee::parce(sprintf(
                '%d bénéficiaire(s) sans montant arrêté : relancez la liquidation.',
                $etat->beneficiairesSansMontant,
            ));
        }
    }

    private static function exigerTousLesReglementsPayes(EtatDossier $etat): void
    {
        if ($etat->beneficiairesNonRegles > 0) {
            throw TransitionRefusee::parce(sprintf(
                "%d bénéficiaire(s) n'ont pas encore été réglés.",
                $etat->beneficiairesNonRegles,
            ));
        }
    }

    private static function exigerMotif(?MotifRejet $motif): void
    {
        if (!$motif instanceof MotifRejet) {
            throw TransitionRefusee::parce(
                'Un rejet ou un classement sans suite doit être motivé.',
            );
        }
    }
}
