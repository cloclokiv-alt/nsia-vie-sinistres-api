<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatutPiece;
use App\Enums\TypePiece;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePieceRequest;
use App\Http\Resources\PieceJustificativeResource;
use App\Models\DossierSinistre;
use App\Models\PieceJustificative;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PieceJustificativeController extends Controller
{
    /**
     * La checklist du dossier. Les pièces médicales sont retirées de la liste
     * pour qui n'a pas à les voir : elles n'apparaissent même pas en creux.
     */
    public function index(Request $request, DossierSinistre $dossier): AnonymousResourceCollection
    {
        $this->authorize('view', $dossier);

        $pieces = $dossier->pieces()
            ->with(['deposeePar', 'controleePar'])
            ->unless($request->user()->peutConsulterLeMedical(), function ($query): void {
                $query->whereNotIn('type', array_map(
                    fn (TypePiece $type) => $type->value,
                    array_filter(TypePiece::cases(), fn (TypePiece $type) => $type->estMedicale()),
                ));
            })
            ->orderBy('beneficiaire_id')
            ->orderBy('id')
            ->get();

        return PieceJustificativeResource::collection($pieces)->additional([
            'meta' => [
                'manquantes' => $dossier->piecesManquantes()->count(),
                'dossier_complet' => $dossier->estComplet(),
            ],
        ]);
    }

    /**
     * Ajoute une pièce que la checklist automatique ne prévoyait pas.
     */
    public function store(StorePieceRequest $request, DossierSinistre $dossier): JsonResponse
    {
        $this->authorize('create', [PieceJustificative::class, $dossier]);

        $piece = $dossier->pieces()->create([
            ...$request->safe()->all(),
            'statut' => StatutPiece::Attendue,
            'obligatoire' => $request->boolean('obligatoire', true),
        ]);

        return PieceJustificativeResource::make($piece->refresh())
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Téléchargement du scan. Le chemin de stockage n'est jamais exposé :
     * on passe forcément par ici, donc par la politique d'accès.
     */
    public function show(DossierSinistre $dossier, PieceJustificative $piece): StreamedResponse
    {
        $this->authorize('view', $piece);

        if (! $piece->aUnFichier()) {
            throw ValidationException::withMessages([
                'piece' => "Cette pièce n'a pas encore été déposée.",
            ]);
        }

        $disque = Storage::disk(config('sinistres.disque'));

        abort_unless($disque->exists($piece->chemin), Response::HTTP_NOT_FOUND, 'Fichier introuvable.');

        return $disque->download($piece->chemin, $piece->nom_origine ?? $piece->libelleAffiche());
    }

    /**
     * Retire une pièce ajoutée à la main. Les lignes posées par la checklist
     * ne se suppriment pas : elles se déclarent « sans objet ».
     */
    public function destroy(DossierSinistre $dossier, PieceJustificative $piece): Response
    {
        $this->authorize('create', [PieceJustificative::class, $dossier]);

        if ($piece->type !== TypePiece::Autre) {
            throw ValidationException::withMessages([
                'piece' => 'Une pièce de la checklist ne se supprime pas : déclarez-la « sans objet ».',
            ]);
        }

        if ($piece->aUnFichier()) {
            Storage::disk(config('sinistres.disque'))->delete($piece->chemin);
        }

        $piece->delete();

        return response()->noContent();
    }
}
