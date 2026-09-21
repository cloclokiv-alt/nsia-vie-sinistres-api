<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ModeReglement;
use App\Enums\StatutBeneficiaire;
use App\Enums\StatutDossier;
use App\Enums\TypeEvenement;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReglementRequest;
use App\Http\Resources\ReglementResource;
use App\Models\Beneficiaire;
use App\Models\DossierSinistre;
use App\Models\Reglement;
use App\Support\JournalDossier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ReglementController extends Controller
{
    public function index(DossierSinistre $dossier): AnonymousResourceCollection
    {
        $this->authorize('view', $dossier);

        $reglements = $dossier->reglements()
            ->with(['beneficiaire', 'emisPar', 'payePar'])
            ->orderBy('id')
            ->get();

        return ReglementResource::collection($reglements)->additional([
            'meta' => [
                'capital_liquide_xaf' => $dossier->capital_liquide_xaf,
                'total_emis_xaf' => (int) $reglements->sum('montant_xaf'),
                'total_paye_xaf' => (int) $reglements->where('paye_le', '!=', null)->sum('montant_xaf'),
            ],
        ]);
    }

    /**
     * Émet le règlement d'un bénéficiaire. Le montant n'est pas saisi : il vient
     * de la liquidation, pour qu'aucune erreur de frappe ne s'y glisse.
     */
    public function store(StoreReglementRequest $request, DossierSinistre $dossier): JsonResponse
    {
        $this->authorize('regler', $dossier);

        if ($dossier->statut !== StatutDossier::EnReglement) {
            throw ValidationException::withMessages([
                'dossier' => sprintf(
                    'Le dossier doit être « %s » pour émettre un règlement ; il est « %s ».',
                    StatutDossier::EnReglement->label(),
                    $dossier->statut->label(),
                ),
            ]);
        }

        $beneficiaire = $dossier->beneficiaires()->findOrFail($request->integer('beneficiaire_id'));

        $this->verifierBeneficiaire($beneficiaire);

        $mode = $request->enum('mode', ModeReglement::class);
        $coordonnees = $request->string('coordonnees')->value() ?: $beneficiaire->coordonnees_reglement;

        $this->verifierMode($mode, $coordonnees, $beneficiaire->montant_du_xaf);

        $reglement = DB::transaction(function () use ($dossier, $beneficiaire, $mode, $coordonnees, $request): Reglement {
            $reglement = $dossier->reglements()->create([
                'beneficiaire_id' => $beneficiaire->id,
                'montant_xaf' => $beneficiaire->montant_du_xaf,
                'mode' => $mode,
                'coordonnees' => $coordonnees,
                'reference' => $request->string('reference')->value() ?: null,
                'emis_le' => now(),
                'emis_par_id' => $request->user()->id,
            ]);

            JournalDossier::enregistrer(
                $dossier,
                TypeEvenement::ReglementEmis,
                sprintf(
                    'Règlement de %s FCFA émis à %s (%s).',
                    number_format($reglement->montant_xaf, 0, ',', ' '),
                    $beneficiaire->nom_complet,
                    $mode->label(),
                ),
                $request->user(),
                ['reglement_id' => $reglement->id, 'montant_xaf' => $reglement->montant_xaf],
            );

            return $reglement;
        });

        return ReglementResource::make($reglement->refresh()->load(['beneficiaire', 'emisPar']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Constate le paiement effectif : virement passé, chèque retiré, transfert reçu.
     * C'est ce geste qui fait basculer le bénéficiaire en « réglé ».
     */
    public function update(Request $request, DossierSinistre $dossier, Reglement $reglement): ReglementResource
    {
        $this->authorize('regler', $dossier);

        $donnees = $request->validate([
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        if ($reglement->estPaye()) {
            throw ValidationException::withMessages([
                'reglement' => 'Ce règlement est déjà constaté payé.',
            ]);
        }

        DB::transaction(function () use ($reglement, $dossier, $donnees, $request): void {
            $reglement->constaterPaiement($request->user(), $donnees['reference'] ?? null);

            $reglement->beneficiaire()->update(['statut' => StatutBeneficiaire::Regle]);

            JournalDossier::enregistrer(
                $dossier,
                TypeEvenement::ReglementPaye,
                sprintf(
                    'Paiement de %s FCFA constaté%s.',
                    number_format($reglement->montant_xaf, 0, ',', ' '),
                    $reglement->reference ? ' (réf. '.$reglement->reference.')' : '',
                ),
                $request->user(),
                ['reglement_id' => $reglement->id],
            );
        });

        return ReglementResource::make($reglement->refresh()->load(['beneficiaire', 'emisPar', 'payePar']));
    }

    private function verifierBeneficiaire(Beneficiaire $beneficiaire): void
    {
        if (! $beneficiaire->statut->estPayable()) {
            throw ValidationException::withMessages([
                'beneficiaire_id' => sprintf(
                    '%s est « %s » : seul un bénéficiaire validé peut être réglé.',
                    $beneficiaire->nom_complet,
                    $beneficiaire->statut->label(),
                ),
            ]);
        }

        if ($beneficiaire->montant_du_xaf <= 0) {
            throw ValidationException::withMessages([
                'beneficiaire_id' => "Aucun montant n'a été arrêté pour ce bénéficiaire.",
            ]);
        }

        if ($beneficiaire->reglement()->exists()) {
            throw ValidationException::withMessages([
                'beneficiaire_id' => 'Ce bénéficiaire a déjà reçu un règlement.',
            ]);
        }
    }

    private function verifierMode(ModeReglement $mode, ?string $coordonnees, int $montantXaf): void
    {
        if ($mode->exigeCoordonnees() && blank($coordonnees)) {
            throw ValidationException::withMessages([
                'coordonnees' => sprintf('Un règlement par %s exige des coordonnées.', $mode->label()),
            ]);
        }

        if ($mode === ModeReglement::Especes && $montantXaf > ModeReglement::PLAFOND_ESPECES_XAF) {
            throw ValidationException::withMessages([
                'mode' => sprintf(
                    'Au-delà de %s FCFA, le règlement doit passer par un canal tracé.',
                    number_format(ModeReglement::PLAFOND_ESPECES_XAF, 0, ',', ' '),
                ),
            ]);
        }
    }
}
