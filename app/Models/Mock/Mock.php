<?php

namespace App\Models\Mock;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mock extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'start_time',
        'end_time',
        'mock_type',
        'total_question',
        'duration',
        'passing_percentage',
        'status',
    ];

    protected $dates = [
        'start_time',
        'end_time',
    ];

    public function questions()
    {
        return $this->belongsToMany(Question::class);
    }
}
