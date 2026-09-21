<?php

namespace App\Enums;

/**
 * Par où la déclaration est arrivée au bureau du courrier.
 */
enum CanalReception: string
{
    case Guichet = 'guichet';
    case CourrierPostal = 'courrier_postal';
    case Email = 'email';
    case Agence = 'agence';
    case Courtier = 'courtier';
    case BanquePartenaire = 'banque_partenaire';

    public function label(): string
    {
        return match ($this) {
            self::Guichet => 'Dépôt au guichet',
            self::CourrierPostal => 'Courrier postal',
            self::Email => 'Courriel',
            self::Agence => 'Agence NSIA',
            self::Courtier => 'Courtier',
            self::BanquePartenaire => 'Banque partenaire',
        };
    }

    /**
     * Un accusé de réception papier n'est remis en main propre qu'au guichet et en agence.
     * Pour les autres canaux, l'accusé est renvoyé à l'expéditeur.
     */
    public function accuseRemisEnMainPropre(): bool
    {
        return in_array($this, [self::Guichet, self::Agence], true);
    }
}
