<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Garden;

class GardenFilter
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        // If user is a garden type, get their garden and add garden_id to request
        if ($user && $user->type === 'garden') {
            $garden = Garden::where('email', $user->email)->first();
            
            if ($garden) {
                // Add garden_id to the request for filtering
                $request->merge(['garden_id' => $garden->id]);
                
                // Also add it to the query parameters if not already present
                if (!$request->has('garden_id')) {
                    $request->query->set('garden_id', $garden->id);
                }
            }
        }
        
        return $next($request);
    }
}
