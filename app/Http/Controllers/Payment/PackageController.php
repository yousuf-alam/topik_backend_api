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
        foreach ($items as $item)
        {
            $data[]=[
                "id"=>$item->id,
                "name"=>$item->name,
                "title"=>$item->title,
                "description"=>$item->description,
                "colorCode"=>$item->color_code,
            ];
        }
        return  response()->json([
            "success"=>true,
            "message"=>"successfully got all package data",
            "data"=>$data
        ]);

    }
}
