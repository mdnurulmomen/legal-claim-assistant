<?php

namespace App\Http\Controllers\Api\AdminRole;

use App\Http\Controllers\Api\AdminRole\Requests\CreateOrUpdateAdminRoleRequest;
use App\Http\Controllers\Api\AdminRole\Requests\UpdateAccessRequest;
use App\Http\Controllers\Api\AdminRole\Resources\AdminRoleResource;
use App\Http\Controllers\Controller;
use App\Models\AdminRole;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminRoleController extends Controller
{
    /**
     * Retrieves a paginated list of Admin Roles based on the given request parameters.
     *
     * @param Request $request
     * @return Response
     */
    public function adminRoleList(Request $request): Response
    {
        $limit = $request->input('limit', 10);

        $roles = AdminRole::query()
                ->select('id', 'name', 'is_show_affiliate')
                ->when(! empty($request->search_txt), function ($query) use ($request) {
                    $query->where('name', 'like', "%{$request->search_txt}%");
                })
                ->whereNot('admin_role', 'super_admin')
                ->latest('id')
                ->paginate($limit);

        return withSuccessResourceList(AdminRoleResource::collection($roles));
    }

    /**
     * Retrieves a list of all Admin Roles.
     *
     * @param Request $request The HTTP request
     * @return Response The response containing the list of Admin Roles
     */
    public function allAdminRoles(Request $request): Response
    {
        $roles = AdminRole::query()
                    ->select('id', 'name')
                    ->whereNot('admin_role', 'super_admin')
                    ->get();
        return withSuccess(AdminRoleResource::collection($roles));
    }

    /**
     * Creates a new Admin Role based on the provided request data.
     *
     * @param CreateOrUpdateAdminRoleRequest $request
     * @return Response
     */
    public function createAdminRole(CreateOrUpdateAdminRoleRequest $request): Response
    {
        $adminRole = new AdminRole();
        $adminRole->name = $request->name;
        $adminRole->admin_role = 'admin';
        $adminRole->save();

        return withSuccess(new AdminRoleResource($adminRole), 'Admin Role created successfully');
    }

    /**
     * Retrieves and shows the Admin Role information based on the provided Admin Role ID.
     *
     * @param int $roleId
     * @return Response
     */
    public function showAdminRole(int $roleId): Response
    {
        $roleId = AdminRole::find($roleId);
        if(empty($roleId)){
            return withError('Admin Role not found', 404);
        }
        return withSuccess(new AdminRoleResource($roleId));
    }

    /**
     * Updates a Admin Role based on the provided Admin Role ID and request data.
     *
     * @param CreateOrUpdateAdminRoleRequest $request
     * @param int $roleId
     * @return Response
     */
    public function updateAdminRole(CreateOrUpdateAdminRoleRequest $request, int $roleId): Response
    {
        $role = AdminRole::find($roleId);
        if(empty($role)){
            return withError('AdminRole not found', 404);
        }

        $role->name = $request->name;
        $role->save();

        return withSuccess(new AdminRoleResource($role), 'Admin Role updated successfully');
    }

    /**
     * Updates the access of an admin role based on the provided Admin Role ID and request data.
     *
     * @param UpdateAccessRequest $request
     * @param int $roleId
     * @return Response
     */
    public function updateAccess(UpdateAccessRequest $request, int $roleId): Response
    {
        $role = AdminRole::find($roleId);
        if(empty($role)){
            return withError('AdminRole not found', 404);
        }

        $role->is_show_affiliate = $request->is_show_affiliate;
        $role->save();

        return withSuccess(new AdminRoleResource($role), 'Admin Role updated successfully');
    }

    /**
     * Deletes a Admin Role with the given Admin Role ID.
     *
     * @param int $roleId
     * @return Response
     */
    public function deleteAdminRole(int $roleId): Response
    {
        $role = AdminRole::find($roleId);
        if(empty($role)){
            return withError('Admin Role not found', 404);
        }

        $role->delete();

        return withSuccess(message: 'Admin Role deleted successfully');
    }

}
