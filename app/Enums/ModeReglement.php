<?php

namespace App\Enums;

enum ModeReglement: string
{
    case VirementBancaire = 'virement_bancaire';
    case Cheque = 'cheque';
    case MobileMoney = 'mobile_money';
    case Especes = 'especes';

    public function label(): string
    {
        return match ($this) {
            self::VirementBancaire => 'Virement bancaire',
            self::Cheque => 'Chèque',
            self::MobileMoney => 'Mobile Money',
            self::Especes => 'Espèces',
        };
    }

    /**
     * Les modes qui exigent des coordonnées enregistrées avant l'émission.
     */
    public function exigeCoordonnees(): bool
    {
        return in_array($this, [self::VirementBancaire, self::MobileMoney], true);
    }

    /**
     * Plafond réglementaire du paiement en espèces, en francs CFA.
     * Au-delà, le règlement passe obligatoirement par un canal tracé.
     */
    public const PLAFOND_ESPECES_XAF = 500000;
}
