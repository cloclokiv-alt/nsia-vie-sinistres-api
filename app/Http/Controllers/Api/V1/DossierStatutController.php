<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MotifRejet;
use App\Enums\StatutDossier;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransitionDossierRequest;
use App\Http\Resources\DossierSinistreResource;
use App\Models\DossierSinistre;
use App\Support\CircuitDossier;

class DossierStatutController extends Controller
{
    /**
     * Fait avancer le dossier dans le circuit.
     *
     * Le contrôle de la transition et l'écriture au journal sont dans
     * CircuitDossier : ce contrôleur ne fait qu'autoriser et transmettre.
     */
    public function store(TransitionDossierRequest $request, DossierSinistre $dossier): DossierSinistreResource
    {
        $cible = $request->enum('statut', StatutDossier::class);

        // Prononcer une décision demande plus de droits que faire avancer
        // l'instruction au quotidien.
        $this->authorize($cible->estDecide() ? 'decider' : 'update', $dossier);

        CircuitDossier::transiterVers($dossier, $cible, $request->user(), [
            'motif' => $request->enum('motif', MotifRejet::class),
            'motif_detail' => $request->string('motif_detail')->value() ?: null,
            'commentaire' => $request->string('commentaire')->value() ?: null,
        ]);

        return DossierSinistreResource::make(
            $dossier->refresh()->load(['contrat.assure', 'gestionnaire', 'decidePar']),
        );
    }
}
