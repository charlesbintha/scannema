<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\ScanLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ScanController extends Controller
{
    /**
     * POST /api/events/{eventId}/scan/verify
     * Verify a QR code scan with transaction lock
     */
    public function verify(Request $request, $eventId)
    {
        try {
            $request->validate([
                'qrPayload' => 'required|string',
                'checkpointId' => 'nullable|string',
                'deviceId' => 'nullable|string',
                'latencyMs' => 'nullable|integer',
            ]);

            $qrPayload = $request->input('qrPayload');
            $checkpointId = $request->input('checkpointId');
            $deviceId = $request->input('deviceId');
            $latencyMs = $request->input('latencyMs');
            $userId = $request->header('X-User-Id');

            if (!$userId) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Non authentifié',
                ], 401);
            }

            // Use transaction with row lock
            $result = DB::transaction(function () use ($eventId, $qrPayload, $userId, $checkpointId, $deviceId, $latencyMs) {
                // SELECT ... FOR UPDATE on invitations table
                $invitation = Invitation::where('event_id', $eventId)
                    ->where('qr_payload', $qrPayload)
                    ->lockForUpdate()
                    ->first();

                // No matching ticket
                if (!$invitation) {
                    $this->logScan($eventId, $qrPayload, null, 'INVALID', $userId, $checkpointId, $deviceId, $latencyMs);
                    return [
                        'status' => 404,
                        'ok' => false,
                        'result' => 'INVALID',
                        'message' => 'Ticket non trouvé',
                        'ticket' => null,
                    ];
                }

                // Check status
                $status = $invitation->status;

                // BLOCKED or CANCELLED
                if ($status === 'BLOCKED') {
                    $this->logScan($eventId, $qrPayload, $invitation->id, 'BLOCKED', $userId, $checkpointId, $deviceId, $latencyMs);
                    return [
                        'status' => 403,
                        'ok' => false,
                        'result' => 'BLOCKED',
                        'message' => 'Ce ticket a été bloqué',
                        'ticket' => $this->formatTicket($invitation),
                    ];
                }

                if ($status === 'CANCELLED') {
                    $this->logScan($eventId, $qrPayload, $invitation->id, 'CANCELLED', $userId, $checkpointId, $deviceId, $latencyMs);
                    return [
                        'status' => 403,
                        'ok' => false,
                        'result' => 'CANCELLED',
                        'message' => 'Ce ticket a été annulé',
                        'ticket' => $this->formatTicket($invitation),
                    ];
                }

                // SCANNED (already scanned)
                if ($status === 'SCANNED') {
                    $lastScan = ScanLog::where('invitation_id', $invitation->id)
                        ->where('result', 'VALID')
                        ->orderByDesc('scanned_at')
                        ->first();

                    $this->logScan($eventId, $qrPayload, $invitation->id, 'ALREADY_SCANNED', $userId, $checkpointId, $deviceId, $latencyMs);

                    return [
                        'status' => 200,
                        'ok' => true,
                        'result' => 'ALREADY_SCANNED',
                        'message' => 'Ce ticket a déjà été scanné',
                        'ticket' => $this->formatTicket($invitation),
                        'previousScan' => $lastScan ? [
                            'scannedAt' => $lastScan->scanned_at->toIso8601String(),
                            'scannedBy' => $lastScan->user_id,
                            'checkpoint' => $lastScan->checkpoint_id,
                        ] : null,
                    ];
                }

                // NOT_SCANNED → mark as SCANNED
                if ($status === 'NOT_SCANNED') {
                    $invitation->update([
                        'status' => 'SCANNED',
                        'scan_count' => ($invitation->scan_count ?? 0) + 1,
                        'scanned_at' => now(),
                    ]);

                    $this->logScan($eventId, $qrPayload, $invitation->id, 'VALID', $userId, $checkpointId, $deviceId, $latencyMs);

                    return [
                        'status' => 200,
                        'ok' => true,
                        'result' => 'VALID',
                        'message' => 'Ticket valide - entrée autorisée',
                        'ticket' => $this->formatTicket($invitation),
                    ];
                }

                // Unknown status
                $this->logScan($eventId, $qrPayload, $invitation->id, 'INVALID', $userId, $checkpointId, $deviceId, $latencyMs);
                return [
                    'status' => 400,
                    'ok' => false,
                    'result' => 'INVALID',
                    'message' => 'Statut de ticket inconnu',
                    'ticket' => $this->formatTicket($invitation),
                ];
            });

            $httpStatus = $result['status'];
            unset($result['status']);

            return response()->json($result, $httpStatus);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'result' => 'ERROR',
                'error' => 'Erreur lors de la vérification du ticket',
                'message' => 'Une erreur s\'est produite lors du traitement',
            ], 500);
        }
    }

    /**
     * Log scan attempt to scan_logs table
     */
    private function logScan($eventId, $qrPayload, $invitationId, $result, $userId, $checkpointId = null, $deviceId = null, $latencyMs = null)
    {
        try {
            ScanLog::create([
                'event_id' => $eventId,
                'invitation_id' => $invitationId,
                'user_id' => $userId,
                'qr_payload' => $qrPayload,
                'result' => $result,
                'checkpoint_id' => $checkpointId,
                'device_id' => $deviceId,
                'latency_ms' => $latencyMs,
                'scanned_at' => now(),
                'ip_address' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            // Log but don't fail the main operation
            \Log::warning('Failed to log scan', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Format invitation data for response
     */
    private function formatTicket($invitation)
    {
        if (!$invitation) {
            return null;
        }

        return [
            'id' => $invitation->id,
            'ticketNumber' => $invitation->ticket_number,
            'qrPayload' => $invitation->qr_payload,
            'guestName' => $invitation->guest_name,
            'phone' => $invitation->phone,
            'status' => $invitation->status,
            'scanCount' => $invitation->scan_count ?? 0,
            'scannedAt' => $invitation->scanned_at?->toIso8601String(),
        ];
    }
}
