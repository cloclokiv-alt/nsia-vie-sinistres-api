<?php

namespace App\Http\Resources;

use App\Models\Courrier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Courrier
 */
class CourrierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_ordre' => $this->numero_ordre,
            'canal' => $this->canal->value,
            'canal_label' => $this->canal->label(),
            'date_reception' => $this->date_reception->toIso8601String(),
            'expediteur' => [
                'nom' => $this->expediteur_nom,
                'qualite' => $this->expediteur_qualite,
                'telephone' => $this->expediteur_telephone,
                'adresse' => $this->expediteur_adresse,
            ],
            'objet' => $this->objet,
            'nombre_pieces' => $this->nombre_pieces,
            'numero_police_declare' => $this->numero_police_declare,
            'accuse' => [
                'code' => $this->accuse_code,
                'remis_le' => $this->accuse_remis_le?->toIso8601String(),
                'en_main_propre' => $this->canal->accuseRemisEnMainPropre(),
            ],
            'est_oriente' => $this->estOriente(),
            'oriente_le' => $this->oriente_le?->toIso8601String(),
            'observation' => $this->observation,
            'recu_par' => AgentResource::make($this->whenLoaded('recuPar')),
            'dossier' => DossierSinistreResource::make($this->whenLoaded('dossier')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
