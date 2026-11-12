<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use App\Support\RoleHierarchy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->only(['search', 'role', 'status', 'sort', 'direction']);

        return view('users.index', [
            'users' => $this->users->paginate($request->user(), $filters),
            'filters' => $filters,
            'roles' => collect(config('school.roles')),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', User::class);

        return view('users.create', [
            'roles' => RoleHierarchy::assignableStaffRoles($request->user()),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->users->create($request->user(), $request->validated());

        return redirect()
            ->route('users.index')
            ->with('success', __('User :name was created and notified.', ['name' => $user->name]));
    }

    public function edit(Request $request, User $user): View|RedirectResponse
    {
        if ($user->is($request->user())) {
            return redirect()
                ->route('profile.edit')
                ->with('info', __('Use your profile page to update your own account.'));
        }

        $this->authorize('update', $user);

        $currentRole = $user->getRoleNames()->first();
        $isStudent = $user->hasRole('student');

        return view('users.edit', [
            'user' => $user->load(['school', 'roles']),
            'roles' => $isStudent
                ? RoleHierarchy::assignableRoles($request->user())
                : RoleHierarchy::assignableStaffRoles($request->user()),
            'currentRole' => $currentRole,
            'orphanStudentAccount' => $isStudent && ! $user->student()->exists(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->users->update($request->user(), $user, $request->validated());

        return redirect()
            ->route('users.index')
            ->with('success', __('User :name was updated.', ['name' => $user->name]));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $name = $user->name;
        $this->users->delete($request->user(), $user);

        return redirect()
            ->route('users.index')
            ->with('success', __('User :name was deleted.', ['name' => $name]));
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $this->authorize('changeStatus', $user);

        $updated = $this->users->toggleStatus($request->user(), $user);

        return back()->with(
            'success',
            $updated->is_active
                ? __('User :name was activated.', ['name' => $updated->name])
                : __('User :name was deactivated.', ['name' => $updated->name]),
        );
    }

    public function sendResetLink(Request $request, User $user): RedirectResponse
    {
        $this->authorize('sendResetLink', $user);

        $this->users->sendResetLink($request->user(), $user);

        return back()->with(
            'success',
            __('A password reset link was sent to :email.', ['email' => $user->email]),
        );
    }
}
