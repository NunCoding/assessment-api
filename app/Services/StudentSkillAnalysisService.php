<?php
namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class StudentSkillAnalysisService
{
    public function getWeakSkills($userId)
    {
        return DB::table('user_assessments')
            ->join('assessments', 'user_assessments.assessment_id', '=', 'assessments.id')
            ->join('categories', 'assessments.categories_id', '=', 'categories.id') // plural here
            ->where('user_assessments.user_id', $userId)
            ->where('user_assessments.score', '<', 60)
            ->select('categories.name as skill', DB::raw('AVG(user_assessments.score) as avg_score'))
            ->groupBy('categories.name')
            ->orderBy('avg_score', 'asc')
            ->get();
    }

    public function generatePrompt($weakSkills)
    {
        $skillsList = collect($weakSkills)->pluck('skill')->join(', ');
        return "A student is weak in the following skills: $skillsList.
                For each skill, recommend 1 to 5 beginner-friendly and free resources (such as YouTube videos, websites, or free courses) that are currently available.
                Keep each description short, helpful, and focused on improving the specific skill.
                The goal is to help the student strengthen all of their weak areas.";
    }

    public function getRecommendationsFromGemini($prompt)
    {
        $apiKey = env('GEMINI_API_KEY');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$apiKey";

        $data = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ]
        ];

        $client = new Client();

        $response = $client->post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($data),
        ]);

        $body = json_decode($response->getBody(), true);

        // Extract generated text from response
        $generatedText = $body['candidates'][0]['content'] ?? null;

        return $generatedText;
    }

    public function parseGeminiRecommendations($text)
    {
        $recommendations = [];

        // Split text by each resource (assumes * or - marks)
        $resources = preg_split('/\n[\*\-]\s+/', $text);

        foreach ($resources as $resource) {
            if (empty(trim($resource))) continue;

            $youtube = null;
            $website = null;

            // Extract all URLs
            preg_match_all('/https?:\/\/[^\s\)\]]+/', $resource, $matches);
            $urls = $matches[0] ?? [];

            foreach ($urls as $url) {
                if (str_contains($url, 'youtube.com')) {
                    $youtube = $url;
                } else {
                    $website = $url;
                }
            }

            $recommendations[] = [
                'youtube_link' => $youtube,
                'website' => $website,
                'text' => strip_tags(trim($resource)),
            ];
        }

        return $recommendations;
    }


}
