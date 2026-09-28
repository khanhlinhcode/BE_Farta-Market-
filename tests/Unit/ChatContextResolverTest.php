<?php

use App\Enums\ChatIntent;
use App\Enums\ChatMutationTarget;
use App\Services\Chat\ChatContextResolver;
use App\Services\Chat\ChatRouteFrame;

function contextualRoute(ChatIntent $intent): ChatRouteFrame
{
    return new ChatRouteFrame(
        $intent,
        'cái đầu',
        ['category' => null, 'min_price' => null, 'max_price' => null, 'in_stock' => null],
        ['topic' => 'product', 'order_id' => '', 'product_name' => '', 'quantity' => 0],
        $intent === ChatIntent::CartActionRequest,
        0.98,
        'deterministic',
        null,
        false,
        'supported',
        null,
        concepts: ['reference_required' => true],
    );
}

it('only enriches a resolved reference and never selects a new business action', function () {
    $resolved = (new ChatContextResolver)->resolve(
        'cai dau',
        contextualRoute(ChatIntent::CartActionRequest),
        ['product_ids' => [42], 'category_id' => 7],
    );

    expect($resolved->intent)->toBe(ChatIntent::CartActionRequest)
        ->and($resolved->contextProductId)->toBe(42)
        ->and($resolved->contextCategoryId)->toBeNull();
});

it('clarifies only when a required reference cannot be resolved', function () {
    $resolved = (new ChatContextResolver)->resolve(
        'mon thu hai con hang khong',
        contextualRoute(ChatIntent::ProductDetail),
        null,
    );

    expect($resolved->intent)->toBe(ChatIntent::Clarification)
        ->and($resolved->decisionState)->toBe('clarification');
});

it('resolves a plural read to the bounded product list without selecting one item', function () {
    $resolved = (new ChatContextResolver)->resolve(
        'cac hang nay gia ra sao',
        contextualRoute(ChatIntent::ProductDetail),
        ['product_ids' => [42, 43], 'category_id' => null],
    );

    expect($resolved->intent)->toBe(ChatIntent::ProductDetail)
        ->and($resolved->entities['context_product_ids'])->toBe([42, 43])
        ->and($resolved->contextProductId)->toBeNull();
});

it('keeps every multi-intent branch terminal while resolving its context independently', function () {
    $route = new ChatRouteFrame(
        ChatIntent::MultiIntent,
        'cái đầu và phí giao hàng',
        ['category' => null, 'min_price' => null, 'max_price' => null, 'in_stock' => null],
        ['topic' => 'unknown', 'order_id' => '', 'product_name' => '', 'quantity' => 0],
        false,
        0.98,
        'deterministic',
        null,
        false,
        'supported',
        null,
        branches: [contextualRoute(ChatIntent::ProductDetail), contextualRoute(ChatIntent::CartActionRequest)],
    );

    $resolved = (new ChatContextResolver)->resolve('cai dau va phi giao hang', $route, null);

    expect($resolved->intent)->toBe(ChatIntent::MultiIntent)
        ->and($resolved->branches)->toHaveCount(2)
        ->and($resolved->branches[0]->intent)->toBe(ChatIntent::Clarification)
        ->and($resolved->branches[1]->intent)->toBe(ChatIntent::Clarification);
});

it('preserves a resolved mutation target for a target-free follow-up', function (ChatMutationTarget $target) {
    $route = new ChatRouteFrame(
        ChatIntent::Clarification,
        'cập nhật lại',
        ['category' => null, 'min_price' => null, 'max_price' => null, 'in_stock' => null],
        ['topic' => 'unknown', 'order_id' => '', 'product_name' => '', 'quantity' => 0],
        false,
        0.0,
        'abstention',
        null,
        true,
        'clarification',
        null,
        semanticIntent: 'clarification',
        operation: 'mutate',
    );

    $resolved = (new ChatContextResolver)->resolve(
        'cap nhat lai',
        $route,
        ['mutation_target' => $target->value],
    );

    expect($resolved->mutationTarget)->toBe($target)
        ->and($resolved->mutationTargetAmbiguous)->toBeFalse()
        ->and($resolved->needsClarification)->toBeFalse();
})->with([
    'order target' => ChatMutationTarget::Order,
    'payment target' => ChatMutationTarget::Payment,
]);

it('clarifies a target-free mutation follow-up without trusted target context', function () {
    $route = new ChatRouteFrame(
        ChatIntent::Clarification,
        'update again',
        ['category' => null, 'min_price' => null, 'max_price' => null, 'in_stock' => null],
        ['topic' => 'unknown', 'order_id' => '', 'product_name' => '', 'quantity' => 0],
        false,
        0.0,
        'abstention',
        null,
        true,
        'clarification',
        null,
        semanticIntent: 'clarification',
        operation: 'mutate',
    );

    $resolved = (new ChatContextResolver)->resolve('update again', $route, null);

    expect($resolved->intent)->toBe(ChatIntent::Clarification)
        ->and($resolved->mutationTarget)->toBeNull()
        ->and($resolved->mutationTargetAmbiguous)->toBeTrue();
});
