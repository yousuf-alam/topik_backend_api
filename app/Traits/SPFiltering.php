<?php
namespace App\Traits;
use App\Models\Location;
use App\Models\Order\Order;
use App\Models\Service\Lineitem;
use App\Models\Service\Service;
use App\Models\Users\Partner;
use Carbon\Carbon;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;

trait SPFiltering
{

    protected $data, $items, $date, $time, $address, $service_id;


    public function setData($all_data)
    {
        $this->data = $all_data;
        $this->items = $all_data['data'];
        $this->address = $all_data['address'];

        if($all_data['date']=='Regular Delivery' || $all_data['date']=='regular')
        {
            $this->date = $all_data['date'];
        }
        else
        {

            $this->date = Carbon::parse($all_data['date']);
        }


        if(isset($all_data['time']))
        {
            $this->time = $all_data['time'];
        }

    }
    public function getServiceAndWalletPartners()
    {

        $lineitem = Lineitem::where('id',$this->items[0]['lineitem_id'])->first();
        $service_id = $lineitem->service_id;
        $this->service_id = $service_id;
       // Log::debug('service_id', [$service_id]);
        if($service_id==2)
        {
            $type = 'tailor';
        }

        elseif($service_id==1)
        {
            $type = 'beauty';
        }
      /*  elseif ($service_id==4)
        {
            $type = 'medicine-groceries';
        }*/
        else
        {
            $type = 'beauty';
        }

        return Partner::orderBy('plan_id', 'desc')->orderBy('priority', 'desc')->where('status','verified')->where('type',$type)->where('plan_id', '2')->whereIn('booking_type',['on-demand','both'])->get();

    }

    public function FilterBooked($partners)
    {

      $available_partners = [];
      if($this->service_id != 1)
      {
          return $partners;
      }

      foreach ($partners as $partner)
      {
          $orders = Partner::where('id',$partner->id)->whereHas('orders',function ($q){
              $q->where('scheduled_date', $this->date->toDateString())
                ->where('scheduled_time',$this->time)
                ->where('status','accepted');
                })
                ->count();
          $resources = $partner->resource->count();
          $leaves = json_decode($partner->leaves);
          $active_status = $leaves->active_status;


          //leave & online status checking
          if($active_status =='online')
          {
              $active = true;
              if(isset($leaves->from))
              {
                  $leave_from = Carbon::parse($leaves->from);
                  $leave_to   = Carbon::parse($leaves->to);
                  $is_leave = $this->date->between($leave_from,$leave_to);
                  if($is_leave)
                  {
                      $active = false;

                  }

              }
          }
          else
          {
              $active = false;
          }

          if($active && $orders < $resources)
          {
              $available_partners[]= $partner;

          }

      }
      return $available_partners;

    }
    public function FilterWorkingTime($partners)
    {
        $available_partners = [];
        if($this->service_id != 1)
        {
            return $partners;
        }
        foreach ($partners as $partner)
        {

            $weekMap =
                [
                    0 => 'Sunday',
                    1 => 'Monday',
                    2 => 'Tuesday',
                    3 => 'Wednesday',
                    4 => 'Thursday',
                    5 => 'Friday',
                    6 => 'Saturday',
                ];



            $dayOfTheWeek = $this->date->dayOfWeek;
            $weekday = $weekMap[$dayOfTheWeek];

            $working_days = json_decode($partner->working_days);


            if (in_array($weekday, $working_days))
            {

                $working_hour = json_decode($partner->working_hour);
                if ($working_hour)
                {
                    if (in_array($this->time, $working_hour->$weekday->time))
                    {
                        $available_partners[] = $partner;
                    }
                }
            }
        }
        return $available_partners;

    }
    public function FilterLocation($partners)
    {
        $address = $this->address;
        $lat = $address['latitude'];
        $lng = $address['longitude'];
        $location    = $this->getLocation($lat, $lng);
        $available_partners = [];
        if(isset($location))
        {
            $location_id = $location->id;
            foreach ($partners as $partner)
            {
                $areas = json_decode($partner->service_areas);

                if(in_array($location_id, $areas))
                {
                    $available_partners[]= $partner;
                }
            }
        }
        return $available_partners;
    }

    public function FilterService($partners)
    {
        $available_partners = [];
        foreach ($partners as  $partner)
        {
            $partner_total_price =0;
            $partner_flag = 0;
            $items = $this->items;


            foreach($this->items as $key1 => $lineitemData)
            {

                $quantity = $lineitemData['quantity'] ;
                $partner_lineitem = $partner->Lineitems->find($lineitemData['lineitem_id']);


                    if(isset($partner_lineitem))
                    {
                        if ($partner_lineitem->pricing_type == "fixed")
                        {
                            $partner_flag++;
                            $partner_total_price += $partner_lineitem->pivot->price * $quantity;
                        }
                        else
                        {
                            $price_table = json_decode($partner_lineitem->pivot->price_table);
                            $number_of_questions = count(json_decode($partner_lineitem->options));

                            foreach ($price_table as $price_key => $price) {
                                $price_flag = 0;
                                foreach ($lineitemData['answer'] as $key2 => $answer) {
                                    if ($price->{'name' . $key2} == $answer['ans']) {
                                        $price_flag++;
                                    }
                                }

                                if ($price_flag == $number_of_questions) {
                                    $partner_flag++;
                                    $partner_total_price += (int) $price->price * (int) $quantity;
                                    break;
                                }
                            }
                        }
                    }


            }

            if(count($this->items)== $partner_flag)
            {
                if($partner->rating>0)
                    $rating = number_format($partner->rating, 1, '.', ',');
                else
                    $rating = "5.0";

                switch ($partner_lineitem->category_id)
                {
                    case 26:
                        $delivery_charge = 30;
                        break;
                    case 27:
                        $delivery_charge = 50;
                        break;
                    case 30:
                        $delivery_charge = 60;
                        break;
                    case 31:
                        $delivery_charge = 40;
                        break;
                    default:
                        $delivery_charge = 0;
                        break;
                }

                $url = env('DO_SPACES_EDGE');
                $available_partners[] = [
                    "id"      =>  $partner->id,
                    "name"    =>  $partner->name,
                    "avatar"   =>  $url.'/'.$partner->avatar,
                    'rating'  =>  $rating,
                    "price"   =>  $partner_total_price,
                    "delivery_charge" => $delivery_charge
                ];
            }
        }
        return $available_partners;
    }
    public function FilterWallet($partners)
    {
        return $partners->where('balance','>=',0);

    }
    public  function MapDistance($lat1, $lon1, $lat2, $lon2, $unit) {

        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        $unit = strtoupper($unit);

        if ($unit == "K") {
            return ($miles * 1609.344);
        } else if ($unit == "N") {
            return ($miles * 0.8684);
        } else {
            return $miles;
        }
    }
    public  function getCity($lat,$lng)
    {

        try {
            $client = new \GuzzleHttp\Client();
            $url = 'https://maps.googleapis.com/maps/api/geocode/json?latlng='.$lat.','.$lng.'&sensor=true&key=AIzaSyCtRtEW4nWKAtW6RH8bU9WkWatB5TSQMEw';
            $response = $client->request('GET', $url);

            $results = \GuzzleHttp\json_decode($response->getBody()->getContents(),true);
            $length = sizeof($results['results']);
            // return $results['results'][5]['formatted_address'];
            $city_name = $results['results'][$length-3]['formatted_address'];
            // return $city_name;
            $city = str_replace(' District, Bangladesh', '',$city_name);

            return $city;

        }
        catch (ClientException $exception) {
            $responseBody = $exception->getResponse()->getBody();
            return $responseBody;

        }
    }

    public function getLocation($lat, $lng)
    {

        $min_distance = 99999999;
        $city = $this->getCity($lat,$lng);
        if($city!='Dhaka' && $city!='Chittagong' && $city!='Gazipur')
        {
            return null;
        }
        if($city=='Gazipur')
            $city = 'Dhaka';
        $locations = Location::where('city',$city)->get();
        foreach ($locations as $location)
        {
            $loc_lat = $location->latitude;//23.795364379882812
            $loc_lng = $location->longitude;//90.50888061523438
            $distance =  $this->MapDistance($lat,$lng,$loc_lat, $loc_lng, 'K');

            if($distance <= $location->radius && $distance < $min_distance)
            {
                $min_distance = $distance;
                $selected_location = $location;
            }
            else
            {
                if($distance<$min_distance)
                {
                    $min_distance = $distance;
                    $nearest_location = $location;
                }

            }

        }
        if(isset($selected_location))
        {
            return $selected_location;
        }
        else
        {
            return $nearest_location;
        }

    }

}
