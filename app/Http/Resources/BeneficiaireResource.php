<?php

namespace App\Http\Resources;

use App\Models\Beneficiaire;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Beneficiaire
 */
class BeneficiaireResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenoms' => $this->prenoms,
            'nom_complet' => $this->nom_complet,
            'qualite' => $this->qualite->value,
            'qualite_label' => $this->qualite->label(),
            'statut' => $this->statut->value,
            'statut_label' => $this->statut->label(),
            'quote_part' => (float) $this->quote_part,
            'montant_du_xaf' => $this->montant_du_xaf,
            'date_naissance' => $this->date_naissance?->toDateString(),
            'type_piece_identite' => $this->type_piece_identite,
            'numero_piece_identite' => $this->numero_piece_identite,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'adresse' => $this->adresse,
            'mode_reglement' => $this->mode_reglement?->value,
            'mode_reglement_label' => $this->mode_reglement?->label(),
            'coordonnees_reglement' => $this->coordonnees_reglement,
            'motif_ecartement' => $this->motif_ecartement,
            'est_regle' => $this->estRegle(),
            'pieces' => PieceJustificativeResource::collection($this->whenLoaded('pieces')),
            'reglement' => ReglementResource::make($this->whenLoaded('reglement')),
        ];
    }
}
