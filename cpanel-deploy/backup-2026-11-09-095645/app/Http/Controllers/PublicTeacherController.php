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
        ]);
    }
}
