<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !$user->is_active) {
            abort(403, 'No autorizado.');
        }

        if (!empty($roles) && !$user->hasAnyRole($roles)) {
            abort(403, 'No tienes permiso para esta acción.');
        }

        return $next($request);
    }
}