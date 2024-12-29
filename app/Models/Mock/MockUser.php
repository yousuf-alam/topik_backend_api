<?php

namespace App\Models\Mock;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MockUser extends Model
{
    use HasFactory;

    public function mock()
    {
        return $this->belongsTo(Mock::class,'mock_id');

    }
}
