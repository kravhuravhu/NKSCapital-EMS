<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    protected $fillable = [
        'user_id', 'report_type', 'format', 'file_path',
        'file_size', 'filters', 'record_count',
    ];

    protected $casts = [
        'filters' => 'array',
        'file_size' => 'integer',
        'record_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}