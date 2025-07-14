<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use function Laravel\Prompts\select;

class FeedbackController extends Controller
{
    public function index()
    {
        $listFeedbacks = Feedback::with('user')->get()->map(function ($feedback) {
            return [
                'id' => $feedback->id,
                'rating' => $feedback->rating,
                'comment' => $feedback->comment,
                'share' => $feedback->share,
                'is_contact_back' => $feedback->is_contact_back,
                'submitted_at' => $feedback->created_at->diffForHumans(),
                'user' => [
                    'id' => $feedback->user->id,
                    'name' => $feedback->user->name,
                    'email' => $feedback->user->email,
                ]
            ];
        });
        return response()->json([
            'data' => $listFeedbacks,
        ]);
    }
    public function show(Request $request,$userId)
    {
        $feedback = Feedback::all()->where('user_id',$userId);
        return response()->json(['data'=>$feedback]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'share' => 'nullable|string|max:255',
            'comment' => 'nullable|string',
            'is_contact_back' => 'nullable|boolean',
        ]);

        $userId = Auth::id();

        $feedback = Feedback::updateOrCreate(
            ['user_id' => $userId],
            [
                'rating' => $validated['rating'],
                'share' => $validated['share'] ?? null,
                'comment' => $validated['comment'] ?? null,
                'is_contact_back' => $validated['is_contact_back'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Feedback submitted successfully.',
        ], 201);
    }
}
