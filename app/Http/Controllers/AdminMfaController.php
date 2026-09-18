<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdminMfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminMfaController extends Controller
{
    public function __construct(private readonly AdminMfaService $mfa) {}

    public function setup(Request $request): JsonResponse
    {
        $user = $this->pendingUser($request);
        if (! $user || $user->mfa_confirmed_at) {
            return $this->invalidChallenge();
        }

        $secret = $this->mfa->generateSecret();
        $user->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => null,
            'mfa_last_used_timestep' => null,
        ])->save();

        return response()->json([
            'secret' => $secret,
            'provisioning_uri' => $this->mfa->provisioningUri($user, $secret),
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $user = $this->pendingUser($request);
        if (! $user || $user->mfa_confirmed_at) {
            return $this->invalidChallenge();
        }

        $timestep = $this->mfa->consumeTotp($user, $data['code']);
        if ($timestep === false) {
            return response()->json(['message' => 'Mã xác thực không hợp lệ.'], 422);
        }

        $recoveryCodes = $this->mfa->newRecoveryCodes();
        $user->forceFill([
            'mfa_confirmed_at' => now(),
            'mfa_recovery_codes' => $this->mfa->recoveryCodeHashes($recoveryCodes),
        ])->save();

        $this->completeLogin($request, $user);

        return response()->json([
            'user' => $user->fresh(),
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function challenge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'digits:6', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:64', 'required_without:code'],
        ]);
        $user = $this->pendingUser($request);
        if (! $user || ! $user->mfa_confirmed_at) {
            return $this->invalidChallenge();
        }

        $verified = false;
        if (isset($data['code'])) {
            $verified = $this->mfa->consumeTotp($user, $data['code']) !== false;
        } elseif (isset($data['recovery_code'])) {
            $verified = $this->mfa->consumeRecoveryCode($user, $data['recovery_code']);
        }

        if (! $verified) {
            return response()->json(['message' => 'Mã xác thực không hợp lệ.'], 422);
        }

        $this->completeLogin($request, $user);

        return response()->json(['user' => $user->fresh()]);
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
        ]);
        $user = $request->user();
        if (! Hash::check($data['password'], $user->password)
            || $this->mfa->consumeTotp($user, $data['code']) === false) {
            return response()->json(['message' => 'Thông tin xác thực không hợp lệ.'], 422);
        }

        $recoveryCodes = $this->mfa->newRecoveryCodes();
        $user->forceFill([
            'mfa_recovery_codes' => $this->mfa->recoveryCodeHashes($recoveryCodes),
        ])->save();

        return response()->json(['recovery_codes' => $recoveryCodes]);
    }

    public function disable(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
        ]);
        $user = $request->user();
        if (! Hash::check($data['password'], $user->password)
            || $this->mfa->consumeTotp($user, $data['code']) === false) {
            return response()->json(['message' => 'Thông tin xác thực không hợp lệ.'], 422);
        }

        $user->forceFill([
            'mfa_secret' => null,
            'mfa_recovery_codes' => null,
            'mfa_confirmed_at' => null,
            'mfa_last_used_timestep' => null,
        ])->save();
        $this->invalidateSession($request);

        return response()->json([
            'message' => 'Đã tắt MFA. Bạn cần đăng nhập và thiết lập lại MFA.',
            'reauthenticate' => true,
        ]);
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('admin_mfa_pending_user_id');
        $startedAt = $request->session()->get('admin_mfa_pending_at');

        if (! is_int($userId) || ! is_int($startedAt) || $startedAt < now()->subMinutes(10)->timestamp) {
            return null;
        }

        return User::query()
            ->whereKey($userId)
            ->whereIn('role', ['admin', 'staff'])
            ->whereNotNull('email_verified_at')
            ->first();
    }

    private function completeLogin(Request $request, User $user): void
    {
        $this->revokeOtherSessions($user);
        $user->tokens()->delete();
        $request->session()->forget(['admin_mfa_pending_user_id', 'admin_mfa_pending_at']);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put(
            'password_hash_'.Auth::getDefaultDriver(),
            $user->getAuthPassword()
        );
    }

    private function revokeOtherSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }

    private function invalidateSession(Request $request): void
    {
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        Auth::forgetGuards();
    }

    private function invalidChallenge(): JsonResponse
    {
        return response()->json(['message' => 'Phiên xác thực MFA không hợp lệ hoặc đã hết hạn.'], 401);
    }
}
