<?php

use App\Enums\ChatMutationTarget;
use App\Services\Chat\ChatMutationTargetResolver;

it('separates mentioned protected resources from the mutation target', function (
    string $message,
    string $operation,
    array $mentioned,
    ?ChatMutationTarget $target,
    bool $clarification,
) {
    $resolution = (new ChatMutationTargetResolver)->resolve($message);

    expect($resolution->operation)->toBe($operation)
        ->and($resolution->mentionedResources)->toBe($mentioned)
        ->and($resolution->mutationTarget)->toBe($target)
        ->and($resolution->needsClarification())->toBe($clarification);
})->with([
    'order target with a payment mention' => [
        'cập nhật đơn 42 thành đã giao sau khi thanh toán',
        'mutate',
        ['order', 'payment'],
        ChatMutationTarget::Order,
        false,
    ],
    'payment target with an order mention' => [
        'set payment của order 42 thành paid',
        'mutate',
        ['order', 'payment'],
        ChatMutationTarget::Payment,
        false,
    ],
    'order read does not become a payment mutation' => [
        'xem order 42 đã paid chưa',
        'read',
        ['order', 'payment'],
        null,
        false,
    ],
    'payment read does not become an order mutation' => [
        'show payment status for order 42',
        'read',
        ['order', 'payment'],
        null,
        false,
    ],
    'order policy question is not an execution request' => [
        'Khi nào mình tự hủy đơn được?',
        'read',
        ['order'],
        null,
        false,
    ],
    'bank transfer wording is not a state transition' => [
        'chuyen khoan Sepay can dung noi dung khong',
        'unknown',
        ['payment'],
        null,
        false,
    ],
    'what happens question is not a state transition' => [
        'QR payment expired thì chuyện gì happens?',
        'unknown',
        ['payment'],
        null,
        false,
    ],
    'coordinated resources without a target clarify' => [
        'cập nhật order và payment',
        'mutate',
        ['order', 'payment'],
        null,
        true,
    ],
    'Vietnamese state coercion targets the order' => [
        'coi đơn 505 như đã giao ngay',
        'mutate',
        ['order'],
        ChatMutationTarget::Order,
        false,
    ],
]);

it('resolves an elliptical state only when the parent mutation supplies the action', function () {
    $resolver = new ChatMutationTargetResolver;

    expect($resolver->resolve('paid')->operation)->toBe('unknown')
        ->and($resolver->ellipticalStateTarget('paid'))->toBe(ChatMutationTarget::Payment)
        ->and($resolver->ellipticalStateTarget('hoàn tất'))->toBe(ChatMutationTarget::Order);
});
