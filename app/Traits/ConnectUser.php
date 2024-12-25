<?php
namespace App\Traits;
use App\Models\Location;
use App\Models\Order\Order;
use App\Models\Service\Lineitem;
use App\Models\Service\Service;
use App\Models\Users\Partner;
use App\Models\Users\UserFcm;
use App\SocialLogin;
use App\User;
use Carbon\Carbon;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;

trait ConnectUser
{

    protected $data, $items, $date, $time, $address, $service_id;


    public function connectUser($orderId)
    {
        $order = Order::where('id',$orderId)->first();
        $user = User::where('id',$order->user_id)->first();

        //valid user
        if(isset($user->phone) && $user->phone != ''){
            return $user->id;
        }

        $phone = $user->phone;
        //get social user
        if(isset($user->sid)){
            $sUser = SocialLogin::where('id',$user->sid)->first();
            $phone = $sUser->phone;
        }

        //phone is exist
        $exists = User::where('phone', $phone)->where('phone','!=','')->first();

        if($exists){
           if($user->id != $exists->id){
               $order->user_id = $exists->id;
               $order->update();
               if(isset($user->sid)){
                   $sUser = SocialLogin::where('id',$user->sid)->first();
                   $sUser->user_id = $exists->id;
                   $sUser->update();

                   $exists->sid = $sUser->id;
                   $exists->update();

                   //update user fcm
                   UserFcm::where('user_id',$user->id)->update(['user_id'=> $exists->id]);

               }

               return $exists->id;
           }
        }

        if(!$exists){
            $user->phone = $sUser->phone;
            $user->update();
        }

        return $user->id;
    }



}
