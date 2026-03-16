<?php
namespace Database\Seeders;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Администратор
        User::create([
            'name' => 'Админ Студии',
            'email' => 'admin@studio.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        // Клиент 1 (Анна)
        User::create([
            'name' => 'Анна Иванова',
            'email' => 'anna@example.com',
            'password' => Hash::make('password'),
            'role' => 'client',
        ]);

        // Клиент 2 (Петр)
        User::create([
            'name' => 'Петр Петров',
            'email' => 'petr@example.com',
            'password' => Hash::make('password'),
            'role' => 'client',
        ]);
    }
}