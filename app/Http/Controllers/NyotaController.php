<?php

namespace App\Http\Controllers;

use App\Infrastructure\PdoStore;
use App\Infrastructure\Reports;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NyotaController extends Controller
{
    public function __construct(private PdoStore $store, private Reports $reports) {}

    public function page(Request $request): View
    {
        $resource = (string) $request->route('resource', 'dashboard');
        [$template, $title] = match ($resource) {
            'dashboard' => ['dashboard', 'Tableau de bord'], 'items' => ['items.items', 'Articles'],
            'category' => ['categories', 'Categories'], 'couts' => ['couts', 'Couts'],
            'administrators' => ['admin.admin', 'Utilisateurs'], 'eventlog' => ['eventlog.eventlog', "Journal d'événement"],
            'transactions' => ['transactions.transactions', 'Transactions'], 'dettes' => ['transactions.dettes', 'Dettes'],
            'approvisionnement' => ['eventlog.eventloglist', "Journal d'approvisionnement"],
            'dbmanagement' => ['dbbackup', 'Base des données'], 'audit' => ['audit', 'Audit'],
            'customers' => ['customers', 'Clients'], 'reports' => ['reports', 'Rapports'],
            default => abort(404),
        };
        $data = [];
        if ($resource === 'dashboard') {
            $data = $this->reports->dashboard();
        }
        if (in_array($resource, ['items', 'transactions', 'dettes'], true)) {
            $data['categories'] = $this->store->query('SELECT * FROM categories ORDER BY nom');
            $data['items'] = $this->store->query('SELECT * FROM items WHERE quantity > 0 ORDER BY name DESC');
        }
        if ($resource === 'approvisionnement') {
            $data['allevents'] = $this->store->list('eventlog', 'id', 'DESC', 5000, 0, '', true)['rows'];
            $data['sn'] = 1;
        }
        if ($resource === 'eventlog') {
            $data['allevents'] = $this->store->list('eventlog', 'id', 'DESC', 10, 0)['rows'];
        }

        return view('nyota.main', ['pageTitle' => $title, 'pageContent' => view('nyota.'.$template, $data)->render()]);
    }

    public function listing(Request $request): JsonResponse
    {
        $resource = (string) $request->route('resource');
        if ($resource === 'administrators') {
            abort_unless($request->attributes->get('actor')->role === 'Super', 403);
        }
        $limit = max(1, min(100, (int) $request->query('limit', 10)));
        $page = max(1, (int) $request->route('page', 1));
        $start = ($page - 1) * $limit;
        $search = mb_substr((string) $request->query('v', ''), 0, 200);
        $defaults = ['items' => 'name', 'category' => 'created_on', 'administrators' => 'first_name', 'couts' => 'date_sortie', 'eventlog' => 'event', 'transactions' => 'transId'];
        $result = $this->store->list($resource, (string) $request->query('orderBy', $defaults[$resource]), (string) $request->query('orderFormat', 'DESC'), $limit, $start, $search, $request->boolean('dette'), (int) $request->attributes->get('actor')->id);
        [$template,$variable,$key] = match ($resource) {
            'items' => ['items.itemslisttable', 'allItems', 'itemsListTable'], 'category' => ['categories.categorieslist', 'allCategories', 'categoriesTable'],
            'administrators' => ['admin.adminlist', 'allAdministrators', 'adminTable'], 'couts' => ['couts.coutslist', 'allcouts', 'coutsTable'],
            'eventlog' => ['eventlog.eventloglist', 'allevents', 'allevents'], 'transactions' => ['transactions.transtable', 'allTransactions', 'transTable'],
        };
        $data = [$variable => $result['rows'], 'sn' => $start + 1, 'range' => $result['count'] ? ($start + 1).'-'.($start + count($result['rows'])).' sur '.$result['count'] : '', 'links' => $this->pagination($request, $page, $limit, $result['count'], $resource)];
        if ($resource === 'items') {
            $data += ['categories' => $this->store->query('SELECT * FROM categories ORDER BY nom'), 'itemCategories' => $this->store->query('SELECT ic.*,c.nom FROM item_category ic JOIN categories c ON c.id=ic.id_category'), 'cum_total' => $this->store->query('SELECT COALESCE(SUM(quantity*unitPrice),0) AS n FROM items')[0]->n, 'critic_items' => $this->store->query('SELECT * FROM items WHERE quantity <= min'), 'itemTotals' => []];
            foreach ($this->store->query('SELECT itemCode,SUM(quantity) AS quantity,SUM(totalPrice) AS totalPrice FROM transactions GROUP BY itemCode') as $row) {
                $data['itemTotals'][$row->itemCode] = $row;
            }
        }

        return response()->json([$key => view('nyota.'.$template, $data)->render()]);
    }

    private function pagination(Request $request, int $page, int $limit, int $count, string $resource): string
    {
        $pages = (int) ceil($count / $limit);
        if ($pages < 2) {
            return '';
        }
        $action = match ($resource) {
            'items' => 'lilt', 'transactions' => 'latr_', 'eventlog' => 'lilt', default => 'laad_'
        };
        $links = '<ul class="pagination">';
        for ($number = max(1, $page - 2); $number <= min($pages, $page + 2); $number++) {
            $url = site_url($resource.'/'.$action.'/'.$number).'?'.http_build_query($request->query());
            $onclick = match ($resource) {
                'items' => 'return lilt(this.href);', 'transactions' => 'return latr_(this.href);', 'eventlog' => 'return lilt(this.href);', default => ''
            };
            $links .= $number === $page ? '<li><a><b style="color:black">'.$number.'</b></a></li>' : '<li><a class="lnp" href="'.e($url).'" onclick="'.$onclick.'">'.$number.'</a></li>';
        }

        return $links.'</ul>';
    }

    public function chart(Request $request): JsonResponse
    {
        $year = (string) $request->route('year', date('Y'));
        abort_unless(preg_match('/^\d{4}$/D', $year), 422);

        return response()->json($request->route('chart') === 'earnings' ? $this->reports->earnings((int) $year) : $this->reports->methods((int) $year));
    }

    public function total(): JsonResponse
    {
        return response()->json(['totalEarnedToday' => number_format($this->store->today(), 2)]);
    }

    public function export(Request $request): StreamedResponse
    {
        $resource = (string) $request->route('resource');
        $columns = match ($resource) {
            'items' => ['name' => 'Nom', 'code' => 'Code', 'description' => 'Description', 'unitPrice' => 'Prix Unitaire', 'quantity' => 'Quantite', 'dateAdded' => 'Date ajout', 'lastUpdated' => 'Modifie le'],
            'category' => ['nom' => 'Nom', 'description' => 'Description'],
            'couts' => ['montant' => 'Montant', 'motif' => 'Motif', 'author' => 'Author', 'date_sortie' => 'Date Sortie', 'staffName' => 'Staff', 'created_on' => 'Creer le', 'lastUpdate' => 'Modifie le', 'deleted' => 'Effacer ?'],
            'eventlog' => ['event' => 'Evenement', 'author' => 'Auteur', 'name' => 'Produit', 'code' => 'Code Unitaire', 'eventDesc' => 'Description', 'eventTime' => 'Date et Heure'],
            'transactions' => ['ref' => 'Ref', 'itemName' => 'Nom', 'itemCode' => 'Code', 'description' => 'Description', 'quantity' => 'Quantité', 'unitPrice' => 'Prix unitaire', 'totalPrice' => 'Prix total', 'totalMoneySpent' => 'Montant Total', 'modeOfPayment' => 'Mode de Payement', 'staffName' => 'Agent', 'cust_name' => 'Client', 'transDate' => 'Date'],
            default => abort(404),
        };
        $filename = match ($resource) {
            'items' => 'articles.xls','eventlog' => 'events.xls',default => $resource.'.xls'
        };
        $cell = static function (int $row, int $col, mixed $value, bool $numeric = false): string {
            if ($numeric) {
                return pack('vvvvv', 0x203, 14, $row, $col, 0).pack('e', (float) $value);
            }
            $value = mb_convert_encoding((string) $value, 'Windows-1252', 'UTF-8');

            return pack('vvvvvv', 0x204, 8 + strlen($value), $row, $col, 0, strlen($value)).$value;
        };

        return response()->streamDownload(function () use ($resource, $columns, $cell): void {
            echo pack('vvvvvv', 0x809, 0x8, 0x0, 0x10, 0x0, 0x0);
            echo $cell(0, 0, 'No');
            $index = 1;
            foreach ($columns as $label) {
                echo $cell(0, $index++, $label);
            }
            $offset = 0;
            do {
                $result = $this->store->list($resource, 'id', 'ASC', 1000, $offset);
                foreach ($result['rows'] as $index => $row) {
                    $number = $offset + $index + 1;
                    echo $cell($number, 0, $number, true);
                    $column = 1;
                    foreach (array_keys($columns) as $field) {
                        $value = $row->$field ?? '';
                        if ($field === 'deleted') {
                            $value = (int) $value === 1 ? 'oui' : 'non';
                        }
                        echo $cell($number, $column++, $value, in_array($field, ['quantity', 'unitPrice', 'totalPrice', 'totalMoneySpent'], true));
                    }
                }
                $offset += count($result['rows']);
            } while ($offset < $result['count'] && count($result['rows']) > 0);
            echo pack('vv', 0x0A, 0);
        }, $filename, ['Content-Type' => 'application/vnd.ms-excel']);
    }
}
