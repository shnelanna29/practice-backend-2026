<?php

namespace App\Http\Controllers;

use App\Models\ScheduleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ScheduleController extends Controller
{
    // Список занятий
    public function index(Request $request)
    {
        $query = ScheduleItem::with('classType')->orderBy('start_time');

        if ($request->has('date')) {
            $query->whereDate('start_time', $request->date);
        }

        return response()->json($query->get());
    }

    // Детали занятия
    public function show($id)
    {
        $item = ScheduleItem::with('classType')->findOrFail($id);
        return response()->json($item);
    }

    // Создание занятия (ТОЛЬКО АДМИН)
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

    // Обновление (ТОЛЬКО АДМИН)
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

    // Удаление (ТОЛЬКО АДМИН)
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