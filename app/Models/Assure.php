<?php

namespace App\Models;

use Database\Factories\AssureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'nom', 'prenoms', 'date_naissance', 'lieu_naissance', 'sexe',
    'type_piece_identite', 'numero_piece_identite', 'telephone', 'email',
    'adresse', 'profession', 'date_deces',
])]
class Assure extends Model
{
    /** @use HasFactory<AssureFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'date_deces' => 'date',
        ];
    }

    /**
     * @return HasMany<Contrat, $this>
     */
    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class);
    }

    /**
     * « NGOMA Jean-Pierre », tel qu'il apparaît sur les courriers et les quittances.
     *
     * @return Attribute<string, never>
     */
    protected function nomComplet(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->nom.' '.$this->prenoms));
    }

    /**
     * Recherche sur l'état civil et le numéro de pièce d'identité : c'est ainsi
     * que le guichet retrouve un assuré quand le numéro de police est illisible.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function recherche(Builder $query, ?string $terme): void
    {
        $query->when(filled($terme), function (Builder $query) use ($terme): void {
            $motif = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $terme).'%';

            $query->where(fn (Builder $query) => $query
                ->where('nom', 'like', $motif)
                ->orWhere('prenoms', 'like', $motif)
                ->orWhere('numero_piece_identite', 'like', $motif));
        });
    }

    public function estDecede(): bool
    {
        return $this->date_deces !== null;
    }
}
