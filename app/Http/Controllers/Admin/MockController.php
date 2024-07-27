<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gameplay\Tournament;
use App\Models\Mock\Mock;
use App\Models\Mock\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class MockController extends Controller
{
    public function createMock(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'description' => 'required',
            'duration' => 'required',
            'total_question' => 'required',
            'start_time' => 'required',
            'end_time' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
        $questions = Question::inRandomOrder()
            ->limit($request->total_question)
            ->pluck('id')
            ->toArray();



        $data = new Mock();
        $data->title = $request->title;
        $data->description = $request->description;
        $data->duration = $request->duration;
        $data->questions = json_encode($questions);
        $data->total_question = $request->total_question;
        $data->passing_percentage = $request->passing_percentage;
        $data->start_time = $request->start_time;
        $data->end_time = $request->end_time;
        $data->status = $request->status;
        $data->save();

        return response()->json([
            'message' => 'Mock created successfully',
            'success' => true,
            'data' => $data
        ], Response::HTTP_CREATED);

    }

public function mockPlay(Request $request)
    {

        $mock = Mock::find($request->mock_id);

        if (!$mock) {
            return response()->json([
                'message' => 'Mock not found',
                'success' => false
            ], Response::HTTP_NOT_FOUND);
        }

        $questions = $this->getQuestionOptions($mock);

        $data = [
            'mock_id' => $mock->id,
            'title' => $mock->title,
            'description' => $mock->description,
            'duration' => $mock->duration,
            'total_question' => $mock->total_question,
            'passing_percentage' => $mock->passing_percentage,
            'questions' => $questions
        ];
        return response()->json([
            'message' => 'Mock fetched successfully',
            'success' => true,
            'data' => $data
        ], Response::HTTP_OK);
    }




    protected function getQuestionOptions($mock)
    {
        $questionIds= json_decode($mock->questions);

        $questions = Question::whereIn('id', $questionIds)->get();
      

        $questions_data = [];
        $count = 0;
        foreach ($questions as $question) {

            $count++;
            $options = $question->options;
            $question_data = [
                'question_id' => $question->id,
                'type' => $question->type,
                'answer_id' => $question->right_answer,
                'timer' => $question->time ?? 10,
                'question_no' => $count,
                'questions_left' => count($questions)  - $count,
                'title' => $question->title,
                'image' => $question->image ?? '',
                'audio' => $question->audio ?? '',
            ];
            $options_data = [];
            foreach ($options as $option) {

                $options_data[] = [
                    'id' => $option->id,
                    'title' => $option->title,
                    'image' => $option->image ?? "",
                    'audio' => $option->audio ?? ""

                ];
            }
            $question_data['options'] = $options_data;
            $questions_data[]=$question_data;
        }
        return $questions_data;
    }
}
