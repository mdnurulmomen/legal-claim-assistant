<?php

namespace App\Http\Controllers\Api\Permission;

use App\Http\Controllers\Api\Permission\Requests\PermissionRequest;
use App\Http\Controllers\Api\Permission\Resources\MenuResource;
use App\Http\Controllers\Controller;
use App\Models\AdminRole;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\SavedReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PermissionController extends Controller
{
    /**
     * Retrieves the menus based on the given request.
     *
     * @param Request $request
     * @param int $roleId
     * @return Response
     */
    public function getMenus(Request $request, int $roleId): Response
    {
        $menus = Menu::whereNull('menu_id')
                    ->select('id', 'title', 'route_name', 'menu_id', 'type')
                    ->withExists(['permissions as has_permission' => function (Builder $query) use ($roleId) {
                        return $query->where('admin_role_id', $roleId);
                    }])
                    ->with(['children' => function ($query) use($roleId) {
                        return $query->withExists(['permissions as has_permission' => function (Builder $query) use ($roleId) {
                                return $query->where('admin_role_id', $roleId);
                            }]);
                    }])
                    ->orderBy('order')
                    ->get();

        return withSuccess(MenuResource::collection($menus));
    }

    /**
     * Updates the permission of an admin role.
     *
     * @param PermissionRequest
     * @param int $roleId
     * @return Response
     */
    public function updatePermission(PermissionRequest $request, int $roleId): Response
    {
        $adminRole = AdminRole::find($roleId);
        if(empty($adminRole)){
            return withError('Admin Role not found', 404);
        }

        $adminRole->menus()->sync($request->menus);
        return withSuccess(message: 'Permission updated successfully');
    }

    /**
     * Retrieves the permissions for the admin role with ID 1 from the database.
     *
     * @param Request $request
     * @return Response
     */
    public function getPermission(Request $request): Response
    {
        $auth = auth('sanctum')->user();
        $data = [
            'permissions' => [],
            'saved_reports' => [],
        ];

        if(! $auth){
            return withSuccess($data);
        }

        $data['saved_reports'] = SavedReport::whereUserId($auth->id)->select('id', 'title', 'uid')->get();

        if($auth->role === 'super_admin'){
            return withSuccess($data);
        }

        $data['permissions'] = Permission::where('permissions.admin_role_id', $auth->admin_role_id)
                                    ->select('permissions.id', 'menus.route_name')
                                    ->leftJoin('menus', 'permissions.menu_id', '=', 'menus.id')
                                    ->pluck('route_name');

        return withSuccess($data);
    }
}
