<?php

namespace App\Tests\Numerotation;

use App\Enum\SerieReference;
use App\Numerotation\FormatReference;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormatReferenceTest extends TestCase
{
    public function testUneReferenceSeComposeAuFormatDuRegistre(): void
    {
        self::assertSame('SIN26-0001', FormatReference::composer(SerieReference::Sinistre, 2026, 1));
        self::assertSame('COU26-0450', FormatReference::composer(SerieReference::Courrier, 2026, 450));
        self::assertSame('SIN27-0001', FormatReference::composer(SerieReference::Sinistre, 2027, 1));
    }

    public function testLesDeuxRegistresSontNumerotesIndependamment(): void
    {
        // La premiere declaration de l'annee est SIN26-0001 meme si le registre du
        // courrier en est deja a COU26-0450.
        self::assertSame('SIN26-0001', FormatReference::composer(SerieReference::Sinistre, 2026, 1));
        self::assertSame('COU26-0450', FormatReference::composer(SerieReference::Courrier, 2026, 450));
    }

    public function testUnRangHorsFormatEstRefuse(): void
    {
        $this->expectException(\DomainException::class);

        FormatReference::composer(SerieReference::Sinistre, 2026, 10000);
    }

    public function testUnRangNulEstRefuse(): void
    {
        $this->expectException(\DomainException::class);

        FormatReference::composer(SerieReference::Courrier, 2026, 0);
    }

    public function testUneReferenceSeRelit(): void
    {
        self::assertTrue(FormatReference::estValide('SIN26-0042'));
        self::assertSame(SerieReference::Sinistre, FormatReference::serie('SIN26-0042'));
        self::assertSame(2026, FormatReference::annee('SIN26-0042'));
        self::assertSame(42, FormatReference::rang('SIN26-0042'));
    }

    #[DataProvider('referencesInvalides')]
    public function testCeQuiNEstPasUneReferenceEstRejete(string $chaine): void
    {
        self::assertFalse(FormatReference::estValide($chaine));
        self::assertNull(FormatReference::serie($chaine));
        self::assertNull(FormatReference::rang($chaine));
    }

    /** @return iterable<string, array{string}> */
    public static function referencesInvalides(): iterable
    {
        yield 'prefixe inconnu' => ['ACT26-0001'];
        yield 'annee sur quatre chiffres' => ['SIN2026-0001'];
        yield 'rang trop court' => ['SIN26-1'];
        yield 'sans tiret' => ['SIN260001'];
        yield 'minuscules' => ['sin26-0001'];
        yield 'vide' => [''];
        yield 'texte libre' => ['dossier de Madame NGOMA'];
    }

    public function testLAnneeNEstPasDevinee(): void
    {
        // Une reference lue en 2030 designe bien 2026 si elle porte « 26 ».
        self::assertSame(2026, FormatReference::annee('SIN26-0001'));
    }
}
