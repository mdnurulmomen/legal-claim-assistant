<?php

namespace App\Services;

use App\Models\AdminRole;
use App\Models\User;
use App\Traits\FileHandlerTrait;
use Illuminate\Http\Request;

class UserService
{
    use FileHandlerTrait;

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

    /**
     * Formats the request data for updating basic user information.
     * If the 'logo' key is present in the request, it will upload the file to the 'profile' directory.
     * If the 'logo' key is empty, it will delete the old logo file.
     * If the 'logo' key is not present, it will keep the current logo.
     * @param Request $request
     * @param array $data
     * @param string|null $oldPic
     * @return array
     */
    public function formatBasicInfo(Request $request, array $data, $oldPic = null): array
    {
        if ($request->file('logo')) {
            $data['logo'] = $this->fileUpload($request->file('logo'), 'profile', $oldPic);
        } elseif (empty($data['logo'])) {
            $this->fileUnlink($oldPic);
        } else {
            if(array_key_exists('logo', $data)) unset($data['logo']);
        }

        return $data;
    }
}
