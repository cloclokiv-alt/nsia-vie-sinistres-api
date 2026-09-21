<?php

namespace App\Http\Resources;

use App\Models\Reglement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reglement
 */
class ReglementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'beneficiaire_id' => $this->beneficiaire_id,
            'montant_xaf' => $this->montant_xaf,
            'mode' => $this->mode->value,
            'mode_label' => $this->mode->label(),
            'coordonnees' => $this->coordonnees,
            'reference' => $this->reference,
            'emis_le' => $this->emis_le->toIso8601String(),
            'emis_par' => AgentResource::make($this->whenLoaded('emisPar')),
            'paye_le' => $this->paye_le?->toIso8601String(),
            'paye_par' => AgentResource::make($this->whenLoaded('payePar')),
            'est_paye' => $this->estPaye(),
            'beneficiaire' => BeneficiaireResource::make($this->whenLoaded('beneficiaire')),
        ];
    }
}
