<?php
namespace App\Http\Middleware;

use App\Models\User;
use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScannerSession
{
    public static function events(User $user)
    {
        return Event::where('organization_id', $user->organization_id)
            ->when($user->role !== 'SUPER_ADMIN', fn ($q) => $q->whereIn('id', DB::table('event_users')->where('user_id', $user->id)->select('event_id')));
    }

    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return response()->json(['ok' => false, 'result' => 'ERROR', 'message' => 'Session invalide. Reconnectez-vous.'], 401);
        }
        $session = DB::table('auth_sessions')->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->first();
        $user = $session ? User::where('is_active', true)->find($session->user_id) : null;
        if (!$user) return response()->json(['ok' => false, 'result' => 'ERROR', 'message' => 'Session expiree. Reconnectez-vous.'], 401);
        $request->setUserResolver(fn () => $user);
        $eventId = $request->route('eventId');
        if ($eventId && !self::events($user)->where('id', $eventId)->exists()) {
            return response()->json(['ok' => false, 'result' => 'ERROR', 'message' => 'Evenement non autorise.'], 403);
        }
        if (!$request->isMethod('GET') && !$request->is('api/events/*/scan/verify') && !$request->is('api/mobile/auth/logout') && !in_array($user->role, ['MANAGER', 'SUPER_ADMIN'])) {
            return response()->json(['ok' => false, 'error' => 'Compte gestionnaire requis.'], 403);
        }
        return $next($request);
    }
}
