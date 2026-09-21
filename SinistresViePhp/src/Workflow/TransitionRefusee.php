<?php

namespace App\Workflow;

use App\Enum\StatutDossier;

/**
 * Levee quand le circuit refuse un changement de statut : soit la transition n'existe
 * pas, soit une condition metier n'est pas remplie.
 *
 * Ce n'est pas un incident technique mais une regle qui s'applique : le controleur la
 * rend a l'ecran, et elle n'a rien a faire dans le journal d'erreurs.
 */
final class TransitionRefusee extends \DomainException
{
    public static function entre(StatutDossier $depuis, StatutDossier $vers): self
    {
        return new self(sprintf(
            'Un dossier « %s » ne peut pas passer à « %s ».',
            $depuis->value,
            $vers->value,
        ));
    }

    public static function parce(string $raison): self
    {
        return new self($raison);
    }
}
