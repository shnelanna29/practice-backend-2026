<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Firebase\JWT\JWT;

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
            'access_token' => $this->createToken($user),
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

        return response()->json([
            'message' => 'Вход выполнен успешно',
            'user' => $user,
            'access_token' => $this->createToken($user),
            'token_type' => 'Bearer',
        ]);
    }

    // Выход
    public function logout(Request $request)
    {
        $user = $request->user();
        Log::info('Выход пользователя', ['user_id' => $user?->id]);

        $token = $this->getBearerToken($request);
        if (!$token) {
            return response()->json(['message' => 'Неавторизован'], 401);
        }

        $payload = $this->decodeToken($token);
        if (!$payload || empty($payload['jti']) || empty($payload['exp'])) {
            return response()->json(['message' => 'Недействительный токен'], 401);
        }

        $ttlSeconds = max(0, $payload['exp'] - time());
        Cache::put("jwt:blacklist:{$payload['jti']}", true, $ttlSeconds);

        return response()->json([
            'message' => 'Выход выполнен успешно',
        ]);
    }

    // Профиль
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    private function createToken(User $user): string
    {
        $ttlMinutes = (int) config('jwt.ttl', 60);
        $now = time();
        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'iat' => $now,
            'exp' => $now + ($ttlMinutes * 60),
            'jti' => bin2hex(random_bytes(16)),
            'role' => $user->role,
        ];

        return JWT::encode($payload, config('jwt.secret'), 'HS256');
    }

    private function getBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function decodeToken(string $token): ?array
    {
        try {
            $payload = JWT::decode($token, new \Firebase\JWT\Key(config('jwt.secret'), 'HS256'));
            return (array) $payload;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
