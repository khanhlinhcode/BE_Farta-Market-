<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Throwable;

class PasswordResetController extends Controller
{
    public function requestLink(Request $request): JsonResponse
    {
        if ($response = $this->requireStatefulSession($request)) {
            return $response;
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);
        $email = Str::lower(trim($data['email']));

        $customerExists = User::query()
            ->where('email', $email)
            ->where('role', 'customer')
            ->exists();

        if ($customerExists && ! $this->usesNonDeliveryMailer()) {
            try {
                Password::sendResetLink(['email' => $email]);
            } catch (Throwable $exception) {
                Log::warning('Could not send the password reset notification.', [
                    'email_hash' => hash('sha256', $email),
                    'error_type' => $exception::class,
                ]);
            }
        }

        return response()->json([
            'message' => 'Nếu email thuộc một tài khoản khách hàng, hướng dẫn đặt lại mật khẩu sẽ được gửi.',
        ], 202);
    }

    public function reset(Request $request): JsonResponse
    {
        if ($response = $this->requireStatefulSession($request)) {
            return $response;
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string', 'max:2048'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);
        $data['email'] = Str::lower(trim($data['email']));

        if (! User::query()->where('email', $data['email'])->where('role', 'customer')->exists()) {
            return $this->invalidResetResponse();
        }

        $status = Password::reset(
            $data,
            function (User $user, string $password): void {
                if ($user->role !== 'customer') {
                    return;
                }

                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->invalidResetResponse();
        }

        return response()->json([
            'message' => 'Mật khẩu đã được đặt lại. Vui lòng đăng nhập bằng mật khẩu mới.',
        ]);
    }

    private function invalidResetResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.',
            'errors' => [
                'email' => ['Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.'],
            ],
        ], 422);
    }

    private function requireStatefulSession(Request $request): ?JsonResponse
    {
        if ($request->hasSession()) {
            return null;
        }

        return response()->json([
            'message' => 'Yêu cầu xác thực cần session cookie hợp lệ.',
        ], 419);
    }

    private function usesNonDeliveryMailer(): bool
    {
        return config('app.env') === 'production'
            && in_array(config('mail.default'), ['array', 'log'], true);
    }
}
