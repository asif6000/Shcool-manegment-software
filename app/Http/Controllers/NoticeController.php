<?php

namespace App\Http\Controllers;

use App\Http\Requests\Notices\SaveNoticeRequest;
use App\Models\AcademicSession;
use App\Models\Notice;
use App\Services\NoticeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class NoticeController extends Controller
{
    public function __construct(private readonly NoticeService $notices) {}

    public function index(Request $request): View
    {
        $this->requirePermission('notices.view');

        $filters = $request->only(['status', 'audience', 'q']);

        $records = Notice::query()
            ->with('academicSession')
            ->when(filled($filters['q'] ?? null), function ($query) use ($filters): void {
                $like = '%'.$filters['q'].'%';
                $query->where(fn ($scope): mixed => $scope->where('title', 'like', $like)->orWhere('body', 'like', $like));
            })
            ->when(($filters['audience'] ?? null) !== null && in_array($filters['audience'], Notice::AUDIENCES, true),
                fn ($query): mixed => $query->where('audience', $filters['audience']))
            ->when(($filters['status'] ?? null) === 'draft', fn ($query): mixed => $query->whereNull('published_at'))
            ->when(($filters['status'] ?? null) === 'scheduled',
                fn ($query): mixed => $query->whereNotNull('published_at')->where('published_at', '>', now()))
            ->when(($filters['status'] ?? null) === 'published', fn ($query): mixed => $query->published())
            ->when(($filters['status'] ?? null) === 'expired',
                fn ($query): mixed => $query->whereNotNull('expires_at')->where('expires_at', '<=', now()))
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate((int) config('school.pagination.per_page', 15))
            ->withQueryString();

        return view('notices.index', [
            'notices' => $records,
            'filters' => $filters,
            'websiteCount' => $this->websiteCount(),
        ]);
    }

    public function create(): View
    {
        $this->requirePermission('notices.manage');

        return view('notices.form', [
            'notice' => new Notice(['audience' => 'all', 'is_pinned' => false]),
            'sessions' => $this->sessions(),
        ]);
    }

    public function store(SaveNoticeRequest $request): RedirectResponse
    {
        $notice = $this->notices->create($request->user(), $request->validated());

        return redirect()
            ->route('notices.edit', $notice)
            ->with('success', $this->savedMessage($notice));
    }

    public function edit(Notice $notice): View
    {
        $this->requirePermission('notices.manage');

        return view('notices.form', [
            'notice' => $notice,
            'sessions' => $this->sessions(),
        ]);
    }

    public function update(SaveNoticeRequest $request, Notice $notice): RedirectResponse
    {
        $updated = $this->notices->update($request->user(), $notice, $request->validated());

        return redirect()
            ->route('notices.index')
            ->with('success', $this->savedMessage($updated));
    }

    public function publish(Request $request, Notice $notice): RedirectResponse
    {
        $this->requirePermission('notices.manage');

        $notify = $request->boolean('notify_audience');
        $published = $this->notices->publish($request->user(), $notice, $notify);

        return back()->with('success', $published->isVisibleOnWebsite()
            ? __('The notice is live on the website.')
            : __('The notice is live in the :audience inbox.', ['audience' => $published->audienceLabel()]));
    }

    public function unpublish(Request $request, Notice $notice): RedirectResponse
    {
        $this->requirePermission('notices.manage');

        $this->notices->unpublish($request->user(), $notice);

        return back()->with('success', __('The notice is back in draft and hidden from the website.'));
    }

    public function togglePin(Request $request, Notice $notice): RedirectResponse
    {
        $this->requirePermission('notices.manage');

        $pinned = $this->notices->togglePin($request->user(), $notice);

        return back()->with('success', $pinned->is_pinned
            ? __('The notice is pinned to the top.')
            : __('The notice is no longer pinned.'));
    }

    public function destroy(Request $request, Notice $notice): RedirectResponse
    {
        $this->requirePermission('notices.manage');

        $this->notices->delete($request->user(), $notice);

        return redirect()->route('notices.index')->with('success', __('The notice was deleted.'));
    }

    /**
     * Notices currently visible to visitors on the public website.
     */
    private function websiteCount(): int
    {
        return Notice::query()->published()->public()->count();
    }

    /**
     * @return Collection<int, AcademicSession>
     */
    private function sessions(): Collection
    {
        return AcademicSession::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name']);
    }

    private function savedMessage(Notice $notice): string
    {
        if ($notice->published_at === null) {
            return __('The notice was saved as a draft.');
        }

        if ($notice->published_at->isFuture()) {
            return __('The notice is scheduled for :date.', [
                'date' => $notice->published_at->format('d M Y, g:i a'),
            ]);
        }

        return $notice->isVisibleOnWebsite()
            ? __('The notice is live on the website.')
            : __('The notice is live in the :audience inbox.', ['audience' => $notice->audienceLabel()]);
    }
}
