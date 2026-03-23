<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Invitation;
use App\Models\ScanLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    /**
     * GET /api/events/{eventId}/stats
     * Get event statistics and recent scans
     */
    public function stats(Request $request, $eventId)
    {
        try {
            // Verify event exists
            $event = Event::findOrFail($eventId);

            // Get invitation counts by status
            $counts = Invitation::where('event_id', $eventId)
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->get()
                ->keyBy('status');

            $total = Invitation::where('event_id', $eventId)->count();
            $scanned = $counts->get('SCANNED')?->count ?? 0;
            $available = $counts->get('NOT_SCANNED')?->count ?? 0;
            $blocked = $counts->get('BLOCKED')?->count ?? 0;
            $cancelled = $counts->get('CANCELLED')?->count ?? 0;

            // Get last 20 scan logs
            $recentScans = ScanLog::where('event_id', $eventId)
                ->with(['invitation:id,ticket_number,guest_name', 'user:id,full_name,email'])
                ->orderByDesc('scanned_at')
                ->limit(20)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'result' => $log->result,
                        'message' => $this->getResultMessage($log->result),
                        'scannedAt' => $log->scanned_at->toIso8601String(),
                        'ticketNumber' => $log->invitation?->ticket_number,
                        'guestName' => $log->invitation?->guest_name,
                        'scannedBy' => $log->user?->full_name ?? $log->user_id,
                        'checkpoint' => $log->checkpoint_id,
                        'device' => $log->device_id,
                        'latencyMs' => $log->latency_ms,
                    ];
                });

            return response()->json([
                'ok' => true,
                'stats' => [
                    'total' => $total,
                    'scanned' => $scanned,
                    'available' => $available,
                    'blocked' => $blocked,
                    'cancelled' => $cancelled,
                ],
                'recentScans' => $recentScans->values(),
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Événement non trouvé',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Erreur lors de la récupération des statistiques',
            ], 500);
        }
    }

    /**
     * GET /api/events/{eventId}/scan-logs
     * Paginated list of scan logs with filtering
     */
    public function scanLogs(Request $request, $eventId)
    {
        try {
            $request->validate([
                'page' => 'nullable|integer|min:1',
                'perPage' => 'nullable|integer|min:1|max:100',
                'result' => 'nullable|in:VALID,INVALID,ALREADY_SCANNED,BLOCKED,CANCELLED,ERROR',
            ]);

            // Verify event exists
            $event = Event::findOrFail($eventId);

            $query = ScanLog::where('event_id', $eventId)
                ->with([
                    'invitation:id,ticket_number,guest_name,status',
                    'user:id,full_name,email,role'
                ])
                ->orderByDesc('scanned_at');

            // Filter by result if provided
            if ($request->has('result')) {
                $query->where('result', $request->input('result'));
            }

            $perPage = $request->input('perPage', 20);
            $logs = $query->paginate($perPage);

            $items = $logs->getCollection()->map(function ($log) {
                return [
                    'id' => $log->id,
                    'result' => $log->result,
                    'message' => $this->getResultMessage($log->result),
                    'scannedAt' => $log->scanned_at->toIso8601String(),
                    'qrPayload' => $log->qr_payload,
                    'ticket' => $log->invitation ? [
                        'id' => $log->invitation->id,
                        'ticketNumber' => $log->invitation->ticket_number,
                        'guestName' => $log->invitation->guest_name,
                        'status' => $log->invitation->status,
                    ] : null,
                    'scannedBy' => $log->user ? [
                        'id' => $log->user->id,
                        'fullName' => $log->user->full_name,
                        'email' => $log->user->email,
                        'role' => $log->user->role,
                    ] : null,
                    'checkpoint' => $log->checkpoint_id,
                    'device' => $log->device_id,
                    'latencyMs' => $log->latency_ms,
                    'ipAddress' => $log->ip_address,
                ];
            });

            return response()->json([
                'ok' => true,
                'scanLogs' => $items,
                'pagination' => [
                    'total' => $logs->total(),
                    'perPage' => $logs->perPage(),
                    'page' => $logs->currentPage(),
                    'lastPage' => $logs->lastPage(),
                    'from' => $logs->firstItem(),
                    'to' => $logs->lastItem(),
                ],
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Événement non trouvé',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Paramètres invalides',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Erreur lors de la récupération des journaux de scan',
            ], 500);
        }
    }

    /**
     * Get French message for result code
     */
    private function getResultMessage($result)
    {
        $messages = [
            'VALID' => 'Ticket valide',
            'INVALID' => 'Ticket non trouvé',
            'ALREADY_SCANNED' => 'Déjà scanné',
            'BLOCKED' => 'Ticket bloqué',
            'CANCELLED' => 'Ticket annulé',
            'ERROR' => 'Erreur lors du scan',
        ];

        return $messages[$result] ?? 'Statut inconnu';
    }
}
