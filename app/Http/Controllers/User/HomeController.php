<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Mock\Mock;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;


class HomeController extends Controller
{
    public function availableMock(Request $request)
    {
        $user=$request->user();
        $items=Mock::where('end_time','>',Now())->get();
        if(!$items){
            return response()->json([
                'error'=>[
                    'status_code'=>Response::HTTP_NOT_FOUND,
                    'error_code'=>'no_mock_found',
                    'error_message'=>'No Mock Found',
                ]
            ],Response::HTTP_NOT_FOUND);
        }
        $data=[];

        foreach ($items as $item)
        {
            $data[]=[
                'title'=>$item->title,
                'description'=>$item->description,
                'image'=>$item->image ?? "",
                'total_question'=> $item->total_question,
                'duration'=>$item->duration,
                'is_already_given'=>false,
                'is_worthy'=>true

            ];

        }
        return  response()->json([
            'success'=>true,
            'message'=>"successfully got data",
            'data'=>$data
        ]);


    }
}
