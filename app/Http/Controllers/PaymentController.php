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
use App\Services\SepayService;
use App\Support\AnalyticsIdentifier;
use App\Support\IdempotencyHasher;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;

class PaymentController extends Controller
{
    public function create(
        Request $request,
        SepayService $sepayService,
        CouponService $couponService,
        AnalyticsSessionService $analyticsSessions
    ) {
        if ($request->user()->role !== 'customer') {
            return response()->json([
                'message' => 'Vui lòng đăng nhập bằng tài khoản khách hàng để thanh toán SePay.',
            ], 403);
        }

        $idempotencyKey = $request->header('X-Idempotency-Key');

        if (! is_string($idempotencyKey) || trim($idempotencyKey) === '') {
            return response()->json([
                'message' => 'Header X-Idempotency-Key là bắt buộc.',
            ], 422);
        }

        $request->merge(['idempotency_key' => $idempotencyKey]);

        $data = $this->validatedCheckoutData($request);
        $data['payment_method'] = Order::PAYMENT_METHOD_SEPAY;
        $payloadHash = IdempotencyHasher::hash($data);
        $userId = $request->user()->id;
        $idempotencyScope = "user:{$userId}";
        $analyticsBinding = $request->hasSession() ? (string) $request->session()->token() : '';
        $analyticsSession = $analyticsBinding !== ''
            ? $analyticsSessions->verify($request->header('X-Analytics-Token'), $analyticsBinding)
            : null;
        $analyticsSessionHash = $analyticsSession
            ? AnalyticsIdentifier::hash($analyticsSession['session_id'])
            : null;

        try {
            [$order, $isReplay] = Cache::lock(
                'payment:create:'.hash('sha256', $idempotencyScope.'|'.$data['idempotency_key']),
                15
            )->block(5, function () use ($data, $payloadHash, $userId, $couponService, $sepayService, $analyticsSessionHash, $idempotencyScope) {
                return DB::transaction(function () use ($data, $payloadHash, $userId, $couponService, $sepayService, $analyticsSessionHash, $idempotencyScope) {
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

                        if ($existingOrder) {
                            $sepayService->paymentDetails($existingOrder);

                            return [$existingOrder, true];
                        }

                        $existingKey->delete();
                    }

                    $order = Order::create([
                        'user_id' => $userId,
                        'fullname' => $data['customer_name'],
                        'address' => $data['address'],
                        'phone' => $data['customer_phone'],
                        'email' => $data['email'],
                        'note' => $data['note'] ?? null,
                        'status' => Order::STATUS_PENDING,
                        'payment_method' => Order::PAYMENT_METHOD_SEPAY,
                        'payment_status' => Order::PAYMENT_STATUS_PENDING,
                        'idempotency_key' => $data['idempotency_key'],
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
                                $userId,
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
                    $order->forceFill([
                        'payment_reference' => $sepayService->createPaymentReference($order),
                        'payment_expires_at' => now()->addMinutes(max(1, (int) config('services.sepay.payment_ttl_minutes', 30))),
                    ])->save();
                    $sepayService->paymentDetails($order);

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
            Log::warning('SePay idempotency lock timed out.', [
                'idempotency_key_hash' => hash('sha256', $data['idempotency_key']),
            ]);

            return response()->json([
                'message' => 'Thanh toán đang được xử lý. Vui lòng thử lại sau.',
            ], 409);
        } catch (RuntimeException $exception) {
            if (! str_starts_with($exception->getMessage(), 'SEPAY_')) {
                throw $exception;
            }

            return response()->json(['message' => 'SePay chưa được cấu hình hoặc hiện không khả dụng.'], 503);
        }

        $order->load(['details.product.category', 'coupon'])->loadSum('details as total', 'line_total');

        return response()->json([
            'data' => $order,
            'payment' => $sepayService->paymentDetails($order),
            'idempotent_replay' => $isReplay,
        ], $isReplay ? 200 : 201);
    }

    public function status(Request $request, Order $order, SepayService $sepayService)
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);
        abort_unless($order->payment_method === Order::PAYMENT_METHOD_SEPAY, 404);

        $order->load(['details.product.category', 'coupon'])->loadSum('details as total', 'line_total');

        return response()->json([
            'data' => $order,
            'payment' => $sepayService->paymentDetails($order),
        ]);
    }

    public function webhook(Request $request, SepayService $sepayService)
    {
        try {
            $verified = $sepayService->verifyWebhook(
                $request->getContent(),
                $request->header('X-SePay-Timestamp'),
                $request->header('X-SePay-Signature')
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => 'SePay webhook chưa được cấu hình.'], 503);
        }

        if (! $verified) {
            return response()->json(['message' => 'Chữ ký webhook không hợp lệ.'], 401);
        }

        $data = $request->validate([
            'id' => ['required', 'integer'],
            'accountNumber' => ['required', 'string', 'max:50'],
            'transferType' => ['required', Rule::in(['in'])],
            'transferAmount' => ['required', 'integer', 'min:1'],
            'code' => ['nullable', 'string', 'max:40'],
            'content' => ['required', 'string', 'max:1000'],
        ]);

        $configuredAccount = trim((string) config('services.sepay.account_number'));
        $incomingAccount = trim($data['accountNumber']);
        if ($configuredAccount === '' || ! hash_equals($configuredAccount, $incomingAccount)) {
            return response()->json(['message' => 'Tài khoản nhận tiền không hợp lệ.'], 422);
        }

        $references = collect([
            $data['code'] ?? null,
            ...preg_split('/[^A-Z0-9]+/', strtoupper($data['content']), -1, PREG_SPLIT_NO_EMPTY),
        ])->filter(fn ($value) => is_string($value) && strlen($value) >= 4 && strlen($value) <= 40)
            ->map(fn ($value) => strtoupper(trim($value)))
            ->unique()
            ->values();
        $transactionId = trim((string) $data['id']);

        [$order, $changed] = DB::transaction(function () use ($references, $transactionId, $data) {
            $orders = Order::query()
                ->whereIn('payment_reference', $references)
                ->lockForUpdate()
                ->get();

            if ($orders->count() !== 1) {
                abort(404, 'Không tìm thấy đơn thanh toán SePay.');
            }

            $order = $orders->first();

            if ($order->payment_method !== Order::PAYMENT_METHOD_SEPAY) {
                abort(404, 'Không tìm thấy đơn thanh toán SePay.');
            }

            if ($order->payment_transaction_id === $transactionId
                && $order->payment_status === Order::PAYMENT_STATUS_PAID) {
                return [$order, false];
            }

            if (Order::query()
                ->where('payment_transaction_id', $transactionId)
                ->where('id', '!=', $order->id)
                ->exists()) {
                abort(409, 'Giao dịch SePay đã được sử dụng.');
            }

            if ($order->payment_status !== Order::PAYMENT_STATUS_PENDING
                || $order->status !== Order::STATUS_PENDING
                || optional($order->payment_expires_at)->isPast()) {
                abort(409, 'Đơn hàng không còn chờ thanh toán.');
            }

            if ((int) $data['transferAmount'] !== (int) round((float) $order->grand_total)) {
                abort(422, 'Số tiền thanh toán không khớp.');
            }

            $order->forceFill([
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'payment_transaction_id' => $transactionId,
            ])->save();
            app(OrderStatusService::class)->transition(
                $order,
                Order::STATUS_CONFIRMED,
                'SePay payment confirmed.'
            );

            return [$order->refresh(), true];
        }, 3);

        if ($changed) {
            SendOrderConfirmationEmail::dispatch($order->id)->onQueue('emails');
        }

        return response()->json(['success' => true]);
    }

    private function validatedCheckoutData(Request $request): array
    {
        $request->merge([
            'customer_name' => $request->input('customer_name', $request->input('fullname')),
            'customer_phone' => $request->input('customer_phone', $request->input('phone')),
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
            'note' => ['nullable', 'string', 'max:2000'],
            'coupon_code' => ['nullable', 'string', 'max:80'],
            'products' => ['required', 'array', 'min:1', 'max:50'],
            'products.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'products.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $data['coupon_code'] = isset($data['coupon_code']) && trim((string) $data['coupon_code']) !== ''
            ? strtoupper(trim((string) $data['coupon_code']))
            : null;

        return $data;
    }

    private function updateOrderTotals(Order $order, float $subtotal, float $discountAmount = 0): void
    {
        $settings = SiteSetting::current();
        $shippingFee = $subtotal > 0 && $subtotal < $settings->free_shipping_threshold
            ? (float) $settings->shipping_fee
            : 0;
        $discountAmount = min(max($discountAmount, 0), $subtotal);

        $order->forceFill([
            'subtotal' => $subtotal,
            'shipping_fee' => $shippingFee,
            'discount_amount' => $discountAmount,
            'grand_total' => $subtotal + $shippingFee - $discountAmount,
        ])->save();
    }
}
