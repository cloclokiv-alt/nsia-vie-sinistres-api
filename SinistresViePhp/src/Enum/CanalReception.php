<?php

namespace App\Enum;

/** Par ou la declaration est arrivee au bureau du courrier. */
enum CanalReception: string
{
    case Guichet = 'Guichet';
    case CourrierPostal = 'Courrier postal';
    case Courriel = 'Courriel';
    case Agence = 'Agence';
    case Courtier = 'Courtier';
    case BanquePartenaire = 'Banque partenaire';

    /**
     * L'accuse de reception est-il remis en main propre ?
     *
     * Au guichet et en agence, l'expediteur repart avec. Pour les autres canaux, il
     * lui est renvoye, et sa remise se constate dans un second temps.
     */
    public function remisEnMainPropre(): bool
    {
        return match ($this) {
            self::Guichet, self::Agence => true,
            default => false,
        };
    }
}
