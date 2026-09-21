<?php

namespace App\Models;

use App\Enums\TypeEvenement;
use App\Support\JournalDossier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne du journal du dossier. Écriture seule : jamais modifiée,
 * jamais supprimée — c'est la pièce opposable en cas de contestation.
 *
 * On n'écrit pas dedans directement : {@see JournalDossier} est
 * le seul point d'entrée.
 */
#[Fillable(['dossier_sinistre_id', 'type', 'auteur_id', 'description', 'donnees'])]
class EvenementDossier extends Model
{
    /**
     * Un journal ne se corrige pas : pas de updated_at.
     */
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypeEvenement::class,
            'donnees' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DossierSinistre, $this>
     */
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(DossierSinistre::class, 'dossier_sinistre_id');
    }

    /**
     * Nul pour les écritures automatiques du système.
     *
     * @return BelongsTo<User, $this>
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
