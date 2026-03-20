<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Request;
use App\Traits\LogsActivity;

class AuthController extends Controller
{
    use LogsActivity;

    public function __construct(private AuthService $authService)
    {
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $result = $this->authService->login($request->only('email', 'password'));

        if (isset($result['user'])) {
            $this->logActivity('login', 'User logged in', $result['user']['id']);
        }

        return response()->json($result);
    }

    public function logout(Request $request)
    {
        $this->logActivity('logout', 'User logged out');
        $this->authService->logout($request->user());
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }
}
