<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatutPiece;
use App\Enums\TypeEvenement;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeposerPieceRequest;
use App\Http\Resources\PieceJustificativeResource;
use App\Models\DossierSinistre;
use App\Models\PieceJustificative;
use App\Support\JournalDossier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DepotPieceController extends Controller
{
    /**
     * Dépose le scan d'une pièce attendue.
     *
     * Redéposer sur une pièce refusée est le cas normal : l'ancien fichier
     * est remplacé et la pièce repasse « à contrôler ».
     */
    public function update(
        DeposerPieceRequest $request,
        DossierSinistre $dossier,
        PieceJustificative $piece,
    ): PieceJustificativeResource {
        $this->authorize('deposer', $piece);

        if ($dossier->statut->estFige()) {
            throw ValidationException::withMessages([
                'piece' => 'Le dossier est clos : plus aucune pièce ne peut y être déposée.',
            ]);
        }

        $fichier = $request->file('fichier');
        $disque = Storage::disk(config('sinistres.disque'));

        $chemin = $fichier->storeAs(
            'dossiers/'.$dossier->numero_sinistre,
            sprintf('%s-%s.%s', $piece->type->value, Str::lower(Str::random(10)), $fichier->getClientOriginalExtension()),
            config('sinistres.disque'),
        );

        $ancienChemin = $piece->chemin;

        DB::transaction(function () use ($piece, $dossier, $fichier, $chemin, $request): void {
            $piece->forceFill([
                'statut' => StatutPiece::Recue,
                'chemin' => $chemin,
                'nom_origine' => $fichier->getClientOriginalName(),
                'mime' => $fichier->getClientMimeType(),
                'taille_octets' => $fichier->getSize(),
                'empreinte' => hash_file('sha256', $fichier->getRealPath()),
                'deposee_le' => now(),
                'deposee_par_id' => $request->user()->id,
                // Un nouveau dépôt annule le contrôle précédent.
                'controlee_le' => null,
                'controlee_par_id' => null,
                'motif_non_conformite' => null,
            ])->save();

            JournalDossier::enregistrer(
                $dossier,
                TypeEvenement::PieceDeposee,
                sprintf('Pièce déposée : %s.', $piece->libelleAffiche()),
                $request->user(),
                array_filter([
                    'piece_id' => $piece->id,
                    'type' => $piece->type->value,
                    'commentaire' => $request->string('commentaire')->value() ?: null,
                ]),
            );
        });

        // Le remplacement n'efface l'ancien fichier qu'une fois la base à jour.
        if ($ancienChemin !== null && $ancienChemin !== $chemin) {
            $disque->delete($ancienChemin);
        }

        return PieceJustificativeResource::make($piece->refresh()->load('deposeePar'));
    }
}
