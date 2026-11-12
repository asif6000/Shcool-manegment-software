<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FindsPublicSchool;
use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\Teacher;
use App\Services\WebsiteDataService;
use App\Support\FileUploadService;
use App\Support\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LandingController extends Controller
{
    use FindsPublicSchool;

    public function __invoke(SchoolContext $context): RedirectResponse|View
    {
        if (auth()->check()) {
            return redirect()->route(auth()->user()->homeRouteName());
        }

        $school = $this->publicSchool();
        $website = app(WebsiteDataService::class);

        $stats = [
            'students' => 0,
            'teachers' => 0,
            'classes' => 0,
            'subjects' => 0,
        ];
        $notices = new Collection;
        $tickerNotices = new Collection;
        $exams = new Collection;
        $sessions = new Collection;
        $subjects = new Collection;
        $videos = new Collection;
        $teachers = new Collection;
        $classes = new Collection;
        $heroImage = null;

        if ($school !== null) {
            $context->run($school->id, function () use (&$stats, &$notices, &$tickerNotices, &$exams, &$sessions, &$subjects, &$videos, &$teachers, &$classes, &$heroImage, $website): void {
                $stats = $website->counts();
                $subjects = $website->subjects();

                $teachers = Teacher::query()
                    ->where('status', 'active')
                    ->orderBy('designation')
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->limit(4)
                    ->get();

                $classes = SchoolClass::query()
                    ->where('is_active', true)
                    ->with(['sections' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
                    ->orderBy('level')
                    ->orderBy('name')
                    ->limit(12)
                    ->get();

                // One read feeds both the ticker and the board panel; the ticker
                // shows more notices than the panel has room for.
                $tickerNotices = $website->liveNotices(8);
                $notices = $tickerNotices->take(4);

                $exams = Exam::query()
                    ->where('status', 'published')
                    ->orderByDesc('published_at')
                    ->orderByDesc('end_date')
                    ->limit(12)
                    ->get();

                $sessions = AcademicSession::query()
                    ->where('status', 'active')
                    ->orderByDesc('start_date')
                    ->limit(12)
                    ->get();

                $videos = $website->videos();

                $heroPath = Setting::value('hero_image');
                $heroImage = app(FileUploadService::class)
                    ->urlIfExists(is_string($heroPath) ? $heroPath : null, 'hero_banner');
            });
        }

        return view('landing', [
            'school' => $school,
            'stats' => $stats,
            'notices' => $notices,
            'tickerNotices' => $tickerNotices,
            'exams' => $exams,
            'sessions' => $sessions,
            'subjects' => $subjects,
            'videos' => $videos,
            'teachers' => $teachers,
            'classes' => $classes,
            'heroImage' => $heroImage,
        ]);
    }
}
