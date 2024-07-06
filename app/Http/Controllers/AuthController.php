<?php

namespace App\Http\Controllers;

use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function userRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'phone' => 'required',
            'email' => 'required',
            'password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        if (User::where('phone', $request->phone)->exists()) {
            return response()->json([
                'error'=>[
                    'status_code'=>Response::HTTP_CONFLICT,
                    'error_code'=>'phone_number_exists',
                    'error_message'=>'Phone number already exists'
                ]

            ], Response::HTTP_CONFLICT);
        }

        if (User::where('email', $request->email)->exists()) {
            return response()->json([
                'error'=>[
                    'status_code'=>Response::HTTP_CONFLICT,
                    'error_code'=>'phone_number_exists',
                    'error_message'=>'Phone number already exists'
                ]

            ], Response::HTTP_CONFLICT);
        }
        $crypt_user_id = $this->generateUniqueCryptUserId();

        $data = new User();
        $data->name = $request->name;
        $data->phone = $request->phone;
        $data->email = $request->email;
        $data->password = bcrypt($request->password);
        $data->crypt_user_id = $crypt_user_id;
        $data->save();

        return response()->json([
            'message' => 'User registered successfully',
            'success' => true,
            'data' => $data
        ], Response::HTTP_CREATED);

    }

    private function generateUniqueCryptUserId()
    {
        $crypt_user_id = $this->generateRandomCryptUserId();
        while (User::where('crypt_user_id', $crypt_user_id)->exists()) {
            $crypt_user_id = $this->generateRandomCryptUserId();
        }
        return $crypt_user_id;
    }

    // Function to generate a random 8-digit crypt_user_id
    private function generateRandomCryptUserId()
    {
        return str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
    }

    public function userLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 400);
        }

        $user = User::where('phone', $request->phone)->first();


        if(!$user)
        {
            return response()->json([
                'error'=>[
                    'status_code'=>Response::HTTP_NOT_FOUND,
                    'error_code'=>'user_not_found',
                    'error_message'=>'User does not exist',
                ]
            ],Response::HTTP_NOT_FOUND);
        }

        if (Hash::check($request->password, $user->password)) {
            $data = [

                "id" => $user->crypt_user_id,
                "name" => $user->name,
                "phone" => $user->phone,
                "token" => $user->createToken('MyApp')->plainTextToken,



            ];
            return response()->json([
                "success" => true,
                "message" => "Logged in Successfully",

                "data" => $data
            ], Response::HTTP_OK);
        }
        return response()->json([
            'error'=>[
                'status_code'=>Response::HTTP_UNAUTHORIZED,
                'error_code'=>'password_not_matched',
                'error_message'=>'Password not matched',
            ]
        ], Response::HTTP_UNAUTHORIZED);

    }

    public function userLogout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            "success" => true,
            "message" => "Logged out successfully"
        ], Response::HTTP_OK);
    }
}
