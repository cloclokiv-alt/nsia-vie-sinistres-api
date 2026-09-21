<?php

namespace App\Enums;

enum StatutPiece: string
{
    case Attendue = 'attendue';
    case Recue = 'recue';
    case Conforme = 'conforme';
    case NonConforme = 'non_conforme';
    case SansObjet = 'sans_objet';

    public function label(): string
    {
        return match ($this) {
            self::Attendue => 'Attendue',
            self::Recue => 'Reçue, à contrôler',
            self::Conforme => 'Conforme',
            self::NonConforme => 'Non conforme',
            self::SansObjet => 'Sans objet',
        };
    }

    /**
     * La pièce bloque-t-elle encore l'instruction du dossier ?
     * Une pièce conforme ou déclarée sans objet ne bloque plus.
     */
    public function bloqueInstruction(): bool
    {
        return ! in_array($this, [self::Conforme, self::SansObjet], true);
    }

    /**
     * Un fichier doit-il être attaché pour que ce statut ait un sens ?
     */
    public function exigeFichier(): bool
    {
        return in_array($this, [self::Recue, self::Conforme, self::NonConforme], true);
    }
}
