<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $table = 'devices';

    protected $fillable = [
        'event_id',
        'label',
        'platform',
        'app_version',
        'device_identifier',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the event this device is registered for.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * Get all scan logs from this device.
     */
    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class, 'device_id');
    }

    /**
     * Get all invitations last scanned by this device.
     */
    public function lastScannedInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'last_scan_device_id');
    }
}
