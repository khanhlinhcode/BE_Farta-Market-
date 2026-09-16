<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($this->profilePayload($request->user()));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9]{10,11}$/'],
            'default_address' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $user->update($data);

        return response()->json($this->profilePayload($user->fresh()));
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['nullable', 'string', 'confirmed', Password::min(8)],
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();
        $newPassword = $data['new_password'] ?? $data['password'] ?? null;

        if (! $newPassword) {
            return response()->json([
                'message' => 'Vui lòng nhập mật khẩu mới.',
                'errors' => [
                    'new_password' => ['Vui lòng nhập mật khẩu mới.'],
                ],
            ], 422);
        }

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Mật khẩu hiện tại không đúng.',
            ], 422);
        }

        $user->forceFill([
            'password' => $newPassword,
        ])->saveQuietly();

        $user->currentAccessToken()?->delete();
        $user->tokens()->delete();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.',
            'reauthenticate' => true,
        ]);
    }

    public function uploadAvatar(Request $request)
    {
        $data = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'avatar.max' => 'Ảnh quá lớn. Vui lòng chọn ảnh nhỏ hơn 2MB',
            'avatar.mimes' => 'Chỉ hỗ trợ định dạng JPG, PNG, WEBP',
            'avatar.image' => 'Chỉ hỗ trợ định dạng JPG, PNG, WEBP',
        ]);

        $user = $request->user();
        $path = $data['avatar']->store('avatars', 'public');
        $avatarUrl = asset('storage/'.$path);

        $user->update([
            'avatar_url' => $avatarUrl,
        ]);

        return response()->json([
            'avatar_url' => $avatarUrl,
            'data' => $this->profilePayload($user->fresh()),
        ]);
    }

    private function profilePayload($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'default_address' => $user->default_address,
            'role' => $user->role,
            'created_at' => $user->created_at,
        ];
    }
}
