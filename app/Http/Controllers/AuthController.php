<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Models\Otp;
use App\Models\User\User;
use App\Models\User\Wallet;
use App\Models\User\WalletHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
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

        $wallet=new Wallet();
        $wallet->user_id=$data->id;
        $wallet->coins=50;
        $wallet->gems=5;
        $wallet->save();

        $walletHistory= new WalletHistory();
        $walletHistory->user_id=$data->id;
        $walletHistory->type='sign_up';
        $walletHistory->description='You have got 50 coins and 5 gems for sign up';
        $walletHistory->credit_coins=$wallet->coins;
        $walletHistory->credit_gems=$wallet->gems;
        $walletHistory->coin_balance=$wallet->coins;
        $walletHistory->gems_balance=$wallet->gems;
        $walletHistory->save();

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

    public function deleteAccount(Request $request)
    {
        $user=$request->user();
        $user->email= 'del_'.$user->email;
        $user->phone='del_'.$user->phone;
        $user->ac_status='deleted';
        $user->save();

        return response()->json([
            "success"=>true,
            "message"=>"account deleted.Please sign up again"
        ]);

    }

    public function sendOtp(Request $request)
    {
        $userExist=User::where('email',$request->email)->first();
        if(!$userExist)
        {
            return response()->json([
                "success"=>false,
                "message"=>'user not found'
            ]);
        }

        $otp = $this->generateOTP();
        Otp::updateOrCreate( [
            'email' => $request->email,
        ], [
            'otp'            => $otp,
            'send_otp_count' => DB::raw( 'send_otp_count+1' ),
        ] );

        Mail::to($request->email)->send(new OtpMail($otp));

        return  response()->json([
            "success"=>true,
            "message"=>"Otp has sent to your email.please check spam folder"
        ]);

    }
    public function generateOTP() {
        $number = mt_rand( 100000, 999999 ); // better than rand()
        // call the same function if the otp exists already
        if ( $this->OTPExists( $number ) ) {
            return $this->generateOTP();
        }

        return $number;
    }
    public function OTPExists( $number ): bool {
        return Otp::where( 'otp', $number )->exists();
    }
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        $email = $request->email;
        $otp = $request->otp;

        // Retrieve OTP from database
        $otpRecord = Otp::where('email', $email)->first();

        if (!$otpRecord) {
            return response()->json([
                'success'=>false,
                'message' => 'OTP not found'
            ], 400);
        }



        if ($otpRecord->otp == $otp) {
            // OTP is correct, delete it from the database

            return response()->json([
                'success'=>true,
                'message' => 'OTP verified successfully!'
            ]);
        }
        else {
            return response()->json([
                'success'=>false,
                'message' => 'Invalid OTP'
            ], 400);
        }
    }
}
