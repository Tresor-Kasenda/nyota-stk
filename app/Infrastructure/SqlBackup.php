<?php

declare(strict_types=1);

namespace App\Infrastructure;

use DomainException;

final readonly class SqlBackup
{
    private const TABLES = ['admin', 'categories', 'items', 'item_category', 'couts', 'transactions', 'payements', 'eventlog'];

    public function __construct(private PdoStore $store) {}

    public function export(): string
    {
        return $this->store->transaction(function (): string {
            $sql = "-- Nyota data backup v1\n";
            foreach (self::TABLES as $table) {
                foreach ($this->store->query('SELECT * FROM '.$table) as $row) {
                    $values = (array) $row;
                    $columns = implode(',', array_map(fn (string $column): string => '`'.$column.'`', array_keys($values)));
                    $literals = array_map(static function (mixed $value): string {
                        if ($value === null) {
                            return 'NULL';
                        }

                        return "'".str_replace(['\\', "'", "\n", "\r", "\0"], ['\\\\', "\\'", '\\n', '\\r', '\\0'], (string) $value)."'";
                    }, array_values($values));
                    $sql .= 'INSERT INTO `'.$table.'` ('.$columns.') VALUES ('.implode(',', $literals).");\n";
                }
            }

            return $sql;
        });
    }

    public function restore(string $sql): void
    {
        $rows = [];
        foreach ($this->statements($sql) as $statement) {
            if (preg_match('/^(?:SET|CREATE TABLE|DROP TABLE|LOCK TABLES|UNLOCK TABLES|ALTER TABLE|BEGIN|COMMIT|START TRANSACTION|DELETE FROM)\b/i', $statement)) {
                continue;
            }
            if (! preg_match('/^INSERT\s+INTO\s+`?([a-z_]+)`?\s*\(([^)]+)\)\s+VALUES\s*(.*)$/is', $statement, $match)) {
                throw new DomainException('Instruction de sauvegarde non reconnue');
            }
            $table = $match[1];
            if (in_array($table, ['backups', 'lk_sess', 'nyota_requests'], true)) {
                continue;
            }
            if (! in_array($table, self::TABLES, true)) {
                throw new DomainException('Table de sauvegarde non autorisée');
            }
            $columns = array_map(fn (string $column): string => trim($column, " \t\r\n`"), explode(',', $match[2]));
            foreach ($columns as $column) {
                if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $column)) {
                    throw new DomainException('Colonne invalide');
                }
            }
            foreach ($this->values($match[3]) as $values) {
                if (count($values) !== count($columns)) {
                    throw new DomainException('Nombre de valeurs incorrect');
                }
                $row = array_combine($columns, $values);
                foreach (['last_login', 'last_seen'] as $column) {
                    if (($row[$column] ?? null) === '0000-00-00 00:00:00') {
                        $row[$column] = null;
                    }
                }
                $rows[$table][] = $row;
            }
        }
        if (! isset($rows['admin']) || ! isset($rows['items'])) {
            throw new DomainException('La sauvegarde doit contenir les comptes et les articles');
        }
        $super = array_filter($rows['admin'], fn (array $row): bool => ($row['role'] ?? '') === 'Super' && (string) ($row['deleted'] ?? '0') === '0' && (string) ($row['account_status'] ?? '1') === '1');
        if ($super === []) {
            throw new DomainException('La sauvegarde ne contient aucun administrateur actif');
        }
        $this->store->transaction(function () use ($rows): void {
            foreach (array_reverse(self::TABLES) as $table) {
                $this->store->execute('DELETE FROM '.$table);
            }
            foreach (self::TABLES as $table) {
                foreach ($rows[$table] ?? [] as $row) {
                    $this->store->insert($table, $row);
                }
            }
            $this->store->execute('DELETE FROM nyota_requests');
            $this->store->execute('UPDATE admin SET auth_version=auth_version+1');
        });
    }

    /** @return list<string> */
    private function statements(string $sql): array
    {
        $result = [];
        $buffer = '';
        $quote = false;
        $length = strlen($sql);
        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];
            $next = $sql[$index + 1] ?? '';
            if ($quote) {
                $buffer .= $char;
                if ($char === '\\' && $next !== '') {
                    $buffer .= $next;
                    $index++;
                } elseif ($char === "'") {
                    if ($next === "'") {
                        $buffer .= $next;
                        $index++;
                    } else {
                        $quote = false;
                    }
                }
            } elseif ($char === "'") {
                $quote = true;
                $buffer .= $char;
            } elseif (($char === '-' && $next === '-') || $char === '#') {
                while ($index < $length && $sql[$index] !== "\n") {
                    $index++;
                }
                $buffer .= ' ';
            } elseif ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $index + 2);
                if ($end === false) {
                    throw new DomainException('Commentaire SQL incomplet');
                }
                $index = $end + 1;
                $buffer .= ' ';
            } elseif ($char === ';') {
                if (trim($buffer) !== '') {
                    $result[] = trim($buffer);
                }
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }
        if ($quote) {
            throw new DomainException('Chaîne SQL incomplète');
        }
        if (trim($buffer) !== '') {
            $result[] = trim($buffer);
        }

        return $result;
    }

    /** @return list<list<string|null>> */
    private function values(string $text): array
    {
        $rows = [];
        $index = 0;
        $length = strlen($text);
        while ($index < $length) {
            while ($index < $length && ctype_space($text[$index])) {
                $index++;
            }
            if (($text[$index] ?? '') !== '(') {
                throw new DomainException('Valeurs SQL invalides');
            }
            $index++;
            $row = [];
            while (true) {
                while ($index < $length && ctype_space($text[$index])) {
                    $index++;
                }
                if (($text[$index] ?? '') === "'") {
                    $index++;
                    $value = '';
                    $closed = false;
                    while ($index < $length) {
                        $char = $text[$index++];
                        if ($char === '\\') {
                            if ($index >= $length) {
                                throw new DomainException('Échappement SQL incomplet');
                            }
                            $escaped = $text[$index++];
                            $value .= match ($escaped) {
                                'n' => "\n",'r' => "\r",'0' => "\0",'t' => "\t",'Z' => "\x1a",default => $escaped
                            };
                        } elseif ($char === "'") {
                            if (($text[$index] ?? '') === "'") {
                                $value .= "'";
                                $index++;
                            } else {
                                $closed = true;
                                break;
                            }
                        } else {
                            $value .= $char;
                        }
                    }
                    if (! $closed) {
                        throw new DomainException('Valeur SQL incomplète');
                    }
                } else {
                    $start = $index;
                    while ($index < $length && ! in_array($text[$index], [',', ')'], true)) {
                        $index++;
                    }
                    $value = trim(substr($text, $start, $index - $start));
                    if (strtoupper($value) === 'NULL') {
                        $value = null;
                    } elseif (! preg_match('/^-?\d+(?:\.\d+)?$/D', $value)) {
                        throw new DomainException('Expression SQL non autorisée');
                    }
                }
                $row[] = $value;
                while ($index < $length && ctype_space($text[$index])) {
                    $index++;
                }
                $delimiter = $text[$index++] ?? '';
                if ($delimiter === ')') {
                    break;
                }
                if ($delimiter !== ',') {
                    throw new DomainException('Séparateur SQL invalide');
                }
            }
            $rows[] = $row;
            while ($index < $length && ctype_space($text[$index])) {
                $index++;
            }
            if ($index === $length) {
                break;
            }
            if ($text[$index++] !== ',') {
                throw new DomainException('Fin SQL invalide');
            }
        }

        return $rows;
    }
}
