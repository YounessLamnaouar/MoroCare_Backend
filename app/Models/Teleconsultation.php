<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teleconsultation extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function ($consultation) {
            $consultation->room_id = (string) \Illuminate\Support\Str::uuid();
        });
    }

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'date',
        'time',
        'status',
        'notes',
        'meeting_link'
    ];

    protected $casts = [
        'patient_id' => 'integer',
        'doctor_id' => 'integer',
        'date' => 'date:Y-m-d',
    ];

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id')->select(['id', 'name', 'speciality', 'address', 'profile_photo']);
    }
}
