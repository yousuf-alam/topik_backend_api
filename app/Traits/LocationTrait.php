<?php
namespace App\Traits;
use App\Models\Location;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;

trait LocationTrait
{
    public  function gmapDistance($lat1, $lon1, $lat2, $lon2, $unit) {

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
    public  function getCityName($lat,$lng)
    {

        try {
            $client = new \GuzzleHttp\Client();
            $url = 'https://maps.googleapis.com/maps/api/geocode/json?latlng='.$lat.','.$lng.'&sensor=true&key=AIzaSyCtRtEW4nWKAtW6RH8bU9WkWatB5TSQMEw';
            $response = $client->request('GET', $url);

                $results = \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
                $length = sizeof($results['results']);
                $city_name = $results['results'][$length-3]['formatted_address'];
                $city = str_replace(' District, Bangladesh', '',$city_name);

                return $city;

        }
        catch (ClientException $exception) {
            $responseBody = $exception->getResponse()->getBody();
            return $responseBody;

        }
    }

    public function findLocation($lat, $lng)
    {

        $min_distance = 99999999;
        $city = self::getCityName($lat,$lng);

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
            $distance = self::gmapDistance($lat,$lng,$loc_lat, $loc_lng, 'K');

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
