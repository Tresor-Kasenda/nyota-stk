<?php

namespace App\Http\Controllers;

use App\Infrastructure\PdoStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountsController extends Controller
{
    public function __construct(private PdoStore $store) {}

    public function save(Request $request): JsonResponse
    {
        $edit = $request->route('operation') === 'update';
        $id = $edit ? (int) $request->input('adminId') : 0;
        $data = $request->validate([
            'adminId' => $edit ? 'required|integer|exists:admin,id' : 'nullable',
            'firstName' => 'required|string|max:20', 'lastName' => 'required|string|max:20',
            'email' => ['required', 'email', 'max:255', Rule::unique('admin', 'email')->ignore($id)],
            'mobile1' => ['required', 'string', 'max:20', Rule::unique('admin', 'mobile1')->ignore($id)],
            'mobile2' => 'nullable|string|max:20', 'role' => 'required|in:Super,Basic',
            'passwordOrig' => $edit ? 'nullable' : 'required|string|min:8|max:72',
            'passwordDup' => $edit ? 'nullable' : 'required|same:passwordOrig',
        ], ['required' => 'Champ obligatoire', 'unique' => 'Cette valeur existe déjà']);
        $values = ['first_name' => $data['firstName'], 'last_name' => $data['lastName'], 'email' => mb_strtolower($data['email']), 'mobile1' => $data['mobile1'], 'mobile2' => (string) ($data['mobile2'] ?? ''), 'role' => $data['role'], 'last_edited' => $this->store->now()];
        if ($edit) {
            $actor = $this->store->find('admin', $id);
            $values['auth_version'] = (int) $actor->auth_version + 1;
            $this->store->update('admin', $id, $values);
        } else {
            $this->store->insert('admin', $values + ['password' => password_hash($data['passwordOrig'], PASSWORD_BCRYPT), 'created_on' => $this->store->now(), 'account_status' => '1', 'deleted' => '0', 'auth_version' => 0]);
        }

        return response()->json(['status' => 1, 'msg' => $edit ? 'Infos mise à jour avec succès' : 'Compte admin créer avec succès']);
    }

    public function status(Request $request): JsonResponse
    {
        $data = $request->validate(['_aId' => 'required|integer|exists:admin,id']);
        $id = (int) $data['_aId'];
        abort_if($id === (int) $request->attributes->get('actor')->id, 403);
        $operation = $request->route('operation');
        $column = $operation === 'suspend' ? 'account_status' : 'deleted';
        $row = $this->store->find('admin', $id);
        $value = (string) $row->$column === '1' ? '0' : '1';
        $this->store->update('admin', $id, [$column => $value, 'auth_version' => (int) $row->auth_version + 1]);

        return response()->json(['status' => 1, '_aId' => $id, $operation === 'suspend' ? '_ns' : '_nv' => (int) $value]);
    }

    public function password(Request $request): JsonResponse
    {
        $data = $request->validate(['adminId' => 'required|integer|exists:admin,id', 'password' => 'required|string|min:8|max:72', 'passwordDup' => 'required|same:password']);
        $row = $this->store->find('admin', (int) $data['adminId']);
        $this->store->update('admin', (int) $row->id, ['password' => password_hash($data['password'], PASSWORD_BCRYPT), 'auth_version' => (int) $row->auth_version + 1]);

        return response()->json(['status' => 1, 'msg' => 'Mot de passe changé avec succès']);
    }
}
