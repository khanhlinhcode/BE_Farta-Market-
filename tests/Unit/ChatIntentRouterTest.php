<?php

use App\Enums\ChatIntent;
use App\Services\Chat\ChatIntentRouter;

it('routes only known intents with validated structured filters', function (string $message, ChatIntent $intent) {
    $result = (new ChatIntentRouter(new \App\Services\Chat\ChatProvider))->route($message);

    expect($result['intent'])->toBe($intent)
        ->and(ChatIntent::tryFrom($result['intent']->value))->toBe($intent)
        ->and($result['confidence'])->toBeGreaterThanOrEqual(0.5);
})->with([
    ['gợi ý món nhẹ cho buổi sáng', ChatIntent::ProductSearch],
    ['Cam còn hàng không?', ChatIntent::ProductDetail],
    ['Trong giỏ của tôi có gì?', ChatIntent::CartQuery],
    ['Thêm 2 Cam vào giỏ', ChatIntent::CartActionRequest],
    ['Đơn #42 đang ở đâu?', ChatIntent::OrderQuery],
    ['Tôi có đơn hàng nào hong?', ChatIntent::OrderQuery],
    ['Mình đã mua gì?', ChatIntent::OrderQuery],
    ['Phí ship là bao nhiêu?', ChatIntent::KnowledgeQuery],
    ['Đơn hàng có miễn phí ship không?', ChatIntent::KnowledgeQuery],
    ['Chính sách đã kiểm chứng gồm những gì?', ChatIntent::KnowledgeQuery],
    ['giai thich chinh sach cua shop', ChatIntent::KnowledgeQuery],
    ['Hướng dẫn mua hàng tại Farta Market như thế nào?', ChatIntent::KnowledgeQuery],
    ['Tôi quên mật khẩu thì lấy lại tài khoản như thế nào?', ChatIntent::KnowledgeQuery],
    ['Xin chào', ChatIntent::GeneralChat],
    ['Đánh dấu đã thanh toán', ChatIntent::Unsupported],
]);

it('extracts bounded price filters without asking a model', function () {
    $result = (new ChatIntentRouter(new \App\Services\Chat\ChatProvider))->route('Tìm sản phẩm từ 50k và không quá 100k còn hàng');

    expect($result['filters'])->toMatchArray([
        'min_price' => 50000,
        'max_price' => 100000,
        'in_stock' => true,
    ]);
});
