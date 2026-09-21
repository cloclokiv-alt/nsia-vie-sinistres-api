<?php

namespace App\Models;

use App\Enums\ModeReglement;
use Database\Factories\ReglementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Le paiement fait à un bénéficiaire. Un bénéficiaire n'est réglé qu'une fois :
 * la contrainte d'unicité en base double la règle métier, parce qu'un double
 * paiement ne se rattrape pas.
 */
#[Fillable([
    'dossier_sinistre_id', 'beneficiaire_id', 'montant_xaf', 'mode',
    'coordonnees', 'reference', 'emis_le', 'emis_par_id',
])]
class Reglement extends Model
{
    /** @use HasFactory<ReglementFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => ModeReglement::class,
            'montant_xaf' => 'integer',
            'emis_le' => 'datetime',
            'paye_le' => 'datetime',
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
     * @return BelongsTo<Beneficiaire, $this>
     */
    public function beneficiaire(): BelongsTo
    {
        return $this->belongsTo(Beneficiaire::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function emisPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emis_par_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function payePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paye_par_id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function enAttenteDePaiement(Builder $query): void
    {
        $query->whereNull('paye_le');
    }

    public function estPaye(): bool
    {
        return $this->paye_le !== null;
    }

    /**
     * Constate le paiement effectif (idempotent) : c'est le comptable qui le pose,
     * une fois le virement passé ou le chèque retiré.
     */
    public function constaterPaiement(User $auteur, ?string $reference = null): void
    {
        if ($this->estPaye()) {
            return;
        }

        $this->forceFill([
            'paye_le' => now(),
            'paye_par_id' => $auteur->id,
            'reference' => $reference ?? $this->reference,
        ])->save();
    }
}
