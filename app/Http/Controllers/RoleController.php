<?php

namespace App\Http\Controllers;

use App\Services\FeeCollectionAuthority;
use App\Support\AuditLogger;
use App\Support\LearningPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('roles.view'), 403);

        $roleMeta = config('school.roles');

        return view('roles.index', [
            // Only catalogue roles are managed here; a leftover role from an older
            // seed must not show up as an unlabelled level 0 column.
            'roles' => Role::query()
                ->whereIn('name', array_keys($roleMeta))
                ->with('permissions')
                ->withCount('users')
                ->get()
                ->sortByDesc(fn (Role $role): int => (int) ($roleMeta[$role->name]['level'] ?? 0))
                ->values(),
            'catalogue' => config('school.permissions'),
            'roleMeta' => $roleMeta,
            'canEdit' => $request->user()->hasRole('school_admin'),
        ]);
    }

    public function update(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()->hasRole('school_admin'), 403);

        $data = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['array'],
            'roles.*.*' => ['nullable', 'string', 'exists:permissions,name'],
        ]);

        $knownRoles = array_keys((array) config('school.roles'));
        $submittedRoles = array_keys($data['roles']);

        if (array_diff($submittedRoles, $knownRoles) !== []) {
            throw ValidationException::withMessages([
                'roles' => __('One or more roles are invalid.'),
            ]);
        }

        // Without roles.view the school admin can never open this screen again,
        // so that one checkbox is not removable from the matrix.
        if (isset($data['roles']['school_admin'])
            && ! in_array('roles.view', $data['roles']['school_admin'], true)) {
            throw ValidationException::withMessages([
                'roles' => __('School Admin must keep the "View roles" permission.'),
            ]);
        }

        // The learning screens belong to the teaching staff. Re-granting them
        // here would undo the rule through the back door.
        if (isset($data['roles']['school_admin'])
            && array_intersect($data['roles']['school_admin'], LearningPermissions::SCHOOL_ADMIN_EXCLUDED) !== []) {
            throw ValidationException::withMessages([
                'roles.school_admin' => __('The School Admin cannot hold homework, classwork or lesson permissions.'),
            ]);
        }

        // The office monitors the cash desk; recording a payment stays with the
        // accountant. Granting it here would only produce a 403 on submit.
        if (isset($data['roles']['school_admin'])
            && array_intersect($data['roles']['school_admin'], FeeCollectionAuthority::MONITORING_ONLY) !== []) {
            throw ValidationException::withMessages([
                'roles.school_admin' => __('The School Admin monitors fee collection but cannot record payments.'),
            ]);
        }

        // Homework and classwork are the teacher's own work; the matrix must not
        // promise another role a screen that the policies answer 403 for.
        foreach ($data['roles'] as $roleName => $permissions) {
            if ($roleName !== 'teacher'
                && array_intersect($permissions, LearningPermissions::TEACHER_ONLY) !== []) {
                throw ValidationException::withMessages([
                    'roles.'.$roleName => __('Homework and classwork belong to teachers only.'),
                ]);
            }
        }

        $changes = [];

        DB::transaction(function () use ($data, $request, $auditLogger, &$changes): void {
            foreach ($data['roles'] as $roleName => $permissions) {
                $role = Role::findByName($roleName, 'web');
                $old = $role->permissions->pluck('name')->sort()->values()->all();
                $new = collect($permissions)
                    ->filter(fn ($permission): bool => is_string($permission) && $permission !== '')
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                if ($old === $new) {
                    continue;
                }

                $role->syncPermissions($new);
                $changes[$roleName] = ['old' => $old, 'new' => $new];
            }

            if ($changes !== []) {
                $auditLogger->log(
                    event: 'role.permissions_updated',
                    auditable: null,
                    description: __('Role permissions were updated'),
                    oldValues: collect($changes)->mapWithKeys(fn (array $change, string $role): array => [$role => $change['old']])->all(),
                    newValues: collect($changes)->mapWithKeys(fn (array $change, string $role): array => [$role => $change['new']])->all(),
                    actor: $request->user(),
                    schoolId: $request->user()->school_id,
                );
            }
        });

        return redirect()
            ->route('roles.index')
            ->with('success', $changes === []
                ? __('No permission changes were needed.')
                : __('Role permissions were updated.'));
    }
}
