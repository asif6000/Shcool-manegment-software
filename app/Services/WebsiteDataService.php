<?php

namespace App\Services;

use App\Models\ClassVideo;
use App\Models\Notice;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Support\Collection;

/**
 * Shared reads for the public website so the first page load and the live
 * polling endpoint always report the same numbers.
 */
class WebsiteDataService
{
    private const BN_MONTHS = [
        1 => 'জানু', 2 => 'ফেব্রু', 3 => 'মার্চ', 4 => 'এপ্রি', 5 => 'মে', 6 => 'জুন',
        7 => 'জুলা', 8 => 'আগ', 9 => 'সেপ্টে', 10 => 'অক্টো', 11 => 'নভে', 12 => 'ডিসে',
    ];

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            'students' => Student::query()->where('status', 'active')->count(),
            'teachers' => Teacher::query()->where('status', 'active')->count(),
            'classes' => SchoolClass::query()->where('is_active', true)->count(),
            'subjects' => Subject::query()->where('is_active', true)->count(),
        ];
    }

    /**
     * Active subjects as the website lists them.
     *
     * @return Collection<int, array{name: string, code: string|null}>
     */
    public function subjects(int $limit = 40): Collection
    {
        return Subject::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Subject $subject): array => [
                'name' => $subject->name,
                'code' => $subject->code,
            ]);
    }

    /**
     * Notices as the website renders them, so a first page load and a live
     * poll produce identical cards.
     *
     * @return Collection<int, array<string, string|int>>
     */
    public function liveNotices(int $limit = 4): Collection
    {
        return $this->noticeCards(
            Notice::query()
                ->published()
                ->public()
                ->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get()
        );
    }

    /**
     * The videos the school has published for its classes, newest first. Only
     * rows that actually have something to play are returned, so the home page
     * never shows a card that leads nowhere.
     *
     * @return Collection<int, ClassVideo>
     */
    public function videos(int $limit = 12): Collection
    {
        return ClassVideo::query()
            ->onWebsite()
            ->with(['schoolClass', 'section', 'subject'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->filter(fn (ClassVideo $video): bool => $video->playerUrl() !== null)
            ->values();
    }

    /**
     * @param  Collection<int, Notice>  $notices
     * @return Collection<int, array<string, string|int>>
     */
    public function noticeCards(Collection $notices): Collection
    {
        return $notices->map(fn (Notice $notice): array => [
            'id' => $notice->id,
            'title' => $notice->title,
            'url' => route('public.notices.show', $notice->id),
            'day' => (int) ($notice->published_at ?? $notice->created_at)->format('j'),
            'month' => self::BN_MONTHS[(int) ($notice->published_at ?? $notice->created_at)->month] ?? '',
            'ago' => ($notice->published_at ?? $notice->created_at)->locale('bn')->diffForHumans(),
            'pinned' => (bool) $notice->is_pinned,
        ]);
    }
}
