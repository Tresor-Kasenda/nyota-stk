<?php

namespace App\Http\Controllers;

use App\Domain\Money;
use App\Infrastructure\PdoStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function __construct(private PdoStore $store) {}

    public function item(Request $request): JsonResponse
    {
        $edit = $request->route('operation') === 'edit';
        $id = $edit ? (int) $request->input('_iId') : 0;
        $data = $request->validate([
            '_iId' => $edit ? 'required|integer|exists:items,id' : 'nullable',
            'itemName' => ['required', 'string', 'max:50', Rule::unique('items', 'name')->ignore($id)],
            'itemCode' => ['required', 'string', 'max:20', Rule::unique('items', 'code')->ignore($id)],
            'itemPrice' => 'required|numeric|min:0|max:999999999.99|decimal:0,2',
            'itemQuantity' => $edit ? 'nullable' : 'required|integer|min:0|max:1000000',
            'stockMin' => 'nullable|integer|min:0|max:1000000', 'minStock' => 'nullable|integer|min:0|max:1000000',
            'itemDescription' => 'nullable|string|max:10000', 'itemDesc' => 'nullable|string|max:10000',
            'itemCategories' => 'nullable|array|max:100', 'itemCategories.*' => 'integer|exists:categories,id',
        ], ['required' => 'Champ obligatoire', 'unique' => 'Cette valeur existe déjà']);
        $this->store->transaction(function () use ($data, $edit, &$id, $request): void {
            $values = ['name' => $data['itemName'], 'code' => $data['itemCode'], 'unitPrice' => Money::decimal(Money::cents($data['itemPrice'])), 'description' => (string) ($data[$edit ? 'itemDesc' : 'itemDescription'] ?? ''), 'min' => (int) ($data['stockMin'] ?? $data['minStock'] ?? 0), 'lastUpdated' => $this->store->now()];
            if ($edit) {
                $this->store->update('items', $id, $values);
                $this->store->execute('DELETE FROM item_category WHERE id_item=?', [$id]);
            } else {
                $id = $this->store->insert('items', $values + ['quantity' => $data['itemQuantity'], 'dateAdded' => $this->store->now()]);
            }
            foreach (array_unique($data['itemCategories'] ?? []) as $category) {
                $this->store->insert('item_category', ['id_item' => $id, 'id_category' => $category]);
            }
            $this->store->recordEvent($edit ? "Mise à jour de l'article" : "Création d'un nouveau article", (string) $id, $values['name'], 'items', (int) $request->attributes->get('actor')->id);
        });

        return response()->json(['status' => 1, 'msg' => $edit ? 'Infos mise à jour avec succès' : 'Article enregistré avec succès']);
    }

    public function stock(Request $request): JsonResponse
    {
        $data = $request->validate(['_iId' => 'required|integer|exists:items,id', '_upType' => 'required|in:newStock,deficit', 'qty' => 'required|integer|min:1|max:1000000', 'desc' => 'required|string|max:10000']);
        $this->store->transaction(function () use ($data, $request): void {
            if ($data['_upType'] === 'deficit') {
                $product = $this->store->find('items', (int) $data['_iId']);
                $this->store->reserve($product->code, (int) $data['qty']);
            } else {
                $this->store->execute('UPDATE items SET quantity=quantity+?,lastUpdated=? WHERE id=?', [(int) $data['qty'], $this->store->now(), (int) $data['_iId']]);
            }
            $this->store->recordEvent('Mise à jour du stock ('.($data['_upType'] === 'deficit' ? 'Déficit' : 'Nouveau Stock').')', (string) $data['_iId'], $data['desc'], 'items', (int) $request->attributes->get('actor')->id);
        });

        return response()->json(['status' => 1, 'msg' => 'Stock mis à jour avec succès']);
    }

    public function category(Request $request): JsonResponse
    {
        $edit = $request->route('operation') === 'update';
        $name = $edit ? 'nomEdit' : 'nom';
        $desc = $edit ? 'descriptionEdit' : 'description';
        $data = $request->validate([$name => 'required|string|max:20', $desc => 'nullable|string|max:255', 'categoryId' => $edit ? 'required|integer|exists:categories,id' : 'nullable']);
        $values = ['nom' => ucfirst($data[$name]), 'description' => (string) ($data[$desc] ?? '')];
        if ($edit) {
            $this->store->update('categories', (int) $data['categoryId'], $values);
        } else {
            $this->store->insert('categories', $values + ['created_on' => $this->store->now()]);
        }

        return response()->json(['status' => 1, 'msg' => $edit ? 'Infos mise à jour avec succès' : 'Catégorie enregistrée avec succès']);
    }

    public function cost(Request $request): JsonResponse
    {
        $edit = $request->route('operation') === 'update';
        $suffix = $edit ? 'Edit' : '';
        $data = $request->validate(['montant'.$suffix => 'required|numeric|min:0|max:999999999.99|decimal:0,2', 'motif'.$suffix => 'required|string|max:10000', 'author'.$suffix => 'required|string|max:255', 'date_sortie'.$suffix => 'required|date_format:Y-m-d', 'coutId' => $edit ? 'required|integer|exists:couts,coutsId' : 'nullable']);
        $values = ['montant' => Money::decimal(Money::cents($data['montant'.$suffix])), 'motif' => ucfirst($data['motif'.$suffix]), 'author' => $data['author'.$suffix], 'date_sortie' => $data['date_sortie'.$suffix], 'staffId' => (int) $request->attributes->get('actor')->id, 'lastUpdate' => $this->store->now()];
        if ($edit) {
            $this->store->update('couts', (int) $data['coutId'], $values);
        } else {
            $this->store->insert('couts', $values + ['created_on' => $this->store->now(), 'deleted' => 0]);
        }

        return response()->json(['status' => 1, 'msg' => $edit ? 'Infos mise à jour avec succès' : 'Coût enregistré avec succès']);
    }

    public function delete(Request $request): JsonResponse
    {
        $resource = (string) $request->route('resource');
        $key = $resource === 'items' ? 'i' : '_aId';
        $data = $request->validate([$key => 'required|integer|min:1']);
        $id = (int) $data[$key];
        $table = match ($resource) {
            'items' => 'items','category' => 'categories','couts' => 'couts',default => abort(404)
        };
        $row = $this->store->find($table, $id);
        abort_unless($row, 404);
        $value = 0;
        if ($resource === 'couts') {
            $value = (int) $row->deleted === 1 ? 0 : 1;
            $this->store->update('couts', $id, ['deleted' => $value]);
        } else {
            $this->store->transaction(function () use ($table, $id, $resource): void {
                $this->store->execute('DELETE FROM item_category WHERE '.($resource === 'items' ? 'id_item' : 'id_category').'=?', [$id]);
                $this->store->execute('DELETE FROM '.$table.' WHERE id=?', [$id]);
            });
        }

        return response()->json(['status' => 1, '_aId' => $id, '_nv' => $value]);
    }

    public function product(Request $request): JsonResponse
    {
        $code = (string) $request->query('_iC', '');
        $row = $this->store->query('SELECT quantity,unitPrice,description FROM items WHERE code=?', [$code])[0] ?? null;

        return response()->json($row ? ['status' => 1, 'availQty' => (int) $row->quantity, 'unitPrice' => $row->unitPrice, 'description' => e($row->description)] : ['status' => 0]);
    }

    public function code(Request $request): JsonResponse
    {
        $id = $this->store->query('SELECT id FROM items WHERE code=?', [(string) $request->route('code')])[0]->id ?? null;

        return response()->json(['status' => $id ? 1 : 0, 'colVal' => $id]);
    }
}
