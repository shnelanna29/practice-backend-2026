<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\ScheduleItem;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    /**
     * Отзывы и средний рейтинг ресурса
     * 
     * @queryParam rating integer Filter by rating (1-5). Example: 5
     * @queryParam per_page integer Items per page. Example: 10
     */
    public function index($scheduleItemId, Request $request)
    {
        $query = Review::with('user')
            ->where('schedule_item_id', $scheduleItemId);

        // Фильтр по рейтингу
        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        // Сортировка и пагинация
        $perPage = min((int)$request->get('per_page', 10), 50);
        $reviews = $query->latest('created_at')->paginate($perPage);

        // Средний рейтинг
        $averageRating = Review::where('schedule_item_id', $scheduleItemId)
            ->avg('rating');

        return response()->json([
            'schedule_item_id' => $scheduleItemId,
            'average_rating' => $averageRating ? round($averageRating, 2) : null,
            'total_reviews' => $reviews->total(),
            'data' => $reviews->items(),
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * Создание отзыва (только после завершённого бронирования)
     */
    public function store(Request $request, $scheduleItemId)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        // Проверка: бронирование принадлежит пользователю
        $booking = Booking::with('scheduleItem')->findOrFail($validated['booking_id']);
        
        if ($booking->user_id !== $user->id) {
            return response()->json(['message' => 'Вы можете оставить отзыв только на своё бронирование'], 403);
        }

        // Проверка: бронирование для указанного занятия
        if ($booking->schedule_item_id != $scheduleItemId) {
            return response()->json(['message' => 'Бронирование не относится к этому занятию'], 422);
        }

        // Проверка: занятие уже завершено
        if (!$booking->scheduleItem->end_time->isPast()) {
            return response()->json(['message' => 'Отзыв можно оставить только после завершения занятия'], 422);
        }

        // Проверка: бронирование подтверждено
        if ($booking->status !== 'confirmed') {
            return response()->json(['message' => 'Отзыв можно оставить только на подтверждённое бронирование'], 422);
        }

        // Проверка: отзыв ещё не оставлен
        $exists = Review::where('booking_id', $validated['booking_id'])->exists();
        if ($exists) {
            return response()->json(['message' => 'Вы уже оставили отзыв на это бронирование'], 422);
        }

        // Создание отзыва
        $review = Review::create([
            'user_id' => $user->id,
            'booking_id' => $validated['booking_id'],
            'schedule_item_id' => $scheduleItemId,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        Log::info('Пользователь оставил отзыв', [
            'user_id' => $user->id,
            'review_id' => $review->id,
            'rating' => $review->rating,
        ]);

        return response()->json([
            'message' => 'Отзыв успешно добавлен',
            'data' => $review,
        ], 201);
    }
}