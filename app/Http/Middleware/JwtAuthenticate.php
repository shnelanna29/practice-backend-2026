<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class JwtAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->getBearerToken($request);
        if (!$token) {
            return response()->json(['message' => 'Неавторизован'], 401);
        }

        $secret = config('jwt.secret');
        if (!$secret) {
            return response()->json(['message' => 'JWT secret is not configured'], 500);
        }

        try {
            $payload = JWT::decode($token, new Key($secret, 'HS256'));
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Недействительный токен'], 401);
        }

        $jti = $payload->jti ?? null;
        if ($jti && Cache::has("jwt:blacklist:{$jti}")) {
            return response()->json(['message' => 'Токен отозван'], 401);
        }

        $userId = $payload->sub ?? null;
        if (!$userId) {
            return response()->json(['message' => 'Недействительный токен'], 401);
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'Пользователь не найден'], 401);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function getBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
