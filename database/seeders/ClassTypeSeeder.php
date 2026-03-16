<?php

namespace Database\Seeders;

use App\Models\ClassType;
use Illuminate\Database\Seeder;

class ClassTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Классический балет', 'description' => 'Основы классического танца', 'default_capacity' => 15],
            ['name' => 'Хип-хоп', 'description' => 'Современный уличный танец', 'default_capacity' => 20],
            ['name' => 'Сальса', 'description' => 'Парный латиноамериканский танец', 'default_capacity' => 12],
            ['name' => 'Контемпорари', 'description' => 'Свободный современный танец', 'default_capacity' => 15],
        ];

        foreach ($types as $type) {
            ClassType::create($type);
        }
    }
}