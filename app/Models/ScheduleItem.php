<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_type_id', 
        'start_time', 
        'end_time', 
        'capacity', 
        'booked_count'
    ];

    // Важно: указываем, что эти поля должны быть датами
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function classType()
    {
        return $this->belongsTo(ClassType::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}