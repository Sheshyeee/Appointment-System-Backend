<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicSetting extends Model
{
    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'working_hours',
    ];

    protected $casts = [
        'working_hours' => 'array',
    ];
}
