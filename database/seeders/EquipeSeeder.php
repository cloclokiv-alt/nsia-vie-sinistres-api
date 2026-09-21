<?php

namespace Database\Seeders;

use App\Enums\RoleUtilisateur;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * L'équipe type d'une direction sinistres Vie : un agent au guichet,
 * deux gestionnaires, un médecin-conseil, un responsable et un comptable.
 */
class EquipeSeeder extends Seeder
{
    public function run(): void
    {
        $equipe = [
            ['NS10001', 'Bernadette MOUKALA', 'courrier@nsia-vie.test', RoleUtilisateur::AgentCourrier, 'Brazzaville Centre'],
            ['NS10002', 'Armand NGAMBOU', 'gestionnaire1@nsia-vie.test', RoleUtilisateur::GestionnaireSinistre, 'Brazzaville Centre'],
            ['NS10003', 'Clarisse ITOUA', 'gestionnaire2@nsia-vie.test', RoleUtilisateur::GestionnaireSinistre, 'Pointe-Noire'],
            ['NS10004', 'Dr Patrick OBAMI', 'medecin@nsia-vie.test', RoleUtilisateur::MedecinConseil, 'Brazzaville Centre'],
            ['NS10005', 'Sylvie MABIALA', 'responsable@nsia-vie.test', RoleUtilisateur::ResponsableSinistres, 'Brazzaville Centre'],
            ['NS10006', 'Roger BANZOUZI', 'comptable@nsia-vie.test', RoleUtilisateur::Comptable, 'Brazzaville Centre'],
            ['NS10000', 'Administrateur NSIA', 'admin@nsia-vie.test', RoleUtilisateur::Administrateur, 'Siège'],
        ];

        foreach ($equipe as [$matricule, $nom, $email, $role, $agence]) {
            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'matricule' => $matricule,
                    'name' => $nom,
                    'phone' => '+24206'.random_int(1000000, 9999999),
                    'password' => Hash::make('password'),
                    'role' => $role,
                    'agence' => $agence,
                    'actif' => true,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
