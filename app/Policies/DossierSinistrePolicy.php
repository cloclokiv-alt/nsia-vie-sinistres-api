<?php

namespace App\Policies;

use App\Models\DossierSinistre;
use App\Models\User;

class DossierSinistrePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->actif;
    }

    public function view(User $user, DossierSinistre $dossier): bool
    {
        return $user->actif;
    }

    public function create(User $user): bool
    {
        return $user->actif && $user->peutInstruire();
    }

    /**
     * Le gestionnaire attitré, ou tout instructeur si le dossier n'est
     * affecté à personne. Un dossier figé n'est plus modifiable.
     */
    public function update(User $user, DossierSinistre $dossier): bool
    {
        if (! $user->actif || $dossier->statut->estFige()) {
            return false;
        }

        if ($user->estAdministrateur()) {
            return true;
        }

        if (! $user->peutInstruire()) {
            return false;
        }

        return $dossier->gestionnaire_id === null || $dossier->gestionnaire_id === $user->id;
    }

    /**
     * Prononcer une prise en charge, un rejet ou un classement.
     */
    public function decider(User $user, DossierSinistre $dossier): bool
    {
        return $user->actif && $user->peutDecider();
    }

    /**
     * Affecter le dossier à un gestionnaire.
     */
    public function affecter(User $user, DossierSinistre $dossier): bool
    {
        return $user->actif && ! $dossier->statut->estFige() && $user->peutDecider();
    }

    /**
     * Arrêter le capital et le ventiler entre les bénéficiaires.
     */
    public function liquider(User $user, DossierSinistre $dossier): bool
    {
        return $this->update($user, $dossier);
    }

    /**
     * Émettre un règlement à un bénéficiaire.
     */
    public function regler(User $user, DossierSinistre $dossier): bool
    {
        return $user->actif && $user->peutRegler();
    }

    /**
     * Le journal de traçabilité est consultable par tout agent : c'est ce qui
     * permet de répondre à un assuré sans rouvrir le dossier physique.
     */
    public function consulterJournal(User $user, DossierSinistre $dossier): bool
    {
        return $user->actif;
    }
}
