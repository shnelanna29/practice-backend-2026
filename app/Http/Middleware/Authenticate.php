<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    protected function unauthenticated(Request $request, array $guards): void
    {
        if ($request->expectsJson()) {
            abort(response()->json(['message' => 'Unauthenticated.'], 401));
        }

        parent::unauthenticated($request, $guards);
    }
}