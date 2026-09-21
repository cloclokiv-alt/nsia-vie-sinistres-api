<?php

namespace App\Http\Resources;

use App\Models\Assure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Assure
 */
class AssureResource extends JsonResource
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
            'date_naissance' => $this->date_naissance?->toDateString(),
            'lieu_naissance' => $this->lieu_naissance,
            'sexe' => $this->sexe,
            'type_piece_identite' => $this->type_piece_identite,
            'numero_piece_identite' => $this->numero_piece_identite,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'adresse' => $this->adresse,
            'profession' => $this->profession,
            'date_deces' => $this->date_deces?->toDateString(),
            'est_decede' => $this->estDecede(),
        ];
    }
}
