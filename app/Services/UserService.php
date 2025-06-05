<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class UserService
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * Get all users with filters
     */
    public function getAllUsers(array $filters = []): Collection
    {
        return $this->userRepository->getAll($filters);
    }

    /**
     * Get paginated users
     */
    public function getPaginatedUsers(int $perPage = 15, array $filters = [])
    {
        return $this->userRepository->paginate($perPage, $filters);
    }

    /**
     * Find user by ID
     */
    public function findUser(int $id): ?User
    {
        return $this->userRepository->findById($id);
    }

    /**
     * Create new user
     */
    public function createUser(array $data): User
    {
        $user = $this->userRepository->create($data);

        // Assign role if provided
        if (isset($data['role'])) {
            $this->userRepository->assignRole($user, $data['role']);
        }

        return $user->load('roles');
    }

    /**
     * Update user
     */
    public function updateUser(User $user, array $data): User
    {
        $updatedUser = $this->userRepository->update($user, $data);

        // Update role if provided
        if (isset($data['role'])) {
            // Remove all current roles and assign new one
            $updatedUser->syncRoles([$data['role']]);
        }

        return $updatedUser->load('roles');
    }

    /**
     * Delete user
     */
    public function deleteUser(User $user): bool
    {
        return $this->userRepository->delete($user);
    }

    /**
     * Assign role to user
     */
    public function assignRole(User $user, string $role): User
    {
        return $this->userRepository->assignRole($user, $role);
    }

    /**
     * Remove role from user
     */
    public function removeRole(User $user, string $role): User
    {
        return $this->userRepository->removeRole($user, $role);
    }

    /**
     * Get users by role
     */
    public function getUsersByRole(string $role): Collection
    {
        return $this->userRepository->getUsersByRole($role);
    }

    /**
     * Check if user has permission
     */
    public function userHasPermission(User $user, string $permission): bool
    {
        return $this->userRepository->hasPermission($user, $permission);
    }

    /**
     * Bulk assign role to users
     */
    public function bulkAssignRole(array $userIds, string $role): int
    {
        $count = 0;
        foreach ($userIds as $userId) {
            $user = $this->userRepository->findById($userId);
            if ($user) {
                $this->userRepository->assignRole($user, $role);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Bulk remove role from users
     */
    public function bulkRemoveRole(array $userIds, string $role): int
    {
        $count = 0;
        foreach ($userIds as $userId) {
            $user = $this->userRepository->findById($userId);
            if ($user) {
                $this->userRepository->removeRole($user, $role);
                $count++;
            }
        }
        return $count;
    }
}
