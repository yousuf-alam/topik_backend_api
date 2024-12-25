<?php

namespace App\Traits;

use App\Http\Controllers\SuperAdmin\UserController;
use App\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

trait AuthTrait
{
    public static $BD_ELEVEN_DIGIT_VALIDATION = '/(^()?(01){1}[23456789]{1}(\d){8})$/u';

    public function register(Request $request)
    {

        if (empty($request->password) || $request->password == '' || $request->password === null) {
            request()->request->add(['password' => 'password']);
            request()->request->add(['password_confirmation' => 'password']);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|regex:/^[\pL\s\-.]+$/u',
            'phone' => [
                'required',
                'unique:users',
                'regex:' . self::$BD_ELEVEN_DIGIT_VALIDATION
            ],
            'email' => 'nullable|email',
            'password' => 'string|confirmed'
        ]);


        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()
            ], 409);
        }
        if (isset($request->avatar)) {
            if (env('SERVER_TYPE') == 'local') {
                $imageName = $request->file('avatar');
                $imageName = 'images/user_avatars/' . $request->name . time() . '.' . $imageName->getClientOriginalExtension();
                $request->avatar->move(public_path('/images/user_avatars'), $imageName);
            } else {
                $imageFile = $request->file('avatar');
                $image_path = 'images/user_avatars';
                $imageName =  'images/user_avatars/' . $request->name . time() . '.' . $imageFile->getClientOriginalExtension();
                Storage::disk('spaces')->putFileAs(env('DO_SPACES_FOLDER') . '/' . $image_path, $imageFile, $imageName, 'public');
            }
        } else {
            $imageName = 'images/user_avatars/default.png';
        }


        $user = new User([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'gender' => $request->gender,
            'password' => bcrypt($request->password),
            'avatar' => $imageName,
            'address' => json_encode($request->address)

        ]);
        $name = explode(" ", $request->name);
        $first_name = strtolower($name[0]);
        $user->save();

        $id = base_convert($user->id, 10, 32);
        $code = $first_name . $id;
        $user->invite_code = $code;

        $user->update(["invite_code" => $code]);

        return response()->json([
            'message' => 'Successfully created user!',
            'user' => $user
        ], 201);
    }



    public function login(Request $request)
    {

        $request->validate([
            'phone' => [
                'required',
                'regex:' . self::$BD_ELEVEN_DIGIT_VALIDATION,
            ],
            'password' => 'required|string',
            'remember_me' => 'boolean'
        ]);

        $credentials = request(['phone', 'password']);
        // dd($credentials);
        if (!Auth::attempt($credentials))
            return response()->json(['message' => 'Unauthorized'], 401);

        $user = $request->user();
        $tokenResult = $user->createToken('Admin Access Token');
        $token = $tokenResult->token;
        if ($request->remember_me)
            $token->expires_at = Carbon::now()->addWeeks(1);
        $token->save();

        $user_permissions = UserController::allPermissionsOfAUser($user->id);

        return response()->json([
            'access_token' => $tokenResult->accessToken,
            'user' => $request->user(),
            'user_permissions' => $user_permissions,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::parse(
                $tokenResult->token->expires_at
            )->toDateTimeString()

        ]);
    }

    public function tesst()
    {
        $user = User::find(1);
        return response()->json([
            'message' => 'Successfully created user!',
            'user' => $user
        ], 201);
    }
    public function grantToken(Request $request)
    {
        $now = Carbon::now();
        $http = new Client();
        $response = $http->post(env('DEVELOPERS_URL') . '/oauth/token', [
            'form_params' => [
                'grant_type' => 'password',
                'client_id' => env('PERSONAL_CLIENT_ID'),
                'client_secret' => env('PERSONAL_CLIENT_SECRET'),
                'username' => '01670258671',
                'password' => '123456',
                'scope' => '',
            ],
        ]);
        $response_json = \GuzzleHttp\json_decode($response->getBody()->getContents());
        $token_exists = DB::table('order_tokens')
            ->where('order_id', '=', $request->order_id)
            ->exists();
        if ($token_exists) {
            $update_token = DB::table('order_tokens')
                ->where('order_id', '=', $request->order_id)
                ->update([
                    'order_id'            => $request->order_id,
                    'access_token'            => $response_json->access_token,
                    'refresh_token'            => $response_json->refresh_token,
                    'expiration_time'  => $now->copy()->addMinutes(10),
                    'updated_at'       => $now
                ]);
        } else {
            $new_token = DB::table('order_tokens')
                ->insert([
                    'order_id'            => $request->order_id,
                    'access_token'            => $response_json->access_token,
                    'refresh_token'            => $response_json->refresh_token,
                    'expiration_time'  => $now->copy()->addMinutes(10),
                    'created_at'       => $now
                ]);
        }
        return $response_json->access_token;
    }
    public function refreshToken($order_id)
    {
        $now = Carbon::now();
        Log::debug('refresh', [$order_id]);
        $http = new Client();
        $existing = DB::table('order_tokens')
            ->where('order_id', '=', $order_id)->first();
        $refresh_token = $existing->refresh_token;
        $response = $http->post(env('DEVELOPERS_URL') . '/oauth/token', [
            'form_params' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh_token,
                'client_id' => env('PERSONAL_CLIENT_ID'),
                'client_secret' => env('PERSONAL_CLIENT_SECRET'),
                'scope' => '',
            ],
        ]);
        $response_json = \GuzzleHttp\json_decode($response->getBody()->getContents());

        $existing = DB::table('order_tokens')
            ->where('order_id', '=', $order_id)
            ->update([
                'access_token'    => $response_json->access_token,
                'refresh_token'   => $response_json->refresh_token,
                'expiration_time' => $now->copy()->addMinutes(10)
            ]);

        return $response_json->access_token;
    }

}
