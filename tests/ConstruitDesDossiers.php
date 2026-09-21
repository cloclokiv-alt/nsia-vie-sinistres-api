<?php

namespace Tests;

use App\Enums\QualiteBeneficiaire;
use App\Enums\StatutPiece;
use App\Models\Beneficiaire;
use App\Models\Contrat;
use App\Models\DossierSinistre;
use App\Support\ChecklistPieces;

/**
 * Monte des dossiers dans un état donné, pour que chaque test parte du
 * moment qui l'intéresse plutôt que de rejouer tout le circuit.
 */
trait ConstruitDesDossiers
{
    /**
     * Un dossier décès ouvert, avec sa checklist et un conjoint bénéficiaire
     * à 100 %. Rien n'est encore fourni.
     */
    protected function dossierOuvert(int $capitalXaf = 15000000): DossierSinistre
    {
        $contrat = Contrat::factory()->create(['capital_garanti_xaf' => $capitalXaf]);

        $dossier = DossierSinistre::factory()->create(['contrat_id' => $contrat->id]);

        Beneficiaire::factory()->create([
            'dossier_sinistre_id' => $dossier->id,
            'qualite' => QualiteBeneficiaire::Conjoint,
            'quote_part' => 100,
        ]);

        ChecklistPieces::synchroniser($dossier);

        return $dossier->refresh();
    }

    /**
     * Le même dossier, toutes pièces reçues et déclarées conformes :
     * il est prêt à être liquidé.
     */
    protected function dossierComplet(int $capitalXaf = 15000000): DossierSinistre
    {
        $dossier = $this->dossierOuvert($capitalXaf);

        $dossier->pieces()->update([
            'statut' => StatutPiece::Conforme,
            'chemin' => 'dossiers/test/piece.pdf',
            'nom_origine' => 'piece.pdf',
            'mime' => 'application/pdf',
            'taille_octets' => 12345,
            'deposee_le' => now(),
            'controlee_le' => now(),
            'updated_at' => now(),
        ]);

        return $dossier->refresh();
    }
}
