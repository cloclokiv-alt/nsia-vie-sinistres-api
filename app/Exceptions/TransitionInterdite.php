<?php

namespace App\Exceptions;

use App\Enums\StatutDossier;
use DomainException;

/**
 * Levée quand on demande au dossier un changement de statut que le circuit
 * n'autorise pas — soit la transition n'existe pas, soit une condition
 * métier n'est pas remplie.
 */
class TransitionInterdite extends DomainException
{
    public static function entre(StatutDossier $depuis, StatutDossier $vers): self
    {
        return new self(sprintf(
            'Un dossier « %s » ne peut pas passer à « %s ».',
            $depuis->label(),
            $vers->label(),
        ));
    }

    public static function parce(string $raison): self
    {
        return new self($raison);
    }
}
