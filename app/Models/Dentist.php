<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dentist extends Model
{
    protected $fillable = [
        'full_name',
        'specialty',
        'rating',
        'years_experience',
        'education',
        'bio',
        'photo_url',
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
