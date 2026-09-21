<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Ce seeder ne coupe pas les événements de modèle (pas de WithoutModelEvents) :
     * les numéros d'ordre et de sinistre sont attribués par un hook « creating ».
     * Les museler produirait des lignes sans numéro.
     */
    public function run(): void
    {
        $this->call([
            EquipeSeeder::class,
            PortefeuilleSeeder::class,
            DossiersDemonstrationSeeder::class,
        ]);
    }
}
