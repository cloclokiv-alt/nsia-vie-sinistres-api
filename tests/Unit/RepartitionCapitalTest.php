<?php

namespace Tests\Unit;

use App\Exceptions\TransitionInterdite;
use App\Support\RepartitionCapital;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Un partage de capital ne doit ni perdre ni inventer un franc : c'est la
 * propriété qu'on vérifie ici, y compris sur les divisions qui ne tombent pas juste.
 */
class RepartitionCapitalTest extends TestCase
{
    #[Test]
    public function un_beneficiaire_unique_recoit_tout(): void
    {
        $this->assertSame([7 => 15000000], RepartitionCapital::repartir(15000000, [7 => 10000]));
    }

    #[Test]
    public function un_partage_en_deux_moities_tombe_juste(): void
    {
        $parts = RepartitionCapital::repartir(9000000, [1 => 5000, 2 => 5000]);

        $this->assertSame([1 => 4500000, 2 => 4500000], $parts);
    }

    #[Test]
    public function un_tiers_indivisible_ne_perd_aucun_franc(): void
    {
        // 1 000 000 en trois parts égales : la division laisse 1 franc.
        $parts = RepartitionCapital::repartir(1000000, [1 => 3333, 2 => 3333, 3 => 3334]);

        $this->assertSame(1000000, array_sum($parts));
        $this->assertSame([333300, 333300, 333400], array_values($parts));
    }

    #[Test]
    public function le_reliquat_va_aux_plus_forts_restes(): void
    {
        // 100 partagés en 3 parts de 33,33/33,33/33,34 % : 33 + 33 + 33 = 99,
        // le franc restant revient à la plus grosse part.
        $parts = RepartitionCapital::repartir(100, [1 => 3333, 2 => 3333, 3 => 3334]);

        $this->assertSame(100, array_sum($parts));
        $this->assertSame(34, $parts[3]);
    }

    #[Test]
    public function la_somme_est_toujours_egale_au_capital(): void
    {
        $cas = [
            [1000001, [1 => 5000, 2 => 5000]],
            [7777777, [1 => 1667, 2 => 1667, 3 => 1666, 4 => 5000]],
            [3, [1 => 3333, 2 => 3333, 3 => 3334]],
            [999999999, [1 => 2500, 2 => 2500, 3 => 2500, 4 => 2500]],
        ];

        foreach ($cas as [$capital, $centiemes]) {
            $parts = RepartitionCapital::repartir($capital, $centiemes);

            $this->assertSame($capital, array_sum($parts), "capital {$capital} mal réparti");
            $this->assertCount(count($centiemes), $parts);
        }
    }

    #[Test]
    public function une_repartition_incomplete_est_refusee(): void
    {
        $this->expectException(TransitionInterdite::class);
        $this->expectExceptionMessage('100');

        RepartitionCapital::repartir(1000000, [1 => 5000, 2 => 4000]);
    }

    #[Test]
    public function une_repartition_excedentaire_est_refusee(): void
    {
        $this->expectException(TransitionInterdite::class);

        RepartitionCapital::repartir(1000000, [1 => 6000, 2 => 6000]);
    }
}
