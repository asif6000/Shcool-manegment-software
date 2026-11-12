<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FindsPublicSchool;
use App\Models\Teacher;
use Illuminate\View\View;

class PublicTeacherController extends Controller
{
    use FindsPublicSchool;

    public function index(): View
    {
        $school = $this->publicSchool();

        $teachers = $this->inPublicSchool($school, fn (): mixed => Teacher::query()
            ->where('status', 'active')
            ->orderBy('designation')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get());

        return view('public.teachers.index', [
            'school' => $school,
            'teachers' => $teachers,
            'activeNav' => 'teachers',
            // SEO data
            'seoTitle' => 'শিক্ষকবৃন্দ - Dream Roots Millennium School',
            'seoDescription' => 'Meet our experienced teaching faculty at Dream Roots Millennium School, Kushtia. Qualified and dedicated teachers committed to student success.',
        ]);
    }

    public function show(string $teacher): View
    {
        $school = $this->publicSchool();

        $record = $this->inPublicSchool($school, function () use ($teacher): ?Teacher {
            return Teacher::query()
                ->whereKey((int) $teacher)
                ->where('status', 'active')
                ->with(['assignments.schoolClass', 'assignments.subject'])
                ->first();
        });

        if ($record === null) {
            abort(404);
        }

        return view('public.teachers.show', [
            'school' => $school,
            'teacher' => $record,
            'activeNav' => 'teachers',
            'seoTitle' => "{$record->full_name} ({$record->designation}) - শিক্ষক পরিচিতি",
            'seoDescription' => "Teacher Profile of {$record->full_name}, {$record->designation} at Dream Roots Millennium School.",
        ]);
    }
}
