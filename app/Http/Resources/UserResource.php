<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matricule' => $this->matricule,
            'nom' => $this->name,
            'email' => $this->email,
            'telephone' => $this->phone,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'agence' => $this->agence,
            'actif' => $this->actif,
            'droits' => [
                'receptionner' => $this->peutReceptionner(),
                'instruire' => $this->peutInstruire(),
                'decider' => $this->peutDecider(),
                'regler' => $this->peutRegler(),
                'consulter_medical' => $this->peutConsulterLeMedical(),
            ],
        ];
    }
}
