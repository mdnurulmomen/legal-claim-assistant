<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder;

class UserService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Formats the request data by removing the 'password' key and setting it to null if not provided.
     *
     * @param array $data
     * @return array
     */
    public function formatRequestData(array $data): array
    {
        $data['password'] = $data['password'] ?? null;
        unset($data['password']);
        return $data;
    }

    /**
     * Retrieves a single user by their ID, including their admin role name.
     *
     * @param int $userId
     * @return User
     */
    public function getSingleUser(int $userId): ?User
    {
        return User::query()->with('adminRole:id,name')->find($userId);
    }

}
