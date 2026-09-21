<?php

namespace App\Tests\Liquidation;

use App\Liquidation\RepartitionCapital;
use App\Liquidation\RepartitionInvalide;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Un partage de capital ne doit ni perdre ni inventer un franc : c'est la propriete
 * verifiee ici, y compris sur les divisions qui ne tombent pas juste.
 */
final class RepartitionCapitalTest extends TestCase
{
    public function testUnBeneficiaireUniqueRecoitTout(): void
    {
        self::assertSame([7 => 15000000], RepartitionCapital::repartir(15000000, [7 => 10000]));
    }

    public function testUnPartageEnDeuxMoitiesTombeJuste(): void
    {
        self::assertSame([1 => 4500000, 2 => 4500000], RepartitionCapital::repartir(9000000, [1 => 5000, 2 => 5000]));
    }

    public function testUnTiersIndivisibleNePerdAucunFranc(): void
    {
        $parts = RepartitionCapital::repartir(1000000, [1 => 3333, 2 => 3333, 3 => 3334]);

        self::assertSame(1000000, array_sum($parts));
        self::assertSame([333300, 333300, 333400], array_values($parts));
    }

    public function testLeReliquatVaAuPlusFortReste(): void
    {
        // 100 partages en 33,33 / 33,33 / 33,34 % : 33 + 33 + 33 = 99, le franc restant
        // revient a la plus grosse part.
        $parts = RepartitionCapital::repartir(100, [1 => 3333, 2 => 3333, 3 => 3334]);

        self::assertSame(100, array_sum($parts));
        self::assertSame(34, $parts[3]);
    }

    /** @param array<array-key, int> $centiemes */
    #[DataProvider('capitauxIndivisibles')]
    public function testLaSommeEstToujoursEgaleAuCapital(int $capital, array $centiemes): void
    {
        $parts = RepartitionCapital::repartir($capital, $centiemes);

        self::assertSame($capital, array_sum($parts), sprintf('capital %d mal reparti', $capital));
        self::assertCount(count($centiemes), $parts);
    }

    /** @return iterable<string, array{int, array<array-key, int>}> */
    public static function capitauxIndivisibles(): iterable
    {
        yield 'capital impair en deux' => [1000001, [1 => 5000, 2 => 5000]];
        yield 'quatre parts inegales' => [7777777, [1 => 1667, 2 => 1667, 3 => 1666, 4 => 5000]];
        yield 'trois francs en trois' => [3, [1 => 3333, 2 => 3333, 3 => 3334]];
        yield 'gros capital en quarts' => [999999999, [1 => 2500, 2 => 2500, 3 => 2500, 4 => 2500]];
        yield 'un franc seul' => [1, [1 => 5000, 2 => 5000]];
    }

    public function testUneRepartitionIncompleteEstRefusee(): void
    {
        $this->expectException(RepartitionInvalide::class);
        $this->expectExceptionMessage('90,00');

        RepartitionCapital::repartir(1000000, [1 => 5000, 2 => 4000]);
    }

    public function testUneRepartitionExcedentaireEstRefusee(): void
    {
        $this->expectException(RepartitionInvalide::class);

        RepartitionCapital::repartir(1000000, [1 => 6000, 2 => 6000]);
    }

    public function testUnPourcentageSaisiDevientDesCentiemes(): void
    {
        self::assertSame(3334, RepartitionCapital::enCentiemes(33.34));
        self::assertSame(10000, RepartitionCapital::enCentiemes(100));
        self::assertSame(50, RepartitionCapital::enCentiemes(0.5));
    }
}
