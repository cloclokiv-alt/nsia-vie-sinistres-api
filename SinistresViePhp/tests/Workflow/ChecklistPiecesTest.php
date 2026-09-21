<?php

namespace App\Tests\Workflow;

use App\Enum\NatureSinistre;
use App\Enum\QualiteBeneficiaire;
use App\Enum\TypeContrat;
use App\Enum\TypePiece;
use App\Workflow\ChecklistPieces;
use PHPUnit\Framework\TestCase;

final class ChecklistPiecesTest extends TestCase
{
    public function testUnDecesReclameLActeEtLeCertificatMedical(): void
    {
        $pieces = ChecklistPieces::pourLeDossier(NatureSinistre::Deces, TypeContrat::TemporaireDeces);

        self::assertContains(TypePiece::ActeDeces, $pieces);
        self::assertContains(TypePiece::CertificatMedicalDeces, $pieces);
        self::assertContains(TypePiece::DeclarationSinistre, $pieces);
    }

    public function testUneInvaliditeReclameUnDossierMedicalPasUnActeDeDeces(): void
    {
        $pieces = ChecklistPieces::pourLeDossier(
            NatureSinistre::InvaliditeAbsolueDefinitive,
            TypeContrat::TemporaireDeces,
        );

        self::assertContains(TypePiece::RapportMedical, $pieces);
        self::assertContains(TypePiece::CertificatInvalidite, $pieces);
        self::assertNotContains(TypePiece::ActeDeces, $pieces);
    }

    public function testUnContratEmprunteurReclameLeDecompteBancaire(): void
    {
        $pieces = ChecklistPieces::pourLeDossier(NatureSinistre::Deces, TypeContrat::Emprunteur);

        self::assertContains(TypePiece::DecompteBancaire, $pieces);
    }

    public function testUnAutreContratNeReclamePasDeDecompte(): void
    {
        $pieces = ChecklistPieces::pourLeDossier(NatureSinistre::Deces, TypeContrat::Mixte);

        self::assertNotContains(TypePiece::DecompteBancaire, $pieces);
    }

    public function testUnConjointProuveLeMariageUnEnfantSaNaissance(): void
    {
        self::assertContains(TypePiece::ActeMariage, ChecklistPieces::pourBeneficiaire(QualiteBeneficiaire::Conjoint));
        self::assertContains(TypePiece::ActeNaissance, ChecklistPieces::pourBeneficiaire(QualiteBeneficiaire::Enfant));
        self::assertContains(TypePiece::ActeNotoriete, ChecklistPieces::pourBeneficiaire(QualiteBeneficiaire::Heritier));
    }

    public function testUneBanqueBeneficiaireNAPasDePieceDIdentite(): void
    {
        $pieces = ChecklistPieces::pourBeneficiaire(QualiteBeneficiaire::Creancier);

        self::assertContains(TypePiece::Rib, $pieces);
        self::assertContains(TypePiece::DecompteBancaire, $pieces);
        self::assertNotContains(TypePiece::PieceIdentiteBeneficiaire, $pieces);
        self::assertNotContains(TypePiece::ActeNaissance, $pieces);
    }

    public function testChaqueBeneficiaireApporteSesPropresPieces(): void
    {
        $lignes = ChecklistPieces::complete(
            NatureSinistre::Deces,
            TypeContrat::TemporaireDeces,
            [QualiteBeneficiaire::Conjoint, QualiteBeneficiaire::Enfant],
        );

        $dossier = array_filter($lignes, static fn (array $l): bool => null === $l['beneficiaire']);
        $premier = array_filter($lignes, static fn (array $l): bool => 0 === $l['beneficiaire']);
        $second = array_filter($lignes, static fn (array $l): bool => 1 === $l['beneficiaire']);

        // Un seul acte de deces pour le dossier, mais deux pieces d'identite.
        self::assertCount(4, $dossier);
        self::assertCount(3, $premier);
        self::assertCount(3, $second);
    }

    public function testLaChecklistNeReclameJamaisDeuxFoisLaMemePiece(): void
    {
        // Un emprunteur dont la banque est beneficiaire : le decompte est reclame au
        // dossier et au creancier. Chacun a sa ligne, mais aucune n'est doublee.
        $dossier = ChecklistPieces::pourLeDossier(NatureSinistre::Deces, TypeContrat::Emprunteur);

        self::assertSame(count($dossier), count(array_unique($dossier, SORT_REGULAR)));
    }

    public function testUnTermeDeContratNeReclameAucunePieceMedicale(): void
    {
        $pieces = ChecklistPieces::pourLeDossier(NatureSinistre::TermeContrat, TypeContrat::Mixte);

        foreach ($pieces as $piece) {
            self::assertFalse($piece->estMedicale(), $piece->value.' ne devrait pas etre reclamee au terme');
        }

        self::assertContains(TypePiece::AttestationVie, $pieces);
    }
}
