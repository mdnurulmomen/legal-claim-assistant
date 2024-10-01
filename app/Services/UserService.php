<?php

namespace App\Services;

use App\Models\AdminRole;
use App\Models\User;

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
        if(empty($data['password'])) {
            unset($data['password']);
        }
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
        return User::query()
                ->addSelect([
                    'admin_role' => AdminRole::select('admin_role')->whereColumn('id', 'users.admin_role_id')->limit(1),
                ])
                ->find($userId);
    }

}
