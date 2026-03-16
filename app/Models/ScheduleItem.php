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
        'booked_count',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'capacity' => 'integer',
        'booked_count' => 'integer',
    ];

    public function classType()
    {
        return $this->belongsTo(ClassType::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'schedule_item_id');
    }
}
