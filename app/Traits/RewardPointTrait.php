<?php

namespace App\Traits;


use App\Models\Service\Service;
use App\Models\Users\RewardHistory;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;


trait RewardPointTrait
{

    protected $base_bronze = 0, $base_silver = 200, $base_gold = 500;
    protected $earning_rate_bronze = 0.02, $earning_rate_silver = 0.03 , $earning_rate_gold = 0.05;
    protected $redeem_rate = 1.5, $max_redeem_allowed = 0.5, $min_redeem_allowed = 50,$min_balance_to_redeem = 80;

    public function getAllInfo()
    {
        return array(
            'base_bronze' => $this->base_bronze,
            'base_silver' => $this->base_silver,
            'base_gold'     => $this->base_gold,
            'earning_rate_bronze' => $this->earning_rate_bronze,
            'earning_rate_silver'  => $this->earning_rate_silver,
            'earning_rate_gold'    => $this->earning_rate_gold,
            'redeem_rate'           => $this->redeem_rate,
            'max_redeem_allowed'    => $this->max_redeem_allowed,
            'min_redeem_allowed'    => $this->min_redeem_allowed,
            'min_balance_to_redeem' => $this->min_balance_to_redeem
        );
    }
    public function applyPoint($request)
    {
        $user = $request->user();

        if(!isset($request->reward_point))
        {
            return array(
                'success'   => false,
                'message'   => 'reward_point/order_amount field missing'
            );
        }
        elseif($user->reward_point < $this->min_redeem_allowed)
        {
            return array(
                'success'   => false,
                'message'   => 'Minimum reward point balance should be '.$this->min_balance_to_redeem
            );
        }
        elseif ($request->reward_point < $this->min_redeem_allowed)
        {
            return array(
                'success'   => false,
                'message'   => 'Minimum '.$this->min_redeem_allowed.' points are needed to redeem for an order'
            );
        }
        elseif ( ceil($request->order_amount * $this->max_redeem_allowed) <= ceil($request->reward_point * $this->redeem_rate))
        {
            return array(
                'success'   => false,
                'message'   => 'You cannot use more than '.ceil($request->order_amount * $this->max_redeem_allowed / $this->redeem_rate ).' points for this order'
            );
        }
        else
        {
            return array(
                'success'  => true,
                'discount' => ceil($request->reward_point * $this->redeem_rate),
                'message'  => 'Reward point applied successfully'
            );
        }

    }
    public function setEarningPoint($user,$order_amount)
    {
        $point_balance = $user->reward_points;
        if($point_balance < $this->base_silver)
        {
            $earning_point = ceil($order_amount * $this->earning_rate_bronze);
        }
        elseif ($point_balance >= $this->base_silver && $point_balance < $this->base_gold)
        {
            $earning_point = ceil($order_amount * $this->earning_rate_silver);
        }
        else
        {
            $earning_point = ceil($order_amount * $this->earning_rate_gold);
        }
        return $earning_point;
    }
    public function getHistory($request)
    {
        $user = $request->user();
        $order_histories = $user->RewardHistory->where($request->type,'!=', null)->where('service','order');
        $b2c_histories = $user->RewardHistory->where($request->type,'!=', null)->where('service','b2c');
        $data = [];
        foreach ($order_histories as $history)
        {

            $data[] = [
              'point'          => $history[$request->type],
              'created_at'     => $history->created_at,
              'date_created'   => $history->created_at->diffForHumans(),
              'service'        => $history->order->service->name,
              'crypt_order_id' => $history->order->crypt_order_id
            ];
        }
        if(count($b2c_histories))
        {
            $order_ids = $b2c_histories->pluck('order_id');
            $body = array('include' => $order_ids);
            $client = new Client();
            $response = $client->request('GET', env('B2C_URL').'/index.php/wp-json/wc/v3/orders',
                [
                    'auth' => [env('B2C_KEY'), env('B2C_SECRET')],
                    'json' => $body
                ]);
            $response_json = \GuzzleHttp\json_decode($response->getBody()->getContents());
            Log::info('res',[$response_json]);
            $b2c_collection = collect($response_json);
            $b2c_histories = $b2c_histories->map(function ($item) use ($b2c_collection,$request) {
                  $b2c_order = $b2c_collection->where('id',$item->order_id)->toArray();
                  $item['point']          = $item[$request->type];
                  $item['created_at']     = $item->created_at;
                  $item['date_created']     = $item->created_at->diffForHumans();
                  $item['service']        = 'Romoni Shop';
                  $item['crypt_order_id'] = implode('',array_column($b2c_order,"order_key"));
                return $item;
            });
            Log::info('$b2c_histories',[$b2c_histories]);
            $data = (array_merge($data, $b2c_histories->toArray()));
            $data = json_decode(json_encode($data));
            usort($data, function($a, $b) {
                return $a->created_at <=> $b->created_at;
            });
            Log::info('$data',[$data]);
        }
        return response()->json($data);

    }
    public function rewardDeduct($order)
    {
        $user = $order->user;
        $points = abs($order->reward_points);
        $user->reward_point -= $points;
        $user->update();

        $reward = new RewardHistory();
        $reward->debit = $points;
        $reward->balance = $user->reward_point;
        $reward->user_id = $user->id;
        $reward->order_id = $order->id;
        $reward->save();
    }
    public function getLabel($point)
    {
        if($point < $this->base_silver)
        {
            $label = 'BRONZE';
        }
        elseif ($point >= $this->base_silver && $point < $this->base_gold)
        {
            $label = 'SILVER';
        }
        else
        {
            $label = 'GOLD';
        }
        return $label;
    }



}
