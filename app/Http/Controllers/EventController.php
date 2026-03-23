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
            $events = Event::orderByDesc('created_at')->get();

            $eventsData = $events->map(function ($event) {
                $counts = Invitation::where('event_id', $event->id)
                    ->select('status', DB::raw('COUNT(*) as count'))
                    ->groupBy('status')
                    ->get()
                    ->keyBy('status');

                $total = Invitation::where('event_id', $event->id)->count();

                return [
                    'id' => $event->id,
                    'code' => $event->code,
                    'name' => $event->name,
                    'startsAt' => $event->starts_at?->toIso8601String(),
                    'endsAt' => $event->ends_at?->toIso8601String(),
                    'timezone' => $event->timezone,
                    'status' => $event->status,
                    'expectedGuests' => $event->expected_guests,
                    'invitationCounts' => [
                        'total' => $total,
                        'notScanned' => $counts->get('NOT_SCANNED')?->count ?? 0,
                        'scanned' => $counts->get('SCANNED')?->count ?? 0,
                        'blocked' => $counts->get('BLOCKED')?->count ?? 0,
                        'cancelled' => $counts->get('CANCELLED')?->count ?? 0,
                    ],
                ];
            });

            return response()->json([
                'ok' => true,
                'events' => $eventsData,
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Events index error', ['error' => $e->getMessage()]);
            return response()->json([
                'ok' => false,
                'error' => 'Erreur lors de la recuperation des evenements',
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
            $event = Event::findOrFail($eventId);

            $counts = Invitation::where('event_id', $event->id)
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->get()
                ->keyBy('status');

            $total = Invitation::where('event_id', $event->id)->count();

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
                    'expectedGuests' => $event->expected_guests,
                    'createdAt' => $event->created_at?->toIso8601String(),
                    'updatedAt' => $event->updated_at?->toIso8601String(),
                    'invitationCounts' => [
                        'total' => $total,
                        'notScanned' => $counts->get('NOT_SCANNED')?->count ?? 0,
                        'scanned' => $counts->get('SCANNED')?->count ?? 0,
                        'blocked' => $counts->get('BLOCKED')?->count ?? 0,
                        'cancelled' => $counts->get('CANCELLED')?->count ?? 0,
                    ],
                ],
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Evenement non trouve',
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Event show error', ['error' => $e->getMessage()]);
            return response()->json([
                'ok' => false,
                'error' => 'Erreur lors de la recuperation de l\'evenement',
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
                'organizationId' => 'required|integer|exists:organizations,id',
                'timezone' => 'nullable|string|timezone',
                'status' => 'nullable|in:DRAFT,LIVE,ENDED,CANCELLED',
            ]);

            $event = Event::create([
                'organization_id' => $request->input('organizationId'),
                'code' => $request->input('code'),
                'name' => $request->input('name'),
                'starts_at' => $request->input('startsAt'),
                'ends_at' => $request->input('endsAt'),
                'timezone' => $request->input('timezone', 'UTC'),
                'status' => $request->input('status', 'DRAFT'),
            ]);

            return response()->json([
                'ok' => true,
                'event' => [
                    'id' => $event->id,
                    'code' => $event->code,
                    'name' => $event->name,
                    'status' => $event->status,
                    'createdAt' => $event->created_at->toIso8601String(),
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Donnees invalides',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Event store error', ['error' => $e->getMessage()]);
            return response()->json([
                'ok' => false,
                'error' => 'Erreur lors de la creation de l\'evenement',
            ], 500);
        }
    }
}
