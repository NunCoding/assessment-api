<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiService
{
    public static function getLearningResources($topics)
    {
        $prompt = "The following IT topics are weak for a student: $topics.
Suggest 2 beginner-friendly learning resources (YouTube, course, or article) per topic.";

        $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . env('GEMINI_API_KEY'), [
            'contents' => [
                [
                    'parts' => [['text' => $prompt]]
                ]
            ]
        ]);

        return $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? 'No response.';
    }
}
