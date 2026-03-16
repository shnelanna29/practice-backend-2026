<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    // Регистрация
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            // ❌ УДАЛИТЕ 'role' из валидации!
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'client', // ✅ ВСЕГДА 'client' при регистрации
        ]);

        Log::info('Новая регистрация пользователя', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Пользователь успешно зарегистрирован',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    // Вход
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            Log::warning('Неудачная попытка входа', [
                'email' => $request->email,
                'ip' => $request->ip()
            ]);
            throw ValidationException::withMessages([
                'email' => ['Неверные учетные данные'],
            ]);
        }

        Log::info('Успешный вход пользователя', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Вход выполнен успешно',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    // Выход
    public function logout(Request $request)
    {
        Log::info('Выход пользователя', ['user_id' => $request->user()->id]);
        
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Выход выполнен успешно',
        ]);
    }

    // Профиль
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}