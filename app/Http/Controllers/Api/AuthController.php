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

        // $d = $request->validate([
        //     'name'          => ['required', 'string', 'max:255'],
        //     'email'         => ['required', 'email', 'unique:users,email'],
        //     'password'      => ['required', Password::min(6)->mixedCase()->numbers()],
        //     // 'password'      => ['required', Password::min(6)],
        //     'business_name' => ['nullable', 'string', 'max:255'],
        // ]);

        $validator = Validator::make($request->all(), [
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'unique:users,email'],
            'password'      => ['required', Password::min(6)->mixedCase()->numbers()],
            // 'password'      => ['required', Password::min(6)],
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
}
