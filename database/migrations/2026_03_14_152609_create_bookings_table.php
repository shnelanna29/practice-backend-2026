<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('schedule_item_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['confirmed', 'cancelled'])->default('confirmed');
            $table->timestamps();

            // МЫ УБРАЛИ ЭТУ СТРОКУ, ТАК КАК ОНА БЛОКИРУЕТ ПОВТОРНУЮ ЗАПИСЬ ПОСЛЕ ОТМЕНЫ:
            // $table->unique(['user_id', 'schedule_item_id']); 
            
            // Вместо этого логика проверки дубликатов должна быть в коде контроллера,
            // где мы проверяем статус 'confirmed'.
        });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};