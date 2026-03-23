<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $table = 'events';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'starts_at',
        'ends_at',
        'timezone',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the organization this event belongs to.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Get all checkpoints for this event.
     */
    public function checkpoints(): HasMany
    {
        return $this->hasMany(Checkpoint::class, 'event_id');
    }

    /**
     * Get all devices for this event.
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'event_id');
    }

    /**
     * Get all invitations for this event.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'event_id');
    }

    /**
     * Get all scan logs for this event.
     */
    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class, 'event_id');
    }
}
