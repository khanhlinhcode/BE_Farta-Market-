<?php

use App\Enums\ChatIntent;
use App\Models\Order;
use App\Models\User;
use App\Services\Chat\ChatIntentRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.ai_chat.semantic_router_enabled', false);
    config()->set('services.ai_chat.knowledge_generation_enabled', false);
    $this->withoutMiddleware(ThrottleRequests::class);
    Http::preventStrayRequests();
});

function phase15Order(User $user): Order
{
    return Order::create([
        'user_id' => $user->id,
        'fullname' => $user->name,
        'address' => 'Phase 15 test address',
        'phone' => '0900000000',
        'email' => $user->email,
        'status' => Order::STATUS_PROCESSING,
        'payment_method' => Order::PAYMENT_METHOD_COD,
        'payment_status' => Order::PAYMENT_STATUS_PENDING,
        'subtotal' => 100000,
        'shipping_fee' => 20000,
        'grand_total' => 120000,
    ]);
}

it('routes ORDER and PAYMENT mutations through distinct target capabilities', function (
    string $message,
    array $mentioned,
    string $target,
) {
    $route = app(ChatIntentRouter::class)->route($message);

    expect($route->intent)->toBe(ChatIntent::Unsupported)
        ->and($route->decisionState)->toBe('denied_action')
        ->and($route->mentionedResources)->toBe($mentioned)
        ->and($route->mutationTarget?->value)->toBe($target)
        ->and($route->resource)->toBe($target)
        ->and($route->operation)->toBe('mutate')
        ->and($route->denialReason)->toBe($target.'_mutation');
})->with([
    'explicit order target' => ['chuyển đơn 42 sang completed', ['order'], 'order'],
    'explicit payment target' => ['đánh dấu thanh toán là đã trả', ['payment'], 'payment'],
    'order target with payment context' => ['cập nhật đơn 42 thành đã giao sau khi thanh toán', ['order', 'payment'], 'order'],
    'order target with irrelevant payment context' => ['cancel order 42 after payment was discussed', ['order', 'payment'], 'order'],
    'payment target with order context' => ['set payment của order 42 thành paid', ['order', 'payment'], 'payment'],
    'payment target with irrelevant order context' => ['update payment status for order 42 to success', ['order', 'payment'], 'payment'],
    'payment mentioned before the order target' => ['thanh toán đã xong; cập nhật đơn 42 thành đã giao', ['order', 'payment'], 'order'],
    'order mentioned before the payment target' => ['đơn 42 đang xử lý; cập nhật thanh toán thành thất bại', ['order', 'payment'], 'payment'],
]);

it('keeps protected reads separate from protected mutations', function (string $message, string $semantic) {
    $route = app(ChatIntentRouter::class)->route($message);

    expect($route->semanticIntent)->toBe($semantic)
        ->and($route->operation)->toBe('read')
        ->and($route->mutationTarget)->toBeNull()
        ->and($route->decisionState)->toBe('supported');
})->with([
    'order read with payment mentioned' => ['sau khi thanh toán, xem order 42 đang ở đâu', 'order_read'],
    'payment read with order mentioned' => ['show payment status for order 42', 'payment_status_read'],
]);

it('clarifies when a mutation names ORDER and PAYMENT without a target relationship', function () {
    $route = app(ChatIntentRouter::class)->route('cập nhật order và payment');

    expect($route->intent)->toBe(ChatIntent::Clarification)
        ->and($route->decisionState)->toBe('clarification')
        ->and($route->mentionedResources)->toBe(['order', 'payment'])
        ->and($route->mutationTarget)->toBeNull()
        ->and($route->mutationTargetAmbiguous)->toBeTrue();
});

it('keeps mutation targets branch-local for mixed and parallel requests', function () {
    $route = app(ChatIntentRouter::class)->route(
        'chuyển order 42 sang completed và set payment của order 42 thành paid',
    );

    expect($route->intent)->toBe(ChatIntent::MultiIntent)
        ->and($route->branches)->toHaveCount(2)
        ->and($route->branches[0]->mutationTarget?->value)->toBe('order')
        ->and($route->branches[0]->resource)->toBe('order')
        ->and($route->branches[1]->mutationTarget?->value)->toBe('payment')
        ->and($route->branches[1]->resource)->toBe('payment')
        ->and($route->branches[0]->decisionState)->toBe('denied_action')
        ->and($route->branches[1]->decisionState)->toBe('denied_action');
});

it('turns coordinated state values into independently targeted mutation branches', function () {
    $route = app(ChatIntentRouter::class)->route('treat order 42 as completed and paid');

    expect($route->intent)->toBe(ChatIntent::MultiIntent)
        ->and($route->decisionState)->toBe('denied_action')
        ->and($route->branches)->toHaveCount(2)
        ->and($route->branches[0]->mutationTarget?->value)->toBe('order')
        ->and($route->branches[0]->decisionState)->toBe('denied_action')
        ->and($route->branches[1]->mutationTarget?->value)->toBe('payment')
        ->and($route->branches[1]->decisionState)->toBe('denied_action');
});

it('keeps a mutation target separate from a sibling read branch', function () {
    $route = app(ChatIntentRouter::class)->route(
        'chuyển order 42 sang completed và check payment status for order 42',
    );

    expect($route->intent)->toBe(ChatIntent::MultiIntent)
        ->and($route->branches)->toHaveCount(2)
        ->and($route->branches[0]->operation)->toBe('mutate')
        ->and($route->branches[0]->mutationTarget?->value)->toBe('order')
        ->and($route->branches[0]->decisionState)->toBe('denied_action')
        ->and($route->branches[1]->operation)->toBe('read')
        ->and($route->branches[1]->mutationTarget)->toBeNull()
        ->and($route->branches[1]->semanticIntent)->toBe('payment_status_read');
});

it('resolves a target-free mutation conjunct from the unambiguous full request', function (
    string $message,
    string $readSemantic,
    string $target,
) {
    $route = app(ChatIntentRouter::class)->route($message);

    expect($route->intent)->toBe(ChatIntent::MultiIntent)
        ->and($route->branches)->toHaveCount(2)
        ->and($route->branches[0]->semanticIntent)->toBe($readSemantic)
        ->and($route->branches[0]->decisionState)->toBe('supported')
        ->and($route->branches[1]->mutationTarget?->value)->toBe($target)
        ->and($route->branches[1]->resource)->toBe($target)
        ->and($route->branches[1]->decisionState)->toBe('denied_action');
})->with([
    'order ellipsis' => ['Đơn của mình tới đâu rồi, với hủy luôn giúp mình nha.', 'order_read', 'order'],
    'payment ellipsis' => ['xem thanh toan don cua toi roi danh dau da tra', 'payment_status_read', 'payment'],
]);

it('denies each resolved target without mutating either own or other-account order state', function () {
    $owner = User::factory()->customer()->create();
    $other = User::factory()->customer()->create();
    $owned = phase15Order($owner);
    $foreign = phase15Order($other);
    $before = Order::query()->orderBy('id')->get(['id', 'status', 'payment_status'])->toArray();
    Sanctum::actingAs($owner);

    $this->postJson('/api/chat', [
        'message' => "cập nhật đơn {$foreign->id} thành đã giao sau khi thanh toán",
    ])->assertOk()
        ->assertJsonPath('resource', 'order')
        ->assertJsonPath('mutation_target', 'order')
        ->assertJsonPath('decision_state', 'denied_action')
        ->assertJsonPath('code', 'ACTION_NOT_ALLOWED')
        ->assertJsonPath('action.type', 'none')
        ->assertJsonCount(0, 'suggested_actions');

    $this->postJson('/api/chat', [
        'message' => "set payment của order {$owned->id} thành paid",
    ])->assertOk()
        ->assertJsonPath('resource', 'payment')
        ->assertJsonPath('mutation_target', 'payment')
        ->assertJsonPath('decision_state', 'denied_action')
        ->assertJsonPath('code', 'ACTION_NOT_ALLOWED')
        ->assertJsonPath('action.type', 'none')
        ->assertJsonCount(0, 'suggested_actions');

    $this->postJson('/api/chat', [
        'message' => "set payment của order {$foreign->id} thành failed",
    ])->assertOk()
        ->assertJsonPath('resource', 'payment')
        ->assertJsonPath('mutation_target', 'payment')
        ->assertJsonPath('decision_state', 'denied_action')
        ->assertJsonPath('code', 'ACTION_NOT_ALLOWED')
        ->assertJsonPath('action.type', 'none');

    expect(Order::query()->orderBy('id')->get(['id', 'status', 'payment_status'])->toArray())->toBe($before);
    Http::assertNothingSent();
});

it('denies mutations for privileged actors without changing authorization rules', function () {
    $admin = User::factory()->admin()->create();
    $order = phase15Order($admin);
    $before = $order->only(['status', 'payment_status']);
    Sanctum::actingAs($admin);

    $this->postJson('/api/chat', [
        'message' => "set payment của order {$order->id} thành paid",
    ])->assertOk()
        ->assertJsonPath('mutation_target', 'payment')
        ->assertJsonPath('decision_state', 'denied_action')
        ->assertJsonPath('code', 'ACTION_NOT_ALLOWED')
        ->assertJsonPath('action.type', 'none');

    expect($order->fresh()->only(['status', 'payment_status']))->toBe($before);
    Http::assertNothingSent();
});

it('denies an unauthenticated mutation before any state can change', function () {
    $owner = User::factory()->customer()->create();
    $order = phase15Order($owner);
    $before = $order->only(['status', 'payment_status']);

    $this->postJson('/api/chat', [
        'message' => "chuyển đơn {$order->id} sang completed",
    ])->assertOk()
        ->assertJsonPath('mutation_target', 'order')
        ->assertJsonPath('decision_state', 'denied_action')
        ->assertJsonPath('code', 'ACTION_NOT_ALLOWED')
        ->assertJsonPath('action.type', 'none');

    expect($order->fresh()->only(['status', 'payment_status']))->toBe($before);
    Http::assertNothingSent();
});

it('keeps authorized ORDER and PAYMENT reads allowed for the owning actor', function () {
    $owner = User::factory()->customer()->create();
    $order = phase15Order($owner);
    Sanctum::actingAs($owner);

    $this->postJson('/api/chat', [
        'message' => "xem đơn {$order->id} đang ở đâu",
    ])->assertOk()
        ->assertJsonPath('semantic_intent', 'order_read')
        ->assertJsonPath('decision_state', 'supported')
        ->assertJsonPath('mutation_target', null)
        ->assertJsonPath('source', 'orders');

    $this->postJson('/api/chat', [
        'message' => "show payment status for order {$order->id}",
    ])->assertOk()
        ->assertJsonPath('semantic_intent', 'payment_status_read')
        ->assertJsonPath('decision_state', 'supported')
        ->assertJsonPath('mutation_target', null)
        ->assertJsonPath('source', 'orders');

    Http::assertNothingSent();
});

it('returns branch target metadata to the shared security-denial handler', function () {
    $response = $this->postJson('/api/chat', [
        'message' => 'chuyển order 42 sang completed và set payment của order 42 thành paid',
    ])->assertOk()
        ->assertJsonPath('intent', 'multi_intent')
        ->assertJsonPath('subresponses.0.mutation_target', 'order')
        ->assertJsonPath('subresponses.0.code', 'ACTION_NOT_ALLOWED')
        ->assertJsonPath('subresponses.1.mutation_target', 'payment')
        ->assertJsonPath('subresponses.1.code', 'ACTION_NOT_ALLOWED');

    expect($response->json('suggested_actions'))->toBe([]);
    Http::assertNothingSent();
});

it('preserves each resolved target through session follow-up state', function (
    string $first,
    string $followUp,
    string $target,
) {
    $headers = ['Origin' => 'http://127.0.0.1:5173', 'Referer' => 'http://127.0.0.1:5173/'];
    $this->withHeaders($headers)->postJson('/api/chat', ['message' => $first])
        ->assertOk()
        ->assertJsonPath('mutation_target', $target)
        ->assertJsonPath('code', 'ACTION_NOT_ALLOWED');
    $sessionId = session()->getId();

    $this->withHeaders($headers)->withCookie(config('session.cookie'), $sessionId)
        ->postJson('/api/chat', ['message' => $followUp])
        ->assertOk()
        ->assertJsonPath('mutation_target', $target)
        ->assertJsonPath('resource', $target)
        ->assertJsonPath('operation', 'mutate')
        ->assertJsonPath('decision_state', 'denied_action')
        ->assertJsonPath('code', 'ACTION_NOT_ALLOWED');

    Http::assertNothingSent();
})->with([
    'order follow-up' => ['cập nhật đơn 42 thành đã giao sau khi thanh toán', 'đánh dấu lại', 'order'],
    'payment follow-up' => ['set payment của order 42 thành paid', 'update again', 'payment'],
]);

it('clarifies a mutation follow-up when no target context exists', function () {
    $this->withHeaders(['Origin' => 'http://127.0.0.1:5173', 'Referer' => 'http://127.0.0.1:5173/'])
        ->postJson('/api/chat', ['message' => 'cập nhật lại'])
        ->assertOk()
        ->assertJsonPath('intent', 'clarification')
        ->assertJsonPath('mutation_target', null)
        ->assertJsonPath('decision_state', 'clarification')
        ->assertJsonPath('code', 'CLARIFICATION_REQUIRED');

    Http::assertNothingSent();
});
