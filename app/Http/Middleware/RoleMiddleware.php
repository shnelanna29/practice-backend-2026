<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // Проверяем, авторизован ли пользователь
        if (!$request->user()) {
            return response()->json(['message' => 'Неавторизован'], 401);
        }

        // Проверяем роль
        if ($request->user()->role !== $role) {
            return response()->json(['message' => 'Доступ запрещен. Недостаточно прав.'], 403);
        }

        return $next($request);
    }
}