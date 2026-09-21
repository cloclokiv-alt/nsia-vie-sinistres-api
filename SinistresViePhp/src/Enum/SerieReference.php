<?php

namespace App\Enum;

/**
 * Les deux registres numerotes de l'application.
 *
 * Chacun a son compteur, propre a l'exercice : le premier dossier de l'annee est
 * SIN26-0001 meme si le registre du courrier en est deja a COU26-0450. Chaque serie
 * se lit donc sans trou, comme l'attend un registre.
 */
enum SerieReference: string
{
    case Courrier = 'Courrier';
    case Sinistre = 'Sinistre';

    public function prefixe(): string
    {
        return match ($this) {
            self::Courrier => 'COU',
            self::Sinistre => 'SIN',
        };
    }
}
