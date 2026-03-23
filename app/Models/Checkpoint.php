<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Checkpoint extends Model
{
    protected $table = 'checkpoints';

    protected $fillable = [
        'event_id',
        'name',
        'location_label',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the event this checkpoint belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * Get all scan logs for this checkpoint.
     */
    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class, 'checkpoint_id');
    }

    /**
     * Get all invitations scanned at this checkpoint.
     */
    public function scannedInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'scanned_checkpoint_id');
    }
}
