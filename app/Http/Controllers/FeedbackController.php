<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeedbackController extends Controller
{
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
            'data' => $feedback
        ], 201);
    }
}
