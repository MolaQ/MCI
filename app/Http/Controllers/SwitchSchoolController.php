<?php

namespace App\Http\Controllers;

use App\Support\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SwitchSchoolController extends Controller
{
    public function __invoke(Request $request, CurrentSchool $current): RedirectResponse
    {
        $schoolId = (int) $request->input('school_id');
        $school = $current->availableFor($request->user())->firstWhere('id', $schoolId);

        abort_unless($school, 403);

        $request->session()->put(CurrentSchool::SESSION_KEY, $school->id);
        $request->user()->forceFill(['last_school_id' => $school->id])->saveQuietly();

        return redirect()->route('dashboard')->with('status', __('Aktywna szkoła: :name', ['name' => $school->name]));
    }
}
