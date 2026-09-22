<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        if ($response = $this->requireStatefulSession($request)) {
            return $response;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($data, $request): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'customer',
            ]);

            Auth::login($user);
            $request->session()->regenerate();
            $this->bindSessionToPassword($request, $user);

            return $user;
        });

        $verificationEmailSent = ! $this->usesNonDeliveryMailer();

        if ($verificationEmailSent) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (Throwable $exception) {
                $verificationEmailSent = false;
                Log::warning('Could not send the registration verification email.', [
                    'user_id_hash' => hash('sha256', (string) $user->id),
                    'error_type' => $exception::class,
                ]);
            }
        }

        return response()->json([
            'user' => $user->fresh(),
            'verification_email_sent' => $verificationEmailSent,
        ], 201);
    }

    public function userLogin(Request $request)
    {
        if ($response = $this->requireStatefulSession($request)) {
            return $response;
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Thông tin đăng nhập không đúng.',
            ], 401);
        }

        $request->session()->regenerate();
        $user = Auth::user();

        if ($user->role !== 'customer') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'message' => 'Thông tin đăng nhập không đúng.',
            ], 401);
        }

        $this->bindSessionToPassword($request, $user);

        return response()->json([
            'user' => $user,
        ]);
    }

    public function login(Request $request)
    {
        if ($response = $this->requireStatefulSession($request)) {
            return $response;
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Thông tin đăng nhập không đúng.',
            ], 401);
        }

        if (! in_array($user->role, ['admin', 'staff'], true)) {
            return response()->json([
                'message' => 'Thông tin đăng nhập không đúng.',
            ], 401);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Tài khoản quản trị chưa xác minh email.',
                'email_verification_required' => true,
            ], 403);
        }

        $request->session()->regenerate();
        $request->session()->put([
            'admin_mfa_pending_user_id' => $user->id,
            'admin_mfa_pending_at' => now()->timestamp,
        ]);

        return response()->json([
            'mfa_required' => (bool) $user->mfa_confirmed_at,
            'mfa_enrollment_required' => ! $user->mfa_confirmed_at,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->flush();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        Auth::forgetGuards();

        return response()->json(null, 204);
    }

    /**
     * Cookie-based Sanctum auth needs StartSession. Stateless API calls should
     * fail cleanly before login/register can mutate data.
     */
    private function requireStatefulSession(Request $request): ?JsonResponse
    {
        if ($request->hasSession()) {
            return null;
        }

        return response()->json([
            'message' => 'Yêu cầu xác thực cần session cookie hợp lệ.',
        ], 419);
    }

    private function bindSessionToPassword(Request $request, User $user): void
    {
        $request->session()->put(
            'password_hash_'.Auth::getDefaultDriver(),
            $user->getAuthPassword()
        );
    }

    private function usesNonDeliveryMailer(): bool
    {
        return config('app.env') === 'production'
            && in_array(config('mail.default'), ['array', 'log'], true);
    }
}
