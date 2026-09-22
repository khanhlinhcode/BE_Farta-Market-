<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailVerificationController extends Controller
{
    public function verify(Request $request, string $id, string $hash): JsonResponse|RedirectResponse
    {
        $user = User::query()->findOrFail($id);
        abort_unless(hash_equals($hash, sha1($user->getEmailForVerification())), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if ($request->expectsJson()) {
            return response()->json(['email_verified' => true]);
        }

        return redirect()->away(
            rtrim((string) config('services.frontend.url'), '/').'/verify-email?status=success'
        );
    }

    public function send(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['email_verified' => true]);
        }

        if ($this->usesNonDeliveryMailer()) {
            return response()->json([
                'message' => 'Dịch vụ email xác minh chưa được cấu hình. Vui lòng thử lại sau.',
                'email_verified' => false,
            ], 503);
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            Log::warning('Could not resend the email verification notification.', [
                'user_id_hash' => hash('sha256', (string) $request->user()->id),
                'error_type' => $exception::class,
            ]);

            return response()->json([
                'message' => 'Không gửi được email xác minh. Vui lòng thử lại sau.',
                'email_verified' => false,
            ], 503);
        }

        return response()->json([
            'message' => 'Đã gửi lại email xác minh.',
            'email_verified' => false,
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'email_verified' => $request->user()->hasVerifiedEmail(),
        ]);
    }

    private function usesNonDeliveryMailer(): bool
    {
        return config('app.env') === 'production'
            && in_array(config('mail.default'), ['array', 'log'], true);
    }
}
