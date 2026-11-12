<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FindsPublicSchool;
use App\Services\WebsiteDataService;
use App\Support\SchoolContext;
use Illuminate\Http\JsonResponse;

/**
 * Live counters and notices for the public website. The page renders the same
 * data server-side first, so this endpoint only refreshes what a visitor is
 * already looking at.
 */
class PublicLiveController extends Controller
{
    use FindsPublicSchool;

    public function __invoke(SchoolContext $context, WebsiteDataService $website): JsonResponse
    {
        $school = $this->publicSchool();

        $payload = [
            'stats' => [
                'students' => 0,
                'teachers' => 0,
                'classes' => 0,
                'subjects' => 0,
            ],
            'notices' => [],
        ];

        if ($school !== null) {
            $payload = $context->run($school->id, fn (): array => [
                'stats' => $website->counts(),
                'notices' => $website->liveNotices()->values()->all(),
            ]);
        }

        return response()->json($payload + [
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
