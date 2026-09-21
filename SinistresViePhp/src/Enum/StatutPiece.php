<?php

namespace App\Enum;

/** Avancement d'une piece, de son attente a son controle. */
enum StatutPiece: string
{
    case Attendue = 'Attendue';
    case Recue = 'Reçue';
    case Conforme = 'Conforme';
    case NonConforme = 'Non conforme';
    case SansObjet = 'Sans objet';

    /** Cle courte, pour les classes CSS des pastilles. */
    public function cle(): string
    {
        return match ($this) {
            self::Attendue => 'attendue',
            self::Recue => 'recue',
            self::Conforme => 'conforme',
            self::NonConforme => 'refusee',
            self::SansObjet => 'sans-objet',
        };
    }

    /** La piece empeche-t-elle encore le dossier d'avancer ? */
    public function bloqueInstruction(): bool
    {
        return match ($this) {
            self::Conforme, self::SansObjet => false,
            default => true,
        };
    }

    /** Un fichier doit-il etre attache pour que ce statut ait un sens ? */
    public function exigeFichier(): bool
    {
        return match ($this) {
            self::Recue, self::Conforme, self::NonConforme => true,
            default => false,
        };
    }
}
