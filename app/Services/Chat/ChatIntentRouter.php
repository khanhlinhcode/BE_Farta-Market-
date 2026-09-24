<?php

namespace App\Services\Chat;

use App\Enums\ChatIntent;
use Illuminate\Support\Str;

final class ChatIntentRouter
{
    /**
     * Route a message without granting the model any application capability.
     *
     * @return array{intent: ChatIntent, query: string, filters: array{category: ?string, min_price: ?int, max_price: ?int, in_stock: ?bool}, requested_product: ?string, requires_auth: bool, confidence: float}
     */
    public function route(string $raw): array
    {
        $message = $this->normalize($raw);
        $intent = $this->detectIntent($message);

        return [
            'intent' => $intent,
            'query' => trim($raw),
            'filters' => $this->filters($message),
            'requested_product' => null,
            'requires_auth' => $intent === ChatIntent::OrderQuery,
            'confidence' => $intent === ChatIntent::Unsupported ? 0.55 : 0.95,
        ];
    }

    private function detectIntent(string $message): ChatIntent
    {
        if ($message === '') {
            return ChatIntent::Unsupported;
        }

        if ($this->matches($message, [
            'xoa don', 'doi trang thai don', 'danh dau da thanh toan', 'hoan tien',
            'delete order', 'change order status', 'mark as paid', 'mark order as paid', 'refund order',
            'tu van y te', 'tu van phap ly', 'tu van tai chinh', 'medical advice', 'legal advice', 'financial advice',
        ]) || preg_match('/\bdanh dau\b.*\bda thanh toan\b/', $message) === 1) {
            return ChatIntent::Unsupported;
        }

        if ($this->matches($message, [
            'don hang cua toi', 'don gan nhat', 'trang thai don', 'ma don', 'da thanh toan',
            'my order', 'latest order', 'order status', 'payment status',
        ]) || preg_match('/\b(?:don|order)\s+#?\d+\b/', $message) === 1) {
            return ChatIntent::OrderQuery;
        }

        if ($this->matches($message, [
            'trong gio', 'gio cua toi', 'gio hang co', 'gio hang cua', 'my cart', 'in my cart', 'cart contain', 'cart contains',
        ])) {
            return ChatIntent::CartQuery;
        }

        if (preg_match('/\b(mua|dat|lay|buy|order)\b|\bthem\b.*\bgio\b|\badd\b.*\bcart\b/', $message) === 1) {
            return ChatIntent::CartActionRequest;
        }

        if ($this->matches($message, [
            'phi ship', 'phi giao hang', 'mien phi van chuyen', 'mien phi giao hang', 'free ship',
            'shipping fee', 'delivery fee', 'free shipping', 'lien he', 'dia chi', 'hotline',
            'contact', 'address', 'doi tra', 'hoan tra', 'return policy', 'refund policy',
            'bao quan', 'storage', 'phuong thuc thanh toan', 'payment method', 'huong dan mua hang',
            'how to order', 'chinh sach giao hang', 'shipping policy', 'payment methods',
        ])) {
            return ChatIntent::KnowledgeQuery;
        }

        if ($this->matches($message, [
            'goi y', 'tu van', 'tim', 'san pham', 'danh muc', 'phu hop', 'tuong tu',
            'recommend', 'suggest', 'find', 'product', 'category', 'similar',
        ]) || $this->hasPriceFilter($message)) {
            return ChatIntent::ProductSearch;
        }

        if ($this->matches($message, [
            'gia', 'bao nhieu', 'ton kho', 'so luong', 'con hang', 'het hang',
            'price', 'stock', 'available', 'inventory',
        ])) {
            return ChatIntent::ProductDetail;
        }

        if ($this->matches($message, [
            'xin chao', 'chao', 'alo', 'hello', 'hi', 'hey', 'cam on', 'thank', 'thanks',
            'ban lam duoc gi', 'ban giup duoc gi', 'what can you do', 'how can you help',
        ])) {
            return ChatIntent::GeneralChat;
        }

        // Unknown shopping language is still allowed through bounded catalog retrieval.
        return ChatIntent::ProductSearch;
    }

    /** @return array{category: ?string, min_price: ?int, max_price: ?int, in_stock: ?bool} */
    private function filters(string $message): array
    {
        $min = null;
        $max = null;

        if (preg_match('/\b(?:duoi|khong qua|toi da|under|below|max)\s+([0-9]+(?:[.,][0-9]+)?)\s*(k|nghin|ngan|trieu|m)?\b/', $message, $match)) {
            $max = $this->money($match[1], $match[2] ?? '');
        }
        if (preg_match('/\b(?:tren|tu|toi thieu|over|above|min)\s+([0-9]+(?:[.,][0-9]+)?)\s*(k|nghin|ngan|trieu|m)?\b/', $message, $match)) {
            $min = $this->money($match[1], $match[2] ?? '');
        }

        return [
            'category' => null,
            'min_price' => $min,
            'max_price' => $max,
            'in_stock' => $this->matches($message, ['con hang', 'available', 'in stock']) ? true : null,
        ];
    }

    private function money(string $number, string $unit): int
    {
        $value = (float) str_replace(',', '.', $number);
        $multiplier = in_array($unit, ['k', 'nghin', 'ngan'], true) ? 1_000
            : (in_array($unit, ['trieu', 'm'], true) ? 1_000_000 : 1);

        return max(0, (int) round($value * $multiplier));
    }

    private function hasPriceFilter(string $message): bool
    {
        return preg_match('/\b(duoi|tren|khong qua|toi da|toi thieu|under|below|over|above)\b.*\d/', $message) === 1;
    }

    /** @param array<int, string> $phrases */
    private function matches(string $message, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (preg_match('/(?:^|\s)'.preg_quote($phrase, '/').'(?=$|\s)/', $message) === 1) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9\s]/', ' ')->squish()->toString();
    }
}
