<?php

namespace App\Enums;

/**
 * L'événement qui déclenche la prestation. En assurance vie, le « sinistre »
 * recouvre aussi bien le décès que l'arrivée du contrat à son terme.
 */
enum NatureSinistre: string
{
    case Deces = 'deces';
    case InvaliditeAbsolueDefinitive = 'invalidite_absolue_definitive';
    case IncapaciteTemporaire = 'incapacite_temporaire';
    case MaladieGrave = 'maladie_grave';
    case TermeContrat = 'terme_contrat';
    case RachatTotal = 'rachat_total';

    public function label(): string
    {
        return match ($this) {
            self::Deces => 'Décès de l\'assuré',
            self::InvaliditeAbsolueDefinitive => 'Invalidité absolue et définitive',
            self::IncapaciteTemporaire => 'Incapacité temporaire de travail',
            self::MaladieGrave => 'Maladie grave',
            self::TermeContrat => 'Arrivée du contrat à son terme',
            self::RachatTotal => 'Rachat total',
        };
    }

    /**
     * L'avis du médecin-conseil est-il requis avant liquidation ?
     */
    public function exigeControleMedical(): bool
    {
        return in_array($this, [
            self::Deces,
            self::InvaliditeAbsolueDefinitive,
            self::IncapaciteTemporaire,
            self::MaladieGrave,
        ], true);
    }

    /**
     * Le capital revient-il à des bénéficiaires désignés plutôt qu'à l'assuré lui-même ?
     * Au terme ou au rachat, c'est l'assuré qui est payé.
     */
    public function verseAuxBeneficiaires(): bool
    {
        return in_array($this, [self::Deces, self::InvaliditeAbsolueDefinitive], true);
    }

    /**
     * Les pièces réclamées d'office à l'ouverture du dossier, avant même
     * l'identification des bénéficiaires.
     *
     * @return array<int, TypePiece>
     */
    public function piecesDeBase(): array
    {
        return match ($this) {
            self::Deces => [
                TypePiece::DeclarationSinistre,
                TypePiece::ActeDeces,
                TypePiece::CertificatMedicalDeces,
                TypePiece::PieceIdentiteAssure,
            ],
            self::InvaliditeAbsolueDefinitive, self::IncapaciteTemporaire, self::MaladieGrave => [
                TypePiece::DeclarationSinistre,
                TypePiece::PieceIdentiteAssure,
                TypePiece::RapportMedical,
                TypePiece::CertificatInvalidite,
            ],
            self::TermeContrat => [
                TypePiece::DeclarationSinistre,
                TypePiece::PieceIdentiteAssure,
                TypePiece::AttestationVie,
            ],
            self::RachatTotal => [
                TypePiece::DeclarationSinistre,
                TypePiece::PieceIdentiteAssure,
            ],
        };
    }
}
