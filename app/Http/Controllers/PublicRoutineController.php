<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FindsPublicSchool;
use App\Models\AcademicSession;
use App\Models\ClassRoutine;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PublicRoutineController extends Controller
{
    use FindsPublicSchool;

    public function index(Request $request): View
    {
        $school = $this->publicSchool();

        $data = $this->inPublicSchool($school, function () use ($request): array {
            $session = AcademicSession::query()
                ->where('is_current', true)
                ->orWhere('status', 'active')
                ->orderByDesc('is_current')
                ->orderByDesc('start_date')
                ->first();

            $classes = SchoolClass::query()
                ->where('is_active', true)
                ->orderBy('level')
                ->orderBy('name')
                ->get();

            $classId = (int) ($request->input('class_id') ?: $classes->first()?->id);
            $class = $classes->firstWhere('id', $classId);

            $sections = $class !== null ? $class->sections()->where('is_active', true)->orderBy('name')->get() : collect();
            $sectionId = (int) ($request->input('section_id') ?: $sections->first()?->id);
            $section = $sections->firstWhere('id', $sectionId);

            $routines = new Collection;

            if ($session !== null && $class !== null) {
                $query = ClassRoutine::query()
                    ->where('academic_session_id', $session->id)
                    ->where('class_id', $class->id)
                    ->with(['subject', 'teacher', 'section'])
                    ->orderBy('day_of_week')
                    ->orderBy('start_time');

                if ($section !== null) {
                    $query->where('section_id', $section->id);
                }

                $routines = $query->get();
            }

            return [
                'session' => $session,
                'classes' => $classes,
                'class' => $class,
                'sections' => $sections,
                'section' => $section,
                'routines' => $routines,
            ];
        });

        return view('public.routines.index', [
            'school' => $school,
            ...$data,
            'activeNav' => 'routines',
        ]);
    }
}
