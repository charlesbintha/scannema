<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\ScanLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                'qrPayload' => 'required|string|max:255',
                'checkpointId' => 'nullable|string',
                'deviceId' => 'nullable|string',
            ]);

            $qrPayload = trim($request->input('qrPayload'));
            $checkpointId = $request->input('checkpointId');
            $deviceId = $request->input('deviceId');
            $userId = $request->user()->id;

            // Use transaction with row lock
            $result = DB::transaction(function () use ($eventId, $qrPayload, $userId, $checkpointId, $deviceId) {
                // Try to find by qr_payload first, then by code (ticket code)
                $invitation = Invitation::where('event_id', $eventId)
                    ->where(function ($query) use ($qrPayload) {
                        $query->where('qr_payload', $qrPayload)
                              ->orWhere('code', $qrPayload);
                    })
                    ->lockForUpdate()
                    ->first();

                // No matching ticket
                if (!$invitation) {
                    $this->logScan($eventId, $qrPayload, null, 'INVALID', $userId, $checkpointId, $deviceId);
                    return [
                        'status' => 404,
                        'ok' => false,
                        'result' => 'INVALID',
                        'message' => 'Ticket non trouve',
                        'ticket' => null,
                    ];
                }

                // Check status
                $status = $invitation->status;

                // BLOCKED
                if ($status === 'BLOCKED') {
                    $this->logScan($eventId, $qrPayload, $invitation->id, 'BLOCKED', $userId, $checkpointId, $deviceId);
                    return [
                        'status' => 403,
                        'ok' => false,
                        'result' => 'BLOCKED',
                        'message' => 'Ce ticket a ete bloque',
                        'ticket' => $this->formatTicket($invitation),
                    ];
                }

                // CANCELLED
                if ($status === 'CANCELLED') {
                    $this->logScan($eventId, $qrPayload, $invitation->id, 'CANCELLED', $userId, $checkpointId, $deviceId);
                    return [
                        'status' => 403,
                        'ok' => false,
                        'result' => 'CANCELLED',
                        'message' => 'Ce ticket a ete annule',
                        'ticket' => $this->formatTicket($invitation),
                    ];
                }

                // SCANNED (already scanned)
                if ($status === 'SCANNED') {
                    $lastScan = ScanLog::where('invitation_id', $invitation->id)
                        ->where('result', 'VALID')
                        ->orderByDesc('created_at')
                        ->first();

                    $this->logScan($eventId, $qrPayload, $invitation->id, 'ALREADY_SCANNED', $userId, $checkpointId, $deviceId);

                    return [
                        'status' => 200,
                        'ok' => true,
                        'result' => 'ALREADY_SCANNED',
                        'message' => 'Ce ticket a deja ete scanne',
                        'ticket' => $this->formatTicket($invitation),
                        'previousScan' => $lastScan ? [
                            'scannedAt' => $lastScan->created_at->toIso8601String(),
                            'scannedBy' => $lastScan->user_id,
                            'checkpoint' => $lastScan->checkpoint_id,
                        ] : null,
                    ];
                }

                if ($invitation->payment_status !== 'PAID') {
                    $this->logScan($eventId, $qrPayload, $invitation->id, 'UNPAID', $userId, $checkpointId, $deviceId);
                    return ['status' => 403, 'ok' => false, 'result' => 'UNPAID', 'message' => 'Ticket non paye. Acces refuse. Veuillez passer a la caisse.', 'ticket' => $this->formatTicket($invitation)];
                }

                // NOT_SCANNED -> mark as SCANNED
                if ($status === 'NOT_SCANNED') {
                    $invitation->update([
                        'status' => 'SCANNED',
                        'scanned_at' => now(),
                    ]);

                    $this->logScan($eventId, $qrPayload, $invitation->id, 'VALID', $userId, $checkpointId, $deviceId);

                    return [
                        'status' => 200,
                        'ok' => true,
                        'result' => 'VALID',
                        'message' => 'Ticket valide - entree autorisee',
                        'ticket' => $this->formatTicket($invitation),
                    ];
                }

                // Unknown status
                $this->logScan($eventId, $qrPayload, $invitation->id, 'INVALID', $userId, $checkpointId, $deviceId);
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
            \Log::error('Scan verify error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'ok' => false,
                'result' => 'ERROR',
                'error' => 'Erreur lors de la verification du ticket',
                'message' => 'Une erreur s\'est produite lors du traitement',
            ], 500);
        }
    }

    /**
     * Log scan attempt to scan_logs table
     */
    private function logScan($eventId, $qrPayload, $invitationId, $result, $userId, $checkpointId = null, $deviceId = null)
    {
        // An audit failure must roll back admission instead of authorizing silently.
        ScanLog::create([
            'event_id' => $eventId,
            'invitation_id' => $invitationId,
            'user_id' => $userId,
            'qr_payload' => $qrPayload,
            'result' => $result,
            'checkpoint_id' => $checkpointId,
            'device_id' => $deviceId,
            'device_ip' => request()->ip(),
        ]);
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
            'ticketNumber' => $invitation->code,
            'qrPayload' => $invitation->qr_payload,
            'guestName' => $invitation->guest_name,
            'phone' => $invitation->guest_phone,
            'status' => $invitation->status,
            'paymentStatus' => $invitation->payment_status,
            'scannedAt' => $invitation->scanned_at?->toIso8601String(),
        ];
    }
}
