<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Throwable;

class MobileAuthController extends Controller
{
    private const DUMMY_PASSWORD_HASH = '$2y$12$eweZJwcVnRNMHAHs8j1D5eMZS7./pvk9SIUgRh9a45ithSyfJMS/K';

    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'device_name' => ['required', 'string', 'max:100'],
        ]);
        $deviceName = $this->validatedDeviceName($data['device_name']);

        if ($deviceName === null) {
            return $this->invalidDeviceNameResponse();
        }

        [$user, $token] = DB::transaction(function () use ($data, $deviceName): array {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => Str::lower(trim($data['email'])),
                'password' => $data['password'],
                'role' => 'customer',
            ]);
            $token = $user->createToken($deviceName, ['mobile:customer'])->plainTextToken;

            return [$user, $token];
        });

        return response()->json([
            'token' => $token,
            'user' => $this->mobileUser($user),
            'verification_email_sent' => $this->sendVerificationEmail($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $deviceName = $this->validatedDeviceName($data['device_name']);

        if ($deviceName === null) {
            return $this->invalidDeviceNameResponse();
        }

        $user = User::query()->where('email', $data['email'])->first();
        $passwordIsValid = Hash::check(
            $data['password'],
            $user?->password ?? self::DUMMY_PASSWORD_HASH
        );

        if (! $user || ! $passwordIsValid || $user->role !== 'customer') {
            return response()->json([
                'message' => 'Thông tin đăng nhập không đúng.',
            ], 401);
        }

        $user->tokens()->where('name', $deviceName)->delete();

        $token = $user->createToken($deviceName, ['mobile:customer'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->mobileUser($user),
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

    private function validatedDeviceName(string $value): ?string
    {
        $deviceName = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $value));

        return $deviceName === '' ? null : $deviceName;
    }

    private function invalidDeviceNameResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Tên thiết bị không hợp lệ.',
            'errors' => [
                'device_name' => ['Tên thiết bị không hợp lệ.'],
            ],
        ], 422);
    }

    /** @return array{id: mixed, name: mixed, email: mixed, role: mixed} */
    private function mobileUser(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'role']);
    }

    private function sendVerificationEmail(User $user): bool
    {
        if ($this->usesNonDeliveryMailer()) {
            return false;
        }

        try {
            $user->sendEmailVerificationNotification();

            return true;
        } catch (Throwable $exception) {
            Log::warning('Could not send the mobile email verification notification.', [
                'user_id_hash' => hash('sha256', (string) $user->id),
                'error_type' => $exception::class,
            ]);

            return false;
        }
    }

    private function usesNonDeliveryMailer(): bool
    {
        return config('app.env') === 'production'
            && in_array(config('mail.default'), ['array', 'log'], true);
    }
}
