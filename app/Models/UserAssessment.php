<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'assessment_id', 'score', 'completion_time'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }

    public function responses()
    {
        return $this->hasManyThrough(
            Response::class,
            Question::class,
            'assessment_id',
            'question_id',
            'assessment_id',
            'id'
        )->where('responses.user_id', $this->user_id);
    }
}
