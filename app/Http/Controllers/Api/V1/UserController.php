<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->only(['search', 'role', 'status', 'sort', 'direction']);
        $users = $this->users->paginate($request->user(), $filters);

        return ApiResponse::paginated(
            UserResource::collection($users),
            __('Users retrieved.'),
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->user(), $request->validated());

        return ApiResponse::created(new UserResource($user), __('User created.'));
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return ApiResponse::success(
            new UserResource($user->load(['roles', 'school'])),
            __('User retrieved.'),
        );
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $updated = $this->users->update($request->user(), $user, $request->validated());

        return ApiResponse::success(new UserResource($updated), __('User updated.'));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $this->users->delete($request->user(), $user);

        return ApiResponse::success(null, __('User deleted.'));
    }

    public function toggleStatus(Request $request, User $user): JsonResponse
    {
        $this->authorize('changeStatus', $user);

        $updated = $this->users->toggleStatus($request->user(), $user);

        return ApiResponse::success(
            new UserResource($updated->load(['roles', 'school'])),
            $updated->is_active ? __('User activated.') : __('User deactivated.'),
        );
    }

    public function sendResetLink(Request $request, User $user): JsonResponse
    {
        $this->authorize('sendResetLink', $user);

        $this->users->sendResetLink($request->user(), $user);

        return ApiResponse::success(null, __('Password reset link sent.'));
    }
}
