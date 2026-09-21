<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LiquidationRequest;
use App\Http\Resources\DossierSinistreResource;
use App\Models\DossierSinistre;
use App\Support\LiquidationCapital;

class LiquidationController extends Controller
{
    /**
     * Arrête le capital dû et le ventile entre les bénéficiaires retenus.
     *
     * Rejouable tant que la prise en charge n'est pas validée : corriger une
     * quote-part puis relancer la liquidation est le geste normal.
     */
    public function store(LiquidationRequest $request, DossierSinistre $dossier): DossierSinistreResource
    {
        $this->authorize('liquider', $dossier);

        LiquidationCapital::appliquer(
            $dossier,
            $request->integer('capital_xaf') ?: null,
            $request->user(),
        );

        return DossierSinistreResource::make(
            $dossier->refresh()->load(['contrat.assure', 'beneficiaires', 'gestionnaire']),
        );
    }
}
