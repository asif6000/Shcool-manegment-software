<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SchoolResource;
use App\Services\DashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): JsonResponse
    {
        abort_unless($request->user()->can('dashboard.view'), 403);

        $overview = $dashboard->overview($request->user());
        $school = $request->user()->school;

        return ApiResponse::success([
            'school' => $school !== null ? new SchoolResource($school) : null,
            'current_session' => $overview['session'] !== null ? [
                'id' => $overview['session']->id,
                'name' => $overview['session']->name,
                'start_date' => $overview['session']->start_date?->toDateString(),
                'end_date' => $overview['session']->end_date?->toDateString(),
            ] : null,
            'stats' => $overview['stats'],
            'growth' => $overview['growth'],
            'chart' => $overview['chart'],
            'activity' => $overview['activity']->map(fn ($log): array => [
                'id' => $log->id,
                'event' => $log->event,
                'description' => $log->description,
                'actor' => $log->user?->name,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->all(),
        ], __('Dashboard overview.'));
    }
}
