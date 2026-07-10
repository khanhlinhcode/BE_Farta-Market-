<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

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
            'password' => $this->passwordRules(),
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

            return $user;
        });

        return response()->json([
            'user' => $user->fresh(),
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

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Thông tin đăng nhập không đúng.',
            ], 401);
        }

        $request->session()->regenerate();
        $user = Auth::user();

        if (! in_array($user->role, ['admin', 'staff'], true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'message' => 'Thông tin đăng nhập không đúng.',
            ], 401);
        }

        return response()->json([
            'user' => $user,
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

    private function passwordRules(): array
    {
        $rule = Password::min(8)
            ->mixedCase()
            ->numbers();

        if (! app()->runningUnitTests() && ! app()->environment('testing')) {
            $rule = $rule->uncompromised();
        }

        return ['required', 'confirmed', $rule];
    }
}
