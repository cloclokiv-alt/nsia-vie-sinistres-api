<?php

namespace App\Enums;

/**
 * Les produits Vie commercialisés. Le type conditionne ce qui est dû au sinistre.
 */
enum TypeContrat: string
{
    case TemporaireDeces = 'temporaire_deces';
    case Mixte = 'mixte';
    case Emprunteur = 'emprunteur';
    case Retraite = 'retraite';
    case Obseques = 'obseques';
    case EpargneEducation = 'epargne_education';

    public function label(): string
    {
        return match ($this) {
            self::TemporaireDeces => 'Temporaire décès',
            self::Mixte => 'Mixte (épargne et décès)',
            self::Emprunteur => 'Assurance décès emprunteur',
            self::Retraite => 'Retraite complémentaire',
            self::Obseques => 'Prévoyance obsèques',
            self::EpargneEducation => 'Épargne éducation',
        };
    }

    /**
     * Sur un contrat emprunteur, le capital revient d'abord à la banque prêteuse
     * à hauteur du capital restant dû : le décompte bancaire est indispensable.
     */
    public function exigeDecompteBancaire(): bool
    {
        return $this === self::Emprunteur;
    }

    /**
     * Les contrats d'épargne constituent une provision mathématique qui entre
     * dans le calcul de la prestation, en plus du capital garanti.
     */
    public function constitueEpargne(): bool
    {
        return in_array($this, [self::Mixte, self::Retraite, self::EpargneEducation], true);
    }
}
