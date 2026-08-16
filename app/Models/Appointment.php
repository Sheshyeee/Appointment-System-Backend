<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'service_id',
        'dentist_id',
        'full_name',
        'email',
        'phone',
        'reason',
        'date',
        'time',
        'status',
        'reminder_at',
        'reminder_sent_at',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'reminder_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function dentist()
    {
        return $this->belongsTo(Dentist::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
