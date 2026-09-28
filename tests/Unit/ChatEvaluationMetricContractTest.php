<?php

use App\Enums\ChatIntent;
use App\Services\Chat\ChatRouteFrame;
use Tests\Support\ChatNluMetrics;

it('calculates intent accuracy and macro F1 from every supplied label', function () {
    $metrics = ChatNluMetrics::intents([
        ['expected' => 'catalog_list', 'predicted' => 'catalog_list'],
        ['expected' => 'catalog_list', 'predicted' => 'product_search'],
        ['expected' => 'unsupported', 'predicted' => 'unsupported'],
        ['expected' => 'shipping_info', 'predicted' => 'shipping_info'],
    ]);

    expect($metrics['accuracy'])->toBe(0.75)
        ->and($metrics['per_intent']['catalog_list']['support'])->toBe(2)
        ->and(array_keys($metrics['confusion_matrix']))->toContain('catalog_list', 'product_search', 'shipping_info', 'unsupported');
});

it('documents the historic V9 entity contract as a joint route and raw-slot comparison', function () {
    $gold = ['product' => 'tra nhe', 'quantity' => 2];
    $matches = static fn (string $intent, array $entities): bool => $intent === 'cart_action_request'
        && ($entities['product_name'] ?? null) === $gold['product']
        && ($entities['quantity'] ?? null) === $gold['quantity'];

    expect($matches('cart_action_request', ['product_name' => 'tra nhe', 'quantity' => 2]))->toBeTrue()
        ->and($matches('product_detail', ['product_name' => 'tra nhe', 'quantity' => 2]))->toBeFalse()
        ->and($matches('cart_action_request', ['product_name' => 'tra dam', 'quantity' => 2]))->toBeFalse()
        ->and($matches('cart_action_request', ['product_name' => 'tra nhe', 'quantity' => 3]))->toBeFalse();
});

it('preserves route-frame entity slots through the evaluator accessor', function () {
    $route = new ChatRouteFrame(
        ChatIntent::CartActionRequest,
        'thêm 2 Trà Nhẹ',
        ['category' => null, 'min_price' => null, 'max_price' => null, 'in_stock' => null],
        ['topic' => 'cart', 'order_id' => '', 'product_name' => 'tra nhe', 'quantity' => 2],
        false,
        1.0,
        'deterministic',
        null,
        false,
        'supported',
        null,
    );

    expect($route['entities'])->toBe([
        'topic' => 'cart',
        'order_id' => '',
        'product_name' => 'tra nhe',
        'quantity' => 2,
    ]);
});

it('documents the historic V9 follow-up success contract', function () {
    $matches = static fn (int $initialStatus, string $intent, ?string $code, ?string $actualSlug, ?string $expectedSlug): bool => $initialStatus === 200
        && $intent === 'product_detail'
        && $code === null
        && ($expectedSlug !== null ? $actualSlug === $expectedSlug : $actualSlug === null);

    expect($matches(200, 'product_detail', null, 'tra-nhe', 'tra-nhe'))->toBeTrue()
        ->and($matches(200, 'clarification', 'CLARIFICATION_REQUIRED', null, 'tra-nhe'))->toBeFalse()
        ->and($matches(200, 'product_detail', null, 'tra-dam', 'tra-nhe'))->toBeFalse()
        ->and($matches(401, 'product_detail', null, 'tra-nhe', 'tra-nhe'))->toBeFalse();
});

it('keeps handler, evidence, safety, and composition denominators explicit', function () {
    $handler = static fn (int $status, string $intent, string $source): bool => $status === 200
        && $intent === 'knowledge_query'
        && $source === 'knowledge';
    $missingEvidence = static fn (?string $code, ?string $status, array $citations): bool => $code === 'NO_EVIDENCE'
        && $status === 'refused_unverified'
        && $citations === [];
    $branchComplete = static fn (array $intents, array $expected): bool => count($intents) >= count($expected)
        && count(array_intersect($expected, $intents)) === count(array_unique($expected));
    $rates = static fn (int $correct, int $total): float => $total > 0 ? $correct / $total : 0.0;

    expect($handler(200, 'knowledge_query', 'knowledge'))->toBeTrue()
        ->and($handler(200, 'knowledge_query', 'catalog'))->toBeFalse()
        ->and($missingEvidence('NO_EVIDENCE', 'refused_unverified', []))->toBeTrue()
        ->and($missingEvidence('NO_EVIDENCE', 'verified', []))->toBeFalse()
        ->and($branchComplete(['shipping_info', 'knowledge_query'], ['shipping_info', 'knowledge_query']))->toBeTrue()
        ->and($branchComplete(['shipping_info'], ['shipping_info', 'knowledge_query']))->toBeFalse()
        ->and($rates(3, 4))->toBe(0.75)
        ->and($rates(0, 0))->toBe(0.0);
});
