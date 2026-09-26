<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedReport extends Model
{
    protected $fillable = [
        'user_id', 'name', 'report_type', 'filters', 'is_shared', 'last_run_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'is_shared' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}