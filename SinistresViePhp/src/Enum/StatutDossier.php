<?php

namespace App\Enum;

/**
 * Circuit d'un dossier sinistre, de l'ouverture a la cloture.
 *
 * La valeur de chaque cas est le libelle exact stocke en base : c'est lui que verifie
 * la contrainte CK_Dossiers_Statut.
 *
 * Les transitions autorisees sont decrites ici et nulle part ailleurs. Un dossier ne
 * peut etre regle sans etre passe par la liquidation, ni rejete sans motif. Toute
 * transition passe par App\Workflow\CircuitDossier, seul a pouvoir changer un statut.
 */
enum StatutDossier: string
{
    case Ouvert = 'Ouvert';
    case PiecesAFournir = 'En attente de pièces';
    case EnInstruction = 'En instruction';
    case ControleMedical = 'Au contrôle médical';
    case EnLiquidation = 'En liquidation';
    case Valide = 'Prise en charge validée';
    case EnReglement = 'En cours de règlement';
    case Regle = 'Réglé';
    case Rejete = 'Rejeté';
    case SansSuite = 'Classé sans suite';
    case Clos = 'Clos';

    /** Cle courte, pour les classes CSS des pastilles. */
    public function cle(): string
    {
        return match ($this) {
            self::Ouvert => 'ouvert',
            self::PiecesAFournir => 'attente-pieces',
            self::EnInstruction => 'instruction',
            self::ControleMedical => 'medical',
            self::EnLiquidation => 'liquidation',
            self::Valide => 'valide',
            self::EnReglement => 'reglement',
            self::Regle => 'regle',
            self::Rejete => 'rejete',
            self::SansSuite => 'sans-suite',
            self::Clos => 'clos',
        };
    }

    /**
     * Statuts que le dossier peut prendre ensuite.
     *
     * @return list<self>
     */
    public function suivants(): array
    {
        return match ($this) {
            self::Ouvert => [self::PiecesAFournir, self::EnInstruction, self::SansSuite],
            self::PiecesAFournir => [self::EnInstruction, self::SansSuite],
            self::EnInstruction => [
                self::PiecesAFournir, self::ControleMedical, self::EnLiquidation,
                self::Rejete, self::SansSuite,
            ],
            self::ControleMedical => [self::EnInstruction, self::EnLiquidation, self::Rejete],
            self::EnLiquidation => [self::EnInstruction, self::Valide, self::Rejete],
            self::Valide => [self::EnLiquidation, self::EnReglement],
            self::EnReglement => [self::Valide, self::Regle],
            self::Regle => [self::Clos],
            self::Rejete => [self::EnInstruction, self::Clos],
            self::SansSuite => [self::EnInstruction, self::Clos],
            self::Clos => [],
        };
    }

    public function peutAllerVers(self $cible): bool
    {
        return in_array($cible, $this->suivants(), true);
    }

    /** Le dossier attend-il encore une action du service sinistres ? */
    public function estEnCours(): bool
    {
        return match ($this) {
            self::Regle, self::Rejete, self::SansSuite, self::Clos => false,
            default => true,
        };
    }

    /** Une decision — prise en charge, rejet ou classement — a-t-elle ete prononcee ? */
    public function estDecide(): bool
    {
        return match ($this) {
            self::Valide, self::EnReglement, self::Regle,
            self::Rejete, self::SansSuite, self::Clos => true,
            default => false,
        };
    }

    /** Les statuts qui exigent un motif documente, opposable a l'assure. */
    public function exigeMotif(): bool
    {
        return match ($this) {
            self::Rejete, self::SansSuite => true,
            default => false,
        };
    }

    /**
     * A partir de ce statut le dossier est fige : plus aucune piece ni beneficiaire
     * ne peut etre modifie sans reouverture.
     */
    public function estFige(): bool
    {
        return match ($this) {
            self::Regle, self::Clos => true,
            default => false,
        };
    }

    /**
     * Les statuts en cours, pour les filtres de la grille.
     *
     * @return list<self>
     */
    public static function enCours(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $s): bool => $s->estEnCours()));
    }
}
