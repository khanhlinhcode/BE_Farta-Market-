<?php

namespace App\Services\Chat;

use Illuminate\Support\Str;

final class ChatEvidencePolicy
{
    /**
     * This is deliberately a small, application-owned allow-list.  A retrieval
     * score is not evidence authority, and callers are not allowed to widen a
     * domain after the router has selected it.
     *
     * @var array<string, array{claim_type: string, allowed_source_ids: array<int, string>, allowed_authority: string, structured_source: ?string}>
     */
    private const DOMAINS = [
        'product_catalog' => ['claim_type' => 'current_product_detail', 'allowed_source_ids' => ['products', 'categories'], 'allowed_authority' => 'structured', 'structured_source' => 'products'],
        'product_price' => ['claim_type' => 'current_product_price', 'allowed_source_ids' => ['products'], 'allowed_authority' => 'structured', 'structured_source' => 'products'],
        'product_inventory' => ['claim_type' => 'current_product_inventory', 'allowed_source_ids' => ['products'], 'allowed_authority' => 'structured', 'structured_source' => 'products'],
        'product_inventory_and_ordering_contract' => ['claim_type' => 'cart_suggestion', 'allowed_source_ids' => ['products', 'ordering-contract'], 'allowed_authority' => 'structured', 'structured_source' => 'products'],
        'product_price_and_shipping_settings' => ['claim_type' => 'delivered_total', 'allowed_source_ids' => ['products', 'site-settings'], 'allowed_authority' => 'structured', 'structured_source' => 'products+site-settings'],
        'owned_order_data' => ['claim_type' => 'owned_order_read', 'allowed_source_ids' => ['owned-orders'], 'allowed_authority' => 'authorized_structured', 'structured_source' => 'owned-orders'],
        'owned_order_payment_status' => ['claim_type' => 'owned_payment_status', 'allowed_source_ids' => ['owned-orders'], 'allowed_authority' => 'authorized_structured', 'structured_source' => 'owned-orders'],
        'store_contact_settings' => ['claim_type' => 'current_contact_value', 'allowed_source_ids' => ['site-settings'], 'allowed_authority' => 'structured', 'structured_source' => 'site-settings'],
        'account' => ['claim_type' => 'account_guidance', 'allowed_source_ids' => ['account-guide-vi'], 'allowed_authority' => 'approved_knowledge', 'structured_source' => null],
        'orders' => ['claim_type' => 'order_guidance', 'allowed_source_ids' => ['order-guide-vi'], 'allowed_authority' => 'approved_knowledge', 'structured_source' => null],
        'payment' => ['claim_type' => 'payment_guidance', 'allowed_source_ids' => ['payment-guide-vi'], 'allowed_authority' => 'approved_knowledge', 'structured_source' => null],
        'ordering' => ['claim_type' => 'ordering_guidance', 'allowed_source_ids' => ['shopping-guide-vi'], 'allowed_authority' => 'approved_knowledge', 'structured_source' => null],
        'policy' => ['claim_type' => 'policy_overview', 'allowed_source_ids' => ['policy-index-vi'], 'allowed_authority' => 'approved_knowledge', 'structured_source' => null],
        'shipping' => ['claim_type' => 'current_shipping_value', 'allowed_source_ids' => ['site-settings'], 'allowed_authority' => 'structured', 'structured_source' => 'site-settings'],
        'contact' => ['claim_type' => 'current_contact_value', 'allowed_source_ids' => ['site-settings'], 'allowed_authority' => 'structured', 'structured_source' => 'site-settings'],
        'shipping_policy' => ['claim_type' => 'shipping_policy', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
        'returns' => ['claim_type' => 'return_or_refund_policy', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
        'storage' => ['claim_type' => 'storage_policy', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
        'organic_certification' => ['claim_type' => 'organic_certification', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
        'delivery_sla' => ['claim_type' => 'delivery_sla', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
        'packaging_return_policy' => ['claim_type' => 'packaging_return_policy', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
        'supplier_provenance' => ['claim_type' => 'supplier_provenance', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
        'traceability_policy' => ['claim_type' => 'traceability_policy', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
        'food_safety_certification' => ['claim_type' => 'food_safety_certification', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
        'warranty_policy' => ['claim_type' => 'warranty_policy', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
        'bulk_order_policy' => ['claim_type' => 'bulk_order_policy', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
        'cold_chain_policy' => ['claim_type' => 'cold_chain_policy', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
        'privacy_policy' => ['claim_type' => 'privacy_policy', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
        'price_match_policy' => ['claim_type' => 'price_match_policy', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
        'subscription_policy' => ['claim_type' => 'subscription_policy', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
        'express_delivery_policy' => ['claim_type' => 'express_delivery_policy', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
        'loyalty_policy' => ['claim_type' => 'loyalty_policy', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
        'fulfilment_compensation_policy' => ['claim_type' => 'fulfilment_compensation_policy', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
        'opened_food_return_policy' => ['claim_type' => 'opened_food_return_policy', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
        'returns_policy' => ['claim_type' => 'returns_policy', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
        'refund_policy' => ['claim_type' => 'refund_policy', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
        'product_usage_suitability' => ['claim_type' => 'product_usage_suitability', 'allowed_source_ids' => [], 'allowed_authority' => 'none', 'structured_source' => null],
    ];

    public function domain(string $topic): ChatEvidenceDomain
    {
        $policy = self::DOMAINS[$topic] ?? [
            'claim_type' => 'unknown',
            'allowed_source_ids' => [],
            'allowed_authority' => 'none',
            'structured_source' => null,
        ];

        return new ChatEvidenceDomain(
            $topic,
            $policy['claim_type'],
            $policy['allowed_source_ids'],
            $policy['allowed_authority'],
            $policy['structured_source'],
        );
    }

    /**
     * A downstream caller may retain or narrow the route domain, but cannot
     * supply a broadened or hand-built replacement.  A mismatch deliberately
     * becomes an empty domain so the answer path fails closed.
     *
     * @param  ChatEvidenceDomain|array<string, mixed>|null  $requested
     */
    public function canonical(ChatEvidenceDomain|array|null $requested, ?string $topic = null): ChatEvidenceDomain
    {
        $requestedTopic = $requested instanceof ChatEvidenceDomain
            ? $requested->topic
            : (is_array($requested) && is_string($requested['topic'] ?? null)
                ? $requested['topic']
                : ($topic ?? 'unknown'));
        $canonical = $this->domain($requestedTopic);

        if ($requested === null) {
            return $canonical;
        }

        $candidate = $requested instanceof ChatEvidenceDomain ? $requested : ChatEvidenceDomain::fromArray($requested);

        return $this->sameDomain($candidate, $canonical)
            ? ($requested instanceof ChatEvidenceDomain ? $requested : $canonical)
            : $this->domain('unknown');
    }

    /** @param array<string, mixed> $chunk */
    public function eligible(array $chunk, ChatEvidenceDomain|array $domain): bool
    {
        $domain = $domain instanceof ChatEvidenceDomain ? $domain : ChatEvidenceDomain::fromArray($domain);
        $authority = $domain->allowedAuthority;
        $approvedKnowledge = $authority !== 'approved_knowledge'
            || (($chunk['owner'] ?? null) === 'Farta Market' && ($chunk['status'] ?? null) === 'published');

        return $approvedKnowledge
            && ($chunk['evidence_eligible'] ?? false) === true
            && ($chunk['topic'] ?? null) === $domain->topic
            && ($chunk['authority'] ?? null) === $authority
            && in_array($chunk['source_id'] ?? null, $domain->allowedSourceIds, true);
    }

    /**
     * Deterministic claim-to-section gate for approved guides. Domain and
     * source eligibility alone do not prove that a chunk answers the claim.
     *
     * @param  array<string, mixed>  $chunk
     */
    public function supportsClaim(string $question, array $chunk, ChatEvidenceDomain|array $domain): bool
    {
        $domain = $domain instanceof ChatEvidenceDomain ? $domain : ChatEvidenceDomain::fromArray($domain);
        if ($domain->allowedAuthority === 'structured') {
            return true;
        }
        if ($domain->allowedAuthority !== 'approved_knowledge') {
            return false;
        }

        $question = $this->normalize($question);
        $section = $this->normalize((string) ($chunk['section'] ?? ''));
        $requiredSection = match ($domain->topic) {
            'account' => match (true) {
                $this->has($question, ['quen mat khau', 'forgot password', 'forgotten password', 'reset password', 'account recovery']) => ['quen mat khau'],
                $this->has($question, ['khong thay mail', 'chua nhan email', 'gui lai email', 'resend', 'did not receive']) => ['gui lai email xac minh'],
                $this->has($question, ['feature', 'chuc nang', 'require verified', 'yeu cau xac minh']) => ['cac chuc nang yeu cau xac minh'],
                default => ['dang ky va xac minh email'],
            },
            'orders' => match (true) {
                $this->has($question, ['trang thai', 'status flow', 'statuses']) => ['cac trang thai don hang'],
                $this->has($question, ['huy', 'cancel']) && ! $this->has($question, ['chatbot']) => ['dieu kien huy don'],
                $this->has($question, ['chatbot']) => ['hoi chatbot ve don hang'],
                default => ['xem don hang'],
            },
            'payment' => match (true) {
                $this->has($question, ['het han', 'expired', 'expiry']) => ['yeu cau thanh toan het han'],
                $this->has($question, ['noi dung', 'dung so tien', 'reference', 'transfer content']) => ['noi dung va so tien chuyen khoan'],
                $this->has($question, ['who marks', 'ai danh dau', 'xac nhan', 'confirmed by']) => ['xac nhan thanh toan'],
                $this->has($question, ['cod', 'khi nhan hang', 'cash on delivery', 'pay cash', 'cash when', 'courier arrives']) => ['thanh toan khi nhan hang', 'cod'],
                default => ['thanh toan bang sepay'],
            },
            'ordering' => match (true) {
                $this->has($question, ['chatbot', 'through chat', 'qua chat', 'add an item', 'them hang', 'them san pham', 'add cart', 'confirm', 'de xuat']) => ['them san pham tu chatbot'],
                $this->has($question, ['coupon', 'ma giam']) => ['ma giam gia'],
                $this->has($question, ['truoc khi', 'before', 'checkout', 'recheck', 'kiem tra']) => ['kiem tra gio hang'],
                default => ['tim kiem san pham'],
            },
            'policy' => ['cac nhom thong tin da kiem chung'],
            default => [],
        };

        return $requiredSection === [] || collect($requiredSection)->contains(
            fn (string $candidate): bool => str_contains($section, $candidate)
        );
    }

    private function sameDomain(ChatEvidenceDomain $left, ChatEvidenceDomain $right): bool
    {
        return $left->sameAs($right);
    }

    /** @param array<int, string> $phrases */
    private function has(string $text, array $phrases): bool
    {
        return collect($phrases)->contains(
            fn (string $phrase): bool => preg_match('/(?:^|\s)'.preg_quote($phrase, '/').'(?=$|\s)/', $text) === 1
        );
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9\s]/', ' ')->squish()->toString();
    }
}
