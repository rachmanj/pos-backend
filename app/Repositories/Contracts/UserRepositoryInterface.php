<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    /**
     * Find user by ID
     */
    public function findById(int $id): ?User;

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User;

    /**
     * Create a new user
     */
    public function create(array $data): User;

    /**
     * Update user
     */
    public function update(User $user, array $data): User;

    /**
     * Delete user
     */
    public function delete(User $user): bool;

    /**
     * Get all users with optional filters
     */
    public function getAll(array $filters = []): Collection;

    /**
     * Get users with pagination
     */
    public function paginate(int $perPage = 15, array $filters = []);

    /**
     * Assign role to user
     */
    public function assignRole(User $user, string $role): User;

    /**
     * Remove role from user
     */
    public function removeRole(User $user, string $role): User;

    /**
     * Get users by role
     */
    public function getUsersByRole(string $role): Collection;

    /**
     * Check if user has permission
     */
    public function hasPermission(User $user, string $permission): bool;
}
