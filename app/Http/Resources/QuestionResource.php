<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->assessment->category->name,
            'difficulty' => $this->assessment->difficulty,
            'assessment_name' => $this->assessment->title,
            'options' => $this->options->pluck('option_text'),
            'correct_answer' => $this->options->search(function ($option) {
                return $option->is_correct;
            }),
            'explanation' => $this->explanation,
            'assessment_id' => $this->assessment_id
        ];
    }
}
