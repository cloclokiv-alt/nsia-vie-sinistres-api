<?php

namespace Tests\Feature\Sinistre;

use App\Enums\ModeReglement;
use App\Enums\StatutBeneficiaire;
use App\Enums\StatutDossier;
use App\Enums\StatutPiece;
use App\Enums\TypeEvenement;
use App\Models\Beneficiaire;
use App\Models\DossierSinistre;
use App\Models\User;
use App\Support\ChecklistPieces;
use App\Support\CircuitDossier;
use App\Support\LiquidationCapital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\ConstruitDesDossiers;
use Tests\TestCase;

/**
 * Le règlement : le moment où l'argent sort. C'est là qu'une erreur coûte
 * le plus cher, donc c'est là qu'on met le plus de garde-fous.
 */
class ReglementTest extends TestCase
{
    use ConstruitDesDossiers, RefreshDatabase;

    #[Test]
    public function on_nemet_pas_de_reglement_sur_un_dossier_qui_ny_est_pas(): void
    {
        $dossier = $this->dossierComplet();
        $beneficiaire = $dossier->beneficiaires()->firstOrFail();

        Sanctum::actingAs(User::factory()->comptable()->create());

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/reglements", [
            'beneficiaire_id' => $beneficiaire->id,
            'mode' => ModeReglement::VirementBancaire->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('dossier');

        $this->assertDatabaseCount('reglements', 0);
    }

    #[Test]
    public function le_montant_regle_vient_de_la_liquidation_pas_de_la_saisie(): void
    {
        $dossier = $this->menerJusquAuReglement(12000000);
        $beneficiaire = $dossier->beneficiairesRetenus()->first();

        Sanctum::actingAs(User::factory()->comptable()->create());

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/reglements", [
            'beneficiaire_id' => $beneficiaire->id,
            'mode' => ModeReglement::VirementBancaire->value,
            // Même si le client envoie un montant, il est ignoré.
            'montant_xaf' => 999999999,
        ])
            ->assertCreated()
            ->assertJsonPath('data.montant_xaf', 12000000);

        $this->assertDatabaseHas('reglements', [
            'beneficiaire_id' => $beneficiaire->id,
            'montant_xaf' => 12000000,
        ]);
    }

    #[Test]
    public function un_beneficiaire_nest_regle_quune_fois(): void
    {
        $dossier = $this->menerJusquAuReglement();
        $beneficiaire = $dossier->beneficiairesRetenus()->first();

        Sanctum::actingAs(User::factory()->comptable()->create());
        $url = "/api/v1/dossiers/{$dossier->numero_sinistre}/reglements";

        $this->postJson($url, [
            'beneficiaire_id' => $beneficiaire->id,
            'mode' => ModeReglement::VirementBancaire->value,
        ])->assertCreated();

        $this->postJson($url, [
            'beneficiaire_id' => $beneficiaire->id,
            'mode' => ModeReglement::VirementBancaire->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('beneficiaire_id');

        $this->assertDatabaseCount('reglements', 1);
    }

    #[Test]
    public function un_gros_montant_ne_se_paie_pas_en_especes(): void
    {
        $dossier = $this->menerJusquAuReglement(15000000);
        $beneficiaire = $dossier->beneficiairesRetenus()->first();

        Sanctum::actingAs(User::factory()->comptable()->create());

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/reglements", [
            'beneficiaire_id' => $beneficiaire->id,
            'mode' => ModeReglement::Especes->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('mode');
    }

    #[Test]
    public function un_virement_sans_coordonnees_est_refuse(): void
    {
        $dossier = $this->menerJusquAuReglement();
        $beneficiaire = $dossier->beneficiairesRetenus()->first();
        $beneficiaire->forceFill(['coordonnees_reglement' => null])->save();

        Sanctum::actingAs(User::factory()->comptable()->create());

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/reglements", [
            'beneficiaire_id' => $beneficiaire->id,
            'mode' => ModeReglement::VirementBancaire->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('coordonnees');
    }

    #[Test]
    public function un_gestionnaire_nemet_pas_de_reglement(): void
    {
        $dossier = $this->menerJusquAuReglement();
        $beneficiaire = $dossier->beneficiairesRetenus()->first();

        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/reglements", [
            'beneficiaire_id' => $beneficiaire->id,
            'mode' => ModeReglement::VirementBancaire->value,
        ])->assertForbidden();
    }

    #[Test]
    public function constater_le_paiement_passe_le_beneficiaire_a_regle(): void
    {
        $dossier = $this->menerJusquAuReglement();
        $beneficiaire = $dossier->beneficiairesRetenus()->first();

        Sanctum::actingAs(User::factory()->comptable()->create());

        $id = $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/reglements", [
            'beneficiaire_id' => $beneficiaire->id,
            'mode' => ModeReglement::VirementBancaire->value,
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/dossiers/{$dossier->numero_sinistre}/reglements/{$id}", [
            'reference' => 'VIR-2026000451',
        ])
            ->assertOk()
            ->assertJsonPath('data.est_paye', true)
            ->assertJsonPath('data.reference', 'VIR-2026000451');

        $this->assertSame(StatutBeneficiaire::Regle, $beneficiaire->refresh()->statut);

        $this->assertDatabaseHas('evenement_dossiers', [
            'dossier_sinistre_id' => $dossier->id,
            'type' => TypeEvenement::ReglementPaye->value,
        ]);
    }

    #[Test]
    public function un_dossier_ne_passe_a_regle_que_si_tous_les_beneficiaires_le_sont(): void
    {
        $dossier = $this->menerJusquAuReglement(10000000, deux: true);
        [$premier, $second] = $dossier->beneficiairesRetenus()->all();

        Sanctum::actingAs($comptable = User::factory()->comptable()->create());

        $id = $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/reglements", [
            'beneficiaire_id' => $premier->id,
            'mode' => ModeReglement::VirementBancaire->value,
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/dossiers/{$dossier->numero_sinistre}/reglements/{$id}")->assertOk();

        // Le second n'a rien touché : le dossier ne peut pas être déclaré réglé.
        Sanctum::actingAs(User::factory()->responsable()->create());

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/statut", [
            'statut' => StatutDossier::Regle->value,
        ])->assertUnprocessable();

        // Une fois le second payé, le dossier se clôt.
        Sanctum::actingAs($comptable);

        $autre = $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/reglements", [
            'beneficiaire_id' => $second->id,
            'mode' => ModeReglement::MobileMoney->value,
            'coordonnees' => '+242066112233',
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/dossiers/{$dossier->numero_sinistre}/reglements/{$autre}")->assertOk();

        Sanctum::actingAs(User::factory()->responsable()->create());

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/statut", ['statut' => StatutDossier::Regle->value])->assertOk();
        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/statut", ['statut' => StatutDossier::Clos->value])
            ->assertOk()
            ->assertJsonPath('data.statut', StatutDossier::Clos->value);

        $this->assertNotNull($dossier->refresh()->clos_le);
    }

    #[Test]
    public function le_total_des_reglements_egale_le_capital_liquide(): void
    {
        $dossier = $this->menerJusquAuReglement(1000000, deux: true);

        // Deux parts égales sur un capital impair : rien ne doit se perdre.
        $this->assertSame(
            $dossier->capital_liquide_xaf,
            (int) $dossier->beneficiairesRetenus()->sum('montant_du_xaf'),
        );
    }

    /**
     * Monte un dossier jusqu'au statut « en cours de règlement », liquidation faite.
     */
    private function menerJusquAuReglement(int $capitalXaf = 15000000, bool $deux = false): DossierSinistre
    {
        $dossier = $this->dossierComplet($capitalXaf);

        if ($deux) {
            $dossier->beneficiaires()->update(['quote_part' => 50]);

            Beneficiaire::factory()->create([
                'dossier_sinistre_id' => $dossier->id,
                'quote_part' => 50,
            ]);

            ChecklistPieces::synchroniser($dossier);
            $dossier->pieces()->update(['statut' => StatutPiece::Conforme, 'updated_at' => now()]);
        }

        $gestionnaire = User::factory()->gestionnaire()->create();
        $responsable = User::factory()->responsable()->create();

        CircuitDossier::transiterVers($dossier->refresh(), StatutDossier::EnInstruction, $gestionnaire);
        CircuitDossier::transiterVers($dossier->refresh(), StatutDossier::EnLiquidation, $gestionnaire);
        LiquidationCapital::appliquer($dossier->refresh(), null, $gestionnaire);
        CircuitDossier::transiterVers($dossier->refresh(), StatutDossier::Valide, $responsable);
        CircuitDossier::transiterVers($dossier->refresh(), StatutDossier::EnReglement, $responsable);

        return $dossier->refresh();
    }
}
