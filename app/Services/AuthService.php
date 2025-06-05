<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * Register a new user
     */
    public function register(array $data): array
    {
        $user = $this->userRepository->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        // Assign default role
        $defaultRole = $data['role'] ?? 'cashier';
        $this->userRepository->assignRole($user, $defaultRole);

        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user->load('roles'),
            'token' => $token,
        ];
    }

    /**
     * Login user
     */
    public function login(array $credentials): array
    {
        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();
        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user->load('roles'),
            'token' => $token,
        ];
    }

    /**
     * Logout user
     */
    public function logout(User $user): bool
    {
        return $user->currentAccessToken()->delete();
    }

    /**
     * Refresh user token
     */
    public function refreshToken(User $user): array
    {
        // Delete current token
        $user->currentAccessToken()->delete();

        // Create new token
        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user->load('roles'),
            'token' => $token,
        ];
    }

    /**
     * Get authenticated user with roles
     */
    public function getAuthenticatedUser(User $user): User
    {
        return $user->load('roles');
    }

    /**
     * Change user password
     */
    public function changePassword(User $user, string $newPassword): User
    {
        return $this->userRepository->update($user, [
            'password' => $newPassword,
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(User $user, array $data): User
    {
        return $this->userRepository->update($user, $data);
    }

    /**
     * Verify user email
     */
    public function verifyEmail(User $user): User
    {
        return $this->userRepository->update($user, [
            'email_verified_at' => now(),
        ]);
    }
}
