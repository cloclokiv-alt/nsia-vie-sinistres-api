<?php

namespace App\Enum;

/** Canal par lequel le beneficiaire est paye. */
enum ModeReglement: string
{
    case VirementBancaire = 'Virement bancaire';
    case Cheque = 'Chèque';
    case MobileMoney = 'Mobile Money';
    case Especes = 'Espèces';

    /**
     * Plafond du paiement en especes, en francs CFA. Au-dela, le reglement passe
     * obligatoirement par un canal trace.
     */
    public const PLAFOND_ESPECES_XAF = 500000;

    /** Le mode exige-t-il des coordonnees enregistrees avant l'emission ? */
    public function exigeCoordonnees(): bool
    {
        return match ($this) {
            self::VirementBancaire, self::MobileMoney => true,
            default => false,
        };
    }

    /** Le montant peut-il etre regle par ce canal ? */
    public function accepte(int $montantXaf): bool
    {
        return self::Especes !== $this || $montantXaf <= self::PLAFOND_ESPECES_XAF;
    }
}
