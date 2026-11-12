<?php

namespace App\Services;

use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Student;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class HomeworkService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly FileUploadService $uploads,
        private readonly PermanentDeleteService $deletes,
        private readonly TeachingScope $teaching,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data, ?UploadedFile $attachment = null): Homework
    {
        $publish = filter_var($data['publish'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $homework = DB::transaction(function () use ($actor, $data, $attachment, $publish): Homework {
            $homework = Homework::query()->create(
                $this->attributes($actor, $data)
                    + ['status' => $publish ? Homework::PUBLISHED : Homework::DRAFT]
                    + ['created_by' => $actor->id]
            );

            if ($publish) {
                $homework->forceFill(['published_at' => now()])->save();
            }

            if ($attachment !== null) {
                $homework->forceFill([
                    'attachment_path' => $this->uploads->storeDocument($attachment, 'homework_attachment', (string) $homework->school_id),
                ])->save();
            }

            $this->audit->log(
                event: 'homework.created',
                auditable: $homework,
                description: __('Homework :title was assigned', ['title' => $homework->title]),
                newValues: $homework->only(['title', 'class_id', 'section_id', 'subject_id', 'due_date', 'status']),
                actor: $actor,
            );

            return $homework;
        });

        if ($publish) {
            $this->announce($homework);
        }

        return $homework;
    }

    /**
     * A draft becomes visible to the class only here, which is also the moment
     * the notification goes out. A cancelled assignment cannot be republished.
     */
    public function publish(User $actor, Homework $homework): Homework
    {
        if ($homework->status === Homework::CANCELLED) {
            throw ValidationException::withMessages([
                'status' => __('Cancelled homework cannot be published again.'),
            ]);
        }

        if ($homework->status === Homework::PUBLISHED) {
            return $homework;
        }

        DB::transaction(function () use ($actor, $homework): void {
            $homework->forceFill([
                'status' => Homework::PUBLISHED,
                'published_at' => now(),
            ])->save();

            $this->audit->log(
                event: 'homework.published',
                auditable: $homework,
                description: __('Homework :title was published', ['title' => $homework->title]),
                oldValues: ['status' => Homework::DRAFT],
                newValues: ['status' => Homework::PUBLISHED],
                actor: $actor,
            );
        });

        $this->announce($homework);

        return $homework->refresh();
    }

    /**
     * Cancelling keeps the row and any submissions for the record; it only
     * withdraws the assignment from the portal.
     */
    public function cancel(User $actor, Homework $homework): Homework
    {
        if ($homework->status === Homework::CANCELLED) {
            return $homework;
        }

        DB::transaction(function () use ($actor, $homework): void {
            $homework->forceFill(['status' => Homework::CANCELLED])->save();

            $this->audit->log(
                event: 'homework.cancelled',
                auditable: $homework,
                description: __('Homework :title was cancelled', ['title' => $homework->title]),
                oldValues: ['status' => $homework->getOriginal('status')],
                newValues: ['status' => Homework::CANCELLED],
                actor: $actor,
            );
        });

        return $homework->refresh();
    }

    /**
     * Withdrawing a published homework takes it off the portal but keeps the row
     * and every answer already sent, so the teacher can edit it and publish again.
     */
    public function unpublish(User $actor, Homework $homework): Homework
    {
        if ($homework->status !== Homework::PUBLISHED) {
            return $homework;
        }

        DB::transaction(function () use ($actor, $homework): void {
            $homework->forceFill(['status' => Homework::DRAFT, 'published_at' => null])->save();

            $this->audit->log(
                event: 'homework.unpublished',
                auditable: $homework,
                description: __('Homework :title was put back to draft', ['title' => $homework->title]),
                oldValues: ['status' => Homework::PUBLISHED],
                newValues: ['status' => Homework::DRAFT],
                actor: $actor,
            );
        });

        return $homework->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Homework $homework, array $data, ?UploadedFile $attachment = null): Homework
    {
        $old = $homework->only(['title', 'description', 'instructions', 'class_id', 'section_id', 'subject_id', 'assigned_date', 'due_date']);

        DB::transaction(function () use ($actor, $homework, $data, $attachment, $old): void {
            $homework->update($this->attributes($actor, $data));

            if ($attachment !== null) {
                $previous = $homework->attachment_path;
                $homework->forceFill([
                    'attachment_path' => $this->uploads->storeDocument($attachment, 'homework_attachment', (string) $homework->school_id),
                ])->save();

                if ($previous !== null && $previous !== $homework->attachment_path) {
                    $this->uploads->delete($previous, 'homework_attachment');
                }
            }

            $this->audit->log(
                event: 'homework.updated',
                auditable: $homework,
                description: __('Homework :title was updated', ['title' => $homework->title]),
                oldValues: Arr::except($old, ['description']),
                newValues: $homework->only(['title', 'class_id', 'section_id', 'subject_id', 'due_date']),
                actor: $actor,
            );
        });

        return $homework->refresh();
    }

    public function delete(User $actor, Homework $homework): void
    {
        // The answers belong to this assignment, so they and their uploads go
        // with it; the class, subject and student records it points at do not.
        $this->deletes->delete($homework, [
            'label' => __('homework'),
            'description' => __('Homework #:id was permanently deleted.', ['id' => $homework->getKey()]),
            'actor' => $actor,
        ]);
    }

    /**
     * A student answers their own homework. Late answers are accepted but marked
     * as late, and a reviewed submission is frozen so the marked answer cannot
     * be replaced.
     *
     * @param  array<string, mixed>  $data
     */
    public function submit(
        Student $student,
        User $actor,
        Homework $homework,
        array $data,
        ?UploadedFile $attachment = null,
    ): HomeworkSubmission {
        if ($homework->status !== Homework::PUBLISHED) {
            throw ValidationException::withMessages([
                'text_answer' => __('This homework is not open for submissions.'),
            ]);
        }

        $existing = HomeworkSubmission::query()
            ->where('homework_id', $homework->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existing !== null && $existing->isReviewed()) {
            throw ValidationException::withMessages([
                'text_answer' => __('This submission has already been reviewed, so it can no longer be changed.'),
            ]);
        }

        $pastDue = $homework->isPastDue();

        if ($existing !== null && $pastDue) {
            throw ValidationException::withMessages([
                'text_answer' => __('The deadline has passed, so this submission can no longer be changed.'),
            ]);
        }

        $status = $pastDue ? 'late' : 'submitted';
        $now = now();

        $submission = DB::transaction(function () use ($student, $actor, $homework, $data, $attachment, $status, $now, $existing): HomeworkSubmission {
            $attributes = [
                'status' => $status,
                'text_answer' => filled($data['text_answer'] ?? null) ? trim((string) $data['text_answer']) : null,
                'submitted_at' => $existing?->submitted_at ?? $now,
                'resubmitted_at' => $existing !== null ? $now : null,
            ];

            if ($attachment !== null) {
                $stored = $this->uploads->storeDocument($attachment, 'homework_submission', (string) $student->school_id);
                $previous = $existing?->attachment_path;

                $attributes['attachment_path'] = $stored;
                $attributes['attachment_name'] = mb_substr($attachment->getClientOriginalName(), 0, 160);

                if ($previous !== null && $previous !== $stored) {
                    $this->uploads->delete($previous, 'homework_submission');
                }
            } elseif ($existing !== null) {
                $attributes['attachment_path'] = $existing->attachment_path;
                $attributes['attachment_name'] = $existing->attachment_name;
            }

            /** @var HomeworkSubmission $record */
            $record = HomeworkSubmission::query()->updateOrCreate(
                ['homework_id' => $homework->id, 'student_id' => $student->id],
                $attributes + ['enrollment_id' => $student->currentEnrollment()?->id],
            );

            $this->audit->log(
                event: 'homework.submitted',
                auditable: $record,
                description: __('Homework :title was submitted', ['title' => $homework->title]),
                newValues: ['status' => $record->status, 'homework_id' => $homework->id],
                actor: $actor,
            );

            return $record;
        });

        return $submission;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function review(User $actor, HomeworkSubmission $submission, array $data): HomeworkSubmission
    {
        DB::transaction(function () use ($actor, $submission, $data): void {
            $old = $submission->only(['status', 'marks', 'feedback']);

            $submission->forceFill([
                'status' => 'reviewed',
                'marks' => Arr::get($data, 'marks') !== null && Arr::get($data, 'marks') !== '' ? (float) $data['marks'] : null,
                'feedback' => filled($data['feedback'] ?? null) ? trim((string) $data['feedback']) : null,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ])->save();

            $this->audit->log(
                event: 'homework.reviewed',
                auditable: $submission,
                description: __('A homework submission was reviewed'),
                oldValues: $old,
                newValues: $submission->only(['status', 'marks']),
                actor: $actor,
            );
        });

        return $submission->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(User $actor, array $data): array
    {
        return [
            'academic_session_id' => (int) $data['academic_session_id'],
            'class_id' => (int) $data['class_id'],
            'section_id' => filled($data['section_id'] ?? null) ? (int) $data['section_id'] : null,
            'subject_id' => (int) $data['subject_id'],
            // A teacher's work is always filed under their own name, whoever the
            // post claimed. The office picks the teacher it assigned.
            'teacher_id' => $this->teacherFor($actor, $data),
            'title' => trim((string) $data['title']),
            'description' => trim((string) $data['description']),
            'instructions' => filled($data['instructions'] ?? null) ? trim((string) $data['instructions']) : null,
            'assigned_date' => (string) $data['assigned_date'],
            'due_date' => (string) $data['due_date'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function teacherFor(User $actor, array $data): ?int
    {
        if (! $this->teaching->isUnrestricted($actor)) {
            return $this->teaching->teacherId($actor);
        }

        return filled($data['teacher_id'] ?? null) ? (int) $data['teacher_id'] : null;
    }

    /**
     * New homework reaches the class inbox. A notification failure must never
     * undo the assignment, so failures are reported and swallowed.
     */
    private function announce(Homework $homework): void
    {
        try {
            app(SchoolEventNotifier::class)->homeworkAssigned($homework);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
