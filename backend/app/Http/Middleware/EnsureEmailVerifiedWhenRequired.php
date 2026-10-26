<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerifiedWhenRequired
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('pgaev.require_verification')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            abort(403, 'Debes verificar tu correo electrónico.');
        }

        return $next($request);
    }
}
