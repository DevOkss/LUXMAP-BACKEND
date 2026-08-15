<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institute;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProgramController extends Controller
{
    public function store(Request $request, Institute $institute): RedirectResponse
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('programs', 'code')->where('institute_id', $institute->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $institute->programs()->create($validated);

        return back()->with('success', 'Program added successfully.');
    }

    public function update(Request $request, Program $program): RedirectResponse
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('programs', 'code')
                    ->where('institute_id', $program->institute_id)
                    ->ignore($program->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $program->update($validated);

        return back()->with('success', 'Program updated successfully.');
    }

    public function destroy(Program $program): RedirectResponse
    {
        $program->delete();

        return back()->with('success', 'Program deleted successfully.');
    }
}
