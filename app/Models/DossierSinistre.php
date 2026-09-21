<?php

namespace App\Models;

use App\Enums\MotifRejet;
use App\Enums\NatureSinistre;
use App\Enums\StatutDossier;
use App\Policies\DossierSinistrePolicy;
use App\Support\CircuitDossier;
use Database\Factories\DossierSinistreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Le dossier sinistre : de l'ouverture sur courrier jusqu'à la clôture.
 *
 * Le statut ne se modifie jamais directement — il passe par
 * {@see CircuitDossier}, qui vérifie la transition et journalise.
 */
#[Fillable([
    'numero_sinistre', 'contrat_id', 'courrier_id', 'nature', 'statut',
    'date_survenance', 'date_declaration', 'lieu_survenance', 'circonstances',
    'gestionnaire_id', 'ouvert_par_id', 'echeance_instruction',
])]
#[RouteKey('numero_sinistre')]
#[UsePolicy(DossierSinistrePolicy::class)]
class DossierSinistre extends Model
{
    /** @use HasFactory<DossierSinistreFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Délai interne d'instruction, en jours ouvrés arrondis, à partir de la déclaration.
     * Les délais légaux de la zone CIMA sont plus longs : cette échéance est un
     * objectif de service, pas une obligation réglementaire.
     */
    public const DELAI_INSTRUCTION_JOURS = 30;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nature' => NatureSinistre::class,
            'statut' => StatutDossier::class,
            'motif_rejet' => MotifRejet::class,
            'date_survenance' => 'date',
            'date_declaration' => 'date',
            'echeance_instruction' => 'date',
            'decide_le' => 'datetime',
            'clos_le' => 'datetime',
            'capital_liquide_xaf' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Contrat, $this>
     */
    public function contrat(): BelongsTo
    {
        return $this->belongsTo(Contrat::class);
    }

    /**
     * Le courrier qui a déclenché l'ouverture.
     *
     * @return BelongsTo<Courrier, $this>
     */
    public function courrierDeclencheur(): BelongsTo
    {
        return $this->belongsTo(Courrier::class, 'courrier_id');
    }

    /**
     * Tous les courriers rattachés, y compris les pièces complémentaires reçues après coup.
     *
     * @return HasMany<Courrier, $this>
     */
    public function courriers(): HasMany
    {
        return $this->hasMany(Courrier::class, 'dossier_sinistre_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function gestionnaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gestionnaire_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function ouvertPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ouvert_par_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decide_par_id');
    }

    /**
     * @return HasMany<Beneficiaire, $this>
     */
    public function beneficiaires(): HasMany
    {
        return $this->hasMany(Beneficiaire::class);
    }

    /**
     * @return HasMany<PieceJustificative, $this>
     */
    public function pieces(): HasMany
    {
        return $this->hasMany(PieceJustificative::class);
    }

    /**
     * @return HasMany<Reglement, $this>
     */
    public function reglements(): HasMany
    {
        return $this->hasMany(Reglement::class);
    }

    /**
     * @return HasMany<EvenementDossier, $this>
     */
    public function evenements(): HasMany
    {
        return $this->hasMany(EvenementDossier::class)->latest('created_at');
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function enCours(Builder $query): void
    {
        $query->whereIn('statut', array_map(
            fn (StatutDossier $statut) => $statut->value,
            array_filter(StatutDossier::cases(), fn (StatutDossier $statut) => $statut->estEnCours()),
        ));
    }

    /**
     * Les dossiers dont l'échéance d'instruction est dépassée et qui n'ont pas abouti.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function enRetard(Builder $query): void
    {
        $query->enCours()
            ->whereNotNull('echeance_instruction')
            ->whereDate('echeance_instruction', '<', now()->toDateString());
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
                ->where('numero_sinistre', 'like', $motif)
                ->orWhereHas('contrat', fn (Builder $contrat) => $contrat
                    ->where('numero_police', 'like', $motif)
                    ->orWhereHas('assure', fn (Builder $assure) => $assure
                        ->where('nom', 'like', $motif)
                        ->orWhere('prenoms', 'like', $motif))));
        });
    }

    public function estEnRetard(): bool
    {
        return $this->statut->estEnCours()
            && $this->echeance_instruction !== null
            && $this->echeance_instruction->isPast();
    }

    /**
     * Les bénéficiaires qui entrent dans la répartition (les écartés en sortent).
     *
     * @return Collection<int, Beneficiaire>
     */
    public function beneficiairesRetenus(): Collection
    {
        return $this->beneficiaires()->retenus()->orderBy('id')->get();
    }

    /**
     * Somme des quotes-parts retenues, en centièmes de pour cent.
     * On raisonne en entiers : 33,33 % + 33,33 % + 33,34 % doit tomber juste.
     */
    public function quotePartTotale(): int
    {
        return $this->beneficiairesRetenus()->sum(
            fn (Beneficiaire $beneficiaire) => $beneficiaire->quotePartEnCentiemes(),
        );
    }

    /**
     * La répartition du capital est-elle complète (exactement 100 %) ?
     */
    public function repartitionEstComplete(): bool
    {
        return $this->quotePartTotale() === Beneficiaire::TOTAL_CENTIEMES;
    }

    /**
     * Les pièces obligatoires qui manquent encore pour instruire.
     *
     * @return Collection<int, PieceJustificative>
     */
    public function piecesManquantes(): Collection
    {
        return $this->pieces()->obligatoires()->bloquantes()->orderBy('id')->get();
    }

    /**
     * Le dossier est-il complet, c'est-à-dire instruisible jusqu'à la liquidation ?
     */
    public function estComplet(): bool
    {
        return $this->piecesManquantes()->isEmpty() && $this->repartitionEstComplete();
    }

    /**
     * Échéance d'instruction par défaut, calculée depuis la date de déclaration.
     */
    public static function echeanceDepuis(Carbon $declaration): Carbon
    {
        return $declaration->copy()->addDays(self::DELAI_INSTRUCTION_JOURS);
    }
}
