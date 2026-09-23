<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Str;
use RuntimeException;

class SepayService
{
    public function createPaymentReference(Order $order): string
    {
        $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) config('services.sepay.payment_prefix', 'FM')));

        return ($prefix ?: 'FM').$order->id.Str::upper(Str::random(6));
    }

    public function paymentDetails(Order $order): array
    {
        $this->ensureQrConfigured();

        $amount = (int) round((float) $order->grand_total);
        if ($amount <= 0 || ! $order->payment_reference) {
            throw new RuntimeException('SEPAY_INVALID_ORDER');
        }

        $accountNumber = (string) config('services.sepay.account_number');
        $bankCode = (string) config('services.sepay.bank_code');
        $query = http_build_query([
            'acc' => $accountNumber,
            'bank' => $bankCode,
            'amount' => $amount,
            'des' => $order->payment_reference,
            'template' => 'compact',
            'showinfo' => 'true',
        ], '', '&', PHP_QUERY_RFC3986);

        return [
            'amount' => $amount,
            'bank_code' => $bankCode,
            'account_number' => $accountNumber,
            'account_holder' => (string) config('services.sepay.account_holder', ''),
            'reference' => $order->payment_reference,
            'expires_at' => optional($order->payment_expires_at)->toIso8601String(),
            'qr_url' => rtrim((string) config('services.sepay.qr_base_url'), '?').'?'.$query,
        ];
    }

    public function verifyWebhook(string $rawBody, ?string $timestamp, ?string $signature): bool
    {
        $secret = (string) config('services.sepay.webhook_secret', '');
        if ($secret === '') {
            throw new RuntimeException('SEPAY_WEBHOOK_SECRET_MISSING');
        }

        if (! is_string($timestamp) || ! ctype_digit($timestamp) || ! is_string($signature)) {
            return false;
        }

        $tolerance = max(30, (int) config('services.sepay.webhook_tolerance_seconds', 300));
        if (abs(now()->timestamp - (int) $timestamp) > $tolerance) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    private function ensureQrConfigured(): void
    {
        foreach (['account_number', 'bank_code', 'qr_base_url'] as $key) {
            if (! config("services.sepay.{$key}")) {
                throw new RuntimeException("SEPAY_CONFIG_MISSING:{$key}");
            }
        }
    }
}
