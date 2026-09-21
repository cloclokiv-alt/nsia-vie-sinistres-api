<?php

namespace App\Enum;

/** Situation de la police au jour du sinistre : c'est le premier controle du gestionnaire. */
enum StatutContrat: string
{
    case EnVigueur = 'En vigueur';
    case Reduit = 'Réduit';
    case Suspendu = 'Suspendu';
    case Resilie = 'Résilié';
    case Echu = 'Échu';
    case Rachete = 'Racheté';

    /**
     * La police ouvre-t-elle encore droit a une prestation ?
     *
     * Un contrat reduit garde une garantie, diminuee ; un contrat suspendu pour primes
     * impayees, resilie ou rachete n'en ouvre plus aucune.
     */
    public function couvre(): bool
    {
        return match ($this) {
            self::EnVigueur, self::Reduit, self::Echu => true,
            default => false,
        };
    }
}
