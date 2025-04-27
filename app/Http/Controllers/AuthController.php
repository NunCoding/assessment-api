<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request){
        $validator = Validator::make($request->all(),[
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()){
            return response()->json(['errors' => $validator->errors()],400);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'student',
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;
        if ($user)
        {
            ActivityLog::create([
                'user_id' => $user->id,
                'activity_type' => 'user_registration',
                'description' => 'New user registered.',
                'metadata' => [
                    'email' => $user->email,
                    'registered_at' => now(),
                ]
            ]);
        }
        return response()->json(['message' => 'User registered successfully', 'user' => $user,'token' => $token], 201);
    }

    public function login(Request $request){
        // Validate the request
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string|min:8',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        // Attempt login
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = Auth::user();

            // Generate token (using Laravel Passport or Sanctum)
            $token = $user->createToken('AppToken')->plainTextToken;  // If using Sanctum

            return response()->json(['message' => 'Login successful', 'token' => $token, 'user' => $user]);
        } else {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
    }

    public function create(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'role'  => 'required|in:student,instructor,admin',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'role'     => strtolower($validated['role']), // optional: lowercase
            'password' => Hash::make('12345678'),          // fixed password
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user'    => $user,
        ], 201);
    }

    public function update(Request $request,$id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
           'name' => 'required|string|max:255',
           'email' => 'required|email|max:255|unique:users,email,' . $user->id,
           'role' => 'required|in:student,instructor,admin',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => strtolower($validated['role']),
            'password' => Hash::make('password123456'),
        ]);

        return response()->json([
            'message' => 'User updated successfully',
        ]);
    }
}
