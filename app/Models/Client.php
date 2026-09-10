<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Client extends Model
{
    protected $fillable = [
        'company_name',
        'registration_number',
        'vat_number',
        'billing_address',
        'primary_contact_name',
        'primary_contact_email',
        'primary_contact_phone',
        'contract_value',
        'payment_terms',
        'status',
        'template_id',
        'is_active',
    ];

    protected $casts = [
        'contract_value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function template(): HasOne
    {
        return $this->hasOne(ClientTemplate::class, 'id', 'template_id');
    }

    public function managers(): HasMany
    {
        return $this->hasMany(ClientManager::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function primaryManager()
    {
        return $this->hasOne(ClientManager::class)->where('is_primary', true);
    }
}