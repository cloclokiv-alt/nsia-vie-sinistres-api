<?php

namespace App\Http\Resources;

use App\Models\EvenementDossier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EvenementDossier
 */
class EvenementDossierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'description' => $this->description,
            'donnees' => $this->donnees,
            'auteur' => AgentResource::make($this->whenLoaded('auteur')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
