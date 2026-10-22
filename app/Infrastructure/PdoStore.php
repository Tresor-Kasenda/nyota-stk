<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\Store;
use App\Domain\Money;
use DateTimeImmutable;
use DomainException;
use PDO;
use Throwable;

final class PdoStore implements Store
{
    public const KEYS = ['admin' => 'id', 'items' => 'id', 'categories' => 'id', 'item_category' => 'id', 'transactions' => 'transId', 'couts' => 'coutsId', 'eventlog' => 'id', 'backups' => 'id', 'payements' => 'id', 'nyota_requests' => 'id'];

    public function __construct(private PDO $pdo, private ?\Closure $clock = null)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function query(string $sql, array $bindings = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute(array_values($bindings));

        return $statement->fetchAll(PDO::FETCH_OBJ);
    }

    public function execute(string $sql, array $bindings = []): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute(array_values($bindings));

        return $statement->rowCount();
    }

    public function transaction(callable $operation): mixed
    {
        $nested = $this->pdo->inTransaction();
        $savepoint = 'nyota_'.bin2hex(random_bytes(6));
        if ($nested) {
            $this->pdo->exec('SAVEPOINT '.$savepoint);
        } else {
            $this->pdo->beginTransaction();
        }
        try {
            $result = $operation();
            if ($nested) {
                $this->pdo->exec('RELEASE SAVEPOINT '.$savepoint);
            } else {
                $this->pdo->commit();
            }

            return $result;
        } catch (Throwable $error) {
            if ($nested) {
                $this->pdo->exec('ROLLBACK TO SAVEPOINT '.$savepoint);
            } elseif ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    private function identifier(string $name): string
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $name)) {
            throw new DomainException('Colonne invalide');
        }

        return '`'.$name.'`';
    }

    public function insert(string $table, array $values): int
    {
        if (! isset(self::KEYS[$table])) {
            throw new DomainException('Table inconnue');
        }
        $columns = array_map($this->identifier(...), array_keys($values));
        $this->execute('INSERT INTO '.$this->identifier($table).' ('.implode(',', $columns).') VALUES ('.implode(',', array_fill(0, count($values), '?')).')', array_values($values));

        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, int $id, array $values): void
    {
        $key = self::KEYS[$table] ?? throw new DomainException('Table inconnue');
        $set = array_map(fn (string $column): string => $this->identifier($column).' = ?', array_keys($values));
        $this->execute('UPDATE '.$this->identifier($table).' SET '.implode(',', $set).' WHERE '.$this->identifier($key).' = ?', [...array_values($values), $id]);
    }

    public function find(string $table, int $id): ?object
    {
        $key = self::KEYS[$table] ?? throw new DomainException('Table inconnue');

        return $this->query('SELECT * FROM '.$this->identifier($table).' WHERE '.$this->identifier($key).' = ?', [$id])[0] ?? null;
    }

    public function products(array $codes): array
    {
        if ($codes === [] || count($codes) > 100) {
            return [];
        }
        $codes = array_values(array_unique(array_filter($codes, is_scalar(...))));
        if ($codes === []) {
            return [];
        }
        $products = $this->query('SELECT * FROM items WHERE code IN ('.implode(',', array_fill(0, count($codes), '?')).') ORDER BY code'.$this->lock(), $codes);
        $result = [];
        foreach ($products as $product) {
            $result[$product->code] = $product;
        }

        return $result;
    }

    public function reserve(string $code, int $quantity): void
    {
        if ($quantity < 1 || $this->execute('UPDATE items SET quantity = quantity - ?, lastUpdated = ? WHERE code = ? AND quantity >= ?', [$quantity, $this->now(), $code, $quantity]) !== 1) {
            throw new DomainException('Stock indisponible');
        }
    }

    public function debt(string $reference): object
    {
        return $this->query('SELECT * FROM transactions WHERE ref = ? ORDER BY transId'.$this->lock(), [$reference])[0] ?? throw new DomainException('Transaction inconnue');
    }

    public function settle(string $reference, int $amount, int $expectedDebt): void
    {
        $affected = $this->execute('UPDATE transactions SET pos = ROUND(COALESCE(pos, 0) - ?, 2), cash = ROUND(COALESCE(cash, 0) + ?, 2), lastUpdated = ? WHERE ref = ? AND pos = ? AND pos >= ?', [Money::decimal($amount), Money::decimal($amount), $this->now(), $reference, Money::decimal($expectedDebt), Money::decimal($amount)]);
        $count = (int) $this->query('SELECT COUNT(*) AS n FROM transactions WHERE ref = ?', [$reference])[0]->n;
        if ($affected !== $count || $count === 0) {
            throw new DomainException('Solde modifié, veuillez réessayer');
        }
    }

    private function lock(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    }

    public function reference(): string
    {
        do {
            $reference = (string) random_int(1000000000, 9999999999);
        } while ($this->query('SELECT transId FROM transactions WHERE ref = ?', [$reference]) !== []);

        return $reference;
    }

    public function now(): string
    {
        return $this->clock ? ($this->clock)() : (new DateTimeImmutable)->format('Y-m-d H:i:s');
    }

    public function recordEvent(string $event, string $reference, string $description, string $table, int $actor): void
    {
        $this->insert('eventlog', ['event' => $event, 'eventRowIdOrRef' => $reference, 'eventDesc' => $description, 'eventTable' => $table, 'staffInCharge' => $actor, 'eventTime' => $this->now()]);
    }

    public function list(string $resource, string $order, string $direction, int $limit, int $offset, string $search = '', bool $debt = false, int $actorId = 0): array
    {
        $name = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? "CONCAT_WS(' ', a.first_name, a.last_name)"
            : "COALESCE(a.first_name, '') || ' ' || COALESCE(a.last_name, '')";
        [$sql, $columns, $searchColumns] = match ($resource) {
            'items' => ['SELECT i.* FROM items i WHERE 1=1', ['id', 'name', 'code', 'quantity', 'unitPrice', 'dateAdded', 'min'], ['i.name', 'i.code', 'i.description']],
            'category' => ['SELECT * FROM categories WHERE 1=1', ['id', 'nom', 'description', 'created_on'], ['nom', 'description']],
            'administrators' => ['SELECT id,first_name,last_name,email,mobile1,mobile2,role,created_on,last_login,account_status,deleted FROM admin WHERE 1=1', ['id', 'first_name', 'last_name', 'email', 'role', 'created_on', 'last_login'], ['first_name', 'last_name', 'email', 'mobile1']],
            'couts' => ['SELECT c.*, '.$name.' AS staffName FROM couts c LEFT JOIN admin a ON c.staffId=a.id WHERE 1=1', ['coutsId', 'montant', 'motif', 'author', 'date_sortie', 'created_on'], ['c.motif', 'c.author']],
            'eventlog' => ['SELECT e.*, '.$name.' AS author, i.name, i.code, i.description FROM eventlog e LEFT JOIN admin a ON e.staffInCharge=a.id LEFT JOIN items i ON e.eventTable=\'items\' AND e.eventRowIdOrRef=CAST(i.id AS CHAR) WHERE 1=1', ['id', 'event', 'eventTime', 'name'], ['e.event', 'e.eventDesc', 'i.name']],
            'transactions' => ['SELECT t.*, '.$name.' AS staffName, q.quantity FROM transactions t JOIN (SELECT ref,MIN(transId) AS firstId,SUM(quantity) AS quantity FROM transactions GROUP BY ref) q ON t.transId=q.firstId LEFT JOIN admin a ON t.staffId=a.id WHERE 1=1', ['transId', 'ref', 'transDate', 'totalMoneySpent', 'modeOfPayment', 'quantity', 'totalPrice'], ['t.ref', 't.cust_name', 't.cust_phone', 't.itemName']],
            default => throw new DomainException('Liste inconnue'),
        };
        $bindings = [];
        if ($resource === 'administrators') {
            $sql .= ' AND id != ? AND email != ?';
            $bindings = [$actorId, 'admin@stkmgr.com'];
        }
        if ($resource === 'eventlog' && $order === 'items') {
            $order = 'name';
        }
        if ($debt && $resource === 'transactions') {
            $sql .= ' AND t.pos > 0 AND t.modeOfPayment != ?';
            $bindings[] = 'Cash';
        }
        if ($debt && $resource === 'eventlog') {
            $sql .= ' AND e.event LIKE ?';
            $bindings[] = 'Mise à jour du stock%';
        }
        if ($search !== '') {
            $sql .= ' AND ('.implode(' OR ', array_map(fn (string $column): string => $column.' LIKE ?', $searchColumns)).')';
            $bindings = [...$bindings, ...array_fill(0, count($searchColumns), '%'.$search.'%')];
        }
        $count = (int) $this->query('SELECT COUNT(*) AS n'.substr($sql, strpos($sql, ' FROM ')), $bindings)[0]->n;
        $order = in_array($order, $columns, true) ? $order : $columns[0];
        $prefix = match ($resource) {
            'items' => 'i.', 'couts' => 'c.', 'eventlog' => 'e.', 'transactions' => 't.', default => ''
        };
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $keyPrefix = $prefix;
        if ($resource === 'eventlog' && $order === 'name') {
            $prefix = 'i.';
        }
        if ($resource === 'transactions' && $order === 'quantity') {
            $prefix = 'q.';
        }
        $sql .= ' ORDER BY '.$prefix.$this->identifier($order).' '.$direction.', '.$keyPrefix.$this->identifier($columns[0]).' '.$direction.' LIMIT '.max(1, min(5000, $limit)).' OFFSET '.max(0, $offset);

        return ['rows' => $this->query($sql, $bindings), 'count' => $count];
    }

    public function once(?string $key, int $actor, string $operation, array $payload, callable $callback): mixed
    {
        if ($key === null || $key === '') {
            return $callback();
        }
        if (! preg_match('/^[a-zA-Z0-9_-]{16,100}$/D', $key)) {
            throw new DomainException('Clé de requête invalide');
        }
        $key = hash('sha256', $actor.'|'.$operation.'|'.$key);
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $read = function () use ($key, $hash): mixed {
            $row = $this->query('SELECT * FROM nyota_requests WHERE request_key=?', [$key])[0] ?? null;
            if (! $row) {
                return null;
            }
            if (! hash_equals($row->payload_hash, $hash)) {
                throw new DomainException('Cette clé a déjà été utilisée pour une autre requête');
            }

            return json_decode($row->result, true, flags: JSON_THROW_ON_ERROR);
        };
        $existing = $read();
        if ($existing !== null) {
            return $existing;
        }
        try {
            return $this->transaction(function () use ($key, $actor, $hash, $callback): mixed {
                $id = $this->insert('nyota_requests', ['request_key' => $key, 'actor_id' => $actor, 'payload_hash' => $hash, 'result' => 'null', 'created_at' => $this->now()]);
                $result = $callback();
                $this->update('nyota_requests', $id, ['result' => json_encode($result, JSON_THROW_ON_ERROR)]);

                return $result;
            });
        } catch (\PDOException $error) {
            if (in_array((string) $error->getCode(), ['23000', '23505'], true)) {
                $existing = $read();
                if ($existing !== null) {
                    return $existing;
                }
            }
            throw $error;
        }
    }

    public function today(): float
    {
        return (float) $this->query('SELECT COALESCE(SUM(totalPrice),0) AS total FROM transactions WHERE DATE(transDate)=?', [substr($this->now(), 0, 10)])[0]->total;
    }
}
