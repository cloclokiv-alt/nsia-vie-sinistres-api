<?php

namespace App\Enum;

/**
 * Evenement qui declenche la prestation.
 *
 * En assurance vie, le « sinistre » recouvre aussi bien le deces que l'arrivee du
 * contrat a son terme : dans les deux cas un capital devient exigible.
 */
enum NatureSinistre: string
{
    case Deces = 'Décès';
    case InvaliditeAbsolueDefinitive = 'Invalidité absolue et définitive';
    case IncapaciteTemporaire = 'Incapacité temporaire';
    case MaladieGrave = 'Maladie grave';
    case TermeContrat = 'Terme du contrat';
    case RachatTotal = 'Rachat total';

    /** L'avis du medecin-conseil est-il requis avant liquidation ? */
    public function exigeControleMedical(): bool
    {
        return match ($this) {
            self::Deces, self::InvaliditeAbsolueDefinitive,
            self::IncapaciteTemporaire, self::MaladieGrave => true,
            default => false,
        };
    }

    /**
     * Le capital revient-il a des beneficiaires designes plutot qu'a l'assure ?
     * Au terme comme au rachat, c'est l'assure lui-meme qui est paye.
     */
    public function verseAuxBeneficiaires(): bool
    {
        return match ($this) {
            self::Deces, self::InvaliditeAbsolueDefinitive => true,
            default => false,
        };
    }

    /**
     * Pieces reclamees d'office a l'ouverture, avant meme l'identification des
     * beneficiaires.
     *
     * @return list<TypePiece>
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
