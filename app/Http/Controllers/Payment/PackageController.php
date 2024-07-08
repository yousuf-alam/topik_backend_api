<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Admin\Package;
use Illuminate\Http\Response;

class PackageController extends Controller
{
    public function getUserPackage()
    {
        $items=Package::all();
        if(!$items)
        {
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
                "id"=>$item->id,
                "title"=>$item->title,
                "oldPrice"=>$item->old_price,
                "price"=>$item->price,
                "description"=>$item->description,
                "colorCode"=>$item->color_code ?? "",
                "duration"=>$item->duration_days
            ];
        }
        return  response()->json([
            "success"=>true,
            "message"=>"successfully got all package data",
            "data"=>$data
        ]);

    }
}
