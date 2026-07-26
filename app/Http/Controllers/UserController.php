<?php

namespace App\Http\Controllers;

use App\Mail\DispatcherCreatedPasswordMail;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UserController extends Controller
{
    //Methods for user registration, login, password reset, etc. would go here
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Create and return a token
        // $token = $user->createToken('YourAppName')->plainTextToken;
        // Create the token with user ability
        $plainTextToken = $user->createToken('UserLogin', ['user'])->plainTextToken;

        // Extract the token part after the '|'
        $token = explode('|', $plainTextToken)[1];

        return response()->json([
            'token' => $token, 
            'user_id' => $user->id,
            'message' => 'Login successful',
            ], 200);
    }

    public function show(Request $request)
    {
        // Get the authenticated user
        $user = $request->user(); // This will get the authenticated user

        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        return response()->json($user);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        // \Log::info($request->all());

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
        ]);

        $data = $request->only(['name', 'email']);

        // if ($request->hasFile('image')) {
        //     $image = $request->file('image');
        //     $path = $image->store('uploads/users', 'public');
        //     $data['image'] = $path;
        // }
        

        $user->update($data);
        // Debugging: Log updated user
        // \Log::info('User updated: ', $user->toArray());

        return response()->json([
            'message' => "Data Updated",
            'user' => $user
            ], 200);
    }

    public function logout(Request $request)
    {
        // Revoke the current user's token
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully'], 200);
    }

    public function updatePassword(Request $request)
    {
        // dd('entered the method updatePassword');
        $user = $request->user();
        // dd($user);

        // Validate the incoming request
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        // dd($request->all());

        // Check if the current password is correct
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Current password is incorrect'], 400);
        }

        // Update the password
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password updated successfully'], 200);
    }

    public function createDispatcher(Request $request)
    {
        $user = $request->user();

        if (! $user || $user->user_type !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $generatedPassword = Str::upper(Str::random(6));

        $data = $request->only(['name', 'email']);
        $data['password'] = Hash::make($generatedPassword);
        $data['user_type'] = 'dispatcher';

        $dispatcher = User::create($data);
        $this->sendDispatcherCredentialsEmail($dispatcher, $generatedPassword);

        return response()->json([
            'message' => 'Dispatcher created successfully and credentials sent by email.',
            'data' => $dispatcher,
        ], 201);
    }

    private function sendDispatcherCredentialsEmail(User $dispatcher, string $generatedPassword): void
    {
        try {
            Mail::to($dispatcher->email)->send(new DispatcherCreatedPasswordMail(
                dispatcher: $dispatcher,
                generatedPassword: $generatedPassword
            ));
        } catch (\Throwable $e) {
            Log::warning('Dispatcher credentials mail failed', [
                'dispatcher_id' => $dispatcher->id,
                'email' => $dispatcher->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
