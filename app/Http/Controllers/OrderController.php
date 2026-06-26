<?php

namespace App\Http\Controllers;

use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Laravel\Sanctum\PersonalAccessToken;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['details.product.category'])
            ->withSum('details as total', 'line_total')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('q')) {
            $keyword = $request->string('q');
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery
                    ->where('fullname', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%");
            });
        }

        return response()->json($query->get());
    }

    public function show(string $id)
    {
        $order = Order::with(['details.product.category'])
            ->withSum('details as total', 'line_total')
            ->findOrFail($id);

        return response()->json($order);
    }

    public function store(Request $request)
    {
        $idempotencyKey = $request->header('X-Idempotency-Key');

        if (! is_string($idempotencyKey) || trim($idempotencyKey) === '') {
            return response()->json([
                'message' => 'Header X-Idempotency-Key là bắt buộc.',
            ], 422);
        }

        $request->merge([
            'customer_name' => $request->input('customer_name', $request->input('fullname')),
            'customer_phone' => $request->input('customer_phone', $request->input('phone')),
            'idempotency_key' => $idempotencyKey,
        ]);

        $data = $request->validate([
            'idempotency_key' => [
                'required',
                'string',
                'min:16',
                'max:100',
                'regex:/^[A-Za-z0-9._:-]+$/',
            ],
            'customer_name' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'min:10', 'max:255'],
            'customer_phone' => ['required', 'string', 'regex:/^[0-9]{10,11}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'products' => ['required', 'array', 'min:1', 'max:50'],
            'products.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id'),
            ],
            'products.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $payloadHash = $this->payloadHash($data);
        $userId = $this->idempotencyUserId($request);
        $idempotencyScope = $userId === null ? 'guest' : "user:{$userId}";

        try {
            [$order, $isReplay] = Cache::lock(
                'order:create:'.hash('sha256', $idempotencyScope.'|'.$data['idempotency_key']),
                15
            )->block(5, function () use ($data, $payloadHash, $userId) {
                return DB::transaction(function () use ($data, $payloadHash, $userId) {
                    IdempotencyKey::query()
                        ->where('idempotency_key', $data['idempotency_key'])
                        ->where('user_id', $userId)
                        ->where('expires_at', '<=', now())
                        ->delete();

                    $existingKey = IdempotencyKey::query()
                        ->where('idempotency_key', $data['idempotency_key'])
                        ->where('user_id', $userId)
                        ->where('expires_at', '>', now())
                        ->lockForUpdate()
                        ->first();

                    if ($existingKey) {
                        if ($existingKey->payload_hash !== $payloadHash) {
                            throw new HttpResponseException(response()->json([
                                'message' => 'Idempotency key đã được dùng với request khác.',
                            ], 409));
                        }

                        $existingOrder = Order::query()
                            ->lockForUpdate()
                            ->find($existingKey->order_id);

                        if (! $existingOrder) {
                            $existingKey->delete();
                        } else {
                            return [$existingOrder, true];
                        }
                    }

                    $order = Order::create([
                        'fullname' => $data['customer_name'],
                        'address' => $data['address'],
                        'phone' => $data['customer_phone'],
                        'email' => $data['email'] ?? null,
                        'note' => $data['note'] ?? null,
                        'status' => Order::STATUS_ORDERED,
                        'idempotency_key' => $data['idempotency_key'],
                    ]);

                    $productIds = collect($data['products'])
                        ->pluck('product_id')
                        ->sort()
                        ->values();
                    $products = Product::query()
                        ->whereIn('id', $productIds)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    foreach ($data['products'] as $productData) {
                        $product = $products->get((int) $productData['product_id']);
                        $quantity = (int) $productData['quantity'];

                        if (! $product) {
                            abort(422, 'Sản phẩm không tồn tại.');
                        }

                        if ($product->inventory < $quantity) {
                            abort(422, "Sản phẩm {$product->name} không đủ tồn kho.");
                        }

                        $product->decrement('inventory', $quantity);
                        $unitPrice = (float) $product->price;

                        $order->details()->create([
                            'product_id' => $product->id,
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'product_name' => $product->name,
                            'line_total' => $unitPrice * $quantity,
                        ]);
                    }

                    IdempotencyKey::create([
                        'idempotency_key' => $data['idempotency_key'],
                        'payload_hash' => $payloadHash,
                        'user_id' => $userId,
                        'order_id' => $order->id,
                        'expires_at' => now()->addHours(24),
                    ]);

                    return [$order, false];
                });
            });
        } catch (LockTimeoutException $exception) {
            Log::warning('Order idempotency lock timed out.', [
                'idempotency_key_hash' => hash('sha256', $data['idempotency_key']),
            ]);

            return response()->json([
                'message' => 'Đơn hàng đang được xử lý. Vui lòng thử lại sau.',
            ], 409);
        }

        $order->load(['details.product.category'])->loadSum('details as total', 'line_total');

        return response()->json([
            'data' => $order,
            'idempotent_replay' => $isReplay,
        ], $isReplay ? 200 : 201);
    }

    public function updateStatus(Request $request, string $id)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Order::STATUSES)],
        ]);
        $newStatus = $data['status'];

        $order = DB::transaction(function () use ($id, $newStatus) {
            $order = Order::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();
            $oldStatus = $order->status;

            if ($oldStatus === $newStatus) {
                return $order;
            }

            $details = $order->details()->get();
            $products = Product::query()
                ->whereIn('id', $details->pluck('product_id')->filter()->unique()->sort()->values())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($newStatus === Order::STATUS_CANCELLED) {
                foreach ($details as $detail) {
                    $products->get($detail->product_id)?->increment(
                        'inventory',
                        $detail->quantity
                    );
                }
            }

            if ($oldStatus === Order::STATUS_CANCELLED) {
                foreach ($details as $detail) {
                    $product = $products->get($detail->product_id);

                    if (! $product || $product->inventory < $detail->quantity) {
                        $productName = $product?->name ?? $detail->product_name;
                        abort(422, "Sản phẩm {$productName} không đủ tồn kho để khôi phục đơn.");
                    }

                    $product->decrement('inventory', $detail->quantity);
                }
            }

            $order->update(['status' => $newStatus]);

            return $order;
        });

        $order->load(['details.product.category']);
        $order->loadSum('details as total', 'line_total');

        return response()->json($order);
    }

    private function payloadHash(array $data): string
    {
        return hash('sha256', json_encode([
            'items' => collect($data['products'])
                ->map(fn (array $item) => [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                ])
                ->sortBy('product_id')
                ->values()
                ->toArray(),
            'address' => $data['address'],
            'phone' => $data['customer_phone'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function idempotencyUserId(Request $request): ?int
    {
        $token = $request->bearerToken();

        if ($token) {
            $tokenable = PersonalAccessToken::findToken($token)?->tokenable;

            if ($tokenable) {
                return $tokenable->getKey();
            }
        }

        $user = $request->user('sanctum') ?? $request->user();

        if ($user) {
            return $user->id;
        }

        return null;
    }
}
