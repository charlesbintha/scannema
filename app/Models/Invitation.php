<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invitation extends Model
{
    protected $table = 'invitations';

    protected $fillable = [
        'payment_status',
        'paid_at',
        'paid_by_user_id',
        'event_id',
        'ticket_number',
        'code',
        'qr_payload',
        'guest_name',
        'guest_email',
        'guest_phone',
        'status',
        'scanned_at',
        'notes',
    ];

    protected $casts = [
        'ticket_number' => 'integer',
        'scanned_at' => 'datetime',
        'paid_at' => 'datetime',
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
     * Get all scan logs for this invitation.
     */
    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class, 'invitation_id');
    }
}
