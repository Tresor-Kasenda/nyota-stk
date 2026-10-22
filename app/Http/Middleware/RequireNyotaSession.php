<?php

namespace App\Http\Middleware;

use App\Infrastructure\PdoStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireNyotaSession
{
    public function __construct(private PdoStore $store) {}

    public function handle(Request $request, Closure $next, string $role = ''): Response
    {
        $id = (int) $request->session()->get('admin_id', 0);
        $actor = $id ? $this->store->find('admin', $id) : null;
        if (! $actor || (string) $actor->deleted === '1' || (string) $actor->account_status !== '1' || (int) $actor->auth_version !== (int) $request->session()->get('auth_version', -1)) {
            $request->session()->forget(['admin_id', 'admin_role', 'auth_version']);
            if ($request->is('access/css', 'misc/check_session_status')) {
                return response()->json(['status' => 0]);
            }

            return $request->expectsJson() || $request->ajax()
                ? response()->json(['status' => 0, 'msg' => 'Veuillez vous connecter'], 401)
                : redirect()->route('login', ['red_uri' => ltrim($request->getRequestUri(), '/')]);
        }
        if ($role === 'Super' && $actor->role !== 'Super') {
            abort(403);
        }
        $request->attributes->set('actor', $actor);
        $request->session()->put('admin_role', $actor->role);

        return $next($request);
    }
}
