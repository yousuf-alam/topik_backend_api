<?php

namespace App\Http\Controllers\Mock;

use App\Http\Controllers\Controller;
use App\Imports\OfferImport;
use App\Imports\QuestionImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class QuestionController extends Controller
{
    public function importQuestion(Request $request)
    {


        try {
            $file = $request->file('excel_file');

            Excel::import(new QuestionImport(), $file);

            return response()->json([
                'success' => 'true',
                'message' => 'Import Successful'
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => 'false',
                'message' => 'Import Failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
