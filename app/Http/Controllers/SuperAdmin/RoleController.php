<?php


namespace App\Http\Controllers\SuperAdmin;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Mockery\Exception;
use function PHPSTORM_META\type;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index() {
       return Role::all();
    }

    public function getSingleRole($role_id) {
        if ($role_id == 1) {
            $role = Role::where('id', $role_id)->first();
            $role->getAllPermissions();
            $permissions = Permission::all();
            $data = [];
            $data['role'] = $role;
            $data['allpermissions'] = $permissions;
            return $data;
        }
        $role = Role::where('id', $role_id)->first();
        $role->getAllPermissions();
        $permissions = Permission::where('name', '!=','manage roles')->get();
        $data = [];
        $data['role'] = $role;
        $data['allpermissions'] = $permissions;
        return $data;

    }

    public function createRole(Request $request) {
        $request->validate([
            'name' => 'required|unique:roles|max:180'
        ]); // This fun will automatically return if the validation fails.

        $name = $request->name;
        $role = Role::create(['name' => $name]);
        return $role;
    }

    public function updateRole($role_id, Request $request) {
        $role = Role::findById($role_id);
        $permission_ids = $request->all();
        $role->syncPermissions($permission_ids);
        return $role;
    }

    public function deleteRole($role_id) {
        $user = Auth::user();
        $roleNamesOfUser = $user->getRoleNames()->toArray();

        $hasSuperAdmin = in_array("superadmin", $roleNamesOfUser);
        if ($hasSuperAdmin) {
            try {
                $role = Role::findById($role_id);
                if ($role->name === 'superadmin') {
                    return response()->json("Sorry Vi ! Super Admin Role can't be deleted.", 422);
                }
                $role->delete();
            } catch (RoleDoesNotExist $exception) {
                $message = $exception->getMessage();
                return response()->json($message, 404);
            }
            return "Role deleted successfully";
        } else {
            return response()->json(
            'You have no permission. Only Super Admin can delete role', 422);
        }
    }

    public function getAllPermissions() {
        return Permission::all();
    }


}