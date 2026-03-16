<?php

namespace App\Http\Controllers;

use App\Models\ClassType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClassTypeController extends Controller
{
    /**
     * Список типов ресурсов (характеристик)
     */
    public function index()
    {
        return response()->json(ClassType::query()->orderBy('name')->get());
    }

    /**
     * Создание типа ресурса (только admin)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'default_capacity' => 'required|integer|min:1',
        ]);

        $classType = ClassType::create($validated);

        Log::info('Админ создал тип ресурса', [
            'admin_id' => $request->user()->id,
            'class_type_id' => $classType->id,
        ]);

        return response()->json(['message' => 'Тип ресурса создан', 'data' => $classType], 201);
    }

    /**
     * Обновление типа ресурса (только admin)
     */
    public function update(Request $request, $id)
    {
        $classType = ClassType::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'default_capacity' => 'sometimes|integer|min:1',
        ]);

        $classType->update($validated);

        Log::info('Админ обновил тип ресурса', [
            'admin_id' => $request->user()->id,
            'class_type_id' => $classType->id,
        ]);

        return response()->json(['message' => 'Тип ресурса обновлен', 'data' => $classType]);
    }

    /**
     * Удаление типа ресурса (только admin)
     */
    public function destroy(Request $request, $id)
    {
        $classType = ClassType::findOrFail($id);

        $classType->delete();

        Log::info('Админ удалил тип ресурса', [
            'admin_id' => $request->user()->id,
            'class_type_id' => $classType->id,
        ]);

        return response()->json(['message' => 'Тип ресурса удален']);
    }
}
