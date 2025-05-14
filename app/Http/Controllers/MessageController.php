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
            'sender_id'    => 'required|exists:users,id',
            'receiver_id' => 'required|exists:users,id',
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
            ->where('messages.receiver_id', $id)
            ->get()
            ->map(function($item){
                return [
                    'id' => $item->id,
                    'instructor_name' => $item->name,
                    'email' => $item->email,
                    'message' => $item->message,
                    'link' => $item->link,
                ];
            });
        return response()->json($message);
    }
}
