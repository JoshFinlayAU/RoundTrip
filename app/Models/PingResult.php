<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PingResult extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'target_id',
        'ts',
        'rtt_ms',
        'seq',
        'lost',
    ];

    protected $casts = [
        'ts' => 'datetime',
        'rtt_ms' => 'float',
        'seq' => 'integer',
        'lost' => 'boolean',
    ];

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d\TH:i:s\Z');
    }
}
