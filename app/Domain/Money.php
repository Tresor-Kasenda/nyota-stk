<?php

declare(strict_types=1);

namespace App\Domain;

use DomainException;

final class Money
{
    public static function cents(mixed $value): int
    {
        if (! is_scalar($value) || ! preg_match('/^\d{1,9}(?:\.\d{1,2})?$/D', (string) $value)) {
            throw new DomainException('Montant invalide');
        }
        $parts = explode('.', (string) $value);

        return ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    public static function decimal(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
