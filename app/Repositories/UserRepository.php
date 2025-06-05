<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class UserRepository implements UserRepositoryInterface
{
    /**
     * Find user by ID
     */
    public function findById(int $id): ?User
    {
        return User::with('roles', 'profile')->find($id);
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User
    {
        return User::with('roles', 'profile')->where('email', $email)->first();
    }

    /**
     * Create a new user
     */
    public function create(array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user = User::create($data);

        // Create profile if profile data is provided
        if (isset($data['profile'])) {
            $user->profile()->create($data['profile']);
            $user->load('profile');
        }

        return $user;
    }

    /**
     * Update user
     */
    public function update(User $user, array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Extract profile data
        $profileData = $data['profile'] ?? null;
        unset($data['profile']);

        $user->update($data);

        // Update or create profile
        if ($profileData) {
            if ($user->profile) {
                $user->profile->update($profileData);
            } else {
                $user->profile()->create($profileData);
            }
        }

        return $user->fresh(['roles', 'profile']);
    }

    /**
     * Delete user
     */
    public function delete(User $user): bool
    {
        return $user->delete();
    }

    /**
     * Get all users with optional filters
     */
    public function getAll(array $filters = []): Collection
    {
        $query = User::with('roles', 'profile');

        if (isset($filters['role'])) {
            $query->role($filters['role']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('email', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['status'])) {
            $query->whereHas('profile', function ($q) use ($filters) {
                $q->where('status', $filters['status']);
            });
        }

        return $query->get();
    }

    /**
     * Get users with pagination
     */
    public function paginate(int $perPage = 15, array $filters = [])
    {
        $query = User::with('roles', 'profile');

        if (isset($filters['role'])) {
            $query->role($filters['role']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('email', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['status'])) {
            $query->whereHas('profile', function ($q) use ($filters) {
                $q->where('status', $filters['status']);
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Assign role to user
     */
    public function assignRole(User $user, string $role): User
    {
        $user->assignRole($role);
        return $user->fresh(['roles', 'profile']);
    }

    /**
     * Remove role from user
     */
    public function removeRole(User $user, string $role): User
    {
        $user->removeRole($role);
        return $user->fresh(['roles', 'profile']);
    }

    /**
     * Get users by role
     */
    public function getUsersByRole(string $role): Collection
    {
        return User::with('roles', 'profile')->role($role)->get();
    }

    /**
     * Check if user has permission
     */
    public function hasPermission(User $user, string $permission): bool
    {
        return $user->hasPermissionTo($permission);
    }
}
