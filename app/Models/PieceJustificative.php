<?php

namespace App\Models;

use App\Enums\StatutPiece;
use App\Enums\TypePiece;
use App\Policies\PieceJustificativePolicy;
use Database\Factories\PieceJustificativeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne de la checklist du dossier. Elle existe dès l'ouverture, à l'état
 * « attendue » : on sait ce qui manque avant même que l'assuré l'apporte.
 */
#[Fillable([
    'dossier_sinistre_id', 'beneficiaire_id', 'type', 'statut', 'libelle', 'obligatoire',
])]
#[UsePolicy(PieceJustificativePolicy::class)]
class PieceJustificative extends Model
{
    /** @use HasFactory<PieceJustificativeFactory> */
    use HasFactory;

    /**
     * Disque de stockage des pièces : hors du dossier public, jamais servi
     * directement par le serveur web.
     */
    public const DISQUE = 'sinistres';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypePiece::class,
            'statut' => StatutPiece::class,
            'obligatoire' => 'boolean',
            'taille_octets' => 'integer',
            'deposee_le' => 'datetime',
            'controlee_le' => 'datetime',
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
    public function deposeePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deposee_par_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function controleePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'controlee_par_id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function obligatoires(Builder $query): void
    {
        $query->where('obligatoire', true);
    }

    /**
     * Les pièces qui empêchent encore le dossier d'avancer.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function bloquantes(Builder $query): void
    {
        $query->whereIn('statut', array_map(
            fn (StatutPiece $statut) => $statut->value,
            array_filter(StatutPiece::cases(), fn (StatutPiece $statut) => $statut->bloqueInstruction()),
        ));
    }

    /**
     * Le libellé affiché : celui de l'énumération, ou le texte libre saisi
     * pour une pièce « autre ».
     */
    public function libelleAffiche(): string
    {
        return $this->type === TypePiece::Autre
            ? ($this->libelle ?? $this->type->label())
            : $this->type->label();
    }

    public function aUnFichier(): bool
    {
        return $this->chemin !== null;
    }

    /**
     * La pièce relève-t-elle du secret médical ?
     */
    public function estMedicale(): bool
    {
        return $this->type->estMedicale();
    }
}
