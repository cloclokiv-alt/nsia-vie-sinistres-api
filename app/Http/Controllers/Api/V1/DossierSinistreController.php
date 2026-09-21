<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NatureSinistre;
use App\Enums\StatutDossier;
use App\Enums\TypeEvenement;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDossierSinistreRequest;
use App\Http\Resources\DossierSinistreResource;
use App\Models\Contrat;
use App\Models\Courrier;
use App\Models\DossierSinistre;
use App\Support\ChecklistPieces;
use App\Support\JournalDossier;
use App\Support\LiquidationCapital;
use App\Support\NumeroSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DossierSinistreController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DossierSinistre::class);

        $dossiers = DossierSinistre::query()
            ->recherche($request->string('recherche')->value())
            ->when($request->filled('statut'), fn (Builder $query) => $query->where('statut', $request->string('statut')))
            ->when($request->filled('nature'), fn (Builder $query) => $query->where('nature', $request->string('nature')))
            ->when($request->filled('gestionnaire_id'), fn (Builder $query) => $query->where('gestionnaire_id', $request->integer('gestionnaire_id')))
            ->when($request->boolean('mes_dossiers'), fn (Builder $query) => $query->where('gestionnaire_id', $request->user()->id))
            ->when($request->boolean('en_cours'), fn (Builder $query) => $query->enCours())
            ->when($request->boolean('en_retard'), fn (Builder $query) => $query->enRetard())
            ->with(['contrat.assure', 'gestionnaire'])
            ->withCount('beneficiaires')
            ->latest('id')
            ->paginate($request->integer('per_page') ?: 20)
            ->withQueryString();

        return DossierSinistreResource::collection($dossiers);
    }

    /**
     * Ouverture d'un dossier, en principe depuis un courrier déjà enregistré.
     *
     * Trois choses se passent d'un coup : le dossier naît, la checklist des
     * pièces est posée d'après la nature du sinistre, et le courrier d'origine
     * est rattaché.
     */
    public function store(StoreDossierSinistreRequest $request): JsonResponse
    {
        $this->authorize('create', DossierSinistre::class);

        $contrat = Contrat::query()->where('numero_police', $request->string('numero_police'))->firstOrFail();

        $courrier = $request->filled('numero_ordre_courrier')
            ? Courrier::query()->where('numero_ordre', $request->string('numero_ordre_courrier'))->firstOrFail()
            : null;

        $declaration = $request->date('date_declaration') ?? now()->startOfDay();

        $dossier = NumeroSequence::enCasDeCollision(fn (): DossierSinistre => DB::transaction(function () use ($request, $contrat, $courrier, $declaration): DossierSinistre {
            $dossier = DossierSinistre::create([
                'contrat_id' => $contrat->id,
                'courrier_id' => $courrier?->id,
                'nature' => $request->enum('nature', NatureSinistre::class),
                'statut' => StatutDossier::Ouvert,
                'date_survenance' => $request->date('date_survenance'),
                'date_declaration' => $declaration,
                'lieu_survenance' => $request->string('lieu_survenance')->value() ?: null,
                'circonstances' => $request->string('circonstances')->value() ?: null,
                'gestionnaire_id' => $request->integer('gestionnaire_id') ?: null,
                'ouvert_par_id' => $request->user()->id,
                'echeance_instruction' => DossierSinistre::echeanceDepuis($declaration),
            ]);

            JournalDossier::enregistrer(
                $dossier,
                TypeEvenement::DossierOuvert,
                sprintf(
                    'Dossier ouvert sur la police %s (%s).',
                    $contrat->numero_police,
                    $dossier->nature->label(),
                ),
                $request->user(),
                ['numero_police' => $contrat->numero_police, 'courrier' => $courrier?->numero_ordre],
            );

            ChecklistPieces::synchroniser($dossier);

            if ($courrier !== null) {
                $courrier->orienterVers($dossier);

                JournalDossier::enregistrer(
                    $dossier,
                    TypeEvenement::CourrierRattache,
                    sprintf('Courrier %s rattaché au dossier.', $courrier->numero_ordre),
                    $request->user(),
                    ['numero_ordre' => $courrier->numero_ordre],
                );
            }

            return $dossier;
        }));

        return DossierSinistreResource::make(
            $dossier->refresh()->load(['contrat.assure', 'gestionnaire', 'pieces', 'courrierDeclencheur']),
        )->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Fiche complète du dossier : contrat, bénéficiaires, pièces, règlements.
     */
    public function show(DossierSinistre $dossier): DossierSinistreResource
    {
        $this->authorize('view', $dossier);

        $dossier->load([
            'contrat.assure',
            'gestionnaire',
            'ouvertPar',
            'decidePar',
            'courrierDeclencheur.recuPar',
            'beneficiaires',
            'pieces.deposeePar',
            'reglements.beneficiaire',
        ]);

        return DossierSinistreResource::make($dossier)->additional([
            'meta' => [
                'est_complet' => $dossier->estComplet(),
                'quote_part_totale' => $dossier->quotePartTotale() / 100,
                'pieces_manquantes' => $dossier->piecesManquantes()->map->libelleAffiche()->values(),
                'capital_propose_xaf' => LiquidationCapital::montantPropose($dossier),
                'garantie_acquise' => $dossier->contrat->garantissaitLe($dossier->date_survenance),
            ],
        ]);
    }
}
