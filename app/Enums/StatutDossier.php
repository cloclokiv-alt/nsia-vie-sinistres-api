<?php

namespace App\Enums;

/**
 * Le circuit d'un dossier sinistre, de l'ouverture à la clôture.
 *
 * Les transitions autorisées sont décrites ici et nulle part ailleurs :
 * un dossier ne peut pas être réglé sans être passé par la liquidation,
 * ni clos sans décision.
 */
enum StatutDossier: string
{
    case Ouvert = 'ouvert';
    case PiecesAFournir = 'pieces_a_fournir';
    case EnInstruction = 'en_instruction';
    case ControleMedical = 'controle_medical';
    case EnLiquidation = 'en_liquidation';
    case Valide = 'valide';
    case EnReglement = 'en_reglement';
    case Regle = 'regle';
    case Rejete = 'rejete';
    case SansSuite = 'sans_suite';
    case Clos = 'clos';

    public function label(): string
    {
        return match ($this) {
            self::Ouvert => 'Ouvert',
            self::PiecesAFournir => 'En attente de pièces',
            self::EnInstruction => 'En instruction',
            self::ControleMedical => 'Au contrôle médical',
            self::EnLiquidation => 'En liquidation',
            self::Valide => 'Prise en charge validée',
            self::EnReglement => 'En cours de règlement',
            self::Regle => 'Réglé',
            self::Rejete => 'Rejeté',
            self::SansSuite => 'Classé sans suite',
            self::Clos => 'Clos',
        };
    }

    /**
     * Les statuts que le dossier peut prendre ensuite.
     *
     * @return array<int, self>
     */
    public function suivants(): array
    {
        return match ($this) {
            self::Ouvert => [self::PiecesAFournir, self::EnInstruction, self::SansSuite],
            self::PiecesAFournir => [self::EnInstruction, self::SansSuite],
            self::EnInstruction => [self::PiecesAFournir, self::ControleMedical, self::EnLiquidation, self::Rejete, self::SansSuite],
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

    /**
     * Le dossier attend-il encore une action du service sinistres ?
     */
    public function estEnCours(): bool
    {
        return ! in_array($this, [self::Regle, self::Rejete, self::SansSuite, self::Clos], true);
    }

    /**
     * Une décision (prise en charge, rejet, classement) a-t-elle été prononcée ?
     */
    public function estDecide(): bool
    {
        return in_array($this, [self::Valide, self::EnReglement, self::Regle, self::Rejete, self::SansSuite, self::Clos], true);
    }

    /**
     * Les statuts qui exigent un motif de rejet documenté.
     */
    public function exigeMotif(): bool
    {
        return in_array($this, [self::Rejete, self::SansSuite], true);
    }

    /**
     * À partir de ce statut, le dossier est figé : plus aucune pièce ni
     * bénéficiaire ne peut être modifié sans réouverture.
     */
    public function estFige(): bool
    {
        return in_array($this, [self::Regle, self::Clos], true);
    }
}
