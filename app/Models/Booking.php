<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'schedule_item_id', 'status'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scheduleItem()
    {
        return $this->belongsTo(ScheduleItem::class);
    }
}