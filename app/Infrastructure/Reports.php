<?php

declare(strict_types=1);

namespace App\Infrastructure;

final readonly class Reports
{
    public function __construct(private PdoStore $store) {}

    public function dashboard(): array
    {
        $summary = 'COUNT(DISTINCT ref) AS tot_trans, SUM(quantity) AS qty_sold, SUM(totalPrice) AS tot_earned';
        $daily = $this->store->query('SELECT '.$summary.', transDate FROM transactions GROUP BY transDate ORDER BY transDate DESC LIMIT 10');
        $days = $this->store->query('SELECT '.$summary.', DATE(transDate) AS transDate FROM transactions GROUP BY DATE(transDate) ORDER BY transDate DESC');
        $byDay = [];
        foreach ($days as $day) {
            $name = (new \DateTimeImmutable($day->transDate))->format('l');
            if (! isset($byDay[$name])) {
                $byDay[$name] = (object) ['day' => $name, 'tot_trans' => 0, 'qty_sold' => 0, 'tot_earned' => 0];
            }
            foreach (['tot_trans', 'qty_sold', 'tot_earned'] as $field) {
                $byDay[$name]->$field += $day->$field;
            }
        }
        $months = $this->store->query('SELECT '.$summary.', SUBSTR(transDate,6,2) AS month FROM transactions GROUP BY SUBSTR(transDate,6,2) ORDER BY tot_earned DESC');
        foreach ($months as $month) {
            $month->month = (new \DateTimeImmutable('2000-'.str_pad((string) $month->month, 2, '0', STR_PAD_LEFT).'-01'))->format('F');
        }
        $years = $this->store->query('SELECT '.$summary.', SUBSTR(transDate,1,4) AS year FROM transactions GROUP BY SUBSTR(transDate,1,4) ORDER BY tot_earned DESC');
        $ranking = 'SELECT i.name, COALESCE(SUM(t.quantity),0) AS totSold, COALESCE(SUM(t.totalPrice),0) AS totEarned FROM items i LEFT JOIN transactions t ON t.itemCode=i.code GROUP BY i.id,i.name';

        return [
            'topDemanded' => $this->store->query($ranking.' ORDER BY totSold DESC LIMIT 5'),
            'leastDemanded' => $this->store->query($ranking.' ORDER BY totSold ASC LIMIT 5'),
            'highestEarners' => $this->store->query($ranking.' ORDER BY totEarned DESC LIMIT 5'),
            'lowestEarners' => $this->store->query($ranking.' ORDER BY totEarned ASC LIMIT 5'),
            'totalItems' => $this->store->query('SELECT COUNT(*) AS n FROM items')[0]->n,
            'totalSalesToday' => $this->store->query('SELECT COALESCE(SUM(quantity),0) AS n FROM transactions WHERE DATE(transDate)=?', [substr($this->store->now(), 0, 10)])[0]->n,
            'totalTransactions' => $this->store->query('SELECT COUNT(DISTINCT ref) AS n FROM transactions')[0]->n,
            'dailyTransactions' => $daily, 'transByDays' => array_values($byDay), 'transByMonths' => $months, 'transByYears' => $years,
        ];
    }

    public function earnings(int $year): array
    {
        $rows = $this->store->query('SELECT SUBSTR(transDate,6,2) AS month, SUM(totalPrice) AS total FROM transactions WHERE SUBSTR(transDate,1,4)=? GROUP BY SUBSTR(transDate,6,2)', [(string) $year]);
        $months = array_fill(0, 12, 0);
        foreach ($rows as $row) {
            $months[(int) $row->month - 1] = (float) $row->total;
        }

        return ['total_earnings' => $months, 'earningsYear' => (string) $year];
    }

    public function methods(int $year): array
    {
        $rows = $this->store->query('SELECT modeOfPayment, COUNT(DISTINCT ref) AS total FROM transactions WHERE SUBSTR(transDate,1,4)=? GROUP BY modeOfPayment', [(string) $year]);
        $total = array_sum(array_map(fn (object $r): int => (int) $r->total, $rows));
        $result = ['status' => $total ? 1 : 0, 'year' => (string) $year, 'cash' => 0, 'pos' => 0, 'cashAndPos' => 0];
        foreach ($rows as $row) {
            $key = match ($row->modeOfPayment) {
                'Cash' => 'cash', 'POS' => 'pos', default => 'cashAndPos'
            };
            $result[$key] = $total ? round($row->total * 100 / $total, 2) : 0;
        }

        return $result;
    }
}
