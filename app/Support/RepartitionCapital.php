<?php

namespace App\Support;

use App\Exceptions\TransitionInterdite;
use App\Models\Beneficiaire;

/**
 * Répartit un capital entre bénéficiaires sans jamais perdre ni inventer un franc.
 *
 * La division laisse toujours un reliquat : 1 000 000 partagés en trois parts de
 * 33,33 / 33,33 / 33,34 % tombe juste, mais 1 000 000 en trois tiers égaux laisse
 * 1 franc. On l'attribue par la méthode du plus fort reste, et la somme des parts
 * est garantie égale au capital.
 */
final class RepartitionCapital
{
    /**
     * @param  array<array-key, int>  $centiemes  quotes-parts en centièmes de pour cent
     * @return array<array-key, int> montants en francs CFA, mêmes clés
     */
    public static function repartir(int $capitalXaf, array $centiemes): array
    {
        if ($centiemes === []) {
            return [];
        }

        $total = array_sum($centiemes);

        if ($total !== Beneficiaire::TOTAL_CENTIEMES) {
            throw TransitionInterdite::parce(sprintf(
                'La répartition doit totaliser 100 %% ; elle totalise %s %%.',
                number_format($total / 100, 2, ',', ' '),
            ));
        }

        $parts = [];
        $restes = [];

        foreach ($centiemes as $cle => $part) {
            $exact = $capitalXaf * $part;
            $parts[$cle] = intdiv($exact, Beneficiaire::TOTAL_CENTIEMES);
            $restes[$cle] = $exact % Beneficiaire::TOTAL_CENTIEMES;
        }

        $reliquat = $capitalXaf - array_sum($parts);

        // Les plus forts restes emportent les francs qui n'ont pas pu être divisés.
        arsort($restes);

        foreach (array_keys($restes) as $cle) {
            if ($reliquat <= 0) {
                break;
            }

            $parts[$cle]++;
            $reliquat--;
        }

        return $parts;
    }
}
