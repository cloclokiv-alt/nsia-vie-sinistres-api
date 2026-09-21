<?php

namespace App\Liquidation;

/** Levee quand les quotes-parts ne totalisent pas exactement 100 %. */
final class RepartitionInvalide extends \DomainException
{
    public static function total(int $centiemes): self
    {
        return new self(sprintf(
            'La répartition doit totaliser 100 %% ; elle totalise %s %%.',
            number_format($centiemes / 100, 2, ',', ' '),
        ));
    }
}
