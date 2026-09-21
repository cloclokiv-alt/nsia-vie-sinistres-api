<?php

namespace Database\Seeders;

use App\Enums\CanalReception;
use App\Enums\ModeReglement;
use App\Enums\NatureSinistre;
use App\Enums\QualiteBeneficiaire;
use App\Enums\RoleUtilisateur;
use App\Enums\StatutBeneficiaire;
use App\Enums\StatutDossier;
use App\Enums\StatutPiece;
use App\Enums\TypeEvenement;
use App\Models\Contrat;
use App\Models\Courrier;
use App\Models\DossierSinistre;
use App\Models\User;
use App\Support\ChecklistPieces;
use App\Support\CircuitDossier;
use App\Support\JournalDossier;
use App\Support\LiquidationCapital;
use Illuminate\Database\Seeder;

/**
 * Trois dossiers qui montrent le circuit à trois moments différents :
 * un qui vient d'arriver, un en cours d'instruction, un mené jusqu'à la clôture.
 *
 * Utile pour prendre en main l'API sans saisir quoi que ce soit.
 */
class DossiersDemonstrationSeeder extends Seeder
{
    public function run(): void
    {
        $agent = User::query()->where('role', RoleUtilisateur::AgentCourrier)->firstOrFail();
        $gestionnaire = User::query()->where('role', RoleUtilisateur::GestionnaireSinistre)->firstOrFail();
        $responsable = User::query()->where('role', RoleUtilisateur::ResponsableSinistres)->firstOrFail();
        $comptable = User::query()->where('role', RoleUtilisateur::Comptable)->firstOrFail();

        // 1. Un courrier tout juste déposé, pas encore orienté.
        $this->enregistrerCourrier($agent, 'VIE-20210338', 'Déclaration de décès — succession MASSAMBA');

        // 2. Un dossier en cours d'instruction, pièces partiellement reçues.
        $enCours = $this->ouvrirDossier($agent, $gestionnaire, 'VIE-20170112', 'OKEMBA');
        CircuitDossier::transiterVers($enCours, StatutDossier::PiecesAFournir, $gestionnaire);

        // 3. Un dossier mené de bout en bout.
        $this->menerAuBout($agent, $gestionnaire, $responsable, $comptable);
    }

    private function enregistrerCourrier(User $agent, string $police, string $objet): Courrier
    {
        $courrier = Courrier::create([
            'canal' => CanalReception::Guichet,
            'date_reception' => now()->subDays(2),
            'expediteur_nom' => 'MASSAMBA Clotilde',
            'expediteur_qualite' => 'Sœur de l\'assurée',
            'expediteur_telephone' => '+242066123456',
            'expediteur_adresse' => 'Avenue de l\'Indépendance, Pointe-Noire',
            'objet' => $objet,
            'nombre_pieces' => 4,
            'numero_police_declare' => $police,
            'recu_par_id' => $agent->id,
        ]);

        $courrier->remettreAccuse();

        return $courrier;
    }

    private function ouvrirDossier(User $agent, User $gestionnaire, string $police, string $famille): DossierSinistre
    {
        $contrat = Contrat::query()->where('numero_police', $police)->firstOrFail();

        $courrier = Courrier::create([
            'canal' => CanalReception::Agence,
            'date_reception' => now()->subDays(20),
            'expediteur_nom' => $famille.' Marie',
            'expediteur_qualite' => 'Conjointe survivante',
            'expediteur_telephone' => '+242055998877',
            'objet' => 'Déclaration de décès',
            'nombre_pieces' => 3,
            'numero_police_declare' => $police,
            'recu_par_id' => $agent->id,
        ]);
        $courrier->remettreAccuse();

        $declaration = now()->subDays(20)->startOfDay();

        $dossier = DossierSinistre::create([
            'contrat_id' => $contrat->id,
            'courrier_id' => $courrier->id,
            'nature' => NatureSinistre::Deces,
            'statut' => StatutDossier::Ouvert,
            'date_survenance' => now()->subDays(35)->startOfDay(),
            'date_declaration' => $declaration,
            'lieu_survenance' => 'Dolisie',
            'circonstances' => 'Décès de cause naturelle, constaté à l\'hôpital général.',
            'gestionnaire_id' => $gestionnaire->id,
            'ouvert_par_id' => $gestionnaire->id,
            'echeance_instruction' => DossierSinistre::echeanceDepuis($declaration),
        ]);

        JournalDossier::enregistrer(
            $dossier,
            TypeEvenement::DossierOuvert,
            sprintf('Dossier ouvert sur la police %s (décès).', $contrat->numero_police),
            $gestionnaire,
        );

        $dossier->beneficiaires()->create([
            'nom' => $famille,
            'prenoms' => 'Marie',
            'qualite' => QualiteBeneficiaire::Conjoint,
            'statut' => StatutBeneficiaire::Identifie,
            'quote_part' => 100,
            'telephone' => '+242055998877',
            'mode_reglement' => ModeReglement::VirementBancaire,
            'coordonnees_reglement' => 'CG3900100'.random_int(1000000000, 9999999999),
        ]);

        ChecklistPieces::synchroniser($dossier);
        $courrier->orienterVers($dossier);

        return $dossier;
    }

    private function menerAuBout(User $agent, User $gestionnaire, User $responsable, User $comptable): void
    {
        $dossier = $this->ouvrirDossier($agent, $gestionnaire, 'VIE-20190045', 'NGOMA');

        // Toutes les pièces arrivent et sont déclarées conformes. En démonstration
        // on ne joint pas de fichier : le circuit, lui, est joué en entier.
        $dossier->pieces()->update([
            'statut' => StatutPiece::Conforme,
            'deposee_le' => now()->subDays(10),
            'deposee_par_id' => $agent->id,
            'controlee_le' => now()->subDays(9),
            'controlee_par_id' => $gestionnaire->id,
            'updated_at' => now(),
        ]);

        CircuitDossier::transiterVers($dossier, StatutDossier::EnInstruction, $gestionnaire);
        CircuitDossier::transiterVers($dossier, StatutDossier::EnLiquidation, $gestionnaire);

        LiquidationCapital::appliquer($dossier->refresh(), null, $gestionnaire);

        CircuitDossier::transiterVers($dossier->refresh(), StatutDossier::Valide, $responsable);
        CircuitDossier::transiterVers($dossier->refresh(), StatutDossier::EnReglement, $responsable);

        $beneficiaire = $dossier->beneficiairesRetenus()->first();

        $reglement = $dossier->reglements()->create([
            'beneficiaire_id' => $beneficiaire->id,
            'montant_xaf' => $beneficiaire->montant_du_xaf,
            'mode' => ModeReglement::VirementBancaire,
            'coordonnees' => $beneficiaire->coordonnees_reglement,
            'emis_le' => now()->subDays(2),
            'emis_par_id' => $comptable->id,
        ]);

        $reglement->constaterPaiement($comptable, 'VIR-2026000451');
        $beneficiaire->forceFill(['statut' => StatutBeneficiaire::Regle])->save();

        JournalDossier::enregistrer(
            $dossier,
            TypeEvenement::ReglementPaye,
            sprintf('Paiement de %s FCFA constaté (réf. VIR-2026000451).', number_format($reglement->montant_xaf, 0, ',', ' ')),
            $comptable,
        );

        CircuitDossier::transiterVers($dossier->refresh(), StatutDossier::Regle, $responsable);
        CircuitDossier::transiterVers($dossier->refresh(), StatutDossier::Clos, $responsable);
    }
}
