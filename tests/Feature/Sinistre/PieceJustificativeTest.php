<?php

namespace Tests\Feature\Sinistre;

use App\Enums\StatutPiece;
use App\Enums\TypeEvenement;
use App\Enums\TypePiece;
use App\Models\PieceJustificative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\ConstruitDesDossiers;
use Tests\TestCase;

/**
 * Les pièces du dossier : dépôt des scans, contrôle de conformité,
 * et cloisonnement du secret médical.
 */
class PieceJustificativeTest extends TestCase
{
    use ConstruitDesDossiers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('sinistres.disque'));
    }

    #[Test]
    public function le_depot_dun_scan_enregistre_le_fichier_et_son_empreinte(): void
    {
        $dossier = $this->dossierOuvert();
        $piece = $dossier->pieces()->where('type', TypePiece::ActeDeces)->firstOrFail();

        Sanctum::actingAs($agent = User::factory()->agentCourrier()->create());

        $this->post("/api/v1/dossiers/{$dossier->numero_sinistre}/pieces/{$piece->id}/depot", [
            'fichier' => UploadedFile::fake()->create('acte-de-deces.pdf', 250, 'application/pdf'),
        ])
            ->assertOk()
            ->assertJsonPath('data.statut', StatutPiece::Recue->value)
            ->assertJsonPath('data.fichier.nom_origine', 'acte-de-deces.pdf');

        $piece->refresh();

        Storage::disk(config('sinistres.disque'))->assertExists($piece->chemin);

        // Le chemin range la pièce sous le numéro de sinistre.
        $this->assertStringStartsWith("dossiers/{$dossier->numero_sinistre}/", $piece->chemin);

        // L'empreinte prouve que l'archive n'a pas été remplacée après coup.
        $this->assertSame(64, strlen($piece->empreinte));
        $this->assertSame($agent->id, $piece->deposee_par_id);
        $this->assertNotNull($piece->deposee_le);

        $this->assertDatabaseHas('evenement_dossiers', [
            'dossier_sinistre_id' => $dossier->id,
            'type' => TypeEvenement::PieceDeposee->value,
        ]);
    }

    #[Test]
    public function le_chemin_de_stockage_nest_jamais_expose(): void
    {
        $dossier = $this->dossierOuvert();
        $piece = $dossier->pieces()->where('type', TypePiece::ActeDeces)->firstOrFail();

        Sanctum::actingAs(User::factory()->agentCourrier()->create());

        $reponse = $this->post("/api/v1/dossiers/{$dossier->numero_sinistre}/pieces/{$piece->id}/depot", [
            'fichier' => UploadedFile::fake()->create('acte.pdf', 100, 'application/pdf'),
        ])->assertOk();

        $this->assertArrayNotHasKey('chemin', $reponse->json('data.fichier'));
        $this->assertStringNotContainsString('dossiers/SIN-VIE', $reponse->content());
    }

    #[Test]
    public function redeposer_remplace_lancien_scan_et_annule_le_controle(): void
    {
        $dossier = $this->dossierOuvert();
        $piece = $dossier->pieces()->where('type', TypePiece::ActeDeces)->firstOrFail();

        Sanctum::actingAs(User::factory()->gestionnaire()->create());
        $url = "/api/v1/dossiers/{$dossier->numero_sinistre}/pieces/{$piece->id}";

        $this->post("{$url}/depot", ['fichier' => UploadedFile::fake()->create('v1.pdf', 100, 'application/pdf')])->assertOk();

        $premierChemin = $piece->refresh()->chemin;

        $this->post("{$url}/controle", [
            'statut' => StatutPiece::NonConforme->value,
            'motif_non_conformite' => 'Acte illisible.',
        ])->assertOk();

        // Le bénéficiaire rapporte un scan correct.
        $this->post("{$url}/depot", ['fichier' => UploadedFile::fake()->create('v2.pdf', 120, 'application/pdf')])->assertOk();

        $piece->refresh();

        $this->assertSame(StatutPiece::Recue, $piece->statut);
        $this->assertNull($piece->controlee_le);
        $this->assertNull($piece->motif_non_conformite);
        $this->assertNotSame($premierChemin, $piece->chemin);

        // L'ancien fichier est effacé : pas d'orphelin sur le disque.
        Storage::disk(config('sinistres.disque'))->assertMissing($premierChemin);
        Storage::disk(config('sinistres.disque'))->assertExists($piece->chemin);
    }

    #[Test]
    public function un_refus_sans_motif_est_impossible(): void
    {
        $dossier = $this->dossierComplet();
        $piece = $dossier->pieces()->where('type', TypePiece::ActeDeces)->firstOrFail();

        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/pieces/{$piece->id}/controle", [
            'statut' => StatutPiece::NonConforme->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('motif_non_conformite');
    }

    #[Test]
    public function on_ne_controle_pas_une_piece_qui_na_pas_ete_deposee(): void
    {
        $dossier = $this->dossierOuvert();
        $piece = $dossier->pieces()->where('type', TypePiece::ActeDeces)->firstOrFail();

        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/pieces/{$piece->id}/controle", [
            'statut' => StatutPiece::Conforme->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('statut');
    }

    #[Test]
    public function une_piece_medicale_nest_pas_visible_du_gestionnaire(): void
    {
        $dossier = $this->dossierOuvert();

        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $types = $this->getJson("/api/v1/dossiers/{$dossier->numero_sinistre}/pieces")
            ->assertOk()
            ->json('data.*.type');

        $this->assertNotContains(TypePiece::CertificatMedicalDeces->value, $types);
        $this->assertContains(TypePiece::ActeDeces->value, $types);
    }

    #[Test]
    public function le_medecin_conseil_voit_et_controle_les_pieces_medicales(): void
    {
        $dossier = $this->dossierOuvert();
        $medicale = $dossier->pieces()->where('type', TypePiece::CertificatMedicalDeces)->firstOrFail();

        Sanctum::actingAs(User::factory()->medecinConseil()->create());

        $types = $this->getJson("/api/v1/dossiers/{$dossier->numero_sinistre}/pieces")
            ->assertOk()
            ->json('data.*.type');

        $this->assertContains(TypePiece::CertificatMedicalDeces->value, $types);

        $this->post("/api/v1/dossiers/{$dossier->numero_sinistre}/pieces/{$medicale->id}/depot", [
            'fichier' => UploadedFile::fake()->create('certificat.pdf', 90, 'application/pdf'),
        ])->assertOk();

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/pieces/{$medicale->id}/controle", [
            'statut' => StatutPiece::Conforme->value,
        ])->assertOk();
    }

    #[Test]
    public function le_guichet_ne_verse_pas_une_piece_medicale_au_dossier(): void
    {
        $dossier = $this->dossierOuvert();
        $medicale = $dossier->pieces()->where('type', TypePiece::CertificatMedicalDeces)->firstOrFail();

        // Le pli médical est fermé : le guichet l'enregistre sans l'ouvrir.
        Sanctum::actingAs(User::factory()->agentCourrier()->create());

        $this->post("/api/v1/dossiers/{$dossier->numero_sinistre}/pieces/{$medicale->id}/depot", [
            'fichier' => UploadedFile::fake()->create('certificat.pdf', 90, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->assertNull($medicale->refresh()->chemin);
    }

    #[Test]
    public function un_gestionnaire_ne_controle_pas_une_piece_medicale(): void
    {
        $dossier = $this->dossierComplet();
        $medicale = $dossier->pieces()->where('type', TypePiece::CertificatMedicalDeces)->firstOrFail();

        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/pieces/{$medicale->id}/controle", [
            'statut' => StatutPiece::Conforme->value,
        ])->assertForbidden();
    }

    #[Test]
    public function un_gestionnaire_ne_telecharge_pas_une_piece_medicale(): void
    {
        $dossier = $this->dossierComplet();
        $medicale = $dossier->pieces()->where('type', TypePiece::CertificatMedicalDeces)->firstOrFail();

        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->getJson("/api/v1/dossiers/{$dossier->numero_sinistre}/pieces/{$medicale->id}/fichier")
            ->assertForbidden();
    }

    #[Test]
    public function le_scan_se_telecharge_sous_son_nom_dorigine(): void
    {
        $dossier = $this->dossierOuvert();
        $piece = $dossier->pieces()->where('type', TypePiece::ActeDeces)->firstOrFail();

        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->post("/api/v1/dossiers/{$dossier->numero_sinistre}/pieces/{$piece->id}/depot", [
            'fichier' => UploadedFile::fake()->create('acte-de-deces.pdf', 100, 'application/pdf'),
        ])->assertOk();

        $this->get("/api/v1/dossiers/{$dossier->numero_sinistre}/pieces/{$piece->id}/fichier")
            ->assertOk()
            ->assertDownload('acte-de-deces.pdf');
    }

    #[Test]
    public function un_format_non_accepte_est_refuse(): void
    {
        $dossier = $this->dossierOuvert();
        $piece = $dossier->pieces()->where('type', TypePiece::ActeDeces)->firstOrFail();

        Sanctum::actingAs(User::factory()->agentCourrier()->create());

        $this->post("/api/v1/dossiers/{$dossier->numero_sinistre}/pieces/{$piece->id}/depot", [
            'fichier' => UploadedFile::fake()->create('script.exe', 40, 'application/x-msdownload'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('fichier');

        $this->assertNull($piece->refresh()->chemin);
    }

    #[Test]
    public function un_scan_trop_lourd_est_refuse(): void
    {
        $dossier = $this->dossierOuvert();
        $piece = $dossier->pieces()->where('type', TypePiece::ActeDeces)->firstOrFail();

        Sanctum::actingAs(User::factory()->agentCourrier()->create());

        $trop = config('sinistres.taille_max_ko') + 1;

        $this->post("/api/v1/dossiers/{$dossier->numero_sinistre}/pieces/{$piece->id}/depot", [
            'fichier' => UploadedFile::fake()->create('enorme.pdf', $trop, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('fichier');
    }

    #[Test]
    public function une_piece_de_la_checklist_ne_se_supprime_pas(): void
    {
        $dossier = $this->dossierOuvert();
        $piece = $dossier->pieces()->where('type', TypePiece::ActeDeces)->firstOrFail();

        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->deleteJson("/api/v1/dossiers/{$dossier->numero_sinistre}/pieces/{$piece->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('piece_justificatives', ['id' => $piece->id]);
    }

    #[Test]
    public function une_piece_ajoutee_a_la_main_se_retire(): void
    {
        $dossier = $this->dossierOuvert();

        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $id = $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/pieces", [
            'type' => TypePiece::Autre->value,
            'libelle' => 'Procès-verbal de constat',
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/v1/dossiers/{$dossier->numero_sinistre}/pieces/{$id}")->assertNoContent();

        $this->assertDatabaseMissing('piece_justificatives', ['id' => $id]);
    }

    #[Test]
    public function une_piece_autre_exige_un_libelle(): void
    {
        $dossier = $this->dossierOuvert();

        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/pieces", [
            'type' => TypePiece::Autre->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('libelle');
    }

    #[Test]
    public function la_liste_annonce_le_nombre_de_pieces_manquantes(): void
    {
        $dossier = $this->dossierOuvert();

        Sanctum::actingAs(User::factory()->medecinConseil()->create());

        $this->getJson("/api/v1/dossiers/{$dossier->numero_sinistre}/pieces")
            ->assertOk()
            ->assertJsonPath('meta.dossier_complet', false)
            ->assertJsonPath('meta.manquantes', PieceJustificative::query()->count());
    }
}
