<?php

namespace App\Policies;

use App\Models\DossierSinistre;
use App\Models\PieceJustificative;
use App\Models\User;

class PieceJustificativePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->actif;
    }

    /**
     * Les pièces médicales relèvent du secret : elles ne sont listées et
     * téléchargées que par le médecin-conseil.
     */
    public function view(User $user, PieceJustificative $piece): bool
    {
        if (! $user->actif) {
            return false;
        }

        return ! $piece->estMedicale() || $user->peutConsulterLeMedical();
    }

    /**
     * Déposer le scan d'une pièce : le bureau courrier comme le gestionnaire.
     */
    public function deposer(User $user, PieceJustificative $piece): bool
    {
        if (! $user->actif || ! $this->view($user, $piece)) {
            return false;
        }

        return $user->peutReceptionner() || $user->peutInstruire();
    }

    /**
     * Déclarer une pièce conforme ou non : c'est un acte d'instruction.
     * Une pièce médicale n'est contrôlée que par le médecin-conseil.
     */
    public function controler(User $user, PieceJustificative $piece): bool
    {
        if (! $user->actif) {
            return false;
        }

        if ($piece->estMedicale()) {
            return $user->peutConsulterLeMedical();
        }

        return $user->peutInstruire();
    }

    /**
     * Ajouter une pièce hors checklist (« autre pièce »).
     */
    public function create(User $user, DossierSinistre $dossier): bool
    {
        return $user->actif
            && ! $dossier->statut->estFige()
            && ($user->peutReceptionner() || $user->peutInstruire());
    }
}
