<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;


Route::get('/test-db', function () {
    try {
        DB::connection()->getPdo();
        return '✅ База данных подключена успешно!';
    } catch (\Exception $e) {
        return '❌ Ошибка: ' . $e->getMessage();
    }
    return response()->json([
        'message' => 'Dance Studio API работает!',
        'docs' => 'Используйте Postman или /api/schedule для проверки',
    ]);
});

    return view('welcome');

