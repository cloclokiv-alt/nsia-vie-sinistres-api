<?php

namespace App\Enums;

/**
 * À quel titre la personne perçoit le capital. Détermine les pièces d'état civil
 * à réclamer pour établir le lien avec l'assuré.
 */
enum QualiteBeneficiaire: string
{
    case Designe = 'designe';
    case Conjoint = 'conjoint';
    case Enfant = 'enfant';
    case Ascendant = 'ascendant';
    case Heritier = 'heritier';
    case Creancier = 'creancier';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Designe => 'Désigné nominativement',
            self::Conjoint => 'Conjoint survivant',
            self::Enfant => 'Enfant',
            self::Ascendant => 'Ascendant',
            self::Heritier => 'Héritier légal',
            self::Creancier => 'Créancier (banque prêteuse)',
            self::Autre => 'Autre',
        };
    }

    /**
     * Les pièces propres à cette qualité, en plus du socle réclamé à tout bénéficiaire.
     *
     * @return array<int, TypePiece>
     */
    public function piecesDeFiliation(): array
    {
        return match ($this) {
            self::Conjoint => [TypePiece::ActeMariage],
            self::Enfant, self::Ascendant => [TypePiece::ActeNaissance],
            self::Heritier => [TypePiece::ActeNotoriete],
            self::Creancier => [TypePiece::DecompteBancaire],
            self::Designe, self::Autre => [],
        };
    }

    /**
     * Un créancier est une personne morale : ni pièce d'identité, ni acte de naissance.
     */
    public function estPersonneMorale(): bool
    {
        return $this === self::Creancier;
    }
}
