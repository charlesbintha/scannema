<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    /**
     * GET /api/events
     * List all events with invitation counts
     */
    public function index(Request $request)
    {
        try {
            $events = Event::withCount(['invitations'])
                ->with([
                    'invitations' => function ($query) {
                        $query->select(DB::raw('status, COUNT(*) as count'))
                            ->groupBy('status');
                    }
                ])
                ->orderByDesc('created_at')
                ->get();

            $eventsData = $events->map(function ($event) {
                $invitations = $event->invitations;

                $statusCounts = [];
                foreach ($invitations as $inv) {
                    $statusCounts[$inv->status] = $inv->count;
                }

                return [
                    'id' => $event->id,
                    'code' => $event->code,
                    'name' => $event->name,
                    'startsAt' => $event->starts_at?->toIso8601String(),
                    'endsAt' => $event->ends_at?->toIso8601String(),
                    'timezone' => $event->timezone,
                    'status' => $event->status,
                    'location' => $event->location,
                    'checkpoint' => $event->checkpoint,
                    'invitationCounts' => [
                        'total' => $event->invitations_count,
                        'notScanned' => $statusCounts['NOT_SCANNED'] ?? 0,
                        'scanned' => $statusCounts['SCANNED'] ?? 0,
                        'blocked' => $statusCounts['BLOCKED'] ?? 0,
                        'cancelled' => $statusCounts['CANCELLED'] ?? 0,
                    ],
                ];
            });

            return response()->json([
                'ok' => true,
                'events' => $eventsData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Erreur lors de la récupération des événements',
            ], 500);
        }
    }

    /**
     * GET /api/events/{eventId}
     * Get single event details
     */
    public function show(Request $request, $eventId)
    {
        try {
            $event = Event::where('id', $eventId)
                ->with(['invitations' => function ($query) {
                    $query->select(DB::raw('status, COUNT(*) as count'))
                        ->groupBy('status');
                }])
                ->firstOrFail();

            $invitations = $event->invitations;
            $statusCounts = [];
            foreach ($invitations as $inv) {
                $statusCounts[$inv->status] = $inv->count;
            }

            return response()->json([
                'ok' => true,
                'event' => [
                    'id' => $event->id,
                    'code' => $event->code,
                    'name' => $event->name,
                    'startsAt' => $event->starts_at?->toIso8601String(),
                    'endsAt' => $event->ends_at?->toIso8601String(),
                    'timezone' => $event->timezone,
                    'status' => $event->status,
                    'location' => $event->location,
                    'checkpoint' => $event->checkpoint,
                    'createdAt' => $event->created_at?->toIso8601String(),
                    'updatedAt' => $event->updated_at?->toIso8601String(),
                    'invitationCounts' => [
                        'total' => $event->invitations_count ?? 0,
                        'notScanned' => $statusCounts['NOT_SCANNED'] ?? 0,
                        'scanned' => $statusCounts['SCANNED'] ?? 0,
                        'blocked' => $statusCounts['BLOCKED'] ?? 0,
                        'cancelled' => $statusCounts['CANCELLED'] ?? 0,
                    ],
                ],
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Événement non trouvé',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Erreur lors de la récupération de l\'événement',
            ], 500);
        }
    }

    /**
     * POST /api/events
     * Create a new event
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'code' => 'required|string|unique:events,code',
                'name' => 'required|string',
                'startsAt' => 'required|date_format:Y-m-d\TH:i:s|date_format:Y-m-d\TH:i:sP',
                'endsAt' => 'required|date_format:Y-m-d\TH:i:s|date_format:Y-m-d\TH:i:sP|after:startsAt',
                'timezone' => 'nullable|string|timezone',
                'status' => 'nullable|in:ACTIVE,ARCHIVED,CANCELLED',
                'location' => 'nullable|string',
                'checkpoint' => 'nullable|string',
            ]);

            $event = Event::create([
                'code' => $request->input('code'),
                'name' => $request->input('name'),
                'starts_at' => $request->input('startsAt'),
                'ends_at' => $request->input('endsAt'),
                'timezone' => $request->input('timezone', 'Europe/Paris'),
                'status' => $request->input('status', 'ACTIVE'),
                'location' => $request->input('location'),
                'checkpoint' => $request->input('checkpoint'),
            ]);

            return response()->json([
                'ok' => true,
                'event' => [
                    'id' => $event->id,
                    'code' => $event->code,
                    'name' => $event->name,
                    'startsAt' => $event->starts_at->toIso8601String(),
                    'endsAt' => $event->ends_at->toIso8601String(),
                    'timezone' => $event->timezone,
                    'status' => $event->status,
                    'location' => $event->location,
                    'checkpoint' => $event->checkpoint,
                    'createdAt' => $event->created_at->toIso8601String(),
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Données invalides',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Erreur lors de la création de l\'événement',
            ], 500);
        }
    }
}
