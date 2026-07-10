<?php

namespace App\Http\Controllers;

use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Services\CouponService;
use App\Services\OrderStatusService;
use App\Services\VNPayService;
use App\Support\IdempotencyHasher;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function create(Request $request, VNPayService $vnPayService, CouponService $couponService)
    {
        if ($request->user()->role !== 'customer') {
            return response()->json([
                'message' => 'Vui lòng đăng nhập bằng tài khoản khách hàng để thanh toán VNPay.',
            ], 403);
        }

        $idempotencyKey = $request->header('X-Idempotency-Key');

        if (! is_string($idempotencyKey) || trim($idempotencyKey) === '') {
            return response()->json([
                'message' => 'Header X-Idempotency-Key là bắt buộc.',
            ], 422);
        }

        $request->merge([
            'idempotency_key' => $idempotencyKey,
        ]);

        $data = $this->validatedCheckoutData($request);
        $data['payment_method'] = Order::PAYMENT_METHOD_VNPAY;
        $payloadHash = IdempotencyHasher::hash($data);
        $userId = $request->user()->id;

        try {
            [$order, $isReplay] = Cache::lock(
                'payment:create:'.hash('sha256', "user:{$userId}|{$data['idempotency_key']}"),
                15
            )->block(5, function () use ($data, $payloadHash, $userId, $couponService) {
                return DB::transaction(function () use ($data, $payloadHash, $userId, $couponService) {
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

                        if ($existingOrder) {
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
                        'payment_method' => Order::PAYMENT_METHOD_VNPAY,
                        'payment_status' => Order::PAYMENT_STATUS_PENDING,
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

                    $subtotal = 0;

                    foreach ($data['products'] as $productData) {
                        $product = $products->get((int) $productData['product_id']);
                        $quantity = (int) $productData['quantity'];

                        if (! $product) {
                            throw new HttpResponseException(response()->json([
                                'message' => 'Sản phẩm không tồn tại.',
                                'code' => 'PRODUCT_NOT_FOUND',
                            ], 422));
                        }

                        if ($product->inventory < $quantity) {
                            throw new HttpResponseException(response()->json([
                                'message' => "Sản phẩm {$product->name} không đủ tồn kho.",
                                'code' => 'INSUFFICIENT_STOCK',
                            ], 422));
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
                        'payload_hash' => $payloadHash,
                        'user_id' => $userId,
                        'order_id' => $order->id,
                        'expires_at' => now()->addHours(24),
                    ]);

                    return [$order, false];
                });
            });
        } catch (LockTimeoutException $exception) {
            Log::warning('VNPay idempotency lock timed out.', [
                'idempotency_key_hash' => hash('sha256', $data['idempotency_key']),
            ]);

            return response()->json([
                'message' => 'Thanh toán đang được xử lý. Vui lòng thử lại sau.',
            ], 409);
        }

        $order->load(['details.product.category', 'coupon'])->loadSum('details as total', 'line_total');

        return response()->json([
            'data' => $order,
            'payment_url' => $vnPayService->createPaymentUrl($order),
            'idempotent_replay' => $isReplay,
        ], $isReplay ? 200 : 201);
    }

    public function vnpayReturn(Request $request, VNPayService $vnPayService): RedirectResponse
    {
        $params = $request->query();
        $order = Order::with('details')
            ->whereKey($params['vnp_TxnRef'] ?? null)
            ->first();
        $frontendUrl = $this->frontendUrl();

        if (! $order || ! $vnPayService->verifyReturn($params)) {
            return redirect()->away($frontendUrl.'/thanh-toan?error=payment_failed');
        }

        $amountMatches = (int) ($params['vnp_Amount'] ?? 0) === (int) round($vnPayService->orderTotal($order) * 100);
        $isPaid = $amountMatches
            && ($params['vnp_ResponseCode'] ?? '') === '00'
            && ($params['vnp_TransactionStatus'] ?? '') === '00';

        if ($isPaid) {
            $wasPaid = $order->payment_status === Order::PAYMENT_STATUS_PAID;

            $this->markPaymentPaid($order);

            if (! $wasPaid) {
                SendOrderConfirmationEmail::dispatch($order->id)->onQueue('emails');
            }

            return redirect()->away(
                $frontendUrl.'/dat-hang-thanh-cong?orderId='.$order->id.'&payment=vnpay'
            );
        }

        $this->markPaymentFailed($order);

        return redirect()->away(
            $frontendUrl.'/thanh-toan?error=payment_failed&orderId='.$order->id
        );
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
                Rule::exists('products', 'id'),
            ],
            'products.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $data['coupon_code'] = isset($data['coupon_code']) && trim((string) $data['coupon_code']) !== ''
            ? strtoupper(trim((string) $data['coupon_code']))
            : null;

        return $data;
    }

    private function markPaymentFailed(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->payment_status === Order::PAYMENT_STATUS_FAILED) {
                return;
            }

            if (
                $lockedOrder->payment_status !== Order::PAYMENT_STATUS_PAID
                && $lockedOrder->status !== Order::STATUS_CANCELLED
            ) {
                $details = $lockedOrder->details()->get();
                $products = Product::query()
                    ->whereIn('id', $details->pluck('product_id')->filter()->unique()->sort()->values())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($details as $detail) {
                    $products->get($detail->product_id)?->increment('inventory', $detail->quantity);
                }

                if ($lockedOrder->coupon_id) {
                    CouponUsage::query()
                        ->where('order_id', $lockedOrder->id)
                        ->delete();

                    Coupon::query()
                        ->whereKey($lockedOrder->coupon_id)
                        ->where('used_count', '>', 0)
                        ->decrement('used_count');
                }
            }

            if ($lockedOrder->status !== Order::STATUS_CANCELLED) {
                OrderStatusHistory::create([
                    'order_id' => $lockedOrder->id,
                    'from_status' => $lockedOrder->status,
                    'to_status' => Order::STATUS_CANCELLED,
                    'changed_by' => null,
                    'note' => 'VNPay payment failed or was cancelled.',
                ]);
            }

            $lockedOrder->update([
                'status' => Order::STATUS_CANCELLED,
                'payment_status' => Order::PAYMENT_STATUS_FAILED,
            ]);
        });
    }

    private function markPaymentPaid(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->payment_status !== Order::PAYMENT_STATUS_PAID) {
                $lockedOrder->update([
                    'payment_status' => Order::PAYMENT_STATUS_PAID,
                ]);
            }

            if ($lockedOrder->status === Order::STATUS_PENDING) {
                app(OrderStatusService::class)
                    ->transition($lockedOrder, Order::STATUS_CONFIRMED, 'VNPay payment confirmed.');
            }
        });
    }

    private function updateOrderTotals(Order $order, float $subtotal, float $discountAmount = 0): void
    {
        $shippingFee = $subtotal > 0 && $subtotal < 200000 ? 20000 : 0;
        $discountAmount = min(max($discountAmount, 0), $subtotal);

        $order->forceFill([
            'subtotal' => $subtotal,
            'shipping_fee' => $shippingFee,
            'discount_amount' => $discountAmount,
            'grand_total' => $subtotal + $shippingFee - $discountAmount,
        ])->save();
    }

    private function frontendUrl(): string
    {
        $frontendUrl = (string) config('services.vnpay.frontend_url', '');

        if ($frontendUrl === '') {
            $frontendUrl = app()->environment('production') ? '' : 'http://127.0.0.1:5173';
        }

        $host = parse_url($frontendUrl, PHP_URL_HOST);
        $isLocalhost = in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true);

        if (app()->environment('production') && ($frontendUrl === '' || $isLocalhost)) {
            Log::critical('FRONTEND_URL is not configured for production VNPay redirect.', [
                'frontend_url' => $frontendUrl,
            ]);

            abort(500, 'FRONTEND_URL chưa được cấu hình cho production.');
        }

        return rtrim($frontendUrl, '/');
    }
}
