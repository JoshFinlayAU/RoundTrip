<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Target extends Model
{
    protected $fillable = [
        'name',
        'host',
        'interval_seconds',
        'enabled',
        'group_id',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'interval_seconds' => 'integer',
        'group_id' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function pingResults(): HasMany
    {
        return $this->hasMany(PingResult::class);
    }
}
