<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class VerifiedMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || is_null($user->email_verified_at)) {
            return ApiResponse::error('Please verify your email address first.', 403);
        }

        return $next($request);
    }
}
