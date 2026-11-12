<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSchoolRequest;
use App\Http\Resources\SchoolResource;
use App\Models\School;
use App\Services\SchoolService;
use App\Support\ApiResponse;
use App\Support\SchoolContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $school = $this->currentSchool($request);

        abort_unless($request->user()->can('view', $school), 403);

        $school->loadCount(['users', 'students', 'teachers']);

        return ApiResponse::success(new SchoolResource($school), __('School profile.'));
    }

    public function update(UpdateSchoolRequest $request, SchoolService $schools): JsonResponse
    {
        $school = $this->currentSchool($request);

        $updated = $schools->update(
            $request->user(),
            $school,
            $request->validated(),
            $request->file('logo'),
        );

        return ApiResponse::success(new SchoolResource($updated), __('School profile updated.'));
    }

    private function currentSchool(Request $request): School
    {
        $schoolId = app(SchoolContext::class)->id() ?? $request->user()->school_id;

        abort_if($schoolId === null, 403, __('Your account is not linked to a school.'));

        return School::query()->findOrFail($schoolId);
    }
}
