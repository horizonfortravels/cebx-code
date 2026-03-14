<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddUserRequest;
use App\Http\Requests\ListUsersRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\AuditLogResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    /**
     * GET /api/v1/users
     *
     * List all users in the current account.
     */
    public function index(ListUsersRequest $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $accountId = $this->currentAccountId($request);
        $users = $this->userService->listUsers(
            $accountId,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'data'    => UserResource::collection($users),
            'meta'    => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/users/{id}
     *
     * Show a specific user's details.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $this->findUserForCurrentTenant($request, $id);
        $this->authorize('view', $user);

        return response()->json([
            'success' => true,
            'data'    => new UserResource($user),
        ]);
    }

    /**
     * POST /api/v1/users
     *
     * Add/invite a new user to the account.
     */
    public function store(AddUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = $this->userService->addUser(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'تمت إضافة المستخدم بنجاح وتم إرسال الدعوة.',
            'data'    => new UserResource($user),
        ], Response::HTTP_CREATED);
    }

    /**
     * PUT /api/v1/users/{id}
     *
     * Update a user's profile information.
     */
    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $targetUser = $this->findUserForCurrentTenant($request, $id);
        $this->authorize('update', $targetUser);

        $user = $this->userService->updateUser(
            $id,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات المستخدم بنجاح.',
            'data'    => new UserResource($user),
        ]);
    }

    /**
     * PATCH /api/v1/users/{id}/disable
     *
     * Disable a user — immediately prevents login.
     */
    public function disable(Request $request, string $id): JsonResponse
    {
        $targetUser = $this->findUserForCurrentTenant($request, $id);
        $this->authorize('disable', $targetUser);

        $user = $this->userService->disableUser($id, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'تم تعطيل المستخدم بنجاح. لن يتمكن من الدخول.',
            'data'    => new UserResource($user),
        ]);
    }

    /**
     * PATCH /api/v1/users/{id}/enable
     *
     * Re-enable a previously disabled user.
     */
    public function enable(Request $request, string $id): JsonResponse
    {
        $targetUser = $this->findUserForCurrentTenant($request, $id);
        $this->authorize('enable', $targetUser);

        $user = $this->userService->enableUser($id, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'تم تفعيل المستخدم بنجاح.',
            'data'    => new UserResource($user),
        ]);
    }

    /**
     * DELETE /api/v1/users/{id}
     *
     * Soft-delete a user (requires responsibility transfer if applicable).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $targetUser = $this->findUserForCurrentTenant($request, $id);
        $this->authorize('delete', $targetUser);

        $forceTransfer = $request->boolean('force_transfer', false);

        $this->userService->deleteUser($id, $request->user(), $forceTransfer);

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المستخدم بنجاح.',
        ]);
    }

    /**
     * GET /api/v1/users/changelog
     *
     * Get the user change history (audit trail).
     */
    public function changelog(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $userId = $request->query('user_id');

        $logs = $this->userService->getUserChangeLog(
            $this->currentAccountId($request),
            $userId
        );

        return response()->json([
            'success' => true,
            'data'    => AuditLogResource::collection($logs),
            'meta'    => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }

    private function currentAccountId(Request $request): string
    {
        $currentAccountId = app()->bound('current_account_id')
            ? trim((string) app('current_account_id'))
            : '';

        if ($currentAccountId !== '') {
            return $currentAccountId;
        }

        return trim((string) $request->user()->account_id);
    }

    private function findUserForCurrentTenant(Request $request, string $id): User
    {
        return User::withoutGlobalScopes()
            ->where('account_id', $this->currentAccountId($request))
            ->where('id', $id)
            ->firstOrFail();
    }
}
