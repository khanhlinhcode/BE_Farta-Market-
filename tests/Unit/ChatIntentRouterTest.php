<?php

use App\Enums\ChatIntent;
use App\Services\Chat\ChatIntentRouter;

it('routes only known intents with validated structured filters', function (string $message, ChatIntent $intent) {
    $result = (new ChatIntentRouter)->route($message);

    expect($result['intent'])->toBe($intent)
        ->and(ChatIntent::tryFrom($result['intent']->value))->toBe($intent)
        ->and($result['confidence'])->toBeGreaterThanOrEqual(0.5);
})->with([
    ['gợi ý món nhẹ cho buổi sáng', ChatIntent::ProductSearch],
    ['Cam còn hàng không?', ChatIntent::ProductDetail],
    ['Trong giỏ của tôi có gì?', ChatIntent::CartQuery],
    ['Thêm 2 Cam vào giỏ', ChatIntent::CartActionRequest],
    ['Đơn #42 đang ở đâu?', ChatIntent::OrderQuery],
    ['Phí ship là bao nhiêu?', ChatIntent::KnowledgeQuery],
    ['Xin chào', ChatIntent::GeneralChat],
    ['Đánh dấu đã thanh toán', ChatIntent::Unsupported],
]);

it('extracts bounded price filters without asking a model', function () {
    $result = (new ChatIntentRouter)->route('Tìm sản phẩm từ 50k và không quá 100k còn hàng');

    expect($result['filters'])->toMatchArray([
        'min_price' => 50000,
        'max_price' => 100000,
        'in_stock' => true,
    ]);
});
