<?php

namespace Database\Factories;

use App\Enums\ModeReglement;
use App\Enums\QualiteBeneficiaire;
use App\Enums\StatutBeneficiaire;
use App\Models\Beneficiaire;
use App\Models\DossierSinistre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Beneficiaire>
 */
class BeneficiaireFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dossier_sinistre_id' => DossierSinistre::factory(),
            'nom' => mb_strtoupper(fake()->lastName()),
            'prenoms' => fake()->firstName(),
            'qualite' => QualiteBeneficiaire::Conjoint,
            'statut' => StatutBeneficiaire::Identifie,
            'quote_part' => 100,
            'date_naissance' => fake()->dateTimeBetween('-65 years', '-20 years'),
            'type_piece_identite' => 'CNI',
            'numero_piece_identite' => fake()->bothify('??########'),
            'telephone' => '+2420'.fake()->numerify('########'),
            'email' => fake()->optional()->safeEmail(),
            'adresse' => fake()->streetAddress().', Brazzaville',
            'mode_reglement' => ModeReglement::VirementBancaire,
            'coordonnees_reglement' => 'CG'.fake()->numerify('########################'),
        ];
    }

    public function qualite(QualiteBeneficiaire $qualite): static
    {
        return $this->state(fn () => ['qualite' => $qualite]);
    }

    public function quotePart(float $pourcentage): static
    {
        return $this->state(fn () => ['quote_part' => $pourcentage]);
    }

    public function statut(StatutBeneficiaire $statut): static
    {
        return $this->state(fn () => ['statut' => $statut]);
    }

    public function ecarte(string $motif = 'Renonciation au bénéfice'): static
    {
        return $this->state(fn () => [
            'statut' => StatutBeneficiaire::Ecarte,
            'motif_ecartement' => $motif,
        ]);
    }
}
