<?php

namespace App\Http\Controllers;

use App\Application\Sales;
use App\Infrastructure\PdoStore;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function __construct(private Sales $sales, private PdoStore $store) {}

    public function create(Request $request): JsonResponse
    {
        $data = $request->validate(['_aoi' => 'required|string|max:100000', '_mop' => 'required|in:Cash,POS,Cash and POS', '_at' => 'required|numeric|min:0', '_ca' => 'required|numeric|min:0', 'ca' => 'required|numeric|min:0', 'pos' => 'required|numeric|min:0', 'discount' => 'nullable|numeric|min:0|max:100', 'vat' => 'nullable|numeric|min:0|max:100', 'cn' => 'nullable|string|max:255', 'cp' => 'nullable|string|max:30', 'ce' => 'nullable|email|max:255']);
        $data['lines'] = json_decode($data['_aoi'], true);
        $actor = $request->attributes->get('actor');
        $reference = $this->store->once($request->header('Idempotency-Key'), (int) $actor->id, 'sale', $data, fn (): string => $this->sales->create($data, (int) $actor->id, $actor->role === 'Super'));

        return response()->json(['status' => 1, 'msg' => 'Transaction traitée avec succès', 'transReceipt' => $this->receiptHtml($reference), 'totalEarnedToday' => number_format($this->store->today(), 2)]);
    }

    public function receipt(Request $request): JsonResponse
    {
        $data = $request->validate(['ref' => 'required|string|max:10']);

        return response()->json(['status' => 1, 'transReceipt' => $this->receiptHtml($data['ref'])]);
    }

    private function receiptHtml(string $reference): string
    {
        $rows = $this->store->query('SELECT * FROM transactions WHERE ref=? ORDER BY transId', [$reference]);
        if ($rows === []) {
            throw new DomainException('Transaction inconnue');
        }
        $row = $rows[0];

        return view('nyota.transactions.transreceipt', [
            'allTransInfo' => array_map(fn (object $row): array => (array) $row, $rows),
            'cumAmount' => $row->totalMoneySpent, 'amountTendered' => $row->amountTendered, 'changeDue' => $row->changeDue, 'ref' => $row->ref, 'transDate' => $row->transDate, '_mop' => $row->modeOfPayment,
            'vatAmount' => $row->vatAmount, 'vatPercentage' => $row->vatPercentage, 'discountAmount' => $row->discount_amount, 'discountPercentage' => $row->discount_percentage,
            'cust_name' => $row->cust_name, 'cust_phone' => $row->cust_phone, 'cust_email' => $row->cust_email, 'cash' => $row->cash, 'pos' => $row->pos,
        ])->render();
    }

    public function payment(Request $request): JsonResponse
    {
        $data = $request->validate(['ref' => 'required|string|max:10', 'montantPercu' => 'required|numeric|min:0', 'monnaieDue' => 'required|numeric|min:0']);
        $actor = (int) $request->attributes->get('actor')->id;
        $this->store->once($request->header('Idempotency-Key'), $actor, 'payment', $data, function () use ($data, $actor): bool {
            $this->sales->pay($data['ref'], $data['montantPercu'], $data['monnaieDue'], $actor);

            return true;
        });

        return response()->json(['status' => 1, 'message' => 'Paiment effctué avec succès.']);
    }

    public function report(Request $request): View
    {
        $data = validator(['from' => $request->route('from'), 'to' => $request->route('to', date('Y-m-d'))], ['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from'])->validate();
        $rows = $this->store->query('SELECT t.*, a.first_name AS staffName, q.quantity FROM transactions t JOIN (SELECT ref, MIN(transId) AS firstId,SUM(quantity) AS quantity FROM transactions GROUP BY ref) q ON t.transId=q.firstId LEFT JOIN admin a ON a.id=t.staffId WHERE DATE(t.transDate)>=? AND DATE(t.transDate)<=? ORDER BY t.transId DESC', [$data['from'], $data['to']]);

        return view('nyota.transactions.transReport', ['allTransactions' => $rows, 'from' => $data['from'], 'to' => $data['to']]);
    }
}
