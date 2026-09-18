<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

        $request->user()->sendEmailVerificationNotification();

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
}
