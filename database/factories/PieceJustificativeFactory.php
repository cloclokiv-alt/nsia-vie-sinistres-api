<?php

namespace Database\Factories;

use App\Enums\StatutPiece;
use App\Enums\TypePiece;
use App\Models\DossierSinistre;
use App\Models\PieceJustificative;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PieceJustificative>
 */
class PieceJustificativeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dossier_sinistre_id' => DossierSinistre::factory(),
            'beneficiaire_id' => null,
            'type' => TypePiece::ActeDeces,
            'statut' => StatutPiece::Attendue,
            'libelle' => null,
            'obligatoire' => true,
        ];
    }

    public function type(TypePiece $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }

    public function conforme(): static
    {
        return $this->state(fn () => [
            'statut' => StatutPiece::Conforme,
            'chemin' => 'sinistres/demo/'.fake()->uuid().'.pdf',
            'nom_origine' => 'acte-de-deces.pdf',
            'mime' => 'application/pdf',
            'taille_octets' => fake()->numberBetween(50000, 900000),
            'empreinte' => hash('sha256', fake()->uuid()),
            'deposee_le' => now(),
            'controlee_le' => now(),
        ]);
    }

    public function facultative(): static
    {
        return $this->state(fn () => ['obligatoire' => false]);
    }
}
