<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next)
    {
        // Set Accept header to application/json
        $request->headers->set('Accept', 'application/json');
        
        // If the request has JSON content type, ensure it's parsed as JSON
        if ($request->header('Content-Type') === 'application/json') {
            $request->headers->set('Content-Type', 'application/json');
        }
        
        return $next($request);
    }
}
