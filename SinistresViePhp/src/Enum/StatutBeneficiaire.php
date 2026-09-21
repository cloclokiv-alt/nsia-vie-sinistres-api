<?php

namespace App\Enum;

/** Avancement d'un beneficiaire, de son identification a son reglement. */
enum StatutBeneficiaire: string
{
    case AIdentifier = 'À identifier';
    case Identifie = 'Identifié';
    case PiecesIncompletes = 'Pièces incomplètes';
    case Valide = 'Validé';
    case Regle = 'Réglé';
    case Ecarte = 'Écarté';

    /** Cle courte, pour les classes CSS des pastilles. */
    public function cle(): string
    {
        return match ($this) {
            self::AIdentifier => 'a-identifier',
            self::Identifie => 'identifie',
            self::PiecesIncompletes => 'incomplet',
            self::Valide => 'valide',
            self::Regle => 'regle',
            self::Ecarte => 'ecarte',
        };
    }

    /**
     * La personne entre-t-elle dans la repartition du capital ?
     * Un beneficiaire ecarte — renonciation, predeces, exclusion — en sort.
     */
    public function entreDansLaRepartition(): bool
    {
        return self::Ecarte !== $this;
    }

    /** Peut-on lui emettre un reglement ? */
    public function estPayable(): bool
    {
        return self::Valide === $this;
    }
}
