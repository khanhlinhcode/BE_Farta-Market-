<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(['admin', 'staff'])],
        ]);

        $query = User::query()
            ->withTrashed()
            ->withCount('orders')
            ->latest();

        if (! empty($filters['q'])) {
            $keyword = trim($filters['q']);
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery
                    ->where('name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%");
            });
        }

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        return response()->json($query->paginate(20));
    }

    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['admin', 'staff'])],
        ]);

        $user->update(['role' => $data['role']]);

        return response()->json($user->fresh()->loadCount('orders'));
    }

    public function orders(User $user)
    {
        return response()->json(
            Order::query()
                ->where('user_id', $user->id)
                ->with(['details.product.category'])
                ->withSum('details as total', 'line_total')
                ->orderByDesc('created_at')
                ->paginate(10)
        );
    }

    public function destroy(User $user)
    {
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'Đã vô hiệu hóa người dùng.',
        ]);
    }
}
