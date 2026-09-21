<?php

namespace App\Models;

use App\Enums\StatutContrat;
use App\Enums\TypeContrat;
use Database\Factories\ContratFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

#[Fillable([
    'numero_police', 'assure_id', 'souscripteur_nom', 'type', 'statut',
    'date_effet', 'date_echeance', 'capital_garanti_xaf', 'prime_xaf',
    'provision_mathematique_xaf', 'periodicite', 'date_derniere_prime',
    'carence_mois', 'organisme_preteur',
])]
#[RouteKey('numero_police')]
class Contrat extends Model
{
    /** @use HasFactory<ContratFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypeContrat::class,
            'statut' => StatutContrat::class,
            'date_effet' => 'date',
            'date_echeance' => 'date',
            'date_derniere_prime' => 'date',
            'capital_garanti_xaf' => 'integer',
            'prime_xaf' => 'integer',
            'provision_mathematique_xaf' => 'integer',
            'carence_mois' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Assure, $this>
     */
    public function assure(): BelongsTo
    {
        return $this->belongsTo(Assure::class);
    }

    /**
     * @return HasMany<DossierSinistre, $this>
     */
    public function dossiers(): HasMany
    {
        return $this->hasMany(DossierSinistre::class);
    }

    /**
     * La garantie jouait-elle au jour du sinistre ? Trois conditions cumulatives :
     * le contrat avait pris effet, il n'était pas éteint, et le délai de carence
     * était écoulé.
     */
    public function garantissaitLe(Carbon $date): bool
    {
        if (! $this->statut->couvre()) {
            return false;
        }

        if ($date->lt($this->date_effet)) {
            return false;
        }

        return ! $this->estSousCarenceLe($date);
    }

    /**
     * Le sinistre est-il survenu pendant le délai de carence suivant la prise d'effet ?
     */
    public function estSousCarenceLe(Carbon $date): bool
    {
        if ($this->carence_mois <= 0) {
            return false;
        }

        return $date->lt($this->date_effet->copy()->addMonths($this->carence_mois));
    }

    /**
     * Montant théoriquement dû avant abattements : capital garanti, augmenté de
     * l'épargne constituée sur les produits qui en accumulent.
     */
    public function capitalMobilisable(): int
    {
        return $this->capital_garanti_xaf
            + ($this->type->constitueEpargne() ? $this->provision_mathematique_xaf : 0);
    }
}
