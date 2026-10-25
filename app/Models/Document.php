<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'category',
        'description',
        'file_path',
        'file_size',
        'mime_type',
        'is_shared',
        'expires_at',
        'uploaded_by',
    ];

    protected $casts = [
        'is_shared'  => 'boolean',
        'expires_at' => 'date',
        'file_size'  => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }

    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeVisibleTo($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('is_shared', true)
              ->orWhere('user_id', $userId);
        });
    }

    public function humanSize(): string
    {
        if (!$this->file_size) {
            return '—';
        }

        $bytes = (int) $this->file_size;

        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1024 / 1024, 1) . ' MB';
    }
}