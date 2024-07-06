<?php

namespace App\Models\Mock;

use App\Models\Mock\Option;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'image',
        'difficulty_level',
        'type',
        'right_answer',
    ];

    public function Options() {
        return $this->hasMany(Option::class);
    }
}
