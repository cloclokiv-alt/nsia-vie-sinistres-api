<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EvenementDossierResource;
use App\Models\DossierSinistre;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class JournalController extends Controller
{
    /**
     * Le journal du dossier, du plus récent au plus ancien.
     *
     * C'est ce qui permet de répondre à un assuré au téléphone sans rouvrir
     * le dossier physique — et de justifier chaque geste en cas de litige.
     */
    public function index(Request $request, DossierSinistre $dossier): AnonymousResourceCollection
    {
        $this->authorize('consulterJournal', $dossier);

        $evenements = $dossier->evenements()
            ->with('auteur')
            ->paginate($request->integer('per_page') ?: 50);

        return EvenementDossierResource::collection($evenements);
    }
}
