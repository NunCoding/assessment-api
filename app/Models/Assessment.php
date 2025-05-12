<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Question;

class Assessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'categories_id',
        'tags',
        'time_estimate',
        'difficulty',
        'user_id',
        'slug',
        'expires_at',
        'image',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public function userAssessments()
    {
        return $this->hasMany(UserAssessment::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class, "categories_id");
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function userAttempts()
    {
        return $this->hasMany(UserAssessment::class);
    }

    public function instructor(){
        return $this->belongsTo(User::class,'user_id');
    }
}
