<?php

namespace App\Enum;

/** Produit Vie souscrit. Il commande ce qui est du au sinistre. */
enum TypeContrat: string
{
    case TemporaireDeces = 'Temporaire décès';
    case Mixte = 'Mixte';
    case Emprunteur = 'Emprunteur';
    case Retraite = 'Retraite';
    case Obseques = 'Obsèques';
    case EpargneEducation = 'Épargne éducation';

    /**
     * Sur un contrat emprunteur, le capital revient d'abord a la banque preteuse, a
     * hauteur du capital restant du : sans decompte bancaire, rien ne peut etre liquide.
     */
    public function exigeDecompteBancaire(): bool
    {
        return self::Emprunteur === $this;
    }

    /**
     * Les contrats d'epargne constituent une provision mathematique qui s'ajoute au
     * capital garanti dans le calcul de la prestation.
     */
    public function constitueEpargne(): bool
    {
        return match ($this) {
            self::Mixte, self::Retraite, self::EpargneEducation => true,
            default => false,
        };
    }
}
