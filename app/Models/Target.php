<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Target extends Model
{
    protected $fillable = [
        'name',
        'host',
        'interval_seconds',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'interval_seconds' => 'integer',
    ];
}
