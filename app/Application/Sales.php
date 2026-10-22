<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Money;
use App\Domain\Sale;
use DomainException;

final readonly class Sales
{
    public function __construct(private Store $store) {}

    /** @param array<string, mixed> $input */
    public function create(array $input, int $actor, bool $super): string
    {
        return $this->store->transaction(function () use ($input, $actor, $super): string {
            $lines = $input['lines'] ?? [];
            if (! is_array($lines)) {
                throw new DomainException('Articles invalides');
            }
            $sale = Sale::calculate($input, $this->store->products(array_column($lines, '_iC')), $super);
            $reference = $this->store->reference();
            $quantities = [];
            foreach ($sale->lines as $line) {
                $quantities[$line['itemCode']] = ($quantities[$line['itemCode']] ?? 0) + $line['quantity'];
            }
            ksort($quantities);
            foreach ($quantities as $code => $qty) {
                $this->store->reserve((string) $code, $qty);
            }
            foreach ($sale->lines as $line) {
                $this->store->insert('transactions', $line + [
                    'ref' => $reference, 'description' => '', 'totalMoneySpent' => Money::decimal($sale->total),
                    'amountTendered' => Money::decimal($sale->tendered), 'changeDue' => Money::decimal($sale->change),
                    'discount_amount' => Money::decimal($sale->discount), 'discount_percentage' => Money::decimal($sale->discountRate),
                    'vatAmount' => Money::decimal($sale->tax), 'vatPercentage' => Money::decimal($sale->taxRate),
                    'cash' => Money::decimal($sale->cash), 'pos' => Money::decimal($sale->debt), 'modeOfPayment' => $sale->method,
                    'cust_name' => (string) ($input['cn'] ?? ''), 'cust_phone' => (string) ($input['cp'] ?? ''), 'cust_email' => (string) ($input['ce'] ?? ''),
                    'staffId' => $actor, 'transType' => '1', 'transDate' => $this->store->now(), 'lastUpdated' => $this->store->now(), 'cancelled' => '0',
                ]);
            }
            $this->store->recordEvent('Nouvelle transaction', $reference, count($sale->lines).' articles totalisant USD '.Money::decimal($sale->total).' avec le numéro de référence '.$reference.' a été acheté', 'transactions', $actor);

            return $reference;
        });
    }

    public function pay(string $reference, mixed $received, mixed $returned, int $actor): void
    {
        $received = Money::cents($received);
        $returned = Money::cents($returned);
        $amount = $received - $returned;
        if ($amount <= 0) {
            throw new DomainException('Montant du paiement invalide');
        }
        $this->store->transaction(function () use ($reference, $received, $returned, $amount, $actor): void {
            $debt = $this->store->debt($reference);
            $outstanding = Money::cents($debt->pos ?? 0);
            if ($amount > $outstanding) {
                throw new DomainException('Le paiement dépasse le solde');
            }
            $this->store->settle($reference, $amount, $outstanding);
            $this->store->insert('payements', ['staff_id' => $actor, 'ref' => $reference, 'montant_percu' => Money::decimal($received), 'montant_retourne' => Money::decimal($returned), 'montant_paye' => Money::decimal($amount), 'created_at' => $this->store->now()]);
            $this->store->recordEvent('Paiement', $reference, 'Paiement de USD '.Money::decimal($amount), 'transactions', $actor);
        });
    }
}
