<?php

namespace App\Models;

use App\Enums\CanalReception;
use App\Policies\CourrierPolicy;
use Database\Factories\CourrierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une entrée du registre du bureau de réception. C'est le point de départ
 * de toute la chaîne : sans courrier enregistré, pas de dossier.
 */
#[Fillable([
    'numero_ordre', 'canal', 'date_reception', 'expediteur_nom', 'expediteur_qualite',
    'expediteur_telephone', 'expediteur_adresse', 'objet', 'nombre_pieces',
    'numero_police_declare', 'recu_par_id', 'dossier_sinistre_id', 'observation',
])]
#[RouteKey('numero_ordre')]
#[UsePolicy(CourrierPolicy::class)]
class Courrier extends Model
{
    /** @use HasFactory<CourrierFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'canal' => CanalReception::class,
            'date_reception' => 'datetime',
            'accuse_remis_le' => 'datetime',
            'oriente_le' => 'datetime',
            'nombre_pieces' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recuPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recu_par_id');
    }

    /**
     * @return BelongsTo<DossierSinistre, $this>
     */
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(DossierSinistre::class, 'dossier_sinistre_id');
    }

    /**
     * Les courriers qui n'ont pas encore été rattachés à un dossier :
     * c'est la corbeille de travail du service sinistres chaque matin.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function enAttenteOrientation(Builder $query): void
    {
        $query->whereNull('dossier_sinistre_id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function recherche(Builder $query, ?string $terme): void
    {
        $query->when(filled($terme), function (Builder $query) use ($terme): void {
            $motif = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $terme).'%';

            $query->where(fn (Builder $query) => $query
                ->where('numero_ordre', 'like', $motif)
                ->orWhere('expediteur_nom', 'like', $motif)
                ->orWhere('numero_police_declare', 'like', $motif)
                ->orWhere('objet', 'like', $motif));
        });
    }

    public function estOriente(): bool
    {
        return $this->dossier_sinistre_id !== null;
    }

    /**
     * Rattache le courrier à un dossier et horodate l'orientation.
     */
    public function orienterVers(DossierSinistre $dossier): void
    {
        $this->forceFill([
            'dossier_sinistre_id' => $dossier->id,
            'oriente_le' => $this->oriente_le ?? now(),
        ])->save();
    }

    /**
     * Constate la remise de l'accusé de réception à l'expéditeur (idempotent).
     */
    public function remettreAccuse(): void
    {
        if ($this->accuse_remis_le !== null) {
            return;
        }

        $this->forceFill(['accuse_remis_le' => now()])->save();
    }
}
