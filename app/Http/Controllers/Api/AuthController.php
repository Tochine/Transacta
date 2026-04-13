<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct(private readonly WalletService $walletService) {}
    public function register(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'unique:users,email'],
            'password'      => ['required', Password::min(6)->mixedCase()->numbers()],
            'business_name' => ['nullable', 'string', 'max:255'],
        ]);

        if($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
 
        $user = User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password'      => Hash::make($data['password']),
            'role'          => 'business',
            'business_name' => $data['business_name'] ?? null,
        ]);
 
        // Auto-provision wallet on registration
        $this->walletService->getOrCreateWallet($user);
 
        $token = $user->createToken('auth_token', ['*'], now()->addDays(30))->plainTextToken;

        $user->refresh();
 
        return response()->json([
            'message' => 'User registered successfully.',
            'data'    => [
                'user'  => $user->only(['id', 'name', 'email', 'role', 'business_name', 'is_active']),
                'token' => $token,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
           'email'    => ['required', 'email'],
            'password' => ['required', 'string'], 
        ]);

        if($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
 
        if (! Auth::attempt($data)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }
 
        $user = Auth::user();
 
        if (! $user->is_active) {
            Auth::logout();
 
            return response()->json(['message' => 'Account is disabled. Contact support.'], 403);
        }
 
        $user->update(['last_login_at' => now()]);
 
        // Revoke previous tokens for this device (single session enforcement)
        $user->tokens()->where('name', 'auth_token')->delete();
        $token = $user->createToken('auth_token', ['*'], now()->addDays(30))->plainTextToken;
 
 
        return response()->json([
            'message' => 'Login successful.',
            'data'    => [
                'user'  => $user->only(['id', 'name', 'email', 'role', 'last_login_at']),
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->user()->currentAccessToken()->delete();
 
        return response()->json(['message' => 'Logged out successfully.']);
    }
 
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('wallet');
 
        return response()->json(['data' => $user]);
    }
}
