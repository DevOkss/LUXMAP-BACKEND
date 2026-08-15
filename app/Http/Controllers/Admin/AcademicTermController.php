<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AcademicTermRequest;
use App\Models\AcademicTerm;
use App\Services\AcademicTermService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademicTermController extends Controller
{
    public function __construct(
        private AcademicTermService $termService
    ) {}

    public function index(): Response
    {
        $terms = AcademicTerm::query()
            ->withCount('enrollments')
            ->orderByDesc('academic_year')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AcademicTerm $term) => [
                'id' => $term->id,
                'academic_year' => $term->academic_year,
                'semester' => $term->semester,
                'start_date' => $term->start_date?->format('Y-m-d'),
                'end_date' => $term->end_date?->format('Y-m-d'),
                'is_active' => $term->is_active,
                'enrollments_count' => $term->enrollments_count,
            ]);

        return Inertia::render('admin/academic-terms/Index', [
            'terms' => $terms,
        ]);
    }

    public function store(AcademicTermRequest $request): RedirectResponse
    {
        if (AcademicTerm::forYearSemester($request->input('academic_year'), $request->input('semester'))->exists()) {
            return redirect()->route('admin.academic-terms.index')
                ->with('error', 'That academic term already exists.');
        }

        $term = AcademicTerm::create([
            'academic_year' => $request->input('academic_year'),
            'semester' => $request->input('semester'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'is_active' => AcademicTerm::active()->doesntExist(),
        ]);

        return redirect()->route('admin.academic-terms.index')
            ->with('success', "{$term->displayName()} added" . ($term->is_active ? ' and set as the current term.' : '.'));
    }

    public function activate(Request $request, AcademicTerm $term): RedirectResponse
    {
        $this->termService->activate($term);

        return redirect()->route('admin.academic-terms.index')
            ->with('success', "{$term->displayName()} is now the current term.");
    }
}