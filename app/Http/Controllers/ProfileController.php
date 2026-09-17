<?php

namespace App\Http\Controllers;

use App\Exceptions\CloudinaryException;
use App\Services\CloudinaryImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Throwable;

class ProfileController extends Controller
{
    public function __construct(private readonly CloudinaryImageService $cloudinary) {}

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
            'new_password' => ['nullable', 'string', 'confirmed', 'different:current_password', Password::defaults()],
            'password' => ['nullable', 'string', 'confirmed', 'different:current_password', Password::defaults()],
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
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'dimensions:max_width=4096,max_height=4096', 'max:2048'],
        ], [
            'avatar.max' => 'Ảnh quá lớn. Vui lòng chọn ảnh nhỏ hơn 2MB',
            'avatar.mimes' => 'Chỉ hỗ trợ định dạng JPG, PNG, WEBP',
            'avatar.image' => 'Chỉ hỗ trợ định dạng JPG, PNG, WEBP',
        ]);

        $user = $request->user();
        $uploaded = $this->cloudinary->uploadToFolder($data['avatar'], 'farta/avatars/'.$user->id);
        $oldPublicId = $user->avatar_public_id;

        try {
            $user->update([
                'avatar_url' => $uploaded['url'],
                'avatar_public_id' => $uploaded['public_id'],
            ]);
        } catch (Throwable $exception) {
            $this->destroyCloudinaryAvatar($uploaded['public_id']);
            throw $exception;
        }

        if ($oldPublicId) {
            $this->destroyCloudinaryAvatar($oldPublicId);
        }

        return response()->json([
            'avatar_url' => $uploaded['url'],
            'data' => $this->profilePayload($user->fresh()),
        ]);
    }

    private function destroyCloudinaryAvatar(string $publicId): void
    {
        try {
            $this->cloudinary->destroy($publicId);
        } catch (CloudinaryException $exception) {
            Log::warning('Could not clean up a Cloudinary avatar.', [
                'public_id_hash' => hash('sha256', $publicId),
                'error' => $exception->getMessage(),
            ]);
        }
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
