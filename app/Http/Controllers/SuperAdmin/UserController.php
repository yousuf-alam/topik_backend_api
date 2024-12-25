<?php


namespace App\Http\Controllers\SuperAdmin;


use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Controller;
use App\Models\User\User;
use App\Traits\AuthTrait;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Mockery\Exception;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

class UserController extends Controller
{
    use AuthTrait;

    public function getUsersByRoleName($rolename) {
        try {
            $users = User::role($rolename)->get();
            foreach ($users as $user) {
                $user->getRoleNames();
            }
            return $users;
        } catch (\Exception $exception) {
            /*
            if ($exception instanceof \Spatie\Permission\Exceptions\RoleDoesNotExist) {
                    return response()->json($exception->getMessage(), 404);
             }
            */
            return [];
        }
    }

    public function index() {
//        $users = User::all();
        $result = DB::table('model_has_roles')
            ->leftJoin('users', 'model_has_roles.model_id', '=', 'users.id')
            ->leftJoin('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->select('users.id', 'users.name as name', 'users.phone', 'roles.name as role_name')
            ->whereNotIn('roles.name', ['user', 'partner', 'resource'])
            ->whereNotNull('users.id')
            ->get();


        return response()->json($result);
    }

    public function create(Request $request) {
        $result = $this->register($request);
        return $result;
    }

    public function update($user_id, Request $request) {

        $validator = Validator::make($request->all(), [
            'phone' => 'required|unique:users,phone,'.$user_id,
        ]);
        if ($validator->fails()) {
            return $validator->errors();
        }

        $data = $request->all();

        $role_ids = $data['role_ids'];
        unset($data['role_ids']);

        $user = User::find($user_id);
        $user->update($data);

        $address = ['latitude' => '','longitude' => '',  'address_details' => $request->address];
        $user->address = json_encode($address);
        $user->update();

        try {
            $user->syncRoles($role_ids);
        } catch (RoleDoesNotExist $exception) {
            $message = $exception->getMessage();
            $message = $message.'Reload the page & Try again';
            return response()->json(['role_warning'=>$message]);
        }

        return response()->json($user, 204);
    }
    public function deleteUser($user_id) {
        DB::table('users')->where('id', $user_id)->delete();
        return json_encode(['heading'=> 'Success', 'message' => 'User Deleted Successfully']);
    }

    public function assignRolesToUser(Request $request) {
        $data = $request->all();
        $user_id = $data['user_id'];
        $role_ids = $data['role_ids'];
        $user = User::find($user_id);
        $response = $user->assignRole($role_ids);
        return $response;
    }

    public function singleUser($id) {
        $user = User::find($id);
        if ($user === null) {
            return response()->json('User not found', 404);
        }
        $user->getRoleNames();
        $user_address = json_decode($user->address);
        $user['address'] = $user_address->address_details;
        return $user;
    }

    public static function allPermissionsOfAUser($user_id) {
        $user = User::find($user_id);


        return $user->getAllPermissions();
    }

    public function updateProfile(Request $request)
    {
        Log::info('admin profile update', [$request->all()]);
        $user = User::find($request->user_id);
        $user->name = $request->name;
        $user->email = $request->email;
        $user->address = $request->address;
        $user->gender = $request->gender;
        if(isset($request->password))
        {
            $user->password = bcrypt($request->password);
        }

        if ($request->hasFile('avatar')){
            if (env('SERVER_TYPE') == 'local') {

                $image_path = 'images/user_avatars/';
                $imageName = $request->file('avatar');
                $imageName = $image_path . time() . '.' . $imageName->getClientOriginalExtension();
                $request->avatar->move(public_path('/images/user_avatars'), $imageName);

                $user->avatar = $imageName;
            } else {

                $imageFile = $request->file('avatar');
                $image_path = 'images/user_avatars/';
                $imageName = $image_path . time() . '.' . $imageFile->getClientOriginalExtension();
                Storage::disk('spaces')->putFileAs(env('DO_SPACES_FOLDER') . '/', $imageFile, $imageName, 'public');
                $user->avatar = $imageName;
            }
        }

        $user->update();
        return response()->json(['success'=>true, 'message'=>'Profile Updated Successfully']);
    }

}
