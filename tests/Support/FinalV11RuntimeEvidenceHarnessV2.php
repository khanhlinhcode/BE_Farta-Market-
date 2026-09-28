<?php

namespace Tests\Support;

use App\Enums\ChatIntent;
use App\Services\Chat\ChatEvidenceDomain;
use App\Services\Chat\ChatRouteFrame;

final class FinalV11RuntimeEvidenceHarnessV2
{
    public const VERSION = 'farta-final-v11-runtime-evidence-harness.2.0.0';

    /**
     * Build claim-level provenance from typed route semantics plus structured
     * response/citation fields. Answer text and V11 record identity are never
     * inspected.
     *
     * @param  array<string, mixed>  $response
     * @return array<int, array<string, mixed>>
     */
    public static function capture(ChatRouteFrame|array $route, array $response): array
    {
        $frame = self::frame($route);
        $semanticIntent = self::string($frame['semantic_intent'] ?? null, 'route.semantic_intent');
        $evidence = [];

        foreach ($response['citations'] ?? [] as $index => $citation) {
            if (! is_array($citation) || array_is_list($citation)) {
                throw new FinalV11ContractException('Runtime citation '.$index.' must be an object.');
            }
            $sourceId = self::string($citation['source_id'] ?? null, 'citation.source_id');
            $section = self::string($citation['section'] ?? null, 'citation.section');
            $evidence[] = self::provenance(
                $sourceId,
                $sourceId.':'.$section,
                'published_section'
            );
        }

        $products = $response['products'] ?? [];
        if (! is_array($products) || ! array_is_list($products)) {
            throw new FinalV11ContractException('Runtime response products must be an array.');
        }
        foreach ($products as $index => $product) {
            if (! is_array($product) || array_is_list($product) || ! is_int($product['id'] ?? null)) {
                throw new FinalV11ContractException('Runtime product '.$index.' lacks a typed ID.');
            }
            $id = $product['id'];
            $fields = match ($semanticIntent) {
                'price', 'shipping_calculation' => ['current_unit_price_vnd'],
                'stock_availability', 'cart_action_request' => ['current_stock'],
                'product_search', 'catalog_listing' => ['name', 'active', 'category'],
                'product_detail' => ['name', 'active', 'category', 'current_unit_price_vnd', 'current_stock'],
                default => [],
            };
            foreach ($fields as $field) {
                $evidence[] = self::provenance(
                    'product-category-authority-v11',
                    'product:'.$id.':'.$field,
                    'structured_field'
                );
            }
        }

        if ($semanticIntent === 'shipping_current_value') {
            $evidence[] = self::provenance(
                'site-settings-authority-v11',
                'site-setting:shipping_fee_vnd',
                'structured_field'
            );
            $evidence[] = self::provenance(
                'site-settings-authority-v11',
                'site-setting:free_shipping_threshold_vnd',
                'structured_field'
            );
        } elseif ($semanticIntent === 'shipping_calculation') {
            $evidence[] = self::provenance(
                'site-settings-authority-v11',
                'site-setting:shipping_fee_vnd',
                'structured_field'
            );
            $evidence[] = self::provenance(
                'site-settings-authority-v11',
                'site-setting:free_shipping_threshold_vnd',
                'structured_field'
            );
        } elseif ($semanticIntent === 'store_contact') {
            foreach (['contact_email', 'contact_phone', 'support_phone', 'address_vi', 'address_en'] as $field) {
                $evidence[] = self::provenance(
                    'site-settings-authority-v11',
                    'site-setting:'.$field,
                    'structured_field'
                );
            }
        }

        $order = $response['order'] ?? null;
        if ($order !== null) {
            if (! is_array($order) || array_is_list($order)
                || (! is_int($order['id'] ?? null) && ! is_string($order['id'] ?? null))) {
                throw new FinalV11ContractException('Runtime response order lacks a typed ID.');
            }
            $orderId = (string) $order['id'];
            $field = $semanticIntent === 'payment_status_read' ? 'payment_status' : 'status';
            $evidence[] = self::provenance(
                'owned-order-authority-v11-r1',
                'order:'.$orderId.':'.$field,
                'structured_field'
            );
        }

        $unique = [];
        foreach ($evidence as $item) {
            $key = implode("\0", [$item['source_id'], $item['evidence_ref'], $item['evidence_type']]);
            $unique[$key] = $item;
        }
        $evidence = array_values($unique);
        usort($evidence, fn (array $left, array $right): int => [
            $left['source_id'], $left['evidence_ref'],
        ] <=> [
            $right['source_id'], $right['evidence_ref'],
        ]);

        return $evidence;
    }

    /** @return array<string, mixed> */
    private static function frame(ChatRouteFrame|array $route): array
    {
        if ($route instanceof ChatRouteFrame) {
            return [
                ...$route->toArray(),
                'intent' => $route->intent->value,
                'required_evidence_domain' => $route->requiredEvidenceDomain?->topic,
            ];
        }
        if (array_is_list($route)) {
            throw new FinalV11ContractException('Runtime evidence route must be an object.');
        }
        if (($route['intent'] ?? null) instanceof ChatIntent) {
            $route['intent'] = $route['intent']->value;
        }
        if (($route['required_evidence_domain'] ?? null) instanceof ChatEvidenceDomain) {
            $route['required_evidence_domain'] = $route['required_evidence_domain']->topic;
        }

        return $route;
    }

    /** @return array{authority: string, source_id: string, source_version: int, evidence_ref: string, evidence_type: string} */
    private static function provenance(string $sourceId, string $evidenceRef, string $evidenceType): array
    {
        $source = self::registry()[$sourceId] ?? null;
        if (! is_array($source)) {
            throw new FinalV11ContractException('Runtime evidence uses an unregistered source '.$sourceId.'.');
        }

        return [
            'authority' => $source['authority_type'],
            'source_id' => $sourceId,
            'source_version' => $source['version'],
            'evidence_ref' => $evidenceRef,
            'evidence_type' => $evidenceType,
        ];
    }

    /** @return array<string, array{authority_type: string, version: int}> */
    private static function registry(): array
    {
        static $registry;

        if (is_array($registry)) {
            return $registry;
        }
        $contract = json_decode(
            (string) file_get_contents(base_path('docs/evaluation/v11-independent/v11-fact-verification-contract.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $registry = [];
        foreach ($contract['authority_sources'] ?? [] as $source) {
            if (! is_array($source) || ! is_string($source['source_id'] ?? null)
                || ! is_string($source['authority_type'] ?? null) || ! is_int($source['version'] ?? null)) {
                throw new FinalV11ContractException('Invalid frozen authority registry entry.');
            }
            $registry[$source['source_id']] = [
                'authority_type' => $source['authority_type'],
                'version' => $source['version'],
            ];
        }

        return $registry;
    }

    private static function string(mixed $value, string $path): string
    {
        if (! is_string($value) || $value === '') {
            throw new FinalV11ContractException($path.' must be a non-empty string.');
        }

        return $value;
    }
}
