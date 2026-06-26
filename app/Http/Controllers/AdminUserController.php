<?php

namespace App\Http\Controllers;

use App\Models\User;

class AdminUserController extends Controller
{
    public function destroy(User $user)
    {
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'Đã xoá người dùng.',
        ]);
    }
}
