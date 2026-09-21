<?php

namespace App\Http\Resources;

use App\Models\Contrat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contrat
 */
class ContratResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_police' => $this->numero_police,
            'souscripteur_nom' => $this->souscripteur_nom,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'statut' => $this->statut->value,
            'statut_label' => $this->statut->label(),
            'couvre' => $this->statut->couvre(),
            'date_effet' => $this->date_effet->toDateString(),
            'date_echeance' => $this->date_echeance?->toDateString(),
            'capital_garanti_xaf' => $this->capital_garanti_xaf,
            'provision_mathematique_xaf' => $this->provision_mathematique_xaf,
            'capital_mobilisable_xaf' => $this->capitalMobilisable(),
            'prime_xaf' => $this->prime_xaf,
            'periodicite' => $this->periodicite,
            'date_derniere_prime' => $this->date_derniere_prime?->toDateString(),
            'carence_mois' => $this->carence_mois,
            'organisme_preteur' => $this->organisme_preteur,
            'assure' => AssureResource::make($this->whenLoaded('assure')),
        ];
    }
}
