<?php

namespace App\Http\Resources;

use App\Enums\StatutDossier;
use App\Models\DossierSinistre;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DossierSinistre
 */
class DossierSinistreResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_sinistre' => $this->numero_sinistre,
            'nature' => $this->nature->value,
            'nature_label' => $this->nature->label(),
            'statut' => $this->statut->value,
            'statut_label' => $this->statut->label(),
            // Ce que l'interface doit proposer comme boutons : inutile de
            // dupliquer la table des transitions côté client.
            'transitions_possibles' => array_map(
                fn (StatutDossier $statut) => [
                    'statut' => $statut->value,
                    'label' => $statut->label(),
                    'exige_motif' => $statut->exigeMotif(),
                ],
                $this->statut->suivants(),
            ),
            'date_survenance' => $this->date_survenance->toDateString(),
            'date_declaration' => $this->date_declaration->toDateString(),
            'lieu_survenance' => $this->lieu_survenance,
            'circonstances' => $this->circonstances,
            'echeance_instruction' => $this->echeance_instruction?->toDateString(),
            'est_en_retard' => $this->estEnRetard(),
            'capital_liquide_xaf' => $this->capital_liquide_xaf,
            'motif_rejet' => $this->motif_rejet?->value,
            'motif_rejet_label' => $this->motif_rejet?->label(),
            'motif_rejet_detail' => $this->motif_rejet_detail,
            'decide_le' => $this->decide_le?->toIso8601String(),
            'clos_le' => $this->clos_le?->toIso8601String(),
            'contrat' => ContratResource::make($this->whenLoaded('contrat')),
            'gestionnaire' => AgentResource::make($this->whenLoaded('gestionnaire')),
            'ouvert_par' => AgentResource::make($this->whenLoaded('ouvertPar')),
            'decide_par' => AgentResource::make($this->whenLoaded('decidePar')),
            'courrier_declencheur' => CourrierResource::make($this->whenLoaded('courrierDeclencheur')),
            'beneficiaires' => BeneficiaireResource::collection($this->whenLoaded('beneficiaires')),
            'pieces' => PieceJustificativeResource::collection($this->whenLoaded('pieces')),
            'reglements' => ReglementResource::collection($this->whenLoaded('reglements')),
            'beneficiaires_count' => $this->whenCounted('beneficiaires'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
