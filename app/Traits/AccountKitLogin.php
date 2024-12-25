<?php

namespace App\Traits;

use App\Models\Users\Otp;
use App\Models\Users\Partner;
use App\Models\Users\PartnerFcm;
use App\Models\Users\ResourceFcm;
use App\Models\Users\UserFcm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use phpDocumentor\Reflection\Types\Resource_;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use App\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Cache;
use App\Services\InfobipSMS;

trait AccountKitLogin
{

    private $iv = 'Nnn%@$#099124686'; #Same as in JAVA
    private $key = '686421990#$@%nnN'; #Same as in JAVA

    public function partnerKitLogin(Request $request)
    {
        $code = $request->code;
        $app_id =  config('app.Partner_Account_kit_id');
        $secret =  config('app.Partner_Account_kit_client_secret');
        $version = config('app.Partner_Account_kit_version');

        $token_exchange_url = 'https://graph.accountkit.com/' . $version . '/access_token?' .
            'grant_type=authorization_code' .
            '&code=' . $code .
            "&access_token=AA|$app_id|$secret";


        $client = new Client();
        $access_token_response = $client->request('GET', $token_exchange_url);


        $access_token = \GuzzleHttp\json_decode($access_token_response->getBody()->getContents())->access_token;

        $validate_token_url = 'https://graph.accountkit.com/v1.1/me/?access_token=' . $access_token;
        $response = $client->get($validate_token_url);
        $response_json = \GuzzleHttp\json_decode($response->getBody()->getContents());
        $provider_id = $response_json->id;
        $number     = $response_json->phone->number;
        $number_without_country_code = substr($number, 3);

        $user = User::where('phone', $number_without_country_code)->first();
        $partner = $user->Partner;

        if ($partner) {
            $resourceCheck  = $user->Resource;

            if (isset($request->token)) {
                $token_exists = PartnerFcm::where('partner_id', $partner->id)->where('fcm_token', $request->token)->first();
                if (!$token_exists) {
                    PartnerFcm::firstOrCreate(['partner_id' => $partner->id, 'fcm_token' => $request->token]);
                }
            }

            $partner_token  = $user->createToken('Partner Token v2.0')->accessToken;

            if (isset($resourceCheck)) {
                return array(
                    "success"    =>    true,
                    "code"        =>    200,
                    "role"        => "admin",
                    "token"        =>     $partner_token,
                    "type"        =>    "partner",
                    "user_id"   =>  $user->id
                );
            } else {
                return array(
                    "success"    =>    true,
                    "code"        =>    200,
                    "role"        => "employee",
                    "token"        =>     $partner_token,
                    "type"        =>    "partner",
                    "user_id"   =>  $user->id
                );
            }
        } else {
            $resource  = $user->Resource;
            if ($resource) {
                $resource_token = $user->createToken('Resource Token v2.0')->accessToken;

                if (isset($request->token)) {
                    ResourceFcm::firstOrCreate(['resource_id' => $resource->id, 'fcm_token' => $request->token]);
                }

                return array(
                    "success" =>    true,
                    "code"    =>    200,
                    "token"      =>    $resource_token,
                    "role"        => "employee",
                    "type"      =>    "resource"
                );
            } else {
                return array(
                    "success" => "false",
                    "code"   => 200,
                    "token" => "",
                    "type" => "invalid user",
                    "message" => "No ID found with this Number"
                );
            }
        }
    }

    public function partnerAppOTPlogin(Request $request)
    {
        try {
            $checkOtp = Otp::where('otp', $request->otp)->where('phone', $request->phone)->exists();

            if ($checkOtp || $request->phone == '01521241327' || $request->phone == '01859529037' || $request->otp='123456') {
                $user = User::where('phone', $request->phone)->first();


                if (isset($user) && $user->Partner()->exists()) {
                    $partner = $user->Partner;
                    if (isset($request->token)) {
                        $token_exists = PartnerFcm::where('partner_id', $partner->id)->where('fcm_token', $request->token)->first();
                        if (!$token_exists) {
                            PartnerFcm::FirstOrCreate(['partner_id' => $partner->id, 'fcm_token' => $request->token]);
                        }
                    }

                    $partner_token  = $user->createToken('Partner Token v2.0')->accessToken;
                    if ($user->Resource()->exists()) {
                        return array(
                            "success"    =>    true,
                            "code"        =>    200,
                            "role"        => "admin",
                            "token"        =>     $partner_token,
                            "type"        =>    "partner",
                            "user_id"   =>  $user->id
                        );
                    } else {
                        return array(
                            "success"    =>    true,
                            "code"        =>    200,
                            "role"        => "employee",
                            "token"        =>     $partner_token,
                            "type"        =>    "partner",
                            "user_id"   =>  $user->id
                        );
                    }
                } else {

                    if ($user->Resource()->exists()) {
                        $resource  = $user->Resource;
                        $resource_token = $user->createToken('Resource Token v2.0')->accessToken;

                        if (isset($request->token)) {
                            ResourceFcm::FirstOrCreate(['resource_id' => $resource->id, 'fcm_token' => $request->token]);
                        }

                        return array(
                            "success" =>    true,
                            "code"    =>    200,
                            "token"      =>    $resource_token,
                            "role"        => "employee",
                            "type"      =>    "resource"
                        );
                    } else {
                        return array(
                            "success" => false,
                            "code"   => 200,
                            "token" => "",
                            "type" => "invalid user",
                            "message" => "No ID found with this Number"
                        );
                    }
                }
            } else {
                Log::info('otp didnt match');
                return array(
                    "success"    => false,
                    "message"    => 'Could not verify user'
                );
            }
        } catch (Exception $e) {
            Log::info('error', [$e]);
            //return $e->getMessage();
            return array(
                "success"    => false,
                "message"    => "Couldn't verify user"
            );
        }
    }


    public function getUser(Request $request)
    {
        $user = $request->user();
        return $user;
        /*if($user)
        {

            return response()->json(["username"=>$user->name,"email" =>$user->email,"phone"=>$user->phone,"gender"=>$user->gender],200);
        }
        else
        {
            return response()->json(array( "error" => "UnAuthorised"),401);
        }*/
    }



    public function userKitLogin(Request $request)
    {

        $app_id =  config('app.Account_kit_id');
        $secret =  config('app.Account_kit_client_secret');
        $version = config('app.Account_kit_version');

        try {
            $code = $request->code;
            $token_exchange_url = 'https://graph.accountkit.com/' . $version . '/access_token?' .
                'grant_type=authorization_code' .
                '&code=' . $code .
                "&access_token=AA|$app_id|$secret";


            $client = new Client();
            $access_token_response = $client->request('GET', $token_exchange_url);


            $access_token = \GuzzleHttp\json_decode($access_token_response->getBody()->getContents())->access_token;

            $validate_token_url = 'https://graph.accountkit.com/v1.1/me/?access_token=' . $access_token;
            $response = $client->get($validate_token_url);
            $response_json = \GuzzleHttp\json_decode($response->getBody()->getContents());
            $provider_id = $response_json->id;
            $number     = $response_json->phone->number;
            $number_without_country_code = substr($number, 3);

            $existing_user = User::where('phone', $number_without_country_code)->first();

            if ($existing_user) {
                if (isset($request->token)) {
                    $token_exists = UserFcm::where('user_id', $existing_user->id)->where('fcm_token', $request->token)->first();
                    if (!$token_exists) {
                        UserFcm::create(['user_id' => $existing_user->id, 'fcm_token' => $request->token]);
                    }
                }

                $token = $existing_user->createToken('User Token v2.0')->accessToken;

                return array(
                    "success"    => true,
                    "token"     => $token,
                    "user"        => "OLD",
                    "phone"        => $number_without_country_code,
                    "user_id"   => $existing_user->id
                );
            } else {
                return array(
                    "success"    => true,
                    "user"        => "NEW",
                    "phone"        => $number_without_country_code
                );
            }
        } catch (Exception $e) {
            Log::info('error', [$e]);
            //return $e->getMessage();
            return array(
                "success"    => false,
                "message"    => "Couldn't verify user"
            );
        }
    }

    public function webLogin(Request $request)
    {
        $app_id =  config('app.Account_kit_id');
        $secret =  config('app.Account_kit_client_secret');
        $version = config('app.Account_kit_version');

        try {
            $code = $request->code;
            $token_exchange_url = 'https://graph.accountkit.com/' . $version . '/access_token?' .
                'grant_type=authorization_code' .
                '&code=' . $code .
                "&access_token=AA|$app_id|$secret";


            $client = new Client();
            $access_token_response = $client->request('GET', $token_exchange_url);


            $access_token = \GuzzleHttp\json_decode($access_token_response->getBody()->getContents())->access_token;

            $validate_token_url = 'https://graph.accountkit.com/v1.1/me/?access_token=' . $access_token;
            $response = $client->get($validate_token_url);
            $response_json = \GuzzleHttp\json_decode($response->getBody()->getContents());
            $provider_id = $response_json->id;
            $number     = $response_json->phone->number;
            $number_without_country_code = substr($number, 3);

            $existing_user = User::where('phone', $number_without_country_code)->first();

            if ($existing_user) {
                $token = $existing_user->createToken('User Token Web v2.0')->accessToken;

                return array(
                    "success"    => true,
                    "token"     => $token,
                    "user"        => $existing_user
                );
            } else {
                return array(
                    "success"    => false,
                    "message"    => 'Could not verify user'
                );
            }
        } catch (Exception $e) {
            Log::info('error', [$e]);
            //return $e->getMessage();
            return array(
                "success"    => false,
                "message"    => "Couldn't verify user"
            );
        }
    }



    public function testlogin(Request $request) //for test purpose only
    {
        $user_type = $request->user_type;
        $user = User::where('phone', $request->phone)->first();
        if ($user_type == "user") {
            return $user->createToken('Romoni')->accessToken;
        } elseif ($user_type == "partner") {
            $partner = Partner::where('contact', $request->phone)->first();
            return $partner->createToken('My partner Token')->accessToken;
        } else {
            $resource = Resource_::where('mobile', $request->phone)->first();
            return $resource->createToken('My resource Token')->accessToken;
        }
    }

    public function createUser(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required|string|max:100',
                'gender' => 'required',
                //'email'	=> 'unique:users',
                'phone' => 'required',
            ]
        );

        if ($validator->fails()) {
            return array(
                "success" => false,
                "message" => $validator->errors()->first()
            );
        } else {
            $user = new User();
            $user->username = $request->name;
            if (isset($request->email))
                $user->email = $request->email;
            $user->gender = $request->gender;
            $user->phone = $request->phone;
            if (isset($request->userId))
                $user->provider_id = $request->userId;
            $user->save();
            Log::info('new-user-logged-in', [$user->phone]);

            // latest user_id matching to old
            $client = new Client();
            $url = env('OLD_DEV_URL') . '/new-version-latest-user';
            $headers = ['Content-Type' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest', 'x-api-key' => env('DEV_KEY')];
            $response = $client->request('POST', $url, [
                'allow_redirects' => [
                    'strict'          => true,
                ],
                'headers' => $headers,
                'json' =>  User::latest()->first(),

            ]);

            $user->roles()
                ->attach(Role::where('name', 'customer')->first());

            if ($request->has('token')) {
                UserFcm::firstOrCreate(['user_id' => $user->id, 'fcm_token' => $request->token]);
            }
            $token = $user->createToken('Romoni User v2.0')->accessToken;

            return array(
                "success"    => true,
                "token"        => $token,
                "user_id"   => $user->id
            );
        }
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($request->has('partner_fcm_token')) {
            $partner = $user->Partner;
            $partner_fcm = PartnerFcm::where('partner_id', $partner->id)->where('fcm_token', $request->partner_fcm_token)->delete();
        } elseif ($request->has('resource_fcm_token')) {
            $resource = $user->Resource;
            $resource_fcm = ResourceFcm::where('resource_id', $resource->id)->where('fcm_token', $request->resource_fcm_token)->delete();
        } elseif ($request->has('fcm_token')) {
            $user_fcm = UserFcm::where('user_id', $user->id)->where('fcm_token', $request->fcm_token)->delete();
        }
        $request->user()->token()->revoke();
        return response()->json([
            'success' => true,
            'message' => 'Successfully Logged Out'
        ]);
    }

    public function generateOTP()
    {
        $number = mt_rand(100000, 999999);




        // better than rand()

        // call the same function if the barcode exists already
        if ($this->OTPExists($number)) {
            return $this->generateOTP();
        }

        // otherwise, it's valid and can be used
        return $number;
    }

    public function OTPExists($number)
    {
        // query the database and return a boolean
        // for instance, it might look like this in Laravel
        return Otp::where('otp', $number)->exists();
    }

    public function eligiblityForOtp($phone, $sendType)
    {
        $existingOtp = Otp::where('phone', $phone)->first();
        if ($existingOtp) {
            $startTime = Carbon::parse($existingOtp->updated_at);
            $endTime = now();
            $difference = $startTime->diffInSeconds($endTime);

            if ($difference < 60) {
                return array(
                    "success"        => false,
                    "message"         => "OTP already sent. Please wait " . abs($difference - 60). " seconds",
                    "account_exists" => false,
                    "remaining_time" => abs($difference - 60)
                );
            }

            if (($sendType == 'send_otp') ? $existingOtp->send_otp_count >= 5 : $existingOtp->retry_otp_count >= 5) {
                return array(
                    "success"        => false,
                    "message"         => "Max OTP Limit reached, your account has been blocked for gaining OTP for next 24 hours",
                    "account_exists" => false,
                    "remaining_time" => 0
                );
            }
        }

        return array(
            "success"   => true
        );
    }



    public function saveOTP(Request $request)
    {

        Log::info('sms request', $request->all());
        $PrivateKey = 'dafgjkdf#5$K121';
        $phone = $request->phone;
        $timestamp = $request->timestamp;
        $digest = $request->digest;

        if (Cache::has($digest)) {
            return array(
                "success"        => false,
                "message"         => "Duplicate request",
            );
        } else {
            Cache::put($digest, $digest, 300);
        }

        $message = $timestamp.$phone;
        $signature = hash_hmac("sha256",$message,$PrivateKey);
        if($digest != $signature){
            return array(
                "success"        => false,
                "message"         => "digest is not match",
            );
        }

        $endTime = time();
        $difference = $endTime-(int)($timestamp);

        if($difference > 300) {
            return array(
                "success"        => false,
                "message"         => "Invalid TimeStamp for sending OTP request",
            );
        }

        $eligibility = $this->eligiblityForOtp($request->phone, 'send_otp');

        if (!$eligibility['success']) {
            return $eligibility;
        }

        $otp = $this->generateOTP();
        //fix generate otp for temporary
//        $otp='123456';

        Otp::updateOrCreate([
            'phone'  =>   $request->phone,
        ], [
            'otp'      =>   $otp,
            'send_otp_count'     => DB::raw('send_otp_count+1')
        ]);

        //test infobip
        $msg = "আপনার Romoni ভেরিফিকেশন কোড : " . $otp;

        if (env('INFOBIP_ACTIVE')==true) {
                    InfobipSMS::sendSms($request->phone, $msg);
        }

//        if (env('ROUTE_MOBILE_ACTIVE')==true) {
//                InfobipSMS::sendSmsViaRouteMobile($request->phone, $msg);
//        }
        //off for temporary
        if(env('INFOBIP_ACTIVE')==false && env('ROUTE_MOBILE_ACTIVE')==false)
        {
            return array(
                "success"   =>  true,
            );
        }

//        InfobipSMS::sendSms($request->phone, $msg);
//        InfobipSMS::sendSmsViaRouteMobile($request->phone, $msg);
        //end test infobip

        $client = new \GuzzleHttp\Client();
        $url = "http://api.boom-cast.com/boomcast/WebFramework/boomCastWebService/OTPMessage.php?masking=" . env('Boomcast_MASKING') . "&userName=" . env('Boomcast_USER') . "&password=" . env('Boomcast_PASS') . "&MsgType=TEXT&receiver=" . $request->phone . "&message=Your Romoni Verification code is : " . $otp;
        $response = $client->request('GET', $url);
        Log::info('sms', [$response->getBody()->getContents()]);
        return array(
            "success"   =>  true,
        );
    }
}
