<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeType extends Model
{
    protected $fillable = [
        'type_code',
        'type_name',
        'workflow_type',
        'has_external_approval',
        'has_internal_approval',
        'description',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'employee_type', 'type_code');
    }
}