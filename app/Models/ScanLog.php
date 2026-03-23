<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanLog extends Model
{
    protected $table = 'scan_logs';

    protected $fillable = [
        'event_id',
        'invitation_id',
        'input_payload',
        'result',
        'checked_at',
        'checker_user_id',
        'checkpoint_id',
        'device_id',
        'latency_ms',
        'latitude',
        'longitude',
        'note',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
        'latency_ms' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'created_at' => 'datetime',
    ];

    /**
     * Get the event this scan log belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * Get the invitation that was scanned.
     */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class, 'invitation_id');
    }

    /**
     * Get the user who performed the check.
     */
    public function checkerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checker_user_id');
    }

    /**
     * Get the checkpoint where the scan occurred.
     */
    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(Checkpoint::class, 'checkpoint_id');
    }

    /**
     * Get the device that performed the scan.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }
}
