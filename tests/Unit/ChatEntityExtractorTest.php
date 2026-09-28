<?php

use App\Services\Chat\ChatEntityExtractor;

it('extracts cart products and quantities across reusable language patterns', function (
    string $message,
    string $product,
    int $quantity,
) {
    $result = (new ChatEntityExtractor)->cart($message);

    expect($result)->toBe([
        'product_name' => $product,
        'quantity' => $quantity,
    ]);
})->with([
    ['nhờ shop cho 2 Bánh Yến Mạch vào giỏ', 'banh yen mach', 2],
    ['bỏ vô giỏ 4 chai Nước Cam', 'nuoc cam', 4],
    ['tui cần 3 phần Sữa Tươi', 'sua tuoi', 3],
    ['mua giúp ba phần rau củ tươi', 'rau cu tuoi', 3],
    ['could you put 6 orange juices in my cart', 'orange juices', 6],
    ['please add nine teas into the basket', 'teas', 9],
    ['thêm giùm bốn hũ Sữa Chua', 'sua chua', 4],
    ['đưa 7 chai Nước Táo sang cart', 'nuoc tao', 7],
    ['please add five Trà Xanh for me', 'tra xanh', 5],
    ['buy me six Bánh Gạo', 'banh gao', 6],
    ['I need 8 Nước Táo', 'nuoc tao', 8],
    ['mua cho em hai Nước Táo với', 'nuoc tao', 2],
    ['cho mình mua 2 Rau Củ Tươi', 'rau cu tuoi', 2],
    ['nhờ shop cho 2 Bánh Yến Mạch vào giỏ', 'banh yen mach', 2],
    ['mình muốn lấy 2 Rau Củ Tươi cho đơn sắp tới', 'rau cu tuoi', 2],
    ['please add two Rau Củ Tươi for my cart please', 'rau cu tuoi', 2],
    ['could you prepare two Mì Soba in my shopping basket', 'mi soba', 2],
    ['thêm 2 Cải Thìa cho lần mua của mình', 'cai thia', 2],
]);

it('rejects unsafe or ambiguous quantities', function (string $message, string $status) {
    expect((new ChatEntityExtractor)->quantity($message)['status'])->toBe($status);
})->with([
    ['thêm 0 Rau Củ Tươi', 'invalid'],
    ['thêm -2 Rau Củ Tươi', 'invalid'],
    ['thêm 2.5 Rau Củ Tươi', 'invalid'],
    ['thêm 2 hoặc 3 Rau Củ Tươi', 'ambiguous'],
    ['thêm Rau Củ Tươi', 'missing'],
    ['Dưa Leo bây giờ giá mấy tiền', 'missing'],
]);

it('orders bilingual canonical product candidates by their mention positions', function () {
    $extractor = new ChatEntityExtractor;

    expect($extractor->canonicalProductMentions('Show burger, fresh orange, then boxed milk'))->toBe([
        'hamburger', 'cam tuoi', 'sua hop',
    ])->and($extractor->canonicalProductMentions('Liệt kê chuối, ổi rồi nho tím'))->toBe([
        'chuoi', 'oi', 'nho tim',
    ]);
});

it('keeps order references and mutation values out of cart quantity', function () {
    $extractor = new ChatEntityExtractor;
    $paid = $extractor->extract('Mark order 482 as paid');
    $stock = $extractor->extract('tăng tồn kho thêm 12');

    expect($paid['quantity'])->toBe(0)
        ->and($paid['order_reference'])->toBe('482')
        ->and($paid['requested_mutation_value'])->toBe('paid')
        ->and($stock['requested_mutation_value'])->toBe('+12');
});
