<?php

namespace App\Http\Middleware;

use App\Support\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentSchool
{
    public function __construct(private CurrentSchool $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $school = $this->current->resolveFor($user, $request->session()->get(CurrentSchool::SESSION_KEY));
            $this->current->set($school, $user);

            if ($school && $request->session()->get(CurrentSchool::SESSION_KEY) !== $school->id) {
                $request->session()->put(CurrentSchool::SESSION_KEY, $school->id);
            }
        }

        return $next($request);
    }
}
