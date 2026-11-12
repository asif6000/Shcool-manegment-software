<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\Setting;
use App\Support\AuditLogger;
use App\Support\FileUploadService;
use App\Support\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeBannerController extends Controller
{
    public function __construct(
        private readonly FileUploadService $uploads,
        private readonly AuditLogger $audit,
    ) {}

    public function edit(): View
    {
        $this->requirePermission('school.update');

        return view('banner.edit', [
            'school' => $this->school(),
            'bannerUrl' => $this->uploads->urlIfExists($this->currentPath(), 'hero_banner'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->requirePermission('school.update');

        $school = $this->school();
        $profile = config('school.uploads.hero_banner');

        $request->validate([
            'banner' => [
                'required',
                'image',
                'mimes:'.implode(',', $profile['mimes']),
                'max:'.$profile['max_kb'],
            ],
        ]);

        $oldPath = $this->currentPath();
        $newPath = $this->uploads->store($request->file('banner'), 'hero_banner', (string) $school->id);

        try {
            Setting::query()->updateOrCreate(
                ['key' => 'hero_image'],
                ['group_name' => 'site', 'value' => $newPath, 'type' => 'string'],
            );
        } catch (\Throwable $exception) {
            $this->uploads->delete($newPath, 'hero_banner');

            throw $exception;
        }

        $this->uploads->delete($oldPath, 'hero_banner');

        $this->audit->log(
            event: 'school.banner_updated',
            auditable: $school,
            description: __('Homepage banner was updated'),
            oldValues: ['hero_image' => $oldPath],
            newValues: ['hero_image' => $newPath],
            schoolId: (int) $school->id,
        );

        return redirect()
            ->route('banner.edit')
            ->with('success', __('The homepage banner has been updated.'));
    }

    public function destroy(): RedirectResponse
    {
        $this->requirePermission('school.update');

        $school = $this->school();
        $path = $this->currentPath();

        Setting::query()->where('key', 'hero_image')->delete();
        $this->uploads->delete($path, 'hero_banner');

        $this->audit->log(
            event: 'school.banner_removed',
            auditable: $school,
            description: __('Homepage banner was removed'),
            oldValues: ['hero_image' => $path],
            schoolId: (int) $school->id,
        );

        return redirect()
            ->route('banner.edit')
            ->with('success', __('The homepage banner was removed. The default hero images are back.'));
    }

    private function currentPath(): ?string
    {
        $value = Setting::query()->where('key', 'hero_image')->value('value');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function school(): School
    {
        $schoolId = app(SchoolContext::class)->id() ?? auth()->user()?->school_id;

        abort_if($schoolId === null, 403, __('Your account is not linked to a school.'));

        return School::query()->findOrFail($schoolId);
    }
}
