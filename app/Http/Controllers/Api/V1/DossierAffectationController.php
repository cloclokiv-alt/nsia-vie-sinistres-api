<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AffectationRequest;
use App\Http\Resources\DossierSinistreResource;
use App\Models\DossierSinistre;
use App\Models\User;
use App\Support\CircuitDossier;
use Illuminate\Validation\ValidationException;

class DossierAffectationController extends Controller
{
    /**
     * Confie le dossier à un gestionnaire, ou le remet au pot commun (null).
     */
    public function store(AffectationRequest $request, DossierSinistre $dossier): DossierSinistreResource
    {
        $this->authorize('affecter', $dossier);

        $gestionnaire = $request->filled('gestionnaire_id')
            ? User::query()->findOrFail($request->integer('gestionnaire_id'))
            : null;

        if ($gestionnaire !== null && ! $gestionnaire->peutInstruire()) {
            throw ValidationException::withMessages([
                'gestionnaire_id' => "Cet agent n'instruit pas de dossiers sinistre.",
            ]);
        }

        if ($gestionnaire !== null && ! $gestionnaire->actif) {
            throw ValidationException::withMessages([
                'gestionnaire_id' => 'Cet agent est désactivé.',
            ]);
        }

        CircuitDossier::affecter($dossier, $gestionnaire, $request->user());

        return DossierSinistreResource::make(
            $dossier->refresh()->load(['contrat.assure', 'gestionnaire']),
        );
    }
}
