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
            // Also try email field as username
            if (!$user && $email) {
                $user = User::whereRaw('LOWER(username) = ?', [strtolower($email)])->first();
            }

            // Fallback to environment variables
            if (!$user) {
                $envEmail = env('MOBILE_LOGIN_EMAIL');
                $envPassword = env('MOBILE_LOGIN_PASSWORD');
                $envFullName = env('MOBILE_LOGIN_FULL_NAME', 'Utilisateur');
                $envRole = env('MOBILE_LOGIN_ROLE', 'CHECKER');

                if ($envEmail && $envPassword) {
                    $lookupEmail = $email ?: $username;

                    if ($lookupEmail) {
                        $emailMatch = strtolower($lookupEmail) === strtolower($envEmail);
                        $usernameMatch = strtolower($lookupEmail) === strtolower(explode('@', $envEmail)[0]);

                        if (($emailMatch || $usernameMatch) && $password === $envPassword) {
                            return $this->buildLoginResponse('env-user', $envFullName, $envEmail, $envRole);
                        }
                    }
                }
            }

            // User not found
            if (!$user) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Identifiants invalides',
                ], 401);
            }

            // Verify password
            if (!Hash::check($password, $user->password_hash)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Identifiants invalides',
                ], 401);
            }

            return $this->buildLoginResponse(
                $user->id,
                $user->name,
                $user->email,
                $user->role ?? 'CHECKER'
            );
        } catch (\Exception $e) {
            \Log::error('Login error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'ok' => false,
                'error' => 'Erreur lors de l\'authentification',
            ], 500);
        }
    }

    /**
     * Build the login response with user session and linked events
     */
    private function buildLoginResponse($userId, $name, $email, $role)
    {
        $timestamp = now()->timestamp;
        $accessToken = "scn-{$timestamp}-{$userId}";

        // Fetch all events (for MVP, all events are linked)
        $events = Event::all();
        $defaultLocation = env('MOBILE_DEFAULT_LOCATION', 'Lieu non defini');
        $defaultCheckpoint = env('MOBILE_DEFAULT_CHECKPOINT', 'Entree Principale');

        $linkedEvents = $events->map(function ($event) use ($defaultLocation, $defaultCheckpoint) {
            return [
                'id' => $event->id,
                'code' => $event->code,
                'name' => $event->name,
                'dateLabel' => $this->formatDateInFrench($event->starts_at),
                'location' => $defaultLocation,
                'checkpoint' => $defaultCheckpoint,
            ];
        });

        return response()->json([
            'ok' => true,
            'session' => [
                'accessToken' => $accessToken,
                'user' => [
                    'id' => $userId,
                    'fullName' => $name,
                    'email' => $email,
                    'role' => $role,
                ],
                'linkedEvents' => $linkedEvents->values(),
            ],
        ], 200);
    }

    /**
     * Format date in French format
     */
    private function formatDateInFrench($date)
    {
        if (!$date) {
            return 'Date non definie';
        }

        $carbon = Carbon::parse($date);

        $days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $months = [
            'janvier', 'fevrier', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'aout', 'septembre', 'octobre', 'novembre', 'decembre'
        ];

        $dayName = $days[$carbon->dayOfWeek];
        $dayNum = $carbon->day;
        $monthName = $months[$carbon->month - 1];
        $year = $carbon->year;

        return ucfirst($dayName) . ' ' . $dayNum . ' ' . $monthName . ' ' . $year;
    }
}
