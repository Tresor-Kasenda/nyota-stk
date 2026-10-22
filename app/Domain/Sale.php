<?php

declare(strict_types=1);

namespace App\Domain;

use DomainException;

final readonly class Sale
{
    /** @param array<int, array<string, mixed>> $lines */
    public function __construct(
        public array $lines, public int $subtotal, public int $discount, public int $tax,
        public int $total, public int $cash, public int $debt, public int $tendered,
        public int $change, public string $method, public int $discountRate, public int $taxRate,
    ) {}

    /** @param array<string, mixed> $input @param array<string, object> $products */
    public static function calculate(array $input, array $products, bool $super): self
    {
        $requested = $input['lines'] ?? [];
        if (! is_array($requested) || count($requested) < 1 || count($requested) > 100) {
            throw new DomainException('Sélectionnez entre 1 et 100 articles');
        }
        $discountRate = self::rate($input['discount'] ?? 0);
        $taxRate = self::rate($input['vat'] ?? 0);
        if (! $super && ($discountRate !== 0 || $taxRate !== 0)) {
            throw new DomainException('Modification de remise ou de TVA non autorisée');
        }
        $lines = [];
        $subtotal = 0;
        foreach ($requested as $line) {
            if (! is_array($line) || ! is_scalar($line['_iC'] ?? null)) {
                throw new DomainException('Article invalide');
            }
            $code = (string) $line['_iC'];
            $product = $products[$code] ?? null;
            $qty = filter_var($line['qty'] ?? null, FILTER_VALIDATE_INT);
            if (! $product || $qty === false || $qty < 1 || $qty > 1000000) {
                throw new DomainException('Article ou quantité invalide');
            }
            $price = Money::cents($product->unitPrice);
            if (Money::cents($line['unitPrice'] ?? '') !== $price || Money::cents($line['totalPrice'] ?? '') !== $price * $qty) {
                throw new DomainException('Le prix de cet article a changé');
            }
            $subtotal += $price * $qty;
            if ($subtotal > 99999999999) {
                throw new DomainException('Montant maximal dépassé');
            }
            $lines[] = ['itemCode' => $code, 'itemName' => $product->name, 'quantity' => $qty, 'unitPrice' => Money::decimal($price), 'totalPrice' => Money::decimal($price * $qty)];
        }
        $discount = intdiv($subtotal * $discountRate + 5000, 10000);
        $tax = intdiv(($subtotal - $discount) * $taxRate + 5000, 10000);
        $total = $subtotal - $discount + $tax;
        $cash = Money::cents($input['ca'] ?? 0);
        $debt = Money::cents($input['pos'] ?? 0);
        $tendered = Money::cents($input['_at'] ?? 0);
        $method = (string) ($input['_mop'] ?? '');
        if (! in_array($method, ['Cash', 'POS', 'Cash and POS'], true) || $cash + $debt !== $total || Money::cents($input['_ca'] ?? '') !== $total || $tendered < $cash) {
            throw new DomainException('Répartition du paiement incorrecte');
        }
        if (($method === 'Cash' && $debt !== 0) || ($method === 'POS' && $cash !== 0)) {
            throw new DomainException('Mode de paiement incorrect');
        }

        return new self($lines, $subtotal, $discount, $tax, $total, $cash, $debt, $tendered, max(0, $tendered - $total), $method, $discountRate, $taxRate);
    }

    private static function rate(mixed $value): int
    {
        $rate = Money::cents($value === '' || $value === null ? 0 : $value);
        if ($rate > 10000) {
            throw new DomainException('Pourcentage invalide');
        }

        return $rate;
    }
}
