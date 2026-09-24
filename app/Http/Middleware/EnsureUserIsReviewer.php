<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsReviewer
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null || ! $request->user()->isReviewer()) {
            abort(403);
        }

        return $next($request);
    }
}
