<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Api\Auth\Resources\AuthResource;
use App\Http\Controllers\Api\User\Requests\CreateOrUpdateUserRequest;
use App\Http\Controllers\Api\User\Requests\UpdateBasicInfoRequest;
use App\Http\Controllers\Api\User\Requests\UpdateMyEmailRequest;
use App\Http\Controllers\Api\User\Requests\UpdateMyPasswordRequest;
use App\Http\Controllers\Api\User\Resources\UserResource;
use App\Http\Controllers\Api\User\Resources\PartnerSelectResource;
use App\Http\Controllers\Api\User\Resources\ManagerSelectResource;
use App\Http\Controllers\Controller;
use App\Models\AdminRole;
use App\Models\AccountManager;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Retrieves a paginated list of users based on the given request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function userList(Request $request): Response
    {
        $limit = $request->input('limit', 10);

        $users = User::query()
                    ->select(
                        'users.id',
                        'users.name',
                        'users.email',
                        'users.role',
                        'users.username',
                        'users.admin_role_id',
                        'users.logo',
                        'users.phone',
                        'users.workspace',
                        'users.status',
                        'ar.admin_role',
                        'ar.name as admin_role_name',
                        'ar.is_show_affiliate',
                        'users.data->affids as affids'
                    )
                    ->leftJoin('admin_roles as ar', 'users.admin_role_id', '=', 'ar.id')
                    ->when(! empty($request->search_txt), function ($query) use ($request) {
                        return $query->where(function ($query2) use ($request) {
                            return $query2->whereAny(['users.name','users.email','users.username'], 'like', "%{$request->search_txt}%");
                        });
                    })
                    ->whereDoesntHave('adminRole', function ($query) {
                        return $query->where('admin_role', 'super_admin');
                    })
                    ->where('users.role', 'admin')
                    ->latest('users.id')
                    ->paginate($limit);

        return withSuccessResourceList(UserResource::collection($users));
    }

    /**
     * Creates a new user based on the provided request data.
     *
     * @param CreateOrUpdateUserRequest $request
     * @return Response
     */
    public function createUser(CreateOrUpdateUserRequest $request): Response
    {
        $validatedData = $request->validated();
        $user = User::create($validatedData)->load('adminRole:id,admin_role');
        $user->admin_role = $user->adminRole->admin_role;

        if( isset($validatedData['manager']) && !empty($validatedData['manager']) ){
            AccountManager::updateOrCreate(
                [ 'affiliate_id'    => $user->id ],
                [ 'user_id'         =>  $validatedData['manager'] ]
            );
        }

        return withSuccess(new UserResource($user), 'User created successfully');
    }

    /**
     * Retrieves and shows the user information based on the provided user ID.
     * @param Request $request
     * @param int $userId
     * @param UserService $userService
     * @return Response
     */
    public function showUser(Request $request, int $userId, UserService $userService): Response
    {
        $user = $userService->getSingleUser($userId);
        if(empty($user)){
            return withError('User not found', 404);
        }
        return withSuccess(new UserResource($user));
    }

    /**
     * Updates a user based on the provided user ID and request data.
     *
     * @param CreateOrUpdateUserRequest $request
     * @param int $userId
     * @param UserService $userService
     * @return Response
     */
    public function updateUser(CreateOrUpdateUserRequest $request, int $userId, UserService $userService): Response
    {
        $user = $userService->getSingleUser($userId);
        if(empty($user)){
            return withError('User not found', 404);
        }

        $validatedData = $request->validated();
        $formattedData = $userService->formatRequestData($validatedData);

        $user->update($formattedData);

        if( isset($validatedData['manager']) && !empty($validatedData['manager']) ){
            AccountManager::updateOrCreate(
                [ 'affiliate_id'    => $user->id ],
                [ 'user_id'         =>  $validatedData['manager'] ]
            );
        }

        return withSuccess(new UserResource($user->refresh()), 'User updated successfully');
    }

    /**
     * Deletes a user with the given user ID.
     *
     * @param int $userId
     * @return Response
     */
    public function deleteUser(int $userId): Response
    {
        $user = User::find($userId);
        if(empty($user)){
            return withError('User not found', 404);
        }
        $user->delete();
        return withSuccess(message: 'User deleted successfully');
    }

    /**
     * Updates the user's basic information.
     *
     * @param UpdateBasicInfoRequest $request
     * @param UserService $userService
     * @return Response
     */
    public function updateMyInfo(UpdateBasicInfoRequest $request, UserService $userService): Response
    {
        $user = $userService->getSingleUser(auth()->id());
        if(empty($user)){
            return withError('User not found', 404);
        }

        $user->update($request->validated());
        return withSuccess(new AuthResource($user->refresh()), 'User updated successfully');
    }

    /**
     * Updates the user's email based on the provided request data.
     *
     * @param UpdateMyEmailRequest $request
     * @param UserService $userService
     * @return Response
     */
    public function updateMyEmail(UpdateMyEmailRequest $request, UserService $userService): Response
    {
        $user = $userService->getSingleUser(auth()->id());
        if(empty($user)){
            return withError('User not found', 404);
        }

        if(! Hash::check($request->password, $user->password)){
            return withError('The provided credentials are incorrect.', 400);
        }

        $user->update(['email' => $request->email]);
        return withSuccess(new AuthResource($user->refresh()), 'Email updated successfully');
    }

    /**
     * Updates the user's password based on the provided request data.
     *
     * @param UpdateMyPasswordRequest $request
     * @param UserService $userService
     * @return Response
     */
    public function updateMyPassword(UpdateMyPasswordRequest $request, UserService $userService): Response
    {
        $user = $userService->getSingleUser(auth()->id());
        if(empty($user)){
            return withError('User not found', 404);
        }

        if(! Hash::check($request->old_password, $user->password)){
            return withError('The provided credentials are incorrect.', 400);
        }

        $user->update(['password' => $request->password]);
        return withSuccess(new AuthResource($user->refresh()), 'Password updated successfully');
    }

    /**
     * Retrieves a list of partners.
     *
     * @param Request $request
     * @return Response
     */
    public function partnerList()
    {
        $partners =  User::query()
            ->where('users.role', '=', 'affiliate')
            ->leftJoin('affiliates', 'users.id', '=', 'affiliates.user_id')
            ->selectRaw("users.id as user_id")
            ->selectRaw("affiliates.company_name as name")
            ->selectRaw("users.workspace as workspace")
            ->distinct()
            ->get();
       return withSuccessResourceList(PartnerSelectResource::collection($partners));
    }

    /**
     * Retrieves a list of manager.
     *
     * @param Request $request
     * @return Response
     */
    public function managerList()
    {
        $managers =  User::query()
            ->where('users.role', '=', 'admin')
            ->get();
       return withSuccessResourceList(ManagerSelectResource::collection($managers));
    }

}
