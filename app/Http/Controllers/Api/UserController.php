<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {}

    /**
     * Display a listing of users
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'role', 'status']);
        $perPage = $request->get('per_page', 15);

        $users = $this->userService->getPaginatedUsers($perPage, $filters);

        return response()->json([
            'users' => UserResource::collection($users->items()),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Store a newly created user
     */
    public function store(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Add role if provided
        if ($request->has('role')) {
            $request->validate(['role' => ['string', 'exists:roles,name']]);
            $data['role'] = $request->role;
        }

        $user = $this->userService->createUser($data);

        return response()->json([
            'message' => 'User created successfully',
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Display the specified user
     */
    public function show(int $userId): JsonResponse
    {
        $user = $this->userService->findUser($userId);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Update the specified user
     */
    public function update(UpdateUserRequest $request, int $userId): JsonResponse
    {
        $user = $this->userService->findUser($userId);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $updatedUser = $this->userService->updateUser($user, $request->validated());

        return response()->json([
            'message' => 'User updated successfully',
            'user' => new UserResource($updatedUser),
        ]);
    }

    /**
     * Remove the specified user
     */
    public function destroy(int $userId): JsonResponse
    {
        $user = $this->userService->findUser($userId);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $this->userService->deleteUser($user);

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Assign role to user
     */
    public function assignRole(Request $request, int $userId): JsonResponse
    {
        $user = $this->userService->findUser($userId);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $updatedUser = $this->userService->assignRole($user, $request->role);

        return response()->json([
            'message' => 'Role assigned successfully',
            'user' => new UserResource($updatedUser),
        ]);
    }

    /**
     * Remove role from user
     */
    public function removeRole(Request $request, int $userId): JsonResponse
    {
        $user = $this->userService->findUser($userId);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $updatedUser = $this->userService->removeRole($user, $request->role);

        return response()->json([
            'message' => 'Role removed successfully',
            'user' => new UserResource($updatedUser),
        ]);
    }

    /**
     * Bulk assign role to users
     */
    public function bulkAssignRole(Request $request): JsonResponse
    {
        $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $count = $this->userService->bulkAssignRole($request->user_ids, $request->role);

        return response()->json([
            'message' => "Role assigned to {$count} users successfully",
            'affected_count' => $count,
        ]);
    }
}
