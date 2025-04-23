<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Analytic extends Model
{
    use HasFactory;

    protected $fillable = [
        'total_users', 'assessments_taken', 'questions_created',
        'avg_completion_time', 'avg_score'
    ];
}
