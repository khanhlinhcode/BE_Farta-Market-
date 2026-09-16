<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalyticsPageView extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'visitor_hash', 'session_hash', 'user_id', 'path', 'referrer_host',
        'device_type', 'browser', 'os', 'occurred_at',
    ];

    protected $hidden = ['visitor_hash', 'session_hash'];

    protected $casts = ['occurred_at' => 'datetime', 'user_id' => 'integer'];
}
