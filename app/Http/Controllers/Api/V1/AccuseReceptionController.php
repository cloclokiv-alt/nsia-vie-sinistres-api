<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourrierResource;
use App\Models\Courrier;

class AccuseReceptionController extends Controller
{
    /**
     * Constate la remise de l'accusé de réception à l'expéditeur.
     *
     * Pour les courriers reçus par la poste ou par courriel, l'accusé part
     * après coup : c'est ici qu'on trace sa remise effective.
     */
    public function store(Courrier $courrier): CourrierResource
    {
        $this->authorize('update', $courrier);

        $courrier->remettreAccuse();

        return CourrierResource::make($courrier->refresh()->load('recuPar'));
    }
}
