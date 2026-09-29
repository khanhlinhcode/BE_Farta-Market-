<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class MobileAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $deviceName = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $data['device_name']));

        if ($deviceName === '') {
            return response()->json([
                'message' => 'Tên thiết bị không hợp lệ.',
                'errors' => [
                    'device_name' => ['Tên thiết bị không hợp lệ.'],
                ],
            ], 422);
        }

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password) || $user->role !== 'customer') {
            return response()->json([
                'message' => 'Thông tin đăng nhập không đúng.',
            ], 401);
        }

        $user->tokens()->where('name', $deviceName)->delete();

        $token = $user->createToken($deviceName, ['mobile:customer'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        Auth::forgetGuards();

        return response()->json(null, 204);
    }
}
