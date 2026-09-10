<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientTemplate extends Model
{
    protected $fillable = [
        'client_id',
        'template_name',
        'template_content',
        'branding_settings',
        'file_path',
        'file_hash',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'branding_settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}