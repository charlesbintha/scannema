<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    /**
     * POST /api/events/{eventId}/invitations/import
     * Import invitations from CSV file
     */
    public function import(Request $request, $eventId)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
            ]);

            // Verify event exists
            $event = Event::findOrFail($eventId);

            $file = $request->file('file');
            $content = file_get_contents($file->getRealPath());

            // Detect delimiter (comma or semicolon)
            $delimiter = $this->detectDelimiter($content);

            // Parse CSV
            $rows = $this->parseCSV($content, $delimiter);

            if (empty($rows)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Le fichier CSV est vide ou invalide',
                ], 422);
            }

            // Map headers to column names
            $headerRow = $rows[0];
            $columnMapping = $this->mapHeaders($headerRow);

            if (empty($columnMapping)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Impossible de mapper les colonnes du CSV',
                ], 422);
            }

            // Process data rows
            $totalRows = count($rows) - 1; // Exclude header
            $created = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];

            DB::transaction(function () use ($rows, $columnMapping, $eventId, &$created, &$updated, &$skipped, &$errors) {
                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];

                    try {
                        $data = $this->extractRowData($row, $columnMapping);

                        if (empty($data['ticket_number'])) {
                            $skipped++;
                            continue;
                        }

                        // Check if ticket already exists
                        $existingTicket = Invitation::where('event_id', $eventId)
                            ->where('ticket_number', $data['ticket_number'])
                            ->lockForUpdate()
                            ->first();

                        if ($existingTicket) {
                            // Upsert logic: preserve SCANNED status
                            if ($existingTicket->status === 'SCANNED') {
                                // Don't update if already scanned
                                $skipped++;
                            } else {
                                // Update other fields but keep status
                                $existingTicket->update([
                                    'guest_name' => $data['guest_name'] ?? $existingTicket->guest_name,
                                    'guest_phone' => $data['phone'] ?? $existingTicket->guest_phone,
                                    'code' => $data['code'],
                                    'qr_payload' => $data['qr_payload'] ?? $existingTicket->qr_payload,
                                ]);
                                $updated++;
                            }
                        } else {
                            // Generate QR payload if not provided
                            if (empty($data['qr_payload'])) {
                                $data['qr_payload'] = $this->generateQRPayload();
                            }

                            // Create new invitation
                            Invitation::create([
                                'event_id' => $eventId,
                                'ticket_number' => $data['ticket_number'],
                                'code' => $data['code'],
                                'qr_payload' => $data['qr_payload'],
                                'guest_name' => $data['guest_name'],
                                'guest_phone' => $data['phone'],
                                'status' => 'NOT_SCANNED',
                                'payment_status' => 'UNPAID',
                            ]);
                            $created++;
                        }
                    } catch (\Exception $e) {
                        $errors[] = [
                            'row' => $i + 1,
                            'error' => $e->getMessage(),
                        ];
                        $skipped++;
                    }
                }
            });

            return response()->json([
                'ok' => true,
                'summary' => [
                    'totalRows' => $totalRows,
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'errors' => count($errors),
                ],
                'errorDetails' => $errors,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Événement non trouvé',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Fichier invalide',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Erreur lors de l\'import des invitations',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Detect CSV delimiter (comma or semicolon)
     */
    private function detectDelimiter($content)
    {
        $lines = explode("\n", $content);
        if (empty($lines[0])) {
            return ',';
        }

        $firstLine = $lines[0];
        $commaCount = substr_count($firstLine, ',');
        $semicolonCount = substr_count($firstLine, ';');

        return $semicolonCount > $commaCount ? ';' : ',';
    }

    /**
     * Parse CSV content and return array of rows
     */
    private function parseCSV($content, $delimiter)
    {
        $rows = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Simple CSV parsing (handle quoted values)
            $row = str_getcsv($line, $delimiter);
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Map header row to column names
     * Supports French and English aliases
     */
    private function mapHeaders($headerRow)
    {
        $mapping = [];

        $columnAliases = [
            'ticket_number' => ['code', 'ticket_number', 'numero_ticket', 'num_ticket', 'n_ticket'],
            'qr_payload' => ['#code', 'qr_payload', 'qr_code', 'code_qr', 'qr', 'payload'],
            'guest_name' => ['guest_name', 'nom', 'name', 'participant_name', 'nom_participant'],
            'phone' => ['phone', 'telephone', 'téléphone', 'tel', 'phone_number', 'numero_telephone'],
        ];

        foreach ($headerRow as $index => $header) {
            $normalizedHeader = strtolower(trim($header));

            foreach ($columnAliases as $column => $aliases) {
                foreach ($aliases as $alias) {
                    if (strtolower($alias) === $normalizedHeader) {
                        $mapping[$column] = $index;
                        break 2;
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Extract data from a row using column mapping
     */
    private function extractRowData($row, $columnMapping)
    {
        $data = [
            'ticket_number' => null,
            'qr_payload' => null,
            'guest_name' => null,
            'phone' => null,
        ];

        foreach ($columnMapping as $column => $index) {
            if (isset($row[$index])) {
                $value = trim($row[$index]);
                if (!empty($value)) {
                    $data[$column] = $value;
                }
            }
        }

        $data['code'] = $data['ticket_number'];
        if (!preg_match('/^(?:[A-Za-z]+-)?([0-9]+)$/', $data['ticket_number'] ?? '', $match) || (int)$match[1] < 1) {
            throw new \InvalidArgumentException('Numero de carte invalide');
        }
        $data['ticket_number'] = (int)$match[1];
        return $data;
    }

    /**
     * Generate QR payload in format SCN-XXXX
     */
    private function generateQRPayload()
    {
        $randomCode = strtoupper(Str::random(4));
        return "SCN-" . $randomCode;
    }
}
