<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gameplay\Tournament;
use App\Models\Mock\Mock;
use App\Models\Mock\MockUser;
use App\Models\Mock\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
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

        if($request->mock_type==='reading')
        {
            $questions = Question::inRandomOrder()
                ->limit($request->total_question)
                ->where('type','reading')
                ->pluck('id')
                ->toArray();

        }
        else if($request->mock_type==='listening')
        {
            $questions = Question::inRandomOrder()
                ->limit($request->total_question)
                ->pluck('id')
                ->toArray();

        }
        else {
            $questions = Question::inRandomOrder()
                ->limit($request->total_question)
                ->pluck('id')
                ->toArray();

        }




        $data = new Mock();
        $data->title = $request->title;
        $data->description = $request->description;
        $data->duration = $request->duration;
        $data->questions = json_encode($questions);
        $data->total_question = $request->total_question;
        $data->passing_percentage = $request->passing_percentage;
        $data->start_time = $request->start_time;
        $data->end_time = $request->end_time;
        $data->mock_type = $request->mock_type;
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
                'image' => $question->image ? asset('reading').'/'.$question->image : '',
                'audio' => $question->audio ? asset('audio'.'/'.$question->audio ) : '',
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

    public function submitAnswer(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'error' => [
                    'status_code' => Response::HTTP_NOT_FOUND,
                    'error_code' => 'user_not_found',
                    'error_message' => 'User not found',
                ]
            ], Response::HTTP_NOT_FOUND);
        }

        // Validate the request
        $validator = Validator::make($request->all(), [
            'mock_id' => 'required|integer',
            'score' => 'required|numeric|min:0',
            'wrong_answer' => 'required|integer|min:0',
            'right_answer' => 'required|integer|min:0',
            'skipped' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'status_code' => Response::HTTP_BAD_REQUEST,
                    'error_code' => 'validation_error',
                    'error_message' => $validator->errors()->first(),
                ]
            ], Response::HTTP_BAD_REQUEST);
        }

        // Create and save the MockUser data
        $data = new MockUser();
        $data->user_id = $user->id;
        $data->mock_id = $request->mock_id;
        $data->score = $request->score;
        $data->wrong_answer = $request->wrong_answer;
        $data->right_answer = $request->right_answer;
        $data->skipped = $request->skipped;
        $data->time_took = $request->time_took;
        $data->save();

        return response()->json([
            'success' => true,
            'message' => 'Successfully submitted answer',
            'data' => $data
        ]);
    }

    public function getAllMocks()
    {
        $items=Mock::all();
        return response()->json([
            "success"=>true,
            "message"=>"all mock data",
            "data"=>$items
        ]);
    }

    public function examHistory(Request $request)
    {
        $user=$request->user();
        $histories=MockUser::where('user_id',$user->id)->get();
        $data=[];
        foreach ($histories as $history)
        {
            $data[]=[
                "mock_id"=>$history->id,
                "title"=>$history->mock->title,
                "type"=>$history->mock->mock_type,
                "right_answer"=>$history->right_answer,
                "wrong_answer"=>$history->wrong_answer,
                "skipped"=>$history->skipped,
                "score"=>$history->score,
                "time_took"=>$history->time_took,

            ];

        }
        return response()->json([
            "success"=>true,
            "message"=>"exam history",
            "data"=>$data
        ]);
    }

}
