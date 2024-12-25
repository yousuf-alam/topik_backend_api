<?php


namespace App\Http\Controllers\Auth;


use App\Http\Controllers\Controller;
use App\Http\Controllers\SuperAdmin\UserController;
use App\Models\User\User;
use App\Models\Users\PartnerFcm;
use App\Models\Users\ResourceFcm;
use App\Models\Users\UserFcm;
use App\Services\TPayService;
use App\SocialLogin;
use Carbon\Carbon;
use Firebase\Auth\Token\Exception\InvalidToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use Kreait\Firebase\Factory;


class AuthController extends Controller
{

    public static $BD_ELEVEN_DIGIT_VALIDATION = '/(^()?(01){1}[23456789]{1}(\d){8})$/u';

    public static function register(Request $request) {

        $validator = Validator::make($request->all(), [
            'name' => 'required|alpha',
            'phone' => [
                    'required',
                    'unique:users',
                    'regex:'.self::$BD_ELEVEN_DIGIT_VALIDATION
                ],
            'email'=> 'nullable|email',
            'password' => 'required|string|confirmed'
        ]);

        if ($validator->fails()) {
            return $validator->errors();
        }

        $user = new User([
            'name' => $request->name,
            'phone' => $request->phone,
            'password' => bcrypt($request->password)
        ]);
        $user->save();
        return response()->json([
            'message' => 'Successfully created user!',
            'user' => $user
        ], 201);
    }

    public function partnerLogin(Request $request) {

        $request->validate([
            'phone' => [
                'required',
                'regex:'.self::$BD_ELEVEN_DIGIT_VALIDATION,
            ],
            'password' => 'required|string',
            'remember_me' => 'boolean'
        ]);
        $credentials = request(['phone', 'password']);
        // dd($credentials);

        $user = User::where('phone', $request->phone)->first();


        if(Auth::attempt($credentials)){
            if (isset($user) && $user->Partner()->exists()) {
                $partner = $user->Partner;
                if (isset($request->token)) {
                    $token_exists = PartnerFcm::where('partner_id', $partner->id)->where('fcm_token', $request->token)->first();
                    if (!$token_exists) {
                        PartnerFcm::FirstOrCreate(['partner_id' => $partner->id, 'fcm_token' => $request->token]);
                    }
                }

                $partner_token  = $user->createToken('Partner Token v3.0')->accessToken;



                if ($user->Resource()->exists()) {
                    return array(
                        "success"    =>    true,
                        "code"        =>    200,
                        "role"        => "admin",
                        "token"        =>     $partner_token,
                        "type"        =>    "partner",
                        "user_id"   =>  $user->id,
                        "plan_id" =>$user->partner->plan_id
                    );
                } else {
                    return array(
                        "success"    =>    true,
                        "code"        =>    200,
                        "role"        => "employee",
                        "token"        =>     $partner_token,
                        "type"        =>    "partner",
                        "user_id"   =>  $user->id,
                        "plan_id" =>$user->partner->plan_id
                    );
                }
            }
        } else {
            Log::info('partner password didnt match');
            return array(
                "success"    => false,
                "message"    => 'Could not verify user'
            );
        }
    }


    public function login(Request $request) {

            $request->validate([
                'phone' => [
                    'required',
                    'regex:'.self::$BD_ELEVEN_DIGIT_VALIDATION,
                ],
                'password' => 'required|string',
                'remember_me' => 'boolean'
            ]);
            $credentials = request(['phone', 'password']);

            if(!Auth::attempt($credentials))
                return response()->json(['message' => 'Unauthorized'], 401);

            $user = $request->user();
            $tokenResult = $user->createToken( 'Admin Token' )->plainTextToken;
            $token = $tokenResult;
            if ($request->remember_me)
                $token->expires_at = Carbon::now()->addWeeks(1);
//            $token->save();







            $user= User::find($user->id);
             $user_permissions = $user->getAllPermissions();



            return response()->json([
                'access_token' => $tokenResult,
                'user' => $request->user(),
                'user_permissions' => $user_permissions,
                'token_type' => 'Bearer',


            ]);



    }

    public function connectUserLogin(Request $request) {


        if ($request->type == "firebase") {
            try {

                $idTokenString = $request->firebase_token;
                Log::info('token_'.$idTokenString);
                Log::info('uid_',$request->all());
                // dd($request->firebase_token);
                $factory = (new Factory)->withServiceAccount(public_path('secrete/romoni_firebase.json'));
                $auth = $factory->createAuth();
                $verifiedIdToken = $auth->verifyIdToken($idTokenString);
                //dd($verifiedIdToken);
                $other_country_user = $auth->getUser($verifiedIdToken->getClaim('sub'));
                $phoneNumber = str_replace($request->country_code, "", $other_country_user->phoneNumber);
                $uid = $verifiedIdToken->getClaim('sub');
                $user_info = $auth->getUser($uid);
                //dd($uid,$request->all());
                //dd($uid,$user_info,$user_info->providerData[0]->uid);
                if ($uid == $request->uid) {
                    $user = SocialLogin::where('provider_id', $user_info->providerData[0]->uid)->first();
                    $token_type = "partial";

                    if(isset($user->user_id)){
                        $user = User::where('id',$user->user_id)->first();
                        $token_type = "full";
                    }
                    if ($user) {
                        $token = $user->createToken('Romoni Social User')->accessToken;

                        Log::info('Access_token'.$token);

                        //fcm_token need to implemented
                        /*if ($request->has('fcm_token')) {
                            UserFcm::firstOrCreate(['user_id' => $user->id, 'fcm_token' => $request->fcm_token]);
                        }*/

                        if ($request->has('fcm_token')) {
                            UserFcm::firstOrCreate(['user_id' => $user->id, 'fcm_token' => $request->fcm_token]);
                        }
                        return array(
                            "success"        => true,
                            "message"        => "Successfully Done & User Exists",
                            "access_token"   => $token,
                            "user_id"        => $user->id,
                            "token_type"     => $token_type,
                            "account_exists" => true
                        );
                    } else {

                        $user= self::connectUserRegistrationProcess($user_info->providerData[0],$request);

                        $token = $user->createToken('Quizgiri Social User')->accessToken;
                        if ($request->has('fcm_token')) {
                            UserFcm::firstOrCreate(['user_id' => $user->id, 'fcm_token' => $request->fcm_token]);
                        }

                        return array(
                            "success"        => true,
                            "message"       => "Account created successfully",
                            "access_token"    => $token,
                            "user_id"       => $user->id,
                            "token_type"     => $token_type,
                            "account_exists" => false
                        );

                    }
                } else {
                    return array(
                        "success"       => false,
                        "message"       => "FireBase Uid mismatch",
                    );
                }
            } catch (\InvalidArgumentException $e) {

                return array(
                    "success"       => false,
                    "message"       => "The token could not be parsed: " . $e->getMessage(),
                );
            } catch (InvalidToken $e) {

                return array(
                    "success"       => false,
                    "message"       =>  'The token is invalid: ' . $e->getMessage(),
                );
            }
        }
        else{
            $request->validate([
                'phone' => [
                    'required',
                    'regex:'.self::$BD_ELEVEN_DIGIT_VALIDATION,
                ],
                'password' => 'required|string',
                'remember_me' => 'boolean'
            ]);
            $credentials = request(['phone', 'password']);
            // dd($credentials);
            if(!Auth::attempt($credentials))
                return response()->json(['message' => 'Unauthorized'], 401);

            $user = $request->user();
            $tokenResult = $user->createToken('Admin Access Token');
            $token = $tokenResult->token;
            if ($request->remember_me)
                $token->expires_at = Carbon::now()->addWeeks(1);
            $token->save();

            $user_permissions = UserController::allPermissionsOfAUser($user->id);
            $partnerhas = $user->Partner;

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


    }

    public function details(Request $request) {
        // dd($request->all(), getallheaders());
        // $headers = getallheaders();
        // $token = $headers['Authorization'];

        return response()->json(\request()->user());
        // dd($token);
    }

    public function logout(Request $request) {

    }

    public function checkPhone(Request $request) {

        $phone = $request->phone;
        $user = User::where('phone', $phone)->exists();
        if (!$user) {
            return response()->json(["success"=> false,"message" => "User does not exist"], 404);
        }
        return response()->json(["success"=> true,"message" => "User Found"], 200);
    }

    public function otpLogin(Request $request) {
        // Initialize variables
        $app_id = config('app.FB_ACCOUNTKIT_APP_ID');
        $secret = config('app.FB_ACCOUNTKIT_APP_SECRET');
        $version = 'v1.1'; // 'v1.1' for example
        $code = $request->code; //MYSELF


        // Exchange authorization code for access token
        $token_exchange_url = 'https://graph.accountkit.com/' . $version . '/access_token?' .
            'grant_type=authorization_code' .
            '&code=' . $code .
            "&access_token=AA|$app_id|$secret";

        $firstResponse = $this->doGuzzle($token_exchange_url);
        $firstResponseBody = $firstResponse->getBody();
        $data =  \GuzzleHttp\json_decode( $firstResponseBody );

        $user_id = $data->id;
        $user_access_token = $data->access_token;
        $refresh_interval = $data->token_refresh_interval_sec;

        /*
         *  Cannot use object of type GuzzleHttp\\Psr7\\Response as array

            $user_id = $data['id'];
            $user_access_token = $data['access_token'];
            $refresh_interval = $data['token_refresh_interval_sec'];
            return $data;
        */

        // Get Account Kit information
        $me_endpoint_url = 'https://graph.accountkit.com/' . $version . '/me?' .
            'access_token=' . $user_access_token;
        $secondResponse = $this->doGuzzle($me_endpoint_url);
        $secondResponseBody = $secondResponse->getBody();
        $data2 = \GuzzleHttp\json_decode($secondResponseBody);

        $phone = property_exists($data2, 'phone')  ? $data2->phone->number : '';

        $elevenDigitPhone = substr($phone,3,strlen($phone));
        $remember_me = $request->remember_me;
        return $this->generateTokenForPartner($elevenDigitPhone, $remember_me);
        /*
        Here "isset" will not work, because the object is json decoded,
        So we have to use "property_exists"
            $phone = isset($data2['phone']) ? $data2['phone']['number'] : '';
            $email = isset($data2['email']) ? $data2['email']['address'] : '';
        */
    }

    function doGuzzle($url) {
        $client = new Client();
        try {
            return $client->request('GET', $url);
        } catch (RequestException $e) {
            //echo Psr7\str($e->getRequest());
            if ($e->hasResponse()) {
                return Psr7\str($e->getResponse());
            } else {
                return $e;
            }
        }
    }

    public function generateTokenForPartner($phone, $remeber_me) {

        $user = User::where('phone', $phone)->first();
        $tokenResult = $user->createToken('Partner Access Token');
        $token = $tokenResult->token;
        if ($remeber_me)
            $token->expires_at = Carbon::now()->addWeeks(1);
        $token->save();

        $user_permissions = UserController::allPermissionsOfAUser($user->id);
        $partnerhas = $user->Partner;

        return response()->json([
            'access_token' => $tokenResult->accessToken,
            'user' => $user,
            'user_permissions' => $user_permissions,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::parse(
                $tokenResult->token->expires_at
            )->toDateTimeString()

        ]);
    }

    public function generatePartnerTokenForAdminLogin(Request $request) {
        //return $request->all();
        $phone = $request->phone;
        $user = User::where('phone', $phone)->first();
        if ($user === null) {
            return response()->json([
                "heading" => "Partner Not Found",
                "message" => "No Partner Found Using This Phone Number"
            ]);
        }
        $tokenResult = $user->createToken('Partner Access Token');
        $token = $tokenResult->token;
        $token->save();

        $user_permissions = UserController::allPermissionsOfAUser($user->id);
        $partnerhas = $user->Partner;

        return response()->json([
            'access_token' => $tokenResult->accessToken,
            'user' => $user,
            'user_permissions' => $user_permissions,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::parse(
                $tokenResult->token->expires_at
            )->toDateTimeString()

        ]);

    }

    public function socialUserRegistration($firebase,$request){
        $sUser = SocialLogin::create([
            'phone' => $firebase->phoneNumber,
            'email' => $firebase->email,
            'provider_name'=> $firebase->providerId,
            'provider_id'=>$firebase->uid
        ]);

        return $sUser;
    }

    public function sUserRegistrationInUsers($firebase,$request,$sUser)
    {
        $existUser = User::where('provider_id', $firebase->uid)->where('status','active')->first();
        if($existUser){
            $existUser->sid = $sUser->id;
            $existUser->update();
            return $existUser;
        }

        $user = User::create([
            'name' => $firebase->displayName?$firebase->displayName:"Romoni S User",
            'phone' => $firebase->phoneNumber,
            'email' => $firebase->email,
            'password' => bcrypt(123456),
            'invite_code' => $firebase->email,
            'provider_name'=> $firebase->providerId,
            'provider_id'=>$firebase->uid
        ]);
        $name = explode(" ", $firebase->displayName);
        $first_name = strtolower($name[0]);
        $id = base_convert($user->id, 10, 32);
        $code = $first_name . $id;
        $user->invite_code = $code;
        $user->sid = $sUser->id;
        $user->save();
        if ($request->has('fcm_token')) {
            UserFcm::firstOrCreate(['user_id' => $user->id, 'fcm_token' => $request->fcm_token]);
        }

        return $user;

    }

    public function userRegistrationProcess($firebase,$request)
    {
        $user = User::create([
            'name' => $firebase->displayName?$firebase->displayName:"Romoni User",
            'phone' => $firebase->phoneNumber,
            //'country_code' => $request->country_code,
            'email' => $firebase->email,
            'password' => bcrypt(123456),
            'invite_code' => $firebase->email,
            'provider_name'=> $firebase->providerId,
            'provider_id'=>$firebase->uid
        ]);
        $name = explode(" ", $firebase->displayName);
        $first_name = strtolower($name[0]);
        $id = base_convert($user->id, 10, 32);
        $code = $first_name . $id;
        $user->invite_code = $code;
        $user->save();
        if ($request->has('fcm_token')) {
            UserFcm::firstOrCreate(['user_id' => $user->id, 'fcm_token' => $request->fcm_token]);
        }

        return $user;

    }


    public function connectUserRegistrationProcess($firebase,$request)
    {
        $user = SocialLogin::create([
            'name' => $firebase->displayName?$firebase->displayName:"Romoni Social User",
            'phone' => $firebase->phoneNumber,
            //'country_code' => $request->country_code,
            'email' => $firebase->email,
            'password' => bcrypt(123456),
            'invite_code' => $firebase->email,
            'provider_name'=> $firebase->providerId,
            'provider_id'=>$firebase->uid
        ]);
        $name = explode(" ", $firebase->displayName);
        $first_name = strtolower($name[0]);
        $id = base_convert($user->id, 10, 32);
        $code = $first_name . $id;
        $user->invite_code = $code;
        $user->save();
        if ($request->has('fcm_token')) {
            UserFcm::firstOrCreate(['user_id' => $user->id, 'fcm_token' => $request->fcm_token]);
        }

        return $user;

    }

    public function checkAccessToken(Request $request)
    {
        $user = Auth::check();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Invalid token']);
        }
        if ($user) {
            return response()->json(['success' => true, 'country_code' => $request->user()->country_code, 'message' => 'Valid token']);
        }
    }

    public function switchToken(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Invalid token', 'shouldSwitch' => false, "access_token"  =>'']);
        }

        if(!isset($user->phone)){
            if(!isset($user->sid)){
                return response()->json(['success' => true, 'country_code' => $request->user()->country_code, 'message' => 'Valid token', 'shouldSwitch' => false, "access_token"  =>'']);
            }

            $sUser = SocialLogin::where('id',$user->sid)->first();
            if(!isset($sUser->phone)){
                return response()->json(['success' => true, 'country_code' => $request->user()->country_code, 'message' => 'Valid token', 'shouldSwitch' => false, "access_token"  =>'']);
            }

            if($user->id == $sUser->user_id){
                return response()->json(['success' => true, 'country_code' => $request->user()->country_code, 'message' => 'Valid token', 'shouldSwitch' => false, "access_token"  =>'']);
            }

            $user = User::where('id', $sUser->user_id)->where('status','active')->first();
            if($user){
                $token = $user->createToken('Romoni User')->accessToken;
                return response()->json(['success' => false, 'message' => 'Invalid token', 'shouldSwitch' => true, "access_token"  => $token, "user_id"=> $user->id,]);
            }

        }
        if ($user) {
            return response()->json(['success' => true, 'country_code' => $request->user()->country_code, 'message' => 'Valid token', 'shouldSwitch' => false, "access_token"  =>'']);
        }
    }
}
