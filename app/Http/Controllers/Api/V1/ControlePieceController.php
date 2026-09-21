<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatutPiece;
use App\Enums\TypeEvenement;
use App\Http\Controllers\Controller;
use App\Http\Requests\ControlePieceRequest;
use App\Http\Resources\PieceJustificativeResource;
use App\Models\DossierSinistre;
use App\Models\PieceJustificative;
use App\Support\JournalDossier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ControlePieceController extends Controller
{
    /**
     * Déclare une pièce conforme, non conforme, ou sans objet.
     *
     * Une pièce médicale n'est contrôlée que par le médecin-conseil : c'est
     * la politique d'accès qui le fait respecter.
     */
    public function update(
        ControlePieceRequest $request,
        DossierSinistre $dossier,
        PieceJustificative $piece,
    ): PieceJustificativeResource {
        $this->authorize('controler', $piece);

        $statut = $request->enum('statut', StatutPiece::class);

        if ($statut->exigeFichier() && ! $piece->aUnFichier()) {
            throw ValidationException::withMessages([
                'statut' => "Aucun scan n'a été déposé : il n'y a rien à contrôler.",
            ]);
        }

        DB::transaction(function () use ($piece, $dossier, $statut, $request): void {
            $piece->forceFill([
                'statut' => $statut,
                'controlee_le' => now(),
                'controlee_par_id' => $request->user()->id,
                'motif_non_conformite' => $statut === StatutPiece::NonConforme
                    ? $request->string('motif_non_conformite')->value()
                    : null,
            ])->save();

            JournalDossier::enregistrer(
                $dossier,
                TypeEvenement::PieceControlee,
                sprintf('%s : %s.', $piece->libelleAffiche(), $statut->label()),
                $request->user(),
                array_filter([
                    'piece_id' => $piece->id,
                    'statut' => $statut->value,
                    'motif' => $piece->motif_non_conformite,
                ]),
            );
        });

        return PieceJustificativeResource::make($piece->refresh()->load(['deposeePar', 'controleePar']));
    }
}
