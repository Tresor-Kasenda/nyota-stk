<?php

namespace App\Http\Controllers;

use App\Infrastructure\PdoStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function __construct(private PdoStore $store) {}

    public function index(Request $request): View|RedirectResponse
    {
        return $request->session()->has('admin_id') ? redirect()->route('dashboard') : view('nyota.home');
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate(['email' => 'required|string|max:255', 'password' => 'required|string|max:1024']);
        $login = mb_strtolower(trim($credentials['email']));
        $phone = preg_replace('/\s+/', '', $login);
        if (preg_match('/^0\d{9}$/', $phone)) {
            $phone = '+243'.substr($phone, 1);
        }
        $actor = $this->store->query('SELECT * FROM admin WHERE LOWER(email) = ? OR mobile1 = ? OR mobile1 = ? LIMIT 1', [$login, $login, $phone])[0] ?? null;
        if (! $actor || ! password_verify($credentials['password'], $actor->password) || (string) $actor->account_status !== '1' || (string) $actor->deleted === '1') {
            return response()->json(['status' => 0, 'msg' => 'Combinaison email et mot de passe incorrecte']);
        }
        $request->session()->regenerate(true);
        $request->session()->put(['admin_id' => $actor->id, 'admin_email' => $actor->email, 'admin_role' => $actor->role, 'admin_initial' => mb_strtoupper(mb_substr($actor->first_name, 0, 1)), 'admin_name' => $actor->first_name.' '.$actor->last_name, 'auth_version' => (int) $actor->auth_version]);
        $this->store->update('admin', (int) $actor->id, ['last_login' => $this->store->now()]);

        return response()->json(['status' => 1, 'csrfToken' => $request->session()->token()]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function status(Request $request): JsonResponse
    {
        $this->store->update('admin', (int) $request->attributes->get('actor')->id, ['last_seen' => $this->store->now()]);

        return response()->json(['status' => 1]);
    }
}
