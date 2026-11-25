<?php

namespace App\Http\Middleware;

use App\Enums\Feature;
use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard: `->middleware('feature:events')`. A switched-off feature
 * answers 404, as if the page did not exist (the active school applies).
 */
class EnsureFeatureEnabled
{
    public function __construct(private Features $features) {}

    public function handle(Request $request, Closure $next, string ...$keys): Response
    {
        foreach ($keys as $key) {
            abort_if($this->features->disabled(Feature::from($key)), 404);
        }

        return $next($request);
    }
}
