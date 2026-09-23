<?php

namespace App\Http\Controllers;

use App\Jobs\SendOrderConfirmationEmail;
use App\Models\CouponUsage;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\AnalyticsSessionService;
use App\Services\CouponService;
use App\Services\OrderStatusService;
use App\Services\TurnstileService;
use App\Support\AnalyticsIdentifier;
use App\Support\IdempotencyHasher;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Laravel\Sanctum\PersonalAccessToken;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(Order::STATUSES)],
            'payment_method' => ['nullable', Rule::in([Order::PAYMENT_METHOD_COD, Order::PAYMENT_METHOD_VNPAY])],
            'payment_status' => ['nullable', Rule::in([
                Order::PAYMENT_STATUS_PENDING,
                Order::PAYMENT_STATUS_PAID,
                Order::PAYMENT_STATUS_FAILED,
            ])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'keyword' => ['nullable', 'string', 'max:255'],
            'q' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $filtered = Order::query();
        $this->applyAdminFilters($filtered, $filters);
        $summary = [
            'orders' => (clone $filtered)->count(),
            'pending' => (clone $filtered)->where('status', Order::STATUS_PENDING)->count(),
            'delivered_revenue' => (float) (clone $filtered)->where('status', Order::STATUS_DELIVERED)->sum('grand_total'),
        ];
        $orders = $filtered
            ->with(['details.product.category', 'coupon', 'statusHistory.changedBy:id,name,role'])
            ->withSum('details as total', 'line_total')
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 20));

        return response()->json([
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
            'summary' => $summary,
        ]);
    }

    public function exportCsv(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(Order::STATUSES)],
            'payment_method' => ['nullable', Rule::in([Order::PAYMENT_METHOD_COD, Order::PAYMENT_METHOD_VNPAY])],
            'payment_status' => ['nullable', Rule::in([
                Order::PAYMENT_STATUS_PENDING,
                Order::PAYMENT_STATUS_PAID,
                Order::PAYMENT_STATUS_FAILED,
            ])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'keyword' => ['nullable', 'string', 'max:255'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $query = Order::query()->with('details')->latest();
        $this->applyAdminFilters($query, $filters);

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'id',
                'customer',
                'phone',
                'email',
                'status',
                'payment_method',
                'payment_status',
                'subtotal',
                'shipping_fee',
                'grand_total',
                'created_at',
            ]);

            $query->chunk(100, function ($orders) use ($handle) {
                foreach ($orders as $order) {
                    fputcsv($handle, array_map($this->csvCell(...), [
                        $order->id,
                        $order->fullname,
                        $order->phone,
                        $order->email,
                        $order->status,
                        $order->payment_method,
                        $order->payment_status,
                        $order->subtotal,
                        $order->shipping_fee,
                        $order->grand_total,
                        optional($order->created_at)->toDateTimeString(),
                    ]));
                }
            });

            fclose($handle);
        }, 'orders.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function show(string $id)
    {
        $order = Order::with(['details.product.category', 'coupon', 'statusHistory.changedBy:id,name,role'])
            ->withSum('details as total', 'line_total')
            ->findOrFail($id);

        return response()->json($order);
    }

    public function myOrders(Request $request)
    {
        return response()->json(
            Order::query()
                ->where('user_id', $request->user()->id)
                ->with(['details.product.category', 'coupon', 'statusHistory.changedBy:id,name,role'])
                ->withSum('details as total', 'line_total')
                ->orderByDesc('created_at')
                ->paginate(10)
        );
    }

    public function myOrder(Request $request, Order $order)
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);

        return response()->json($order->load(['details.product.category', 'coupon', 'statusHistory.changedBy:id,name,role']));
    }

    public function store(
        Request $request,
        CouponService $couponService,
        TurnstileService $turnstile,
        AnalyticsSessionService $analyticsSessions
    ) {
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
            'email' => ['required', 'email', 'max:255'],
            'turnstile_token' => ['nullable', 'string', 'max:2048'],
            'note' => ['nullable', 'string', 'max:2000'],
            'coupon_code' => ['nullable', 'string', 'max:80'],
            'products' => ['required', 'array', 'min:1', 'max:20'],
            'products.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'products.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        if (collect($data['products'])->sum('quantity') > 50) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'products' => ['Tổng số lượng sản phẩm không được vượt quá 50.'],
            ]);
        }

        $turnstileToken = $data['turnstile_token'] ?? null;
        unset($data['turnstile_token']);
        $data['email'] = Str::lower(trim($data['email']));
        $data['customer_phone'] = preg_replace('/\D+/', '', $data['customer_phone']);

        $data['coupon_code'] = isset($data['coupon_code']) && trim((string) $data['coupon_code']) !== ''
            ? strtoupper(trim((string) $data['coupon_code']))
            : null;
        $data['payment_method'] = Order::PAYMENT_METHOD_COD;
        $payloadHash = IdempotencyHasher::hash($data);
        $userId = $this->idempotencyUserId($request);
        $authenticatedUser = $request->user('sanctum') ?? $request->user();
        if ($authenticatedUser && ! $authenticatedUser->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Vui lòng xác minh email trước khi đặt hàng.',
                'email_verification_required' => true,
            ], 403);
        }

        $checkoutIpHash = AnalyticsIdentifier::hash('checkout-ip|'.(string) $request->ip());
        if ($userId === null) {
            if (! $turnstile->verify($turnstileToken, $request->ip())) {
                return response()->json([
                    'message' => 'Không thể xác minh yêu cầu. Vui lòng thử lại.',
                ], 422);
            }

            if ($this->hasTooManyPendingGuestOrders($data['email'], $data['customer_phone'], $checkoutIpHash)) {
                return response()->json([
                    'message' => 'Bạn đã có nhiều đơn đang chờ xử lý. Vui lòng thử lại sau.',
                ], 429);
            }
        }
        $analyticsBinding = $request->hasSession() ? (string) $request->session()->token() : '';
        $analyticsSession = $analyticsBinding !== ''
            ? $analyticsSessions->verify($request->header('X-Analytics-Token'), $analyticsBinding)
            : null;
        $analyticsSessionHash = $analyticsSession
            ? AnalyticsIdentifier::hash($analyticsSession['session_id'])
            : null;
        $idempotencyScope = $userId === null ? 'guest' : "user:{$userId}";

        if ($data['coupon_code'] && $userId === null) {
            return response()->json([
                'message' => 'Vui lòng đăng nhập để sử dụng mã giảm giá.',
            ], 422);
        }

        try {
            [$order, $isReplay] = Cache::lock(
                'order:create:'.hash('sha256', $idempotencyScope.'|'.$data['idempotency_key']),
                15
            )->block(5, function () use ($data, $payloadHash, $userId, $couponService, $analyticsSessionHash, $checkoutIpHash, $idempotencyScope) {
                return DB::transaction(function () use ($data, $payloadHash, $userId, $couponService, $analyticsSessionHash, $checkoutIpHash, $idempotencyScope) {
                    IdempotencyKey::query()
                        ->where('idempotency_key', $data['idempotency_key'])
                        ->where('scope', $idempotencyScope)
                        ->where('expires_at', '<=', now())
                        ->delete();

                    $existingKey = IdempotencyKey::query()
                        ->where('idempotency_key', $data['idempotency_key'])
                        ->where('scope', $idempotencyScope)
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
                        'user_id' => $userId,
                        'fullname' => $data['customer_name'],
                        'address' => $data['address'],
                        'phone' => $data['customer_phone'],
                        'email' => $data['email'],
                        'note' => $data['note'] ?? null,
                        'status' => Order::STATUS_PENDING,
                        'payment_method' => Order::PAYMENT_METHOD_COD,
                        'payment_status' => Order::PAYMENT_STATUS_PENDING,
                        'idempotency_key' => $data['idempotency_key'],
                        'checkout_ip_hash' => $userId === null ? $checkoutIpHash : null,
                        'guest_expires_at' => $userId === null
                            ? now()->addMinutes(max(15, (int) config('services.turnstile.guest_order_ttl_minutes', 120)))
                            : null,
                    ]);
                    if ($analyticsSessionHash) {
                        $order->forceFill(['analytics_session_hash' => $analyticsSessionHash])->save();
                    }

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

                    $subtotal = 0;

                    foreach ($data['products'] as $productData) {
                        $product = $products->get((int) $productData['product_id']);
                        $quantity = (int) $productData['quantity'];

                        if (! $product || ! $product->is_active) {
                            abort(422, 'Sản phẩm không khả dụng hoặc đã ngưng kinh doanh.');
                        }

                        if ($product->inventory < $quantity) {
                            abort(422, "Sản phẩm {$product->name} không đủ tồn kho.");
                        }

                        $product->decrement('inventory', $quantity);
                        $unitPrice = (float) $product->price;
                        $lineTotal = $unitPrice * $quantity;
                        $subtotal += $lineTotal;

                        $order->details()->create([
                            'product_id' => $product->id,
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'product_name' => $product->name,
                            'line_total' => $lineTotal,
                        ]);
                    }

                    $couponResult = null;
                    $discountAmount = 0;

                    if ($data['coupon_code']) {
                        try {
                            $couponResult = $couponService->validate(
                                $data['coupon_code'],
                                $subtotal,
                                (int) $userId,
                                true
                            );
                        } catch (\Exception $exception) {
                            throw new HttpResponseException(response()->json([
                                'message' => $exception->getMessage(),
                            ], 422));
                        }

                        $discountAmount = (float) $couponResult['discount_amount'];

                        $order->forceFill([
                            'coupon_id' => $couponResult['coupon']->id,
                            'discount_amount' => $discountAmount,
                        ])->save();
                    }

                    $this->updateOrderTotals($order, $subtotal, $discountAmount);

                    if ($couponResult) {
                        CouponUsage::create([
                            'coupon_id' => $couponResult['coupon']->id,
                            'user_id' => $userId,
                            'order_id' => $order->id,
                            'discount_amount' => $discountAmount,
                            'created_at' => now(),
                        ]);

                        $couponResult['coupon']->increment('used_count');
                    }

                    IdempotencyKey::create([
                        'idempotency_key' => $data['idempotency_key'],
                        'scope' => $idempotencyScope,
                        'payload_hash' => $payloadHash,
                        'user_id' => $userId,
                        'order_id' => $order->id,
                        'expires_at' => now()->addHours(24),
                    ]);

                    return [$order, false];
                }, 3);
            });
        } catch (LockTimeoutException $exception) {
            Log::warning('Order idempotency lock timed out.', [
                'idempotency_key_hash' => hash('sha256', $data['idempotency_key']),
            ]);

            return response()->json([
                'message' => 'Đơn hàng đang được xử lý. Vui lòng thử lại sau.',
            ], 409);
        }

        $order->load(['details.product.category', 'coupon', 'statusHistory.changedBy:id,name,role'])
            ->loadSum('details as total', 'line_total');

        if (! $isReplay && Cache::add(
            'order-confirmation-email:'.AnalyticsIdentifier::hash($data['email']),
            true,
            now()->addMinute()
        )) {
            SendOrderConfirmationEmail::dispatch($order->id)->onQueue('emails');
        }

        return response()->json([
            'data' => $order,
            'idempotent_replay' => $isReplay,
        ], $isReplay ? 200 : 201);
    }

    public function updateStatus(Request $request, string $id, OrderStatusService $statusService)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Order::STATUSES)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $order = Order::query()->findOrFail($id);

        try {
            $order = $statusService->transition($order, $data['status'], $data['note'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $order->load(['details.product.category', 'coupon', 'statusHistory.changedBy:id,name,role'])
            ->loadSum('details as total', 'line_total');

        return response()->json($order);
    }

    public function cancelMyOrder(Request $request, Order $order, OrderStatusService $statusService)
    {
        if ((int) $order->user_id !== (int) $request->user()->id) {
            abort(404);
        }

        if ($order->status !== Order::STATUS_PENDING) {
            return response()->json([
                'message' => 'Chỉ có thể hủy đơn hàng đang chờ xử lý.',
            ], 422);
        }

        try {
            $order = $statusService->transition($order, Order::STATUS_CANCELLED, 'Customer cancelled the order.', (int) $request->user()->id);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $order->load(['details.product.category', 'coupon', 'statusHistory.changedBy:id,name,role'])
            ->loadSum('details as total', 'line_total');

        return response()->json($order);
    }

    private function applyAdminFilters($query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        $dateFrom = $filters['date_from'] ?? $filters['from'] ?? null;
        $dateTo = $filters['date_to'] ?? $filters['to'] ?? null;

        if (! empty($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if (! empty($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $keyword = trim((string) ($filters['keyword'] ?? $filters['q'] ?? ''));

        if ($keyword !== '') {
            $query->where(function ($subQuery) use ($keyword) {
                if (ctype_digit($keyword)) {
                    $subQuery->where('id', (int) $keyword);
                }

                $subQuery
                    ->orWhere('fullname', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('address', 'like', "%{$keyword}%");
            });
        }
    }

    private function updateOrderTotals(Order $order, float $subtotal, float $discountAmount = 0): void
    {
        $shippingFee = $this->shippingFee($subtotal);
        $discountAmount = min(max($discountAmount, 0), $subtotal);

        $order->forceFill([
            'subtotal' => $subtotal,
            'shipping_fee' => $shippingFee,
            'discount_amount' => $discountAmount,
            'grand_total' => $subtotal + $shippingFee - $discountAmount,
        ])->save();
    }

    private function shippingFee(float $subtotal): float
    {
        $settings = SiteSetting::current();
        if ($subtotal <= 0 || $subtotal >= $settings->free_shipping_threshold) {
            return 0;
        }

        return (float) $settings->shipping_fee;
    }

    private function csvCell(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1
            ? "'".$value
            : $value;
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

    private function hasTooManyPendingGuestOrders(string $email, string $phone, string $ipHash): bool
    {
        $pending = Order::query()
            ->whereNull('user_id')
            ->where('status', Order::STATUS_PENDING)
            ->where('created_at', '>=', now()->subHours(2));

        return (clone $pending)->where('email', $email)->count() >= 5
            || (clone $pending)->where('phone', $phone)->count() >= 5
            || (clone $pending)->where('checkout_ip_hash', $ipHash)->count() >= 5;
    }
}
