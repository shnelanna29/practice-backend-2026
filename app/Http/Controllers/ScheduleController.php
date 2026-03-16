<?php

namespace App\Http\Controllers;

use App\Models\ScheduleItem;
use App\Models\ClassType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    /**
     * Список занятий с пагинацией, фильтрацией и сортировкой
     * 
     * @queryParam date string Format: YYYY-MM-DD. Filter by date. Example: 2026-03-20
     * @queryParam class_type_id integer Filter by class type ID. Example: 1
     * @queryParam capacity_min integer Filter by minimum capacity. Example: 10
     * @queryParam sort string Field to sort by: start_time, capacity, created_at. Example: start_time
     * @queryParam direction string Sort direction: asc or desc. Example: asc
     * @queryParam per_page integer Items per page (max 50). Example: 10
     */
    public function index(Request $request)
    {
        $query = ScheduleItem::with('classType');

        // === ФИЛЬТРАЦИЯ ===
        // По дате
        if ($request->filled('date')) {
            $query->whereDate('start_time', $request->date);
        }

        // По типу занятия
        if ($request->filled('class_type_id')) {
            $query->where('class_type_id', $request->class_type_id);
        }

        // По минимальной вместимости
        if ($request->filled('capacity_min')) {
            $query->where('capacity', '>=', $request->capacity_min);
        }

        // По названию типа занятия (поиск)
        if ($request->filled('search')) {
            $query->whereHas('classType', function ($q) use ($request) {
                $q->where('name', 'LIKE', "%{$request->search}%");
            });
        }

        // === СОРТИРОВКА ===
        $allowedSorts = ['start_time', 'end_time', 'capacity', 'created_at', 'booked_count'];
        $sortBy = $request->get('sort', 'start_time');
        $direction = strtolower($request->get('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'start_time';
        }
        $query->orderBy($sortBy, $direction);

        // === ПАГИНАЦИЯ ===
        $perPage = min((int)$request->get('per_page', 10), 50);
        $items = $query->paginate($perPage);

        return response()->json($items);
    }

    /**
     * Расписание ресурса на день или неделю
     * 
     * @queryParam date string Format: YYYY-MM-DD. Date for schedule. Example: 2026-03-20
     * @queryParam period string Schedule period: day or week. Example: day
     */
    public function schedule($id, Request $request)
    {
        $period = $request->get('period', 'day');
        $date = $request->get('date', now()->toDateString());
        
        if ($period === 'week') {
            $startDate = Carbon::parse($date)->startOfWeek();
            $endDate = (clone $startDate)->endOfWeek();
        } else {
            $startDate = Carbon::parse($date)->startOfDay();
            $endDate = Carbon::parse($date)->endOfDay();
        }

        $items = ScheduleItem::with('classType')
            ->where('id', $id)
            ->whereBetween('start_time', [$startDate, $endDate])
            ->orderBy('start_time')
            ->get();

        // Группировка по дням
        $grouped = $items->groupBy(function ($item) {
            return Carbon::parse($item->start_time)->toDateString();
        });

        return response()->json([
            'resource_id' => $id,
            'period' => $period,
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'schedule' => $grouped->map(function ($dayItems) {
                return $dayItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'start_time' => $item->start_time,
                        'end_time' => $item->end_time,
                        'class_type' => $item->classType->name,
                        'capacity' => $item->capacity,
                        'booked_count' => $item->booked_count,
                        'available' => $item->capacity - $item->booked_count,
                    ];
                });
            }),
        ]);
    }

    /**
     * Поиск свободных ресурсов на заданную дату и время
     * 
     * @queryParam date string required Format: YYYY-MM-DD. Example: 2026-03-20
     * @queryParam start_time string required Format: HH:MM. Example: 10:00
     * @queryParam end_time string required Format: HH:MM. Example: 11:00
     * @queryParam capacity_min integer Minimum required capacity. Example: 10
     * @queryParam class_type_id integer Filter by class type. Example: 1
     */
    public function available(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'capacity_min' => 'nullable|integer|min:1',
            'class_type_id' => 'nullable|exists:class_types,id',
        ]);

        $date = $request->date;
        $startTime = "{$date} {$request->start_time}:00";
        $endTime = "{$date} {$request->end_time}:00";

        // Запрос на поиск свободных занятий:
        // 1. Занятие должно быть в тот же день
        // 2. Должно быть достаточно мест
        // 3. Не должно быть подтверждённых броней, пересекающихся по времени
        $query = ScheduleItem::with('classType')
            ->whereDate('start_time', $date)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->whereColumn('capacity', '>', 'booked_count');

        // Фильтр по вместимости
        if ($request->filled('capacity_min')) {
            $query->where('capacity', '>=', $request->capacity_min);
        }

        // Фильтр по типу занятия
        if ($request->filled('class_type_id')) {
            $query->where('class_type_id', $request->class_type_id);
        }

        // Исключаем занятия, у которых есть подтверждённые брони, пересекающиеся с запрошенным временем
        $query->whereDoesntHave('bookings', function ($q) use ($startTime, $endTime) {
            $q->where('status', 'confirmed')
              ->where(function ($sub) use ($startTime, $endTime) {
                  $sub->whereBetween('start_time', [$startTime, $endTime])
                      ->orWhereBetween('end_time', [$startTime, $endTime])
                      ->orWhereRaw('? BETWEEN start_time AND end_time', [$startTime])
                      ->orWhereRaw('? BETWEEN start_time AND end_time', [$endTime]);
              });
        });

        // Пагинация и сортировка
        $perPage = min((int)$request->get('per_page', 10), 50);
        $sortBy = $request->get('sort', 'start_time');
        $direction = strtolower($request->get('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['start_time', 'capacity', 'created_at'];
        
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $direction);
        }

        $available = $query->paginate($perPage);

        return response()->json([
            'search_params' => [
                'date' => $date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
            ],
            'data' => $available->items(),
            'pagination' => [
                'current_page' => $available->currentPage(),
                'per_page' => $available->perPage(),
                'total' => $available->total(),
                'last_page' => $available->lastPage(),
            ],
        ]);
    }

    /**
     * Детали занятия
     */
    public function show($id)
    {
        $item = ScheduleItem::with(['classType', 'bookings.user'])->findOrFail($id);
        
        return response()->json([
            'id' => $item->id,
            'class_type' => $item->classType,
            'start_time' => $item->start_time,
            'end_time' => $item->end_time,
            'capacity' => $item->capacity,
            'booked_count' => $item->booked_count,
            'available' => $item->capacity - $item->booked_count,
            'bookings' => $item->bookings->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'user_name' => $booking->user->name,
                    'status' => $booking->status,
                ];
            }),
        ]);
    }

    /**
     * Создание занятия (ТОЛЬКО АДМИН)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'class_type_id' => 'required|exists:class_types,id',
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
            'capacity' => 'required|integer|min:1',
        ]);

        $item = ScheduleItem::create($validated);

        Log::info('Админ создал новое занятие', [
            'admin_id' => $request->user()->id,
            'schedule_item_id' => $item->id,
            'data' => $validated
        ]);

        return response()->json(['message' => 'Занятие создано', 'data' => $item], 201);
    }

    /**
     * Обновление (ТОЛЬКО АДМИН)
     */
    public function update(Request $request, $id)
    {
        $item = ScheduleItem::findOrFail($id);

        $validated = $request->validate([
            'class_type_id' => 'sometimes|exists:class_types,id',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after:start_time',
            'capacity' => 'sometimes|integer|min:1',
        ]);

        $item->update($validated);

        Log::info('Админ обновил занятие', [
            'admin_id' => $request->user()->id,
            'schedule_item_id' => $item->id,
            'changes' => $validated
        ]);

        return response()->json(['message' => 'Занятие обновлено', 'data' => $item]);
    }

    /**
     * Удаление (ТОЛЬКО АДМИН)
     */
    public function destroy($id)
    {
        $item = ScheduleItem::findOrFail($id);
        
        Log::info('Админ удалил занятие', [
            'admin_id' => request()->user()->id,
            'schedule_item_id' => $item->id
        ]);
        
        $item->delete();

        return response()->json(['message' => 'Занятие удалено']);
    }
}