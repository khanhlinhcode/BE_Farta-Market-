<?php

namespace App\Http\Controllers;

use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Order;
use App\Models\Product;
use App\Services\VNPayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function create(Request $request, VNPayService $vnPayService)
    {
        $data = $this->validatedCheckoutData($request);

        $order = DB::transaction(function () use ($data, $request) {
            $order = Order::create([
                'user_id' => $request->user()->id,
                'fullname' => $data['customer_name'],
                'address' => $data['address'],
                'phone' => $data['customer_phone'],
                'email' => $data['email'] ?? null,
                'note' => $data['note'] ?? null,
                'status' => Order::STATUS_PENDING_PAYMENT,
                'payment_method' => Order::PAYMENT_METHOD_VNPAY,
                'payment_status' => Order::PAYMENT_STATUS_PENDING,
                'idempotency_key' => $request->header('X-Idempotency-Key'),
            ]);

            $products = Product::query()
                ->whereIn(
                    'id',
                    collect($data['products'])->pluck('product_id')->sort()->values()
                )
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

            return $order;
        });

        $order->load(['details.product.category'])->loadSum('details as total', 'line_total');

        return response()->json([
            'data' => $order,
            'payment_url' => $vnPayService->createPaymentUrl($order),
        ], 201);
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

            $order->update([
                'status' => Order::STATUS_PREPARING,
                'payment_status' => Order::PAYMENT_STATUS_PAID,
            ]);

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

        return $request->validate([
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

            if ($lockedOrder->payment_status !== Order::PAYMENT_STATUS_PAID) {
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
            }

            $lockedOrder->update([
                'status' => Order::STATUS_PAYMENT_FAILED,
                'payment_status' => Order::PAYMENT_STATUS_FAILED,
            ]);
        });
    }

    private function frontendUrl(): string
    {
        return rtrim((string) config('services.vnpay.frontend_url', 'http://127.0.0.1:5173'), '/');
    }
}
