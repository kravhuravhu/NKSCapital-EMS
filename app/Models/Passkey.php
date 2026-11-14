<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Passkey extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'credential_id',
        'credential',
        'last_used_at',
    ];

    protected $casts = [
        'credential' => 'array',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = ['credential'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}