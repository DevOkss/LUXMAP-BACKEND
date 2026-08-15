<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;

class InstituteController extends Controller
{
    public function index(): Response
    {
        $institutes = Institute::withCount('programs')->orderBy('name')->get();

        return Inertia::render('admin/institutes/Index', [
            'institutes' => $institutes,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/institutes/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $logoPath = $request->file('logo')
            ? $request->file('logo')->store('logos', 'public')
            : null;

        Institute::create([...$validated, 'logo_path' => $logoPath]);

        return to_route('admin.institutes.index')
            ->with('success', 'Institute created successfully.');
    }

    public function show(Institute $institute): Response
    {
        $institute->load('programs');

        return Inertia::render('admin/institutes/Show', [
            'institute' => $institute,
        ]);
    }

    public function edit(Institute $institute): Response
    {
        return Inertia::render('admin/institutes/Edit', [
            'institute' => $institute,
        ]);
    }

    public function update(Request $request, Institute $institute): RedirectResponse
    {
        $validated = $this->validated($request, $institute);

        if ($request->hasFile('logo')) {
            if ($institute->logo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($institute->logo_path);
            }

            $validated['logo_path'] = $request->file('logo')->store('logos', 'public');
        } elseif ($request->boolean('remove_logo') && $institute->logo_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($institute->logo_path);
            $validated['logo_path'] = null;
        }

        $institute->update($validated);

        return to_route('admin.institutes.show', $institute)
            ->with('success', 'Institute updated successfully.');
    }

    public function destroy(Institute $institute): RedirectResponse
    {
        if ($institute->logo_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($institute->logo_path);
        }

        $institute->delete();

        return to_route('admin.institutes.index')
            ->with('success', 'Institute deleted successfully.');
    }

    private function validated(Request $request, ?Institute $institute = null): array
    {
        $instituteId = $institute?->id;

        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('institutes', 'code')->ignore($instituteId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', File::image()->max(2048)],
            'remove_logo' => ['sometimes', 'boolean'],
            'is_active' => ['boolean'],
        ]);
    }
}
