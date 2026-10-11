<?php

namespace App\Http\Controllers;

use App\Exceptions\CloudinaryException;
use App\Http\Resources\UserResource;
use App\Services\CloudinaryImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProfileController extends Controller
{
    public function __construct(private readonly CloudinaryImageService $cloudinary) {}

    public function show(Request $request)
    {
        return response()->json((new UserResource($request->user()))->resolve($request));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        if ($request->has('email')) {
            $request->merge([
                'email' => Str::lower(trim((string) $request->input('email'))),
            ]);
        }
        $emailChanged = $request->has('email') && $request->input('email') !== $user->email;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'regex:/^[0-9]{10,11}$/'],
            'default_address' => ['nullable', 'string', 'max:255'],
            'current_password' => [Rule::requiredIf($emailChanged), 'nullable', 'string'],
        ]);

        if ($emailChanged && ! Hash::check((string) $data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mật khẩu hiện tại không đúng.'],
            ]);
        }

        unset($data['current_password']);
        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null]);
        }
        $user->update($data);

        if ($emailChanged) {
            $this->rotateSessionsAfterEmailChange($request);
            $user->sendEmailVerificationNotification();
        }

        return response()->json((new UserResource($user->fresh()))->resolve($request));
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
            'data' => (new UserResource($user->fresh()))->resolve($request),
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

    private function rotateSessionsAfterEmailChange(Request $request): void
    {
        $user = $request->user();
        $user->tokens()->delete();

        if (config('session.driver') === 'database') {
            $sessions = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id);

            if ($request->hasSession()) {
                $sessions->where('id', '!=', $request->session()->getId());
            }

            $sessions->delete();
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
    }
}
