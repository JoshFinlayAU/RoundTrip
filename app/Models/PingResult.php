<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PingResult extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'target_id',
        'ts',
        'min_ms',
        'avg_ms',
        'max_ms',
        'loss_pct',
    ];

    protected $casts = [
        'ts' => 'datetime',
        'min_ms' => 'float',
        'avg_ms' => 'float',
        'max_ms' => 'float',
        'loss_pct' => 'float',
    ];

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d\TH:i:s\Z');
    }
}
