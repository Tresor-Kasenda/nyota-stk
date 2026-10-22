<?php

declare(strict_types=1);

namespace App\Application;

interface Store
{
    public function transaction(callable $operation): mixed;

    /** @return array<string, object> */
    public function products(array $codes): array;

    public function reserve(string $code, int $quantity): void;

    public function insert(string $table, array $values): int;

    public function reference(): string;

    public function now(): string;

    public function recordEvent(string $event, string $reference, string $description, string $table, int $actor): void;

    public function debt(string $reference): object;

    public function settle(string $reference, int $amount, int $expectedDebt): void;
}
