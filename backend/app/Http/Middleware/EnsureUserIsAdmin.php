<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->role !== User::ROLE_ADMIN) {
            abort(403, 'Solo los administradores pueden acceder a esta sección.');
        }

        return $next($request);
    }
}
