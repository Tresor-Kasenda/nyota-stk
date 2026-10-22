<?php

namespace Tests\Unit;

use App\Domain\Money;
use App\Domain\Sale;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SaleTest extends TestCase
{
    public function test_calculates_exact_discount_tax_and_change_without_framework(): void
    {
        $sale = Sale::calculate(['lines' => [['_iC' => 'P', 'qty' => 3, 'unitPrice' => '2.75', 'totalPrice' => '8.25']], 'discount' => '10', 'vat' => '16', 'ca' => '8.61', 'pos' => '0', '_ca' => '8.61', '_at' => '10', '_mop' => 'Cash'], ['P' => (object) ['name' => 'Produit', 'unitPrice' => '2.75']], true);

        $this->assertSame(825, $sale->subtotal);
        $this->assertSame(83, $sale->discount);
        $this->assertSame(119, $sale->tax);
        $this->assertSame(861, $sale->total);
        $this->assertSame(139, $sale->change);
    }

    public static function invalidAmounts(): array
    {
        return [['-1'], ['1e2'], ['NaN'], ['1.001'], [[]], [null]];
    }

    #[DataProvider('invalidAmounts')]
    public function test_rejects_invalid_money(mixed $value): void
    {
        $this->expectException(DomainException::class);

        Money::cents($value);
    }

    public function test_basic_operator_cannot_override_discount(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Modification de remise ou de TVA non autorisée');

        Sale::calculate(['lines' => [['_iC' => 'P', 'qty' => 1, 'unitPrice' => 10, 'totalPrice' => 10]], 'discount' => 100], [], false);
    }
}
