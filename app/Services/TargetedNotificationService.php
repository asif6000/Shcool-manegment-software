<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SchoolEventNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TargetedNotificationService
{
    public const AUDIENCES = ['students', 'teachers', 'staff'];

    public const STAFF_ROLES = ['school_admin', 'principal', 'accountant', 'teacher'];

    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function send(User $actor, array $data): int
    {
        $campaignId = (string) $data['campaign_id'];
        $title = trim((string) $data['title']);
        $message = trim((string) $data['message']);
        $recipientIds = array_map('intval', (array) ($data['recipient_ids'] ?? []));
        $sent = 0;

        $this->candidates(
            $actor,
            (string) $data['audience'],
            (string) $data['recipient_scope'],
            $recipientIds,
        )->chunkById(100, function (Collection $users) use (&$sent, $actor, $campaignId, $title, $message): void {
            foreach ($users as $user) {
                $record = $this->notifications->send($user, new SchoolEventNotification(
                    title: $title,
                    message: $message,
                    url: route('notifications.index'),
                    icon: 'bi-megaphone',
                    category: 'general',
                    dedupeKey: 'targeted:'.$campaignId.':'.$user->id,
                    pushTitle: __('New message from the school office'),
                    pushBody: __('Sign in to read the message.'),
                ));

                if ($record !== null) {
                    $sent++;
                }
            }
        });

        return $sent;
    }

    /**
     * @return array{students: Collection<int, User>, teachers: Collection<int, User>, staff: Collection<int, User>}
     */
    public function options(User $actor): array
    {
        return [
            'students' => $this->candidates($actor, 'students', 'all', [])
                ->with('student:id,user_id,student_id,first_name,last_name')
                ->orderBy('name')
                ->get(),
            'teachers' => $this->candidates($actor, 'teachers', 'all', [])
                ->with('teacher:id,user_id,first_name,last_name')
                ->orderBy('name')
                ->get(),
            'staff' => $this->candidates($actor, 'staff', 'all', [])
                ->with([
                    'teacher:id,user_id,first_name,last_name',
                    'employee:id,user_id,first_name,last_name',
                ])
                ->orderBy('name')
                ->get(),
        ];
    }

    /**
     * @param  list<int>  $recipientIds
     * @return Builder<User>
     */
    private function candidates(User $actor, string $audience, string $scope, array $recipientIds): Builder
    {
        $query = User::query()
            ->where('school_id', $actor->school_id)
            ->where('is_active', true)
            ->whereKeyNot($actor->id);

        $query = match ($audience) {
            'students' => $query->role('student')->whereHas('student'),
            'teachers' => $query->role('teacher')->whereHas('teacher'),
            'staff' => $query->role(self::STAFF_ROLES),
            default => $query->whereRaw('1 = 0'),
        };

        if ($scope === 'selected') {
            $query->whereIn('id', $recipientIds !== [] ? $recipientIds : [0]);
        }

        return $query;
    }
}
