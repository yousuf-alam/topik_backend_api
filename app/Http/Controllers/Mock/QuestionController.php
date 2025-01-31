<?php

namespace App\Http\Controllers\Mock;

use App\Http\Controllers\Controller;
use App\Imports\OfferImport;
use App\Imports\QuestionImport;
use App\Models\Mock\Option;
use App\Models\Mock\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class QuestionController extends Controller
{
    public function importQuestion(Request $request)
    {


        try {
            $file = $request->file('excel_file');
            $type= $request->type;



            Excel::import(new QuestionImport($type), $file);

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

    public function allQuestion()
    {
        $data=Question::all();
        return response()->json([
            "success"=>true,
            "message"=>"all question",
            "data"=>$data
        ]);

    }

    public function questionById(Request $request,$id)
    {
        $question = Question::with('options')->find($id);

        return response()->json([
            "success"=>true,
            "message"=>"question with options",
            "data"=>$question
        ]);

    }

    public function updateQuestion(Request $request,$id)
    {

        // Validate input


        try {
            DB::beginTransaction();

            // Find question
            $question = Question::find($id);
            if (!$question) {
                return response()->json(['success' => false, 'message' => 'Question not found'], 404);
            }

            // Update question fields
            $question->update([
                'title' => $request->title,
                'type' => $request->type,
                'difficulty_level' => $request->difficulty_level,
                'right_answer' => $request->right_answer,
                'image' => $request->image ?? $question->image,
                'audio' => $request->audio ?? $question->audio,
            ]);

            // Update options
            foreach ($request->options as $optionData) {
                if (isset($optionData['id'])) {
                    // Update existing option
                    Option::where('id', $optionData['id'])->update([
                        'title' => $optionData['title'],
                    ]);
                } else {
                    // Create new option
                    Option::create([
                        'question_id' => $question->id,
                        'title' => $optionData['title'],
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Question updated successfully',
                'data' => $question->load('options'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
