<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ScheduleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    /**
     * Мои брони с пагинацией и фильтрацией
     * 
     * @queryParam status string Filter by status: confirmed, cancelled. Example: confirmed
     * @queryParam only_future boolean Show only future bookings. Example: true
     * @queryParam sort string Sort field: created_at, status. Example: created_at
     * @queryParam direction string Sort direction: asc, desc. Example: desc
     * @queryParam per_page integer Items per page. Example: 10
     */
    public function myBookings(Request $request)
    {
        $user = $request->user();
        
        $query = $user->bookings()->with(['scheduleItem.classType']);

        // Фильтрация по статусу
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Только будущие брони
        if ($request->boolean('only_future')) {
            $query->whereHas('scheduleItem', function ($q) {
                $q->where('start_time', '>', now());
            });
        }

        // Сортировка
        $allowedSorts = ['created_at', 'status'];
        $sortBy = $request->get('sort', 'created_at');
        $direction = strtolower($request->get('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }
        $query->orderBy($sortBy, $direction);

        // Пагинация
        $perPage = min((int)$request->get('per_page', 10), 50);
        $bookings = $query->paginate($perPage);

        return response()->json($bookings);
    }

    /**
     * Все брони (ТОЛЬКО АДМИН) с пагинацией
     * 
     * @queryParam user_id integer Filter by user ID (admin only). Example: 1
     * @queryParam status string Filter by status. Example: confirmed
     * @queryParam per_page integer Items per page. Example: 10
     */
    public function index(Request $request)
    {
        $query = Booking::with(['user', 'scheduleItem.classType']);

        // Фильтры (только для админа)
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min((int)$request->get('per_page', 10), 50);
        $bookings = $query->latest()->paginate($perPage);

        return response()->json($bookings);
    }

    /**
     * Создание брони
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'schedule_item_id' => 'required|exists:schedule_items,id',
        ]);

        $scheduleItem = ScheduleItem::findOrFail($validated['schedule_item_id']);

        Log::info('Попытка бронирования занятия', [
            'user_id' => $user->id,
            'schedule_item_id' => $scheduleItem->id,
        ]);

        // Проверки...
        if ($scheduleItem->start_time->isPast()) {
            return response()->json(['message' => 'Нельзя записаться на занятие, которое уже прошло или идет'], 422);
        }
        if ($scheduleItem->booked_count >= $scheduleItem->capacity) {
            return response()->json(['message' => 'Мест нет'], 422);
        }

        $exists = Booking::where('user_id', $user->id)
            ->where('schedule_item_id', $scheduleItem->id)
            ->where('status', 'confirmed')
            ->exists();
        if ($exists) {
            return response()->json(['message' => 'Вы уже записаны на это занятие'], 422);
        }

        // Проверка пересечений
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

        // Создание в транзакции
        DB::transaction(function () use ($user, $scheduleItem) {
            $booking = Booking::create([
                'user_id' => $user->id,
                'schedule_item_id' => $scheduleItem->id,
                'status' => 'confirmed',
            ]);
            $scheduleItem->increment('booked_count');
            Log::info('Бронирование успешно', ['booking_id' => $booking->id]);
        });

        return response()->json(['message' => 'Вы успешно записаны!'], 201);
    }

    /**
     * Отмена брони
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $booking = Booking::findOrFail($id);

        if ($user->role === 'client' && $booking->user_id !== $user->id) {
            return response()->json(['message' => 'Доступ запрещен'], 403);
        }
        if ($booking->status === 'cancelled') {
            return response()->json(['message' => 'Бронь уже отменена'], 422);
        }
        if ($booking->scheduleItem->start_time->isPast()) {
            return response()->json(['message' => 'Нельзя отменить бронь после начала занятия'], 422);
        }

        DB::transaction(function () use ($booking) {
            $booking->update(['status' => 'cancelled']);
            $booking->scheduleItem->decrement('booked_count');
        });

        return response()->json(['message' => 'Бронь отменена']);
    }
}