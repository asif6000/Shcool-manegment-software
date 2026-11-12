<?php

namespace App\Services;

use App\Exceptions\PermanentDeleteDenied;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\FileUploadService;
use App\Support\ForeignKeyMap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One delete path for the whole admin panel: a delete button removes the row
 * for real, together with the records that belong to it, and refuses when
 * something it is not allowed to touch still points at it.
 *
 * The plan is built before anything is written, so a refusal never leaves the
 * database half-changed. Anything the policy does not know about blocks: an
 * unlisted relationship is a decision nobody made yet, not a licence to cascade.
 */
class PermanentDeleteService
{
    public function __construct(
        private readonly ForeignKeyMap $keys,
        private readonly FileUploadService $uploads,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{label?: string, description?: string, extra?: array<string, mixed>, audit?: bool, acknowledged?: bool, actor?: User|null, consume_history?: bool}  $options
     * @return array{table: string, id: int|string, removed: array<string, int>, detached: array<string, int>, files: int}
     */
    public function delete(Model $record, array $options = []): array
    {
        $table = $record->getTable();
        $label = $options['label'] ?? $this->label($table);

        if ($this->isProtected($table)) {
            throw new PermanentDeleteDenied(
                __(':label records are financial, academic or audit history, so they are cancelled, reversed or adjusted instead of deleted.', ['label' => $label]),
                [__('This record is protected history and cannot be permanently deleted.')]
            );
        }

        $this->guardTypedConfirmation($table, $options);

        $plan = $this->plan($record, (bool) ($options['consume_history'] ?? false));

        if ($plan['denials'] !== []) {
            $reasons = array_values($plan['denials']);

            throw new PermanentDeleteDenied(
                __(':label cannot be permanently deleted yet.', ['label' => $label]).' '
                .implode(' ', array_slice($reasons, 0, 3)),
                $reasons
            );
        }

        $summary = DB::transaction(function () use ($record, $table, $plan, $options, $label): array {
            if ($options['audit'] ?? true) {
                $this->audit->log(
                    event: Str::snake(class_basename($record)).'.permanently_deleted',
                    auditable: $record,
                    description: $options['description'] ?? __(':label #:id was permanently deleted with :count related record(s).', [
                        'label' => $label,
                        'id' => $record->getKey(),
                        'count' => array_sum($plan['counts']),
                    ]),
                    oldValues: array_merge([
                        'record_id' => $record->getKey(),
                        'related_rows_removed' => $plan['counts'],
                        'references_cleared' => $plan['detached'],
                        'files_removed' => count($plan['files']),
                    ], ! empty($options['consume_history']) ? ['history_consumed' => true] : [], $options['extra'] ?? []),
                    actor: $options['actor'] ?? null,
                );
            }

            // Tables with no foreign key to their owner have to be emptied by
            // hand, or the delete leaves rows naming an id that no longer exists.
            foreach ($plan['cleanup'] as $row) {
                $query = DB::table($row['table'])->whereIn($row['column'], $row['values']);

                if ($row['morph'] !== null) {
                    $query->where($row['morph'], $row['morph_value']);
                }

                $query->delete();
            }

            foreach ($plan['steps'] as $step) {
                DB::table($step['table'])->whereIn($step['column'], $step['values'])->delete();
            }

            $this->removeRow($record);

            return [
                'table' => $table,
                'id' => $record->getKey(),
                'removed' => $plan['counts'],
                'detached' => $plan['detached'],
                'files' => count($plan['files']),
            ];
        });

        // Files only go once the rows that named them are really gone.
        foreach ($plan['files'] as $file) {
            $this->uploads->delete($file['path'], $file['profile']);
        }

        return $summary;
    }

    /**
     * The rows a delete would take with it and the reasons it is refused,
     * without writing anything. Used by the tests and by any screen that wants
     * to warn an admin before the button is pressed.
     *
     * Passing $consumeHistory tells the planner that the caller has been given
     * the whole history of this record — a leaving student, for instance — so
     * the financial and academic rows inside it are taken with it instead of
     * blocking the delete. It never applies to the record itself: a fee invoice
     * still cannot be deleted from the fee screen, only cancelled.
     *
     * @return array{steps: list<array<string, mixed>>, cleanup: list<array<string, mixed>>, files: list<array{path: string, profile: string}>, counts: array<string, int>, detached: array<string, int>, denials: array<string, string>, claimed: array<string, list<int|string>>, consume: bool}
     */
    public function plan(Model $record, bool $consumeHistory = false): array
    {
        $state = $this->emptyState();
        $state['consume'] = $consumeHistory;
        $this->visit($record->getTable(), [$record->getKey()], $record->getKeyName(), $state, [], true, $record);

        return $state;
    }

    /**
     * @param  array{steps: list<array<string, mixed>>, cleanup: list<array<string, mixed>>, files: list<array{path: string, profile: string}>, counts: array<string, int>, detached: array<string, int>, denials: array<string, string>, claimed: array<string, list<int|string>>}  $state
     * @param  list<string>  $path
     */
    private function visit(string $table, array $values, string $key, array &$state, array $path, bool $isRoot, ?Model $model = null): void
    {
        $path[] = $table;

        foreach (config("deletions.files.{$table}", []) as $column => $profile) {
            $paths = $isRoot && $model !== null
                ? array_filter([$model->getAttribute($column)])
                : DB::table($table)->whereIn($key, $values)->whereNotNull($column)->pluck($column);

            foreach ($paths as $stored) {
                if (is_string($stored) && $stored !== '') {
                    $state['files'][] = ['path' => $stored, 'profile' => $profile];
                }
            }
        }

        foreach (config("deletions.cleanup.{$table}", []) as $entry) {
            // A polymorphic cleanup row is only safe when the owner is the
            // record being deleted, because nested nodes cannot name its class.
            if (($entry['morph'] ?? null) !== null && ! $isRoot) {
                continue;
            }

            $state['cleanup'][] = [
                'table' => $entry['table'],
                'column' => $entry['column'],
                'morph' => $entry['morph'] ?? null,
                'morph_value' => $model?->getMorphClass() ?? '',
                'values' => $values,
            ];
        }

        $links = [];
        foreach ($this->keys->childrenOf($table) as $child) {
            if ($child['referenced'] !== $key) {
                continue;
            }

            if (in_array($child['table'], $path, true)) {
                $this->deny($state, $child['table'], __(':label is linked back to this record through a circular relationship, so the delete cannot be decided automatically.', [
                    'label' => $this->label($child['table']),
                ]));

                continue;
            }

            $links[$child['table']][] = $child;
        }

        foreach ($links as $child => $columns) {
            $this->classify($table, $child, $columns, $values, $state, $path);
        }

        if (! $isRoot) {
            // Leaf first: this node is scheduled only after everything below it.
            $state['steps'][] = ['table' => $table, 'column' => $key, 'values' => $values];
            $state['counts'][$table] = ($state['counts'][$table] ?? 0) + count($values);
        }
    }

    /**
     * Decide what one dependent table does to the delete: it is protected
     * history, it belongs to the record being deleted, the database is allowed
     * to drop the reference, or the record stays where it is.
     *
     * @param  list<array{table: string, column: string, referenced: string, rule: string}>  $columns
     * @param  array{steps: list<array<string, mixed>>, cleanup: list<array<string, mixed>>, files: list<array{path: string, profile: string}>, counts: array<string, int>, detached: array<string, int>, denials: array<string, string>, claimed: array<string, list<int|string>>}  $state
     * @param  list<string>  $path
     */
    private function classify(string $parent, string $child, array $columns, array $values, array &$state, array $path): void
    {
        $childKey = $this->keys->keyOf($child);
        $rows = [];

        foreach ($columns as $link) {
            $rows = array_merge(
                $rows,
                DB::table($child)->whereIn($link['column'], $values)->pluck($childKey)->all()
            );
        }

        $rows = array_values(array_unique(array_diff($rows, $state['claimed'][$child] ?? [])));

        if ($rows === []) {
            return;
        }

        $detachable = (array) config("deletions.detachable.{$parent}", []);
        $cascades = count(array_filter($columns, fn (array $link): bool => $link['rule'] === 'CASCADE')) === count($columns);
        $nullsReference = count(array_filter($columns, fn (array $link): bool => $link['rule'] === 'SET NULL')) === count($columns);

        if ($nullsReference) {
            // The database clears the reference; the row itself is not ours to
            // remove, so it neither blocks the delete nor counts as a removal.
            $state['detached'][$child] = ($state['detached'][$child] ?? 0) + count($rows);

            return;
        }

        if ($this->isProtected($child)) {
            if (empty($state['consume'])) {
                $this->deny($state, $child, __(':count :label are kept as financial, academic or audit history and would be destroyed by this delete, so it is refused. Cancel, reverse or clear them first.', [
                    'count' => count($rows),
                    'label' => $this->label($child),
                ]));

                return;
            }

            // The caller deletes the record together with everything that
            // happened to it, so this history is scheduled for removal like any
            // other owned row, and its own children are walked below.
            $state['claimed'][$child] = array_values(array_unique(array_merge($state['claimed'][$child] ?? [], $rows)));
            $this->visit($child, $rows, $childKey, $state, $path, false);

            return;
        }

        if (in_array($child, $detachable, true) || $cascades) {
            $state['claimed'][$child] = array_values(array_unique(array_merge($state['claimed'][$child] ?? [], $rows)));
            $this->visit($child, $rows, $childKey, $state, $path, false);

            return;
        }

        $this->deny($state, $child, __(':count :label still point to this record. Clear them before deleting it.', [
            'count' => count($rows),
            'label' => $this->label($child),
        ]));
    }

    /**
     * The same table can hold the record from two directions, and the admin
     * only needs to read each reason once.
     *
     * @param  array{denials: array<string, string>, ...}  $state
     */
    private function deny(array &$state, string $child, string $reason): void
    {
        $state['denials'][$child] = $reason;
    }

    /**
     * @return array{steps: list<array<string, mixed>>, cleanup: list<array<string, mixed>>, files: list<array{path: string, profile: string}>, counts: array<string, int>, detached: array<string, int>, denials: array<string, string>, claimed: array<string, list<int|string>>, consume: bool}
     */
    private function emptyState(): array
    {
        return [
            'steps' => [],
            'cleanup' => [],
            'files' => [],
            'counts' => [],
            'detached' => [],
            'denials' => [],
            'claimed' => [],
            'consume' => false,
        ];
    }

    private function removeRow(Model $record): void
    {
        $usesSoftDelete = in_array(SoftDeletes::class, class_uses_recursive($record), true);

        $usesSoftDelete ? $record->forceDelete() : $record->delete();
    }

    private function isProtected(string $table): bool
    {
        return in_array($table, (array) config('deletions.protected'), true);
    }

    /**
     * Critical records need the typed word from the dialog on the request as
     * well, so the confirmation cannot be skipped by posting the form directly.
     */
    private function guardTypedConfirmation(string $table, array $options): void
    {
        if (! in_array($table, (array) config('deletions.critical'), true)) {
            return;
        }

        if (($options['acknowledged'] ?? false) === true) {
            return;
        }

        if (request()?->input('permanent_delete') === 'DELETE') {
            return;
        }

        throw new PermanentDeleteDenied(
            __('Type DELETE in the confirmation box to permanently remove this record.'),
            [__('The typed confirmation is missing, so nothing was deleted.')]
        );
    }

    private function label(string $table): string
    {
        $label = config("deletions.labels.{$table}");

        return is_string($label)
            ? Str::of($label)->headline()->lower()->toString()
            : Str::of($table)->replace('_', ' ')->lower()->toString();
    }
}
