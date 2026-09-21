<?php

namespace Tests\Feature\Sinistre;

use App\Enums\NatureSinistre;
use App\Enums\StatutDossier;
use App\Enums\TypeEvenement;
use App\Enums\TypePiece;
use App\Models\Contrat;
use App\Models\Courrier;
use App\Models\DossierSinistre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'ouverture du dossier : le moment où un courrier devient un sinistre suivi.
 */
class OuvertureDossierTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function louverture_cree_le_dossier_pose_la_checklist_et_oriente_le_courrier(): void
    {
        $contrat = Contrat::factory()->create(['numero_police' => 'VIE-20190045']);
        $courrier = Courrier::factory()->create();
        $gestionnaire = User::factory()->gestionnaire()->create();

        Sanctum::actingAs($gestionnaire);

        $reponse = $this->postJson('/api/v1/dossiers', [
            'numero_police' => $contrat->numero_police,
            'numero_ordre_courrier' => $courrier->numero_ordre,
            'nature' => NatureSinistre::Deces->value,
            'date_survenance' => now()->subMonth()->toDateString(),
            'lieu_survenance' => 'Brazzaville',
        ])
            ->assertCreated()
            ->assertJsonPath('data.statut', StatutDossier::Ouvert->value)
            ->assertJsonPath('data.numero_sinistre', 'SIN-VIE-'.now()->format('Y').'-000001');

        $dossier = DossierSinistre::query()->firstOrFail();

        // Le courrier d'origine est rattaché et horodaté.
        $courrier->refresh();
        $this->assertSame($dossier->id, $courrier->dossier_sinistre_id);
        $this->assertNotNull($courrier->oriente_le);

        // La checklist d'un décès est posée d'office.
        $this->assertEqualsCanonicalizing(
            [
                TypePiece::DeclarationSinistre->value,
                TypePiece::ActeDeces->value,
                TypePiece::CertificatMedicalDeces->value,
                TypePiece::PieceIdentiteAssure->value,
            ],
            $dossier->pieces()->whereNull('beneficiaire_id')->pluck('type')->map->value->all(),
        );

        // Et le journal porte la trace de l'ouverture.
        $this->assertDatabaseHas('evenement_dossiers', [
            'dossier_sinistre_id' => $dossier->id,
            'type' => TypeEvenement::DossierOuvert->value,
        ]);

        $this->assertSame($reponse->json('data.id'), $dossier->id);
    }

    #[Test]
    public function la_checklist_suit_la_nature_du_sinistre(): void
    {
        $contrat = Contrat::factory()->create();
        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->postJson('/api/v1/dossiers', [
            'numero_police' => $contrat->numero_police,
            'nature' => NatureSinistre::InvaliditeAbsolueDefinitive->value,
            'date_survenance' => now()->subMonth()->toDateString(),
        ])->assertCreated();

        $types = DossierSinistre::query()->firstOrFail()->pieces()->pluck('type')->map->value->all();

        // Une invalidité réclame un dossier médical, pas un acte de décès.
        $this->assertContains(TypePiece::RapportMedical->value, $types);
        $this->assertContains(TypePiece::CertificatInvalidite->value, $types);
        $this->assertNotContains(TypePiece::ActeDeces->value, $types);
    }

    #[Test]
    public function un_contrat_emprunteur_reclame_le_decompte_bancaire(): void
    {
        $contrat = Contrat::factory()->emprunteur()->create();
        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->postJson('/api/v1/dossiers', [
            'numero_police' => $contrat->numero_police,
            'nature' => NatureSinistre::Deces->value,
            'date_survenance' => now()->subMonth()->toDateString(),
        ])->assertCreated();

        $this->assertDatabaseHas('piece_justificatives', [
            'type' => TypePiece::DecompteBancaire->value,
            'obligatoire' => true,
        ]);
    }

    #[Test]
    public function une_police_inconnue_est_refusee(): void
    {
        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->postJson('/api/v1/dossiers', [
            'numero_police' => 'VIE-INEXISTANTE',
            'nature' => NatureSinistre::Deces->value,
            'date_survenance' => now()->subMonth()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('numero_police');
    }

    #[Test]
    public function une_declaration_anterieure_a_la_survenance_est_refusee(): void
    {
        $contrat = Contrat::factory()->create();
        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->postJson('/api/v1/dossiers', [
            'numero_police' => $contrat->numero_police,
            'nature' => NatureSinistre::Deces->value,
            'date_survenance' => now()->subDays(5)->toDateString(),
            'date_declaration' => now()->subDays(20)->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('date_declaration');
    }

    #[Test]
    public function un_sinistre_dans_le_futur_est_refuse(): void
    {
        $contrat = Contrat::factory()->create();
        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->postJson('/api/v1/dossiers', [
            'numero_police' => $contrat->numero_police,
            'nature' => NatureSinistre::Deces->value,
            'date_survenance' => now()->addDay()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('date_survenance');
    }

    #[Test]
    public function lagent_du_guichet_nouvre_pas_de_dossier(): void
    {
        $contrat = Contrat::factory()->create();
        Sanctum::actingAs(User::factory()->agentCourrier()->create());

        $this->postJson('/api/v1/dossiers', [
            'numero_police' => $contrat->numero_police,
            'nature' => NatureSinistre::Deces->value,
            'date_survenance' => now()->subMonth()->toDateString(),
        ])->assertForbidden();

        $this->assertDatabaseCount('dossier_sinistres', 0);
    }

    #[Test]
    public function la_fiche_signale_si_la_garantie_jouait_au_jour_du_sinistre(): void
    {
        // Contrat sous carence de 12 mois, sinistre survenu au 6e mois.
        $contrat = Contrat::factory()->avecCarence(12)->create([
            'date_effet' => now()->subMonths(8)->startOfDay(),
        ]);

        $dossier = DossierSinistre::factory()->create([
            'contrat_id' => $contrat->id,
            'date_survenance' => now()->subMonths(2)->startOfDay(),
        ]);

        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->getJson("/api/v1/dossiers/{$dossier->numero_sinistre}")
            ->assertOk()
            ->assertJsonPath('meta.garantie_acquise', false);
    }

    #[Test]
    public function lecheance_dinstruction_est_posee_a_louverture(): void
    {
        $contrat = Contrat::factory()->create();
        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->postJson('/api/v1/dossiers', [
            'numero_police' => $contrat->numero_police,
            'nature' => NatureSinistre::Deces->value,
            'date_survenance' => now()->subMonth()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath(
                'data.echeance_instruction',
                now()->startOfDay()->addDays(DossierSinistre::DELAI_INSTRUCTION_JOURS)->toDateString(),
            );
    }
}
