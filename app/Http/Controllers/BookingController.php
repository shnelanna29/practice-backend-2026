<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ScheduleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    // Мои брони
    public function myBookings(Request $request)
    {
        $bookings = $request->user()
            ->bookings()
            ->with('scheduleItem.classType')
            ->latest()
            ->get();

        return response()->json($bookings);
    }

    // Все брони (ТОЛЬКО АДМИН)
    public function index()
    {
        $bookings = Booking::with(['user', 'scheduleItem.classType'])->latest()->get();
        return response()->json($bookings);
    }

    // Создание брони
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'schedule_item_id' => 'required|exists:schedule_items,id',
        ]);

        $scheduleItem = ScheduleItem::findOrFail($validated['schedule_item_id']);

        // 1. Проверка: занятие еще не началось?
        if ($scheduleItem->start_time->isPast()) {
            return response()->json(['message' => 'Нельзя записаться на занятие, которое уже прошло или идет'], 422);
        }

        // 2. Проверка: есть ли места?
        if ($scheduleItem->booked_count >= $scheduleItem->capacity) {
            return response()->json(['message' => 'Мест нет'], 422);
        }

        // 3. Проверка: не записан ли уже пользователь?
        $exists = Booking::where('user_id', $user->id)
            ->where('schedule_item_id', $scheduleItem->id)
            ->where('status', 'confirmed')
            ->exists();
        
        if ($exists) {
            return response()->json(['message' => 'Вы уже записаны на это занятие'], 422);
        }

        // 4. Проверка: нет ли пересечений по времени?
        $conflict = Booking::join('schedule_items', 'bookings.schedule_item_id', '=', 'schedule_items.id')
            ->where('bookings.user_id', $user->id)
            ->where('bookings.status', 'confirmed')
            ->where(function ($query) use ($scheduleItem) {
                $query->whereBetween('schedule_items.start_time', [$scheduleItem->start_time, $scheduleItem->end_time])
                      ->orWhereBetween('schedule_items.end_time', [$scheduleItem->start_time, $scheduleItem->end_time])
                      ->orWhere(function ($q) use ($scheduleItem) {
                          $q->where('schedule_items.start_time', '<=', $scheduleItem->start_time)
                            ->where('schedule_items.end_time', '>=', $scheduleItem->end_time);
                      });
            })
            ->exists();

        if ($conflict) {
            return response()->json(['message' => 'У вас есть запись на другое занятие в это время'], 422);
        }

        // Создаем бронь в транзакции
        DB::transaction(function () use ($user, $scheduleItem) {
            $booking = Booking::create([
                'user_id' => $user->id,
                'schedule_item_id' => $scheduleItem->id,
                'status' => 'confirmed',
            ]);
            $scheduleItem->increment('booked_count');
        });

        return response()->json(['message' => 'Вы успешно записаны!'], 201);
    }

    // Отмена брони
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $booking = Booking::findOrFail($id);

        // Проверка прав
        if ($user->role === 'client' && $booking->user_id !== $user->id) {
            return response()->json(['message' => 'Доступ запрещен'], 403);
        }

        if ($booking->scheduleItem->start_time->isPast()) {
            return response()->json(['message' => 'Нельзя отменить бронь после начала занятия'], 422);
        }

        if ($booking->status === 'cancelled') {
            return response()->json(['message' => 'Бронь уже отменена'], 422);
        }

        DB::transaction(function () use ($booking) {
            $booking->update(['status' => 'cancelled']);
            $booking->scheduleItem->decrement('booked_count');
        });

        return response()->json(['message' => 'Бронь отменена']);
    }
}