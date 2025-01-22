<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Mock\Mock;
use App\Models\User\Wallet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;


class UserController extends Controller
{
    public function userProfile(Request $request)
    {
        $user=$request->user();
        $wallet =[
        'gems'  => $user->wallet?->gems ?? 0,
        'coins' => $user->wallet?->coins ?? 0,
    ];
//        $user['wallet']=$wallet;

        return response()->json([
            "success"=>true,
            "message"=>"User Profile",
            "data"=>$user,

        ]);

    }

    public function availableMock(Request $request)
    {
        $user=$request->user();
        $now = Carbon::now();
        $items=Mock::where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->where('mock_type','=','practice')
            ->get();;
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
                'id'=>$item->id,
                'title'=>$item->title,
                'description'=>$item->description,
                'image'=>$item->image ?? "",
                'coin_fee'=>$item->coin_fee,
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
    public function upComingMock(Request $request)
    {
        $user=$request->user();
        $items=Mock::where('mock_type','=','practice')
            ->whereDate('start_time', '>', Carbon::now())
            ->get();
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
                'id'=>$item->id,
                'title'=>$item->title,
                'description'=>$item->description,
                'image'=>$item->image ?? "",
                'coin_fee'=>$item->coin_fee,
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

    public function readingPracticeMock(Request $request)
         {
             $user=$request->user();
             $items=Mock::where('mock_type','=','reading')->get();
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
                     'id'=>$item->id,
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
    public function listeningPracticeMock(Request $request)
    {
        $user=$request->user();
        $items=Mock::where('mock_type','=','listening')->get();
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
                'id'=>$item->id,
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
