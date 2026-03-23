<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $table = 'organizations';

    protected $fillable = [
        'name',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all events for this organization.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'organization_id');
    }

    /**
     * Get all users for this organization.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'organization_id');
    }
}
