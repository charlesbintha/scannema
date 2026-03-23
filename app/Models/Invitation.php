<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invitation extends Model
{
    protected $table = 'invitations';

    protected $fillable = [
        'event_id',
        'ticket_number',
        'qr_payload',
        'guest_name',
        'phone',
        'seat_label',
        'ticket_type',
        'status',
        'issued_at',
        'scanned_at',
        'scanned_by_user_id',
        'scanned_checkpoint_id',
        'last_scan_device_id',
        'scan_count',
        'metadata_json',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'scanned_at' => 'datetime',
        'scan_count' => 'integer',
        'metadata_json' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the event this invitation belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * Get the user who scanned this invitation.
     */
    public function scannedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by_user_id');
    }

    /**
     * Get the checkpoint where this invitation was scanned.
     */
    public function scannedCheckpoint(): BelongsTo
    {
        return $this->belongsTo(Checkpoint::class, 'scanned_checkpoint_id');
    }

    /**
     * Get the device that last scanned this invitation.
     */
    public function lastScanDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'last_scan_device_id');
    }

    /**
     * Get all scan logs for this invitation.
     */
    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class, 'invitation_id');
    }
}
