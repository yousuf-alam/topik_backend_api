<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;


class UserController extends Controller
{
    public function userProfile(Request $request)
    {
        $user=$request->user();
        return response()->json([
            "success"=>true,
            "message"=>"User Profile",
            "data"=>$user
        ]);

    }
}
