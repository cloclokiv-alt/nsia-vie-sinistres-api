<?php

namespace App\Http\Resources;

use App\Models\PieceJustificative;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PieceJustificative
 */
class PieceJustificativeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'libelle' => $this->libelleAffiche(),
            'statut' => $this->statut->value,
            'statut_label' => $this->statut->label(),
            'obligatoire' => $this->obligatoire,
            'bloque_instruction' => $this->obligatoire && $this->statut->bloqueInstruction(),
            'est_medicale' => $this->estMedicale(),
            'beneficiaire_id' => $this->beneficiaire_id,
            // Le chemin de stockage n'est jamais exposé : le téléchargement passe
            // par une route qui applique la politique d'accès.
            'fichier' => $this->when($this->aUnFichier(), fn () => [
                'nom_origine' => $this->nom_origine,
                'mime' => $this->mime,
                'taille_octets' => $this->taille_octets,
                'empreinte' => $this->empreinte,
            ]),
            'deposee_le' => $this->deposee_le?->toIso8601String(),
            'deposee_par' => AgentResource::make($this->whenLoaded('deposeePar')),
            'controlee_le' => $this->controlee_le?->toIso8601String(),
            'controlee_par' => AgentResource::make($this->whenLoaded('controleePar')),
            'motif_non_conformite' => $this->motif_non_conformite,
        ];
    }
}
