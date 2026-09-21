<?php

namespace App\Enum;

/**
 * A quel titre la personne percoit le capital.
 *
 * La qualite commande les pieces d'etat civil a reclamer pour etablir le lien avec
 * l'assure : un conjoint prouve le mariage, un heritier produit un acte de notoriete.
 */
enum QualiteBeneficiaire: string
{
    case Designe = 'Désigné';
    case Conjoint = 'Conjoint survivant';
    case Enfant = 'Enfant';
    case Ascendant = 'Ascendant';
    case Heritier = 'Héritier légal';
    case Creancier = 'Créancier';
    case Autre = 'Autre';

    /**
     * Pieces propres a cette qualite, en plus du socle reclame a tout beneficiaire.
     *
     * @return list<TypePiece>
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

    /** Un creancier est une banque : ni piece d'identite, ni acte de naissance. */
    public function estPersonneMorale(): bool
    {
        return self::Creancier === $this;
    }

    /**
     * Socle reclame a cette personne, filiation comprise.
     *
     * @return list<TypePiece>
     */
    public function piecesAttendues(): array
    {
        $socle = $this->estPersonneMorale()
            ? [TypePiece::Rib]
            : [TypePiece::PieceIdentiteBeneficiaire, TypePiece::Rib];

        return array_values(array_unique([...$socle, ...$this->piecesDeFiliation()], SORT_REGULAR));
    }
}
