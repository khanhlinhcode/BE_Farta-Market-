<?php

use App\Enums\ChatIntent;
use App\Enums\ChatMutationTarget;
use App\Services\Chat\ChatEvidenceDomain;
use App\Services\Chat\ChatRouteFrame;

function routeFrame(ChatIntent $intent = ChatIntent::ProductDetail, ?ChatEvidenceDomain $domain = null): ChatRouteFrame
{
    return new ChatRouteFrame(
        $intent,
        'Rau Củ Tươi giá bao nhiêu?',
        ['category' => null, 'min_price' => null, 'max_price' => null, 'in_stock' => null],
        ['topic' => 'product', 'order_id' => '', 'product_name' => '', 'quantity' => 0],
        false,
        0.98,
        'deterministic',
        null,
        false,
        'supported',
        null,
        requiredEvidenceDomain: $domain,
    );
}

it('keeps the routed evidence domain immutable', function () {
    $domain = new ChatEvidenceDomain('payment', 'knowledge', ['payment-guide-vi'], 'approved_knowledge', false);

    expect($domain['topic'])->toBe('payment')
        ->and($domain->toArray()['allowed_source_ids'])->toBe(['payment-guide-vi'])
        ->and(fn () => $domain['topic'] = 'returns')->toThrow(\LogicException::class);
});

it('requires a domain for knowledge routes and typed frames for composite branches', function () {
    expect(fn () => routeFrame(ChatIntent::KnowledgeQuery))->toThrow(\LogicException::class)
        ->and(fn () => new ChatRouteFrame(
            ChatIntent::MultiIntent,
            'x',
            ['category' => null, 'min_price' => null, 'max_price' => null, 'in_stock' => null],
            ['topic' => 'unknown', 'order_id' => '', 'product_name' => '', 'quantity' => 0],
            false,
            0.98,
            'deterministic',
            null,
            false,
            'supported',
            null,
            branches: ['not-a-frame'],
        ))->toThrow(\LogicException::class);
});

it('preserves read compatibility without allowing route mutation', function () {
    $frame = routeFrame();

    expect($frame['intent'])->toBe(ChatIntent::ProductDetail)
        ->and($frame['primary_intent'])->toBe(ChatIntent::ProductDetail->value)
        ->and(fn () => $frame['intent'] = ChatIntent::CartActionRequest)->toThrow(\LogicException::class);
});

it('enforces typed mutation target invariants', function () {
    expect(fn () => new ChatRouteFrame(
        ChatIntent::Clarification,
        'update order and payment',
        ['category' => null, 'min_price' => null, 'max_price' => null, 'in_stock' => null],
        ['topic' => 'unknown', 'order_id' => '', 'product_name' => '', 'quantity' => 0],
        false,
        0.0,
        'abstention',
        'mutation_target',
        true,
        'clarification',
        null,
        operation: 'mutate',
        mentionedResources: ['order', 'payment'],
        mutationTarget: ChatMutationTarget::Payment,
        mutationTargetAmbiguous: true,
    ))->toThrow(\LogicException::class)
        ->and(fn () => new ChatRouteFrame(
            ChatIntent::Clarification,
            'update order and payment',
            ['category' => null, 'min_price' => null, 'max_price' => null, 'in_stock' => null],
            ['topic' => 'unknown', 'order_id' => '', 'product_name' => '', 'quantity' => 0],
            false,
            0.0,
            'abstention',
            'mutation_target',
            true,
            'clarification',
            null,
            operation: 'mutate',
            mentionedResources: ['order', 'inventory'],
            mutationTargetAmbiguous: true,
        ))->toThrow(\LogicException::class);
});
