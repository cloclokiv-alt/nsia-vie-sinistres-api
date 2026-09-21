<?php

namespace App\Tests\Documents;

use App\Documents\FormatsPieceJointe;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormatsPieceJointeTest extends TestCase
{
    #[DataProvider('scansAdmis')]
    public function testUnScanConformeEstAccepte(string $nom): void
    {
        self::assertNull(FormatsPieceJointe::refus($nom, 250_000));
    }

    /** @return iterable<string, array{string}> */
    public static function scansAdmis(): iterable
    {
        yield 'pdf' => ['acte-de-deces.pdf'];
        yield 'jpeg' => ['piece-identite.jpeg'];
        yield 'png' => ['rib.png'];
        yield 'tiff' => ['acte-mariage.tiff'];
        yield 'heic' => ['photo.heic'];
        yield 'extension en majuscules' => ['ACTE.PDF'];
    }

    public function testUnExecutableEstRefuse(): void
    {
        $refus = FormatsPieceJointe::refus('script.exe', 40_000);

        self::assertNotNull($refus);
        self::assertStringContainsString('Formats acceptés', $refus);
    }

    public function testUnClasseurNEstPasUnePieceDeDossier(): void
    {
        // Le perimetre est plus etroit que celui de l'ERP qualite : un dossier sinistre
        // n'archive que des scans, jamais un tableur.
        self::assertNotNull(FormatsPieceJointe::refus('calcul.xlsx', 10_000));
        self::assertNotNull(FormatsPieceJointe::refus('note.docx', 10_000));
    }

    public function testUnFichierSansExtensionEstRefuse(): void
    {
        self::assertNotNull(FormatsPieceJointe::refus('scan', 10_000));
        self::assertNotNull(FormatsPieceJointe::refus(null, 10_000));
    }

    public function testUnFichierVideEstRefuse(): void
    {
        $refus = FormatsPieceJointe::refus('acte.pdf', 0);

        self::assertNotNull($refus);
        self::assertStringContainsString('vide', $refus);
    }

    public function testUnScanTropLourdEstRefuseAvecLesDeuxTailles(): void
    {
        $refus = FormatsPieceJointe::refus('enorme.pdf', FormatsPieceJointe::TAILLE_MAXIMALE_OCTETS + 1);

        self::assertNotNull($refus);
        self::assertStringContainsString('Mo', $refus);
    }

    public function testLePlafondEffectifNeDepasseJamaisCeluiDeLApplication(): void
    {
        // Sur un mutualise, c'est souvent php.ini qui commande : le plafond annonce
        // doit etre le plus bas des deux, jamais le plus flatteur.
        self::assertLessThanOrEqual(
            FormatsPieceJointe::TAILLE_MAXIMALE_OCTETS,
            FormatsPieceJointe::tailleMaximaleEffective(),
        );
        self::assertGreaterThan(0, FormatsPieceJointe::tailleMaximaleEffective());
    }

    public function testLAttributAcceptCouvreExactementLesFormatsAdmis(): void
    {
        $accept = FormatsPieceJointe::accept();

        self::assertStringContainsString('.pdf', $accept);
        self::assertStringContainsString('.heic', $accept);
        self::assertStringNotContainsString('.xlsx', $accept);

        foreach (explode(',', $accept) as $extension) {
            self::assertTrue(
                FormatsPieceJointe::estAutorise('scan'.$extension),
                $extension.' est annonce par accept mais refuse par la validation',
            );
        }
    }

    public function testUneTailleSeLitEnMoOuEnKo(): void
    {
        self::assertSame('2,0 Mo', FormatsPieceJointe::lisible(2 * 1024 * 1024));
        self::assertSame('512 Ko', FormatsPieceJointe::lisible(512 * 1024));
    }
}
