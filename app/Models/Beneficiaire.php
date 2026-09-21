<?php

namespace App\Models;

use App\Enums\ModeReglement;
use App\Enums\QualiteBeneficiaire;
use App\Enums\StatutBeneficiaire;
use App\Enums\TypePiece;
use Database\Factories\BeneficiaireFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Celui à qui le capital revient. Porté par le dossier et non par le contrat :
 * la clause bénéficiaire désigne souvent une catégorie (« mes héritiers »),
 * l'instruction du sinistre lui donne un nom et une part.
 */
#[Fillable([
    'dossier_sinistre_id', 'nom', 'prenoms', 'qualite', 'statut', 'quote_part',
    'date_naissance', 'type_piece_identite', 'numero_piece_identite',
    'telephone', 'email', 'adresse', 'mode_reglement', 'coordonnees_reglement',
    'motif_ecartement',
])]
class Beneficiaire extends Model
{
    /** @use HasFactory<BeneficiaireFactory> */
    use HasFactory;

    /**
     * 100 % exprimés en centièmes. Toute la répartition se calcule sur cet entier,
     * jamais sur des flottants : un centime d'écart sur un capital est un incident.
     */
    public const TOTAL_CENTIEMES = 10000;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qualite' => QualiteBeneficiaire::class,
            'statut' => StatutBeneficiaire::class,
            'mode_reglement' => ModeReglement::class,
            'quote_part' => 'decimal:2',
            'date_naissance' => 'date',
            'montant_du_xaf' => 'integer',
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
     * @return HasMany<PieceJustificative, $this>
     */
    public function pieces(): HasMany
    {
        return $this->hasMany(PieceJustificative::class);
    }

    /**
     * @return HasOne<Reglement, $this>
     */
    public function reglement(): HasOne
    {
        return $this->hasOne(Reglement::class);
    }

    /**
     * Les bénéficiaires qui entrent dans la répartition du capital.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function retenus(Builder $query): void
    {
        $query->where('statut', '!=', StatutBeneficiaire::Ecarte);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function nomComplet(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->nom.' '.($this->prenoms ?? '')));
    }

    /**
     * La quote-part en centièmes de pour cent : 33,34 % devient 3334.
     */
    public function quotePartEnCentiemes(): int
    {
        return (int) round(((float) $this->quote_part) * 100);
    }

    /**
     * Les pièces à réclamer à cette personne : le socle d'identification,
     * plus ce qu'exige sa qualité (acte de mariage, de naissance, de notoriété…).
     *
     * @return array<int, TypePiece>
     */
    public function piecesAttendues(): array
    {
        $socle = $this->qualite->estPersonneMorale()
            ? [TypePiece::Rib]
            : [TypePiece::PieceIdentiteBeneficiaire, TypePiece::Rib];

        return array_values(array_unique(
            [...$socle, ...$this->qualite->piecesDeFiliation()],
            SORT_REGULAR,
        ));
    }

    public function estRegle(): bool
    {
        return $this->statut === StatutBeneficiaire::Regle;
    }
}
