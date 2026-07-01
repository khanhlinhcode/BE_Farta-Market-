<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Arr;
use RuntimeException;

class VNPayService
{
    public function createPaymentUrl(Order $order): string
    {
        $this->ensureConfigured();

        $amount = $this->orderTotal($order);
        if ($amount <= 0) {
            throw new RuntimeException('VNPAY_INVALID_ORDER_AMOUNT');
        }

        $params = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => config('services.vnpay.tmn_code'),
            'vnp_Amount' => (int) round($amount * 100),
            'vnp_CurrCode' => 'VND',
            'vnp_TxnRef' => (string) $order->id,
            'vnp_OrderInfo' => 'Thanh toan don hang #'.$order->id,
            'vnp_OrderType' => 'other',
            'vnp_Locale' => 'vn',
            'vnp_ReturnUrl' => config('services.vnpay.return_url'),
            'vnp_IpAddr' => request()->ip() ?: '127.0.0.1',
            'vnp_CreateDate' => now()->format('YmdHis'),
            'vnp_ExpireDate' => now()->addMinutes(15)->format('YmdHis'),
        ];

        ksort($params);
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $secureHash = hash_hmac(
            'sha512',
            $query,
            (string) config('services.vnpay.hash_secret')
        );

        return rtrim((string) config('services.vnpay.url'), '?')
            .'?'.$query
            .'&vnp_SecureHash='.$secureHash;
    }

    public function verifyReturn(array $params): bool
    {
        $secureHash = $params['vnp_SecureHash'] ?? '';

        if (! is_string($secureHash) || $secureHash === '') {
            return false;
        }

        $hashParams = Arr::except($params, ['vnp_SecureHash', 'vnp_SecureHashType']);
        ksort($hashParams);

        $hashData = http_build_query($hashParams, '', '&', PHP_QUERY_RFC3986);
        $expectedHash = hash_hmac(
            'sha512',
            $hashData,
            (string) config('services.vnpay.hash_secret')
        );

        return hash_equals($expectedHash, $secureHash);
    }

    public function orderTotal(Order $order): float
    {
        if ($order->relationLoaded('details')) {
            return (float) $order->details->sum(fn ($detail) => (float) $detail->line_total);
        }

        return (float) $order->details()->sum('line_total');
    }

    private function ensureConfigured(): void
    {
        foreach (['tmn_code', 'hash_secret', 'url', 'return_url'] as $key) {
            if (! config("services.vnpay.{$key}")) {
                throw new RuntimeException("VNPAY_CONFIG_MISSING:{$key}");
            }
        }
    }
}
