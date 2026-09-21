<?php

namespace App\Support;

use App\Enums\TypeEvenement;
use App\Models\DossierSinistre;
use App\Models\EvenementDossier;
use App\Models\User;

/**
 * Seul point d'entrée du journal de traçabilité. On n'écrit jamais dans
 * EvenementDossier directement : passer par ici garantit qu'un événement
 * porte toujours un auteur identifiable et un contexte exploitable.
 */
final class JournalDossier
{
    /**
     * @param  array<string, mixed>  $donnees
     */
    public static function enregistrer(
        DossierSinistre $dossier,
        TypeEvenement $type,
        string $description,
        ?User $auteur = null,
        array $donnees = [],
    ): EvenementDossier {
        return EvenementDossier::create([
            'dossier_sinistre_id' => $dossier->id,
            'type' => $type,
            'auteur_id' => $auteur?->id,
            'description' => mb_substr($description, 0, 500),
            'donnees' => $donnees === [] ? null : $donnees,
        ]);
    }
}
