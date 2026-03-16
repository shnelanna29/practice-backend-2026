<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ClassType extends Model
{
    protected $fillable = ['name', 'description', 'default_capacity'];
    
    public function scheduleItems()
    {
        return $this->hasMany(ScheduleItem::class);
    }
}