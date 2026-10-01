<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebSession
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->session()->get('scanner_token');
        $session = is_string($token) ? DB::table('auth_sessions')
            ->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->first() : null;
        $user = $session ? User::where('is_active', true)->find($session->user_id) : null;
        if (! $user) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }
        $request->setUserResolver(fn () => $user);
        view()->share('webUser', $user);
        $eventId = $request->route('eventId');
        if ($eventId) {
            abort_unless(ScannerSession::events($user)->whereKey($eventId)->exists(), 403);
        }
        if (! $request->isMethod('GET') && ! $request->routeIs('web.logout')) {
            abort_unless(in_array($user->role, ['SUPER_ADMIN', 'MANAGER']), 403);
        }

        return $next($request)->header('Cache-Control', 'no-store, private');
    }
}
