<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourrierRequest;
use App\Http\Requests\UpdateCourrierRequest;
use App\Http\Resources\CourrierResource;
use App\Models\Courrier;
use App\Support\NumeroSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CourrierController extends Controller
{
    /**
     * Le registre du bureau de réception, du plus récent au plus ancien.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Courrier::class);

        $courriers = Courrier::query()
            ->recherche($request->string('recherche')->value())
            ->when($request->filled('canal'), fn (Builder $query) => $query->where('canal', $request->string('canal')))
            ->when($request->boolean('en_attente'), fn (Builder $query) => $query->enAttenteOrientation())
            ->with(['recuPar', 'dossier'])
            ->latest('date_reception')
            ->latest('id')
            ->paginate($request->integer('per_page') ?: 20)
            ->withQueryString();

        return CourrierResource::collection($courriers);
    }

    /**
     * Enregistrement d'un courrier au guichet. Le numéro d'ordre et le code
     * d'accusé sont attribués par le système : jamais saisis à la main.
     */
    public function store(StoreCourrierRequest $request): JsonResponse
    {
        $this->authorize('create', Courrier::class);

        // Le numéro d'ordre et le code d'accusé sont posés par le modèle à
        // l'insertion ; si un autre guichet a pris le même numéro, on rejoue.
        $courrier = NumeroSequence::enCasDeCollision(fn (): Courrier => Courrier::create([
            ...$request->safe()->except('date_reception'),
            'date_reception' => $request->date('date_reception') ?? now(),
            'recu_par_id' => $request->user()->id,
        ]));

        // Au guichet et en agence, l'accusé est remis séance tenante.
        if ($courrier->canal->accuseRemisEnMainPropre()) {
            $courrier->remettreAccuse();
        }

        // On relit la ligne pour renvoyer les valeurs par défaut posées par la base.
        return CourrierResource::make($courrier->refresh()->load('recuPar'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Courrier $courrier): CourrierResource
    {
        $this->authorize('view', $courrier);

        return CourrierResource::make($courrier->load(['recuPar', 'dossier.contrat.assure']));
    }

    public function update(UpdateCourrierRequest $request, Courrier $courrier): CourrierResource
    {
        $this->authorize('update', $courrier);

        $courrier->fill($request->safe()->all())->save();

        return CourrierResource::make($courrier->load('recuPar'));
    }
}
