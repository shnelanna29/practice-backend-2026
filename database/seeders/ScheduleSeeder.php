<?php

namespace Database\Seeders;

use App\Models\ScheduleItem;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classTypes = [1, 2, 3, 4]; // ID типов занятий
        $startDate = Carbon::now()->addDays(1);

        $data = [];

        // Генерируем 60 занятий на ближайшие 2 недели
        for ($i = 0; $i < 60; $i++) {
            $dayOffset = intdiv($i, 4); // Каждые 4 занятия - новый день
            $hourOffset = ($i % 4) * 2; // Каждое занятие через 2 часа (10:00, 12:00, 14:00, 16:00)
            
            $startTime = (clone $startDate)->addDays($dayOffset)->setTime(10 + $hourOffset, 0);
            $endTime = (clone $startTime)->addHour();

            $data[] = [
                'class_type_id' => $classTypes[array_rand($classTypes)],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'capacity' => rand(10, 20),
                'booked_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Вставляем данные пачкой для скорости
        ScheduleItem::insert($data);
    }
}