<?php

namespace App\Enums;

enum StatutBeneficiaire: string
{
    case AIdentifier = 'a_identifier';
    case Identifie = 'identifie';
    case PiecesIncompletes = 'pieces_incompletes';
    case Valide = 'valide';
    case Regle = 'regle';
    case Ecarte = 'ecarte';

    public function label(): string
    {
        return match ($this) {
            self::AIdentifier => 'À identifier',
            self::Identifie => 'Identifié',
            self::PiecesIncompletes => 'Pièces incomplètes',
            self::Valide => 'Validé, en attente de règlement',
            self::Regle => 'Réglé',
            self::Ecarte => 'Écarté',
        };
    }

    /**
     * Le bénéficiaire entre-t-il dans la répartition du capital ?
     * Un bénéficiaire écarté (renonciation, prédécès, exclusion) n'y entre plus.
     */
    public function entreDansLaRepartition(): bool
    {
        return $this !== self::Ecarte;
    }

    /**
     * Peut-on lui émettre un règlement ?
     */
    public function estPayable(): bool
    {
        return $this === self::Valide;
    }
}
