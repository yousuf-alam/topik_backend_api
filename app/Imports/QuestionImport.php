<?php

namespace App\Imports;


use App\Models\Mock\Question;
use App\Models\Mock\Option;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class QuestionImport implements ToCollection, WithHeadingRow
{
    protected $type;
    public function __construct($type)
    {
        $this->type = $type;
    }
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {




                $question = Question::create(
                    [
                        'title' => $row['question'],
                        'image' => $row['image'],
                        'audio' => $row['audio'],
                        'difficulty_level' => $row['level'],
                        'type'=>$this->type,
                    ]
                );


                $options = $this->shuffleOptions($row);
                $optionList = $this->createOptions($options, $question->id, Option::class);
                $rightAnswer = $this->rightAnswer($optionList);

                $question->update(['right_answer' => $rightAnswer]);


            }

        }




    protected function rightAnswer($optionList): int
    {

        foreach ($optionList as $options) {
            if ($options->is_right == 1)
                return $options->id;
        }
        return 0;
    }
    protected function createOptions(array $options, int $question_id, $model): array
    {



        $optionList = [];
        foreach ($options as $option) {



            $value = $model::create(
                [
                    'title' => $option['value'],
                    'image' => $option['img'] ?? "",
                    'audio' => $option['audio'] ?? "",
                    'question_id' => $question_id,

                ]
            );

            $value['is_right'] = $option['is_right'];
            $optionList[] = $value;
        }

        return $optionList;
    }

    protected function shuffleOptions(object $data): array
    {


        $string = $data['answer'];


        $answer = $string;
        $options = [
            ['value' => $data['a'], 'is_right' => $answer=='A' ? 1 : 0],
            ['value' => $data['b'], 'is_right' => $answer=='B' ? 1 : 0],
            ['value' => $data['c'], 'is_right' => $answer=='C' ? 1 : 0],
            ['value' => $data['d'], 'is_right' => $answer=='D' ? 1 : 0],

        ];


        shuffle($options);


        return $options;
    }

}
