<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::with('role')->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                    'message' => 'Email atau password tidak valid.',
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        $deviceName = $validated['device_name'] ?? 'api-client';
        $token = $user->createToken($deviceName)->plainTextToken;

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'auth.login',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'diff' => ['device' => $deviceName, 'ip' => $request->ip()],
        ]);

        return response()->json([
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->name,
                    'theme_preferences' => $user->theme_preferences,
                ],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $user?->currentAccessToken()?->delete();

        if ($user) {
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'auth.logout',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'diff' => ['ip' => $request->ip()],
            ]);
        }

        return response()->json([
            'data' => [
                'message' => 'Logged out successfully.',
            ],
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load('role');

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->name,
                'theme_preferences' => $user->theme_preferences,
            ],
        ]);
    }

    public function updateThemePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'presetId' => ['required', 'string', 'max:50'],
            'mode' => ['required', 'in:dark,light,system'],
            'radius' => ['required', 'in:sm,md,lg,xl'],
            'density' => ['required', 'in:compact,comfortable,spacious'],
            'customColors' => ['sometimes', 'array'],
        ]);

        $user = $request->user();
        $user->theme_preferences = $validated;
        $user->save();

        return response()->json([
            'data' => [
                'theme_preferences' => $user->theme_preferences,
            ],
        ]);
    }
}
