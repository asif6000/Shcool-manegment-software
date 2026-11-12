<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FindsPublicSchool;
use App\Models\Notice;
use Illuminate\View\View;

class PublicNoticeController extends Controller
{
    use FindsPublicSchool;

    public function index(): View
    {
        $school = $this->publicSchool();

        $notices = $this->inPublicSchool($school, fn (): mixed => Notice::query()
            ->published()
            ->public()
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->paginate(10)
            ->withQueryString());

        return view('public.notices.index', [
            'school' => $school,
            'notices' => $notices,
            'activeNav' => 'notices',
            // SEO data
            'seoTitle' => 'নোটিস বোর্ড - Dream Roots Millennium School',
            'seoDescription' => 'Latest notices and announcements from Dream Roots Millennium School, Kushtia. Stay updated with school news, events, and important information.',
        ]);
    }

    public function show(string $notice): View
    {
        $school = $this->publicSchool();

        $record = $this->inPublicSchool($school, function () use ($notice): ?Notice {
            $record = Notice::query()->whereKey((int) $notice)->first();

            if ($record === null || $record->published_at === null || $record->published_at->isFuture()) {
                return null;
            }

            if ($record->expires_at !== null && $record->expires_at->isPast()) {
                return null;
            }

            if (! in_array($record->audience, ['all', 'students', 'guardians'], true)) {
                return null;
            }

            return $record;
        });

        if ($record === null) {
            abort(404);
        }

        return view('public.notices.show', [
            'school' => $school,
            'notice' => $record,
            'activeNav' => 'notices',
            // SEO data for this specific notice
            'seoTitle' => $record->title . ' - Dream Roots Millennium School',
            'seoDescription' => $record->body ?? 'Important notice from Dream Roots Millennium School.',
            'seoCanonical' => route('public.notices.show', $record->id),
        ]);
    }
}
