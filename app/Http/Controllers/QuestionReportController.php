<?php

namespace App\Http\Controllers;

use App\Models\QuestionReport;
use Illuminate\Http\Request;

class QuestionReportController extends Controller
{
    public function submitReport(Request $request)
    {
        $user=$request->user();
        $data= new QuestionReport();
        $data->user_id=$user->id;
        $data->question_id=$request->question_id;
        $data->comment=$request->comment;
        $data->save();
        return response()->json([
            "success"=>true,
            "message"=>"question report submitted",
            "data"=>$data
        ]);
    }

}
