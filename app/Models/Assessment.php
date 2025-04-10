<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Console\Question\Question;

class Assessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'categories_id',
        'rating',
        'difficulty',
        'time_estimate',
        'image',
        'tags',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public function category(){
        return $this->belongsTo(Category::class,"categories_id");
    }

    public function question(){
        return $this->hasMany(Question::class);
    }
}
