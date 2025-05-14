<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function store(Request $request)
    {
        $message = $request->validate([
            'sender_id'    => 'required',
            'receiver_id' => 'required',
            'message' => 'required|string',
            'link' => 'nullable|string',
        ]);

        Message::create([
            'sender_id' => $message['sender_id'],
            'receiver_id' => $message['receiver_id'],
            'message' => $message['message'],
            'link' => $message['link'],
        ]);

        return response()->noContent();
    }

    public function show($id){
        $message = DB::table('messages')
            ->join('users', 'messages.sender_id', '=', 'users.id')
            ->leftJoin('assessments', 'messages.assessment_id', '=', 'assessments.id')
            ->where('messages.receiver_id', $id)
            ->select(
                'messages.id',
                'users.name',
                'users.email',
                'assessments.title as assessment_title',
                'messages.message',
                'messages.link',
                'messages.created_at',
            )
            ->get()
            ->map(function($item){
                return [
                    'id' => $item->id,
                    'instructor_name' => $item->name,
                    'email' => $item->email,
                    'assessment' => $item->assessment_title,
                    'message' => $item->message,
                    'link' => $item->link,
                    'created_at' => $item->created_at,
                ];
            });
        return response()->json($message);
    }
}
