<?php

namespace Database\Seeders;

use App\Enums\StatutContrat;
use App\Enums\TypeContrat;
use App\Models\Assure;
use App\Models\Contrat;
use Illuminate\Database\Seeder;

/**
 * Un échantillon de portefeuille représentatif : une temporaire décès,
 * un contrat emprunteur adossé à un prêt bancaire, et un contrat mixte
 * avec épargne constituée.
 */
class PortefeuilleSeeder extends Seeder
{
    public function run(): void
    {
        $polices = [
            [
                'assure' => ['NGOMA', 'Jean-Pierre', '1968-04-12', 'M', 'Brazzaville'],
                'police' => 'VIE-20190045',
                'type' => TypeContrat::TemporaireDeces,
                'capital' => 15000000,
                'provision' => 0,
                'preteur' => null,
            ],
            [
                'assure' => ['MASSAMBA', 'Adèle', '1981-11-03', 'F', 'Pointe-Noire'],
                'police' => 'VIE-20210338',
                'type' => TypeContrat::Emprunteur,
                'capital' => 8500000,
                'provision' => 0,
                'preteur' => 'BGFIBank Congo',
            ],
            [
                'assure' => ['OKEMBA', 'Firmin', '1975-07-21', 'M', 'Dolisie'],
                'police' => 'VIE-20170112',
                'type' => TypeContrat::Mixte,
                'capital' => 6000000,
                'provision' => 2400000,
                'preteur' => null,
            ],
        ];

        foreach ($polices as $ligne) {
            [$nom, $prenoms, $naissance, $sexe, $ville] = $ligne['assure'];

            $assure = Assure::query()->updateOrCreate(
                ['numero_piece_identite' => 'CG'.substr(md5($ligne['police']), 0, 8)],
                [
                    'nom' => $nom,
                    'prenoms' => $prenoms,
                    'date_naissance' => $naissance,
                    'lieu_naissance' => $ville,
                    'sexe' => $sexe,
                    'type_piece_identite' => 'CNI',
                    'telephone' => '+24206'.random_int(1000000, 9999999),
                    'adresse' => $ville,
                    'profession' => 'Salarié',
                ],
            );

            Contrat::query()->updateOrCreate(
                ['numero_police' => $ligne['police']],
                [
                    'assure_id' => $assure->id,
                    'type' => $ligne['type'],
                    'statut' => StatutContrat::EnVigueur,
                    'date_effet' => now()->subYears(5)->startOfDay(),
                    'date_echeance' => now()->addYears(5)->startOfDay(),
                    'capital_garanti_xaf' => $ligne['capital'],
                    'provision_mathematique_xaf' => $ligne['provision'],
                    'prime_xaf' => 45000,
                    'periodicite' => 'trimestrielle',
                    'date_derniere_prime' => now()->subMonth()->startOfDay(),
                    'carence_mois' => 0,
                    'organisme_preteur' => $ligne['preteur'],
                ],
            );
        }
    }
}
