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
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        if (User::where('phone', $request->phone)->exists()) {
            return response()->json([
                'message' => 'Phone number already exists',
                'success' => false
            ], Response::HTTP_CONFLICT);
        }

        if (User::where('email', $request->email)->exists()) {
            return response()->json([
                'message' => 'Email  already exists',
                'success' => false
            ], Response::HTTP_CONFLICT);
        }

        $data = new User();
        $data->name = $request->name;
        $data->phone = $request->phone;
        $data->email = $request->email;
        $data->password = bcrypt($request->password);
        $data->save();

        return response()->json([
            'message' => 'User registered successfully',
            'success' => true,
            'data' => $data
        ], Response::HTTP_CREATED);

    }

    public function userLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required',
            'password' => 'required',
        ]);

        // If validation fails, return the error response
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 400);
        }

        $user = User::where('phone', $request->phone)->first();


        if(!$user)
        {
            return response()->json([
                "success"=>false,
                "message"=>"User not found"
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
            "success" => false,
            "message" => "Password Not Matched",
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
