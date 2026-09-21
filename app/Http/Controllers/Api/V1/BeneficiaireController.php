<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatutBeneficiaire;
use App\Enums\TypeEvenement;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBeneficiaireRequest;
use App\Http\Requests\UpdateBeneficiaireRequest;
use App\Http\Resources\BeneficiaireResource;
use App\Models\Beneficiaire;
use App\Models\DossierSinistre;
use App\Support\ChecklistPieces;
use App\Support\JournalDossier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class BeneficiaireController extends Controller
{
    public function index(DossierSinistre $dossier): AnonymousResourceCollection
    {
        $this->authorize('view', $dossier);

        $beneficiaires = $dossier->beneficiaires()
            ->with(['pieces', 'reglement'])
            ->orderBy('id')
            ->get();

        return BeneficiaireResource::collection($beneficiaires)->additional([
            'meta' => [
                'quote_part_totale' => $dossier->quotePartTotale() / 100,
                'repartition_complete' => $dossier->repartitionEstComplete(),
            ],
        ]);
    }

    /**
     * Ajoute un bénéficiaire et pose aussitôt les pièces à lui réclamer,
     * qui dépendent de sa qualité (acte de mariage, de naissance, de notoriété).
     */
    public function store(StoreBeneficiaireRequest $request, DossierSinistre $dossier): JsonResponse
    {
        $this->authorize('update', $dossier);

        $beneficiaire = DB::transaction(function () use ($request, $dossier): Beneficiaire {
            $beneficiaire = $dossier->beneficiaires()->create([
                ...$request->safe()->all(),
                'statut' => StatutBeneficiaire::Identifie,
            ]);

            JournalDossier::enregistrer(
                $dossier,
                TypeEvenement::BeneficiaireAjoute,
                sprintf(
                    '%s ajouté comme bénéficiaire (%s, %s %%).',
                    $beneficiaire->nom_complet,
                    $beneficiaire->qualite->label(),
                    number_format((float) $beneficiaire->quote_part, 2, ',', ' '),
                ),
                $request->user(),
                ['beneficiaire_id' => $beneficiaire->id, 'quote_part' => (float) $beneficiaire->quote_part],
            );

            ChecklistPieces::synchroniser($dossier);

            return $beneficiaire;
        });

        return BeneficiaireResource::make($beneficiaire->refresh()->load('pieces'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Met à jour un bénéficiaire. Écarter quelqu'un (statut « écarté ») retire
     * sa part de la répartition et neutralise les pièces qu'on lui réclamait.
     */
    public function update(
        UpdateBeneficiaireRequest $request,
        DossierSinistre $dossier,
        Beneficiaire $beneficiaire,
    ): BeneficiaireResource {
        $this->authorize('update', $dossier);

        $etaitEcarte = $beneficiaire->statut === StatutBeneficiaire::Ecarte;

        DB::transaction(function () use ($request, $dossier, $beneficiaire, $etaitEcarte): void {
            $beneficiaire->fill($request->safe()->all())->save();

            $estEcarte = $beneficiaire->statut === StatutBeneficiaire::Ecarte;

            if ($estEcarte && ! $etaitEcarte) {
                ChecklistPieces::neutraliserPour($beneficiaire);

                JournalDossier::enregistrer(
                    $dossier,
                    TypeEvenement::BeneficiaireEcarte,
                    sprintf(
                        '%s écarté de la répartition%s',
                        $beneficiaire->nom_complet,
                        $beneficiaire->motif_ecartement ? ' : '.$beneficiaire->motif_ecartement : '.',
                    ),
                    $request->user(),
                    ['beneficiaire_id' => $beneficiaire->id],
                );

                return;
            }

            if (! $estEcarte && $etaitEcarte) {
                ChecklistPieces::synchroniser($dossier);
            }

            JournalDossier::enregistrer(
                $dossier,
                TypeEvenement::BeneficiaireModifie,
                sprintf('Bénéficiaire %s modifié.', $beneficiaire->nom_complet),
                $request->user(),
                ['beneficiaire_id' => $beneficiaire->id],
            );
        });

        return BeneficiaireResource::make($beneficiaire->refresh()->load('pieces'));
    }

    /**
     * Supprime un bénéficiaire saisi par erreur. Dès qu'un règlement existe,
     * la suppression est refusée : on écarte, on ne supprime pas.
     */
    public function destroy(DossierSinistre $dossier, Beneficiaire $beneficiaire): Response
    {
        $this->authorize('update', $dossier);

        if ($beneficiaire->reglement()->exists()) {
            throw ValidationException::withMessages([
                'beneficiaire' => 'Ce bénéficiaire a déjà un règlement : écartez-le plutôt que de le supprimer.',
            ]);
        }

        $nom = $beneficiaire->nom_complet;

        DB::transaction(function () use ($dossier, $beneficiaire, $nom): void {
            $beneficiaire->delete();

            JournalDossier::enregistrer(
                $dossier,
                TypeEvenement::BeneficiaireModifie,
                sprintf('Bénéficiaire %s supprimé du dossier.', $nom),
                request()->user(),
            );
        });

        return response()->noContent();
    }
}
