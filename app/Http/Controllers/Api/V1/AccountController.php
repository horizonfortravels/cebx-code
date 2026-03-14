<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterAccountRequest;
use App\Http\Resources\AccountResource;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService
    ) {}

    /**
     * POST /api/v1/register
     *
     * Create a new account + owner user.
     */
    public function register(RegisterAccountRequest $request): JsonResponse
    {
        $result = $this->accountService->createAccount($request->validated());

        $token = $result['user']->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الحساب بنجاح.',
            'data'    => [
                'account' => new AccountResource($result['account']),
                'user'    => [
                    'id'       => $result['user']->id,
                    'name'     => $result['user']->name,
                    'email'    => $result['user']->email,
                    'is_owner' => $result['user']->is_owner,
                ],
                'token' => $token,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/v1/account
     *
     * Get current tenant's account info (requires auth + tenant middleware).
     */
    public function show(Request $request): JsonResponse
    {
        $account = $request->user()->account()->with('users')->first();

        return response()->json([
            'success' => true,
            'data'    => new AccountResource($account),
        ]);
    }
}
