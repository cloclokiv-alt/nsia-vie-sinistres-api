<?php

namespace App\Enums;

/**
 * Situation de la police au jour de la déclaration : c'est le premier contrôle du gestionnaire.
 */
enum StatutContrat: string
{
    case EnVigueur = 'en_vigueur';
    case Reduit = 'reduit';
    case Suspendu = 'suspendu';
    case Resilie = 'resilie';
    case Echu = 'echu';
    case Rachete = 'rachete';

    public function label(): string
    {
        return match ($this) {
            self::EnVigueur => 'En vigueur',
            self::Reduit => 'Réduit',
            self::Suspendu => 'Suspendu pour primes impayées',
            self::Resilie => 'Résilié',
            self::Echu => 'Arrivé à échéance',
            self::Rachete => 'Racheté',
        };
    }

    /**
     * Le contrat ouvre-t-il encore droit à une prestation ?
     * Un contrat réduit garde une garantie, diminuée ; un contrat suspendu, résilié
     * ou racheté n'en ouvre plus.
     */
    public function couvre(): bool
    {
        return in_array($this, [self::EnVigueur, self::Reduit, self::Echu], true);
    }
}
