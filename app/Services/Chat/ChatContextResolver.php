<?php

namespace App\Services\Chat;

use App\Enums\ChatIntent;
use App\Enums\ChatMutationTarget;

/**
 * Resolves references against already-authorized context. It may attach a
 * product/category reference or request clarification; it never chooses a new
 * business intent.
 */
final class ChatContextResolver
{
    /** @param array<string, mixed>|null $context */
    public function resolve(string $normalizedMessage, ChatRouteFrame $route, ?array $context): ChatRouteFrame
    {
        if ($route->intent === ChatIntent::MultiIntent) {
            return $route->withBranches(array_map(
                fn (ChatRouteFrame $branch): ChatRouteFrame => $this->resolve($normalizedMessage, $branch, $context),
                $route->branches,
            ));
        }

        if ($route->decisionState === 'denied_action'
            || $route->intent === ChatIntent::Unsupported
            || $route->clarificationReason === 'multi_intent') {
            return $route;
        }

        if ($route->mutationTargetAmbiguous) {
            return $route;
        }

        if ($context === null) {
            if ($this->isMutationFollowUp($normalizedMessage, $route)) {
                return $route->withMutationTarget(null, true);
            }

            return ($route->concepts['reference_required'] ?? false) === true
                ? $route->withContext(null, null, true)
                : $route;
        }

        if ($this->isMutationFollowUp($normalizedMessage, $route)) {
            $target = is_string($context['mutation_target'] ?? null)
                ? ChatMutationTarget::tryFrom($context['mutation_target'])
                : null;

            return $route->withMutationTarget($target, $target === null);
        }

        $productIds = $context['product_ids'] ?? [];
        if (! is_array($productIds) || $productIds === []) {
            return $route;
        }

        $singular = preg_match('/\b(?:cai|mon|san pham|mat hang|loai|thu|qua|chai|goi|hop|phan)\s+(?:do|nay|ay|vua roi|vua noi|vua nhac|vua xem|luc nay)|\b(?:san pham|mat hang)\s+vua\s+(?:xem|noi|nhac)|\b(?:no|it|its|that item|this item|that product|this product|the item|the one|that one)\b/', $normalizedMessage) === 1;
        $plural = preg_match('/\b(?:hai|may|cac|nhung)\s+(?:cai|mon|san pham|mat hang|hang|lua chon)\s+(?:do|nay|kia|vua roi|vua neu|vua nhac)|\bchung(?:\s+no)?\s+(?:thuoc|con|co|gia|dang)|\b(?:those|these)(?:\s+(?:items|products|ones))?\b|\bboth of them\b/', $normalizedMessage) === 1;
        $category = preg_match('/\b(?:nhom|danh muc|loai hang)\s+(?:do|nay|vua roi)\b|\b(?:cung\s+(?:nhom|loai)|same\s+category|same\s+type)\b/', $normalizedMessage) === 1;
        $ordinal = $this->ordinal($normalizedMessage, count($productIds));
        $implicitEllipsis = $route->intent === ChatIntent::ProductDetail
            && $route->semanticIntent === 'clarification'
            && in_array($route->concepts['product_facet'] ?? null, ['price', 'stock'], true);
        $implicitCartReference = $route->intent === ChatIntent::CartActionRequest
            && (int) ($route->entities['quantity'] ?? 0) > 0
            && trim((string) ($route->entities['product_name'] ?? '')) === '';

        if ($category && is_int($context['category_id'] ?? null) && $route->intent === ChatIntent::CatalogList) {
            return $route->withContext(null, $context['category_id']);
        }

        if ($plural && count($productIds) > 1 && $ordinal === null) {
            return $route->intent === ChatIntent::ProductDetail
                ? $route->withContext(null, null, false, $productIds)
                : $route->withContext(null, null, true);
        }

        if (($singular || $implicitEllipsis || $implicitCartReference) && count($productIds) !== 1 && $ordinal === null) {
            return $route->withContext(null, null, true);
        }

        $productId = $ordinal !== null ? ($productIds[$ordinal] ?? null) : ($productIds[0] ?? null);
        if (! is_int($productId) || $productId < 1) {
            return $route;
        }

        if (($singular || $plural || $ordinal !== null || $implicitEllipsis || $implicitCartReference)
            && in_array($route->intent, [ChatIntent::ProductDetail, ChatIntent::CartActionRequest], true)) {
            return $route->withContext($productId, null, false, [$productId]);
        }

        return $route;
    }

    public function isNegative(string $message): bool
    {
        return in_array($message, ['khong', 'khong can', 'thoi', 'huy', 'no', 'no thanks', 'cancel'], true)
            || preg_match('/\b(?:khong|dung|chua|huy|do not|dont|don t|no|not|never|stop|cancel)\b.*\b(?:mua|dat|lay|them|muon|buy|order|add|want)\b/', $message) === 1;
    }

    public function isAffirmative(string $message): bool
    {
        return in_array($message, ['co', 'co a', 'co nhe', 'dong y', 'duoc', 'ok', 'okay', 'yes', 'yes please'], true);
    }

    private function isMutationFollowUp(string $message, ChatRouteFrame $route): bool
    {
        if ($route->operation !== 'mutate'
            || $route->mutationTarget !== null
            || $route->mentionedResources !== []) {
            return false;
        }

        return preg_match(
            '/\b(?:cap nhat|chinh sua|sua|doi|thay|danh dau|ghi nhan|huy|xac nhan|set|update|edit|change|mark|record|cancel|confirm)\b.*\b(?:lai|again)\b/',
            $message,
        ) === 1;
    }

    private function ordinal(string $message, int $count): ?int
    {
        return match (true) {
            preg_match('/\b(?:cai|mon|san pham|item|product)?\s*(?:thu nhat|dau tien|dau|first|first one|first item|first product)\b/', $message) === 1 => 0,
            preg_match('/\b(?:the\s+)?first\s+(?:(?:shown|listed|displayed)\s+)?(?:one|item|product)\b/', $message) === 1 => 0,
            preg_match('/\b(?:cai|mon|san pham|item|product)?\s*(?:thu hai|second one|second item|second product)\b/', $message) === 1 => 1,
            preg_match('/\b(?:cai|mon|san pham|item|product)?\s*(?:thu ba|third one|third item|third product)\b/', $message) === 1 => 2,
            preg_match('/\b(?:cai|mon|san pham|item|product)?\s*(?:cuoi(?: cung)?|last one|last item|last product)\b/', $message) === 1 => $count - 1,
            preg_match('/\b(?:the\s+)?(?:final|last)\s+(?:(?:shown|listed|displayed)\s+)?(?:one|item|product)\b/', $message) === 1 => $count - 1,
            preg_match('/\b(?:mon|san pham|mat hang)\s+hien thi\s+cuoi\b/', $message) === 1 => $count - 1,
            preg_match('/\b(?:mon|san pham|mat hang)\s+(?:(?:dung|o vi tri)\s+)?cuoi(?:\s+danh sach)?\b/', $message) === 1 => $count - 1,
            preg_match('/\b(?:mon|san pham|item|product)\s*(?:so\s*)?(\d{1,2})\b/', $message, $match) === 1 => (int) $match[1] - 1,
            default => null,
        };
    }
}
