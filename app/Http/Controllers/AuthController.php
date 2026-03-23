<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

class AuthController extends Controller
{
    /**
     * POST /api/mobile/auth/login
     * Authenticate mobile scanner user
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'nullable|string',
                'username' => 'nullable|string',
                'password' => 'required|string',
            ]);

            $email = $request->input('email');
            $username = $request->input('username');
            $password = $request->input('password');

            // Try to find user by email or username (case insensitive)
            $user = null;
            if ($email) {
                $user = User::whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
            }
            if (!$user && $username) {
                $user = User::whereRaw('LOWER(username) = ?', [strtolower($username)])->first();
            }

            // Fallback to environment variables
            if (!$user) {
                $envEmail = config('app.mobile_login_email');
                $envPassword = config('app.mobile_login_password');
                $envFullName = config('app.mobile_login_full_name');
                $envRole = config('app.mobile_login_role', 'CHECKER');

                if ($envEmail && $envPassword) {
                    $lookupEmail = $email ?: $username;

                    // Match email or username (email part before @)
                    $emailMatch = strtolower($lookupEmail) === strtolower($envEmail);
                    $usernameMatch = strtolower($lookupEmail) === strtolower(explode('@', $envEmail)[0]);

                    if (($emailMatch || $usernameMatch) && $password === $envPassword) {
                        // Use env fallback user
                        $user = new User([
                            'id' => 'mobile-env-user',
                            'email' => $envEmail,
                            'full_name' => $envFullName,
                            'role' => $envRole,
                            'password_hash' => $envPassword,
                        ]);
                    }
                }
            }

            // User not found or password invalid
            if (!$user) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Identifiants invalides',
                ], 401);
            }

            // Verify password
            $isEnvUser = !$user->getKey() || $user->id === 'mobile-env-user';
            if (!$isEnvUser && !Hash::check($password, $user->password_hash)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Identifiants invalides',
                ], 401);
            }

            // Generate access token
            $timestamp = now()->timestamp;
            $userId = $user->id ?? 'mobile-env-user';
            $accessToken = "scn-{$timestamp}-{$userId}";

            // Fetch linked events
            $linkedEventIds = config('app.mobile_linked_event_ids');
            $query = Event::query();

            if ($linkedEventIds) {
                $ids = explode(',', $linkedEventIds);
                $query->whereIn('id', $ids);
            }

            $events = $query->get();
            $defaultLocation = config('app.mobile_default_location', 'Lieu non defini');
            $defaultCheckpoint = config('app.mobile_default_checkpoint', 'Entree Principale');

            $linkedEvents = $events->map(function ($event) use ($defaultLocation, $defaultCheckpoint) {
                return [
                    'id' => $event->id,
                    'code' => $event->code,
                    'name' => $event->name,
                    'dateLabel' => $this->formatDateInFrench($event->starts_at),
                    'location' => $event->location ?? $defaultLocation,
                    'checkpoint' => $event->checkpoint ?? $defaultCheckpoint,
                ];
            });

            return response()->json([
                'ok' => true,
                'session' => [
                    'accessToken' => $accessToken,
                    'user' => [
                        'id' => $userId,
                        'fullName' => $user->full_name ?? $user->name ?? 'Utilisateur',
                        'email' => $user->email,
                        'role' => $user->role ?? 'CHECKER',
                    ],
                    'linkedEvents' => $linkedEvents->values(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Erreur lors de l\'authentification',
            ], 500);
        }
    }

    /**
     * Format date in French format (e.g., "samedi 18 avril 2026")
     */
    private function formatDateInFrench($date)
    {
        if (!$date) {
            return 'Date non definie';
        }

        $carbon = Carbon::parse($date);

        $days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $months = [
            'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'
        ];

        $dayName = $days[$carbon->dayOfWeek];
        $dayNum = $carbon->day;
        $monthName = $months[$carbon->month - 1];
        $year = $carbon->year;

        return ucfirst($dayName) . ' ' . $dayNum . ' ' . $monthName . ' ' . $year;
    }
}
