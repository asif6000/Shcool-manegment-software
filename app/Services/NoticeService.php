<?php

namespace App\Services;

use App\Models\Notice;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class NoticeService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PermanentDeleteService $deletes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Notice
    {
        $notice = DB::transaction(function () use ($actor, $data): Notice {
            $notice = Notice::query()->create($this->attributes($data) + ['created_by' => $actor->id]);

            $this->audit->log(
                event: 'notice.created',
                auditable: $notice,
                description: __('Notice :title was created', ['title' => $notice->title]),
                newValues: $notice->only(['title', 'audience', 'published_at', 'expires_at', 'is_pinned']),
                actor: $actor,
            );

            return $notice;
        });

        $this->announce($actor, $notice, $this->shouldNotify($data));

        return $notice;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Notice $notice, array $data): Notice
    {
        $updated = DB::transaction(function () use ($actor, $notice, $data): Notice {
            $old = $notice->only(['title', 'body', 'audience', 'published_at', 'expires_at', 'is_pinned']);
            $notice->update($this->attributes($data));
            $notice->refresh();

            $this->audit->log(
                event: 'notice.updated',
                auditable: $notice,
                description: __('Notice :title was updated', ['title' => $notice->title]),
                oldValues: Arr::except($old, ['body']),
                newValues: $notice->only(['title', 'audience', 'published_at', 'expires_at', 'is_pinned']),
                actor: $actor,
            );

            return $notice;
        });

        $this->announce($actor, $updated, $this->shouldNotify($data));

        return $updated;
    }

    /**
     * Make the notice visible on the website and in the audience inbox now.
     */
    public function publish(User $actor, Notice $notice, bool $notify = true): Notice
    {
        $published = DB::transaction(function () use ($actor, $notice): Notice {
            $old = $notice->only(['published_at', 'expires_at']);
            $scheduled = $notice->published_at;

            // Keep an earlier scheduled timestamp so re-publishing does not
            // silently move the notice to a new date. An expiry that has
            // already passed would keep hiding the notice the admin just
            // re-published, so it is cleared.
            $notice->forceFill([
                'published_at' => $scheduled !== null && $scheduled->isPast() ? $scheduled : now(),
                'expires_at' => $notice->expires_at !== null && $notice->expires_at->isPast() ? null : $notice->expires_at,
            ])->save();

            $this->audit->log(
                event: 'notice.published',
                auditable: $notice,
                description: __('Notice :title was published', ['title' => $notice->title]),
                oldValues: $old,
                newValues: $notice->only(['published_at', 'expires_at']),
                actor: $actor,
            );

            return $notice->refresh();
        });

        $this->announce($actor, $published, $notify, onlyWhenPublished: true);

        return $published;
    }

    public function unpublish(User $actor, Notice $notice): Notice
    {
        return DB::transaction(function () use ($actor, $notice): Notice {
            $old = $notice->only(['published_at']);
            $notice->forceFill(['published_at' => null])->save();

            $this->audit->log(
                event: 'notice.unpublished',
                auditable: $notice,
                description: __('Notice :title was moved back to draft', ['title' => $notice->title]),
                oldValues: $old,
                actor: $actor,
            );

            return $notice->refresh();
        });
    }

    public function togglePin(User $actor, Notice $notice): Notice
    {
        return DB::transaction(function () use ($actor, $notice): Notice {
            $notice->forceFill(['is_pinned' => ! $notice->is_pinned])->save();

            $this->audit->log(
                event: 'notice.pinned_toggled',
                auditable: $notice,
                description: __('Notice :title pin state changed', ['title' => $notice->title]),
                newValues: $notice->only(['is_pinned']),
                actor: $actor,
            );

            return $notice->refresh();
        });
    }

    public function delete(User $actor, Notice $notice): void
    {
        $this->deletes->delete($notice, [
            'label' => __('notice'),
            'description' => __('Notice #:id was permanently deleted.', ['id' => $notice->getKey()]),
            'actor' => $actor,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'academic_session_id' => $data['academic_session_id'] ?? null,
            'title' => trim((string) $data['title']),
            'body' => trim((string) $data['body']),
            'audience' => (string) $data['audience'],
            'published_at' => $this->moment($data['published_at'] ?? null),
            'expires_at' => $this->moment($data['expires_at'] ?? null),
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
        ];
    }

    private function moment(mixed $value): ?Carbon
    {
        return is_string($value) && filled($value) ? Carbon::parse($value) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function shouldNotify(array $data): bool
    {
        return (bool) ($data['notify_audience'] ?? false);
    }

    /**
     * Notify the audience after the record is committed. A notification failure
     * must never undo a publish, so failures are reported and swallowed.
     */
    private function announce(User $actor, Notice $notice, bool $notify, bool $onlyWhenPublished = false): void
    {
        if (! $notify || ($onlyWhenPublished && $notice->published_at === null)) {
            return;
        }

        if ($notice->published_at !== null && $notice->published_at->isFuture()) {
            return;
        }

        try {
            app(SchoolEventNotifier::class)->noticePublished($notice, $actor);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
