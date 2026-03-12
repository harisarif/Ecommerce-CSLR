<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthenticate
{
    public function handle($request, Closure $next, $guard = null)
    {

            // DEBUG ONLY
    \Log::info('AUTH HEADER', [
        'authorization' => $request->header('Authorization')
    ]);
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $request->setUserResolver(fn() => $user);
            auth()->setUser($user);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
