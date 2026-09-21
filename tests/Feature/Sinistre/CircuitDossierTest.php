<?php

namespace Tests\Feature\Sinistre;

use App\Enums\MotifRejet;
use App\Enums\NatureSinistre;
use App\Enums\StatutDossier;
use App\Enums\TypeEvenement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\ConstruitDesDossiers;
use Tests\TestCase;

/**
 * Le circuit du dossier : ce qui est permis, ce qui ne l'est pas, et ce qui
 * doit rester tracé.
 */
class CircuitDossierTest extends TestCase
{
    use ConstruitDesDossiers, RefreshDatabase;

    #[Test]
    public function un_dossier_ouvert_ne_saute_pas_directement_au_reglement(): void
    {
        $dossier = $this->dossierComplet();
        Sanctum::actingAs(User::factory()->responsable()->create());

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/statut", [
            'statut' => StatutDossier::EnReglement->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('statut');

        $this->assertSame(StatutDossier::Ouvert, $dossier->refresh()->statut);
    }

    #[Test]
    public function on_ne_liquide_pas_un_dossier_aux_pieces_incompletes(): void
    {
        $dossier = $this->dossierOuvert();
        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/statut", [
            'statut' => StatutDossier::EnInstruction->value,
        ])->assertOk();

        $reponse = $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/statut", [
            'statut' => StatutDossier::EnLiquidation->value,
        ])->assertUnprocessable();

        $this->assertStringContainsString('pièce', $reponse->json('message'));
    }

    #[Test]
    public function un_dossier_complet_passe_en_liquidation(): void
    {
        $dossier = $this->dossierComplet();
        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/statut", [
            'statut' => StatutDossier::EnInstruction->value,
        ])->assertOk();

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/statut", [
            'statut' => StatutDossier::EnLiquidation->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.statut', StatutDossier::EnLiquidation->value);
    }

    #[Test]
    public function un_rejet_sans_motif_est_refuse(): void
    {
        $dossier = $this->dossierComplet();
        Sanctum::actingAs($responsable = User::factory()->responsable()->create());

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/statut", [
            'statut' => StatutDossier::EnInstruction->value,
        ])->assertOk();

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/statut", [
            'statut' => StatutDossier::Rejete->value,
        ])->assertUnprocessable();

        $this->assertNull($dossier->refresh()->decide_le);

        // Avec un motif, le rejet passe et il est daté et signé.
        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/statut", [
            'statut' => StatutDossier::Rejete->value,
            'motif' => MotifRejet::ExclusionContractuelle->value,
            'motif_detail' => 'Décès survenu au cours d\'une activité exclue au contrat.',
        ])
            ->assertOk()
            ->assertJsonPath('data.motif_rejet', MotifRejet::ExclusionContractuelle->value);

        $dossier->refresh();
        $this->assertNotNull($dossier->decide_le);
        $this->assertSame($responsable->id, $dossier->decide_par_id);
    }

    #[Test]
    public function un_gestionnaire_ne_prononce_pas_la_decision(): void
    {
        $dossier = $this->dossierComplet();
        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/statut", [
            'statut' => StatutDossier::EnInstruction->value,
        ])->assertOk();

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/statut", [
            'statut' => StatutDossier::Rejete->value,
            'motif' => MotifRejet::Prescription->value,
        ])->assertForbidden();
    }

    #[Test]
    public function un_controle_medical_na_pas_de_sens_sur_un_terme_de_contrat(): void
    {
        $dossier = $this->dossierComplet();
        $dossier->forceFill([
            'nature' => NatureSinistre::TermeContrat,
            'statut' => StatutDossier::EnInstruction,
        ])->save();

        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/statut", [
            'statut' => StatutDossier::ControleMedical->value,
        ])->assertUnprocessable();
    }

    #[Test]
    public function chaque_changement_de_statut_laisse_une_trace_au_journal(): void
    {
        $dossier = $this->dossierComplet();
        Sanctum::actingAs($gestionnaire = User::factory()->gestionnaire()->create());

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/statut", [
            'statut' => StatutDossier::EnInstruction->value,
            'commentaire' => 'Pièces reçues, instruction lancée.',
        ])->assertOk();

        $this->assertDatabaseHas('evenement_dossiers', [
            'dossier_sinistre_id' => $dossier->id,
            'type' => TypeEvenement::ChangementStatut->value,
            'auteur_id' => $gestionnaire->id,
        ]);

        $this->getJson("/api/v1/dossiers/{$dossier->numero_sinistre}/journal")
            ->assertOk()
            ->assertJsonPath('data.0.type', TypeEvenement::ChangementStatut->value)
            ->assertJsonPath('data.0.auteur.id', $gestionnaire->id);
    }

    #[Test]
    public function la_fiche_annonce_les_transitions_possibles(): void
    {
        $dossier = $this->dossierOuvert();
        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $possibles = $this->getJson("/api/v1/dossiers/{$dossier->numero_sinistre}")
            ->assertOk()
            ->json('data.transitions_possibles');

        $this->assertEqualsCanonicalizing(
            [StatutDossier::PiecesAFournir->value, StatutDossier::EnInstruction->value, StatutDossier::SansSuite->value],
            array_column($possibles, 'statut'),
        );
    }

    #[Test]
    public function un_dossier_rejete_peut_etre_rouvert_et_la_decision_est_effacee(): void
    {
        $dossier = $this->dossierComplet();
        Sanctum::actingAs($responsable = User::factory()->responsable()->create());

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/statut", ['statut' => StatutDossier::EnInstruction->value])->assertOk();
        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/statut", [
            'statut' => StatutDossier::Rejete->value,
            'motif' => MotifRejet::SinistreNonJustifie->value,
        ])->assertOk();

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/statut", [
            'statut' => StatutDossier::EnInstruction->value,
        ])->assertOk();

        $dossier->refresh();
        $this->assertSame(StatutDossier::EnInstruction, $dossier->statut);
        $this->assertNull($dossier->motif_rejet);
        $this->assertNull($dossier->decide_le);

        // Mais le rejet reste lisible dans le journal.
        $this->assertDatabaseHas('evenement_dossiers', [
            'dossier_sinistre_id' => $dossier->id,
            'type' => TypeEvenement::DossierReouvert->value,
        ]);
        $this->assertDatabaseHas('evenement_dossiers', [
            'dossier_sinistre_id' => $dossier->id,
            'type' => TypeEvenement::Decision->value,
        ]);
        $this->assertSame($responsable->id, $dossier->evenements()->first()->auteur_id);
    }

    #[Test]
    public function laffectation_a_un_agent_qui_ninstruit_pas_est_refusee(): void
    {
        $dossier = $this->dossierOuvert();
        $comptable = User::factory()->comptable()->create();

        Sanctum::actingAs(User::factory()->responsable()->create());

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/affectation", [
            'gestionnaire_id' => $comptable->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('gestionnaire_id');
    }
}
