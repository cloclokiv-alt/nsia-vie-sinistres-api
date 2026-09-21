<?php

namespace App\Policies;

use App\Models\Courrier;
use App\Models\User;

class CourrierPolicy
{
    /**
     * Le registre est consultable par tout agent en activité : l'accueil doit
     * pouvoir dire à un assuré où en est son courrier.
     */
    public function viewAny(User $user): bool
    {
        return $user->actif;
    }

    public function view(User $user, Courrier $courrier): bool
    {
        return $user->actif;
    }

    /**
     * Seul le bureau de réception enregistre au registre.
     */
    public function create(User $user): bool
    {
        return $user->actif && $user->peutReceptionner();
    }

    /**
     * Un courrier déjà orienté vers un dossier n'est plus modifiable :
     * le registre est un document d'archive.
     */
    public function update(User $user, Courrier $courrier): bool
    {
        if (! $user->actif) {
            return false;
        }

        if ($courrier->estOriente() && ! $user->estAdministrateur()) {
            return false;
        }

        return $user->peutReceptionner();
    }

    /**
     * Orienter un courrier vers un dossier relève du service sinistres.
     */
    public function orienter(User $user, Courrier $courrier): bool
    {
        return $user->actif && $user->peutInstruire();
    }
}
