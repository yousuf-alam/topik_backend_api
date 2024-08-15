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
        $character = mb_substr($string, 0, 1, 'UTF-8');
        $code = unpack('N', mb_convert_encoding($character, 'UCS-4BE', 'UTF-8'));
        $answer = $code[1] - 9311;
        $options = [
            ['value' => $data['a'], 'is_right' => $answer==1 ? 1 : 0],
            ['value' => $data['b'], 'is_right' => $answer==2 ? 1 : 0],
            ['value' => $data['c'], 'is_right' => $answer==3 ? 1 : 0],
            ['value' => $data['d'], 'is_right' => $answer==4 ? 1 : 0],

        ];

        shuffle($options);


        return $options;
    }

}
