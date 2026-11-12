<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Services\ProfilePhotoService;
use App\Services\TeacherWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The teacher's own workspace screens. What is on the timetable is what is on
 * these pages, so an admin who opens them sees the whole school and a teacher
 * with no periods sees an honest empty state rather than someone else's classes.
 */
class TeacherWorkspaceController extends Controller
{
    private const PHOTO_PROFILE = 'teacher_photo';

    public function __construct(
        private readonly TeacherWorkspaceService $workspace,
        private readonly ProfilePhotoService $photos,
    ) {}

    public function routine(Request $request): View
    {
        $this->requirePermission('timetable.view');

        $actor = $request->user();
        $date = $this->dateOrToday($request->query('date'));
        $mode = $request->query('mode') === 'day' ? 'day' : 'week';

        $data = $mode === 'day'
            ? $this->workspace->day($actor, $date)
            : $this->workspace->week($actor, $date);

        return view('teacher.routine', array_merge($data, [
            'mode' => $mode,
            'date' => $date,
        ]));
    }

    public function classes(Request $request): View
    {
        $this->requirePermission('timetable.view');

        return view('teacher.classes', $this->workspace->assignments($request->user()));
    }

    /**
     * The teacher's own profile. Unlike the other workspace screens it needs no
     * timetable permission, because it only ever shows and changes the record
     * that belongs to the signed-in account.
     */
    public function profile(Request $request): View|RedirectResponse
    {
        $teacher = $this->ownTeacher($request);

        if (! $teacher instanceof Teacher) {
            return $teacher;
        }

        $user = $request->user();

        return view('teacher.profile', [
            'teacher' => $teacher,
            'shellSchool' => $user->school,
            'shellUser' => $user,
            'shellUnreadCount' => $user->unreadNotifications()->count(),
            'photoMaxKb' => $this->photos->maxKb(self::PHOTO_PROFILE),
            'photoAccept' => $this->photos->acceptedFormats(self::PHOTO_PROFILE),
        ]);
    }

    public function storePhoto(Request $request): RedirectResponse|JsonResponse
    {
        $teacher = $this->ownTeacher($request);

        if (! $teacher instanceof Teacher) {
            return $teacher;
        }

        $request->validate($this->photos->rules(self::PHOTO_PROFILE), $this->photos->messages(self::PHOTO_PROFILE));

        $this->photos->replace($request->user(), $teacher, $request->file('image'), self::PHOTO_PROFILE);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('Your profile photo has been updated.'),
                'photo' => [
                    'url' => route('teacher.profile.photo', ['v' => $teacher->photoVersion()]),
                    'version' => $teacher->photoVersion(),
                ],
            ]);
        }

        return back()->with('success', __('Your profile photo has been updated.'));
    }

    public function destroyPhoto(Request $request): RedirectResponse
    {
        $teacher = $this->ownTeacher($request);

        if (! $teacher instanceof Teacher) {
            return $teacher;
        }

        $this->photos->remove($request->user(), $teacher, self::PHOTO_PROFILE);

        return back()->with('success', __('Your profile photo has been removed.'));
    }

    /**
     * Streams the signed-in teacher's own picture. The staff-screen route needs
     * the staff.view permission, which a teacher does not hold, so the portal
     * avatar has to come from a route that is scoped to the owner alone.
     */
    public function photo(Request $request): StreamedResponse
    {
        $teacher = $request->user()->teacher()->firstOrFail();

        abort_if($teacher->photo_path === null || ! str_starts_with($teacher->photo_path, 'teachers/'), 404);

        $disk = Storage::disk('school_uploads');
        abort_unless($disk->exists($teacher->photo_path), 404);

        return $disk->response($teacher->photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return Teacher|RedirectResponse
     */
    private function ownTeacher(Request $request): Teacher|RedirectResponse
    {
        $teacher = $request->user()->teacher;

        if ($teacher === null) {
            return redirect()
                ->route('profile.edit')
                ->with('info', __('This account is not linked to a teacher record, so it has no teacher profile.'));
        }

        return $teacher;
    }
}
