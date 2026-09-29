<?php

use App\Services\Chat\ChatConceptExtractor;

it('extracts reusable operation and business concepts', function (string $message, array $expected) {
    $concepts = (new ChatConceptExtractor)->extract($message);

    foreach ($expected as $key => $value) {
        expect($concepts[$key])->toBe($value);
    }
})->with([
    ['Trong giỏ của tôi có gì?', ['operation' => 'read', 'cart_read' => true, 'cart_action' => false]],
    ['Mình đã mua gì?', ['operation' => 'read', 'order_read' => true]],
    ['Đơn #42 đang ở đâu?', ['operation' => 'read', 'order_read' => true]],
    ['Đơn hàng có miễn phí ship không?', ['shipping_value' => true]],
    ['Ngưỡng đơn không trả tiền giao hàng là bao nhiêu?', ['shipping_value' => true, 'knowledge_topic' => null]],
    ['delivery cost to a customer address is how much', ['shipping_value' => true, 'knowledge_topic' => null]],
    ['is cash accepted when my groceries arrive', ['knowledge_topic' => 'payment']],
    ['Bánh Yến Mạch còn trong kho chứ', ['product_detail' => true]],
    ['Nước Dừa đã hết hay vẫn còn vậy', ['product_detail' => true]],
    ['hôm nay cửa hàng có món gì bán', ['catalog_list' => true]],
    ['where is order number 515', ['order_read' => true]],
    ['which purchases have I made recently', ['order_read' => true]],
    ['gửi đồ tận nơi hết bao nhiêu tiền hiện tại', ['shipping_value' => true]],
    ['free delivery bắt đầu ở tổng thanh toán bao nhiêu', ['shipping_value' => true, 'knowledge_topic' => null]],
    ['browse every available market item please', ['catalog_list' => true]],
    ['đưa phần giỏ hàng đang dùng ra đây', ['cart_read' => true, 'cart_action' => false]],
    ['đặt hàng online theo thứ tự nào', ['knowledge_topic' => 'ordering', 'cart_action' => false]],
    ['đồ ăn đã dùng một phần có được đổi chính thức không', ['knowledge_topic' => 'returns']],
    ['approved compensation policy for spoiled groceries', ['knowledge_topic' => 'returns']],
    ['give the inventory count for Sữa Chua', ['product_detail' => true]],
    ['cho biết số lượng tồn hiện tại của Trà Xanh', ['product_detail' => true]],
    ['Bún Gạo hiện còn sẵn để bán chứ', ['product_detail' => true]],
    ['Nước Táo hết hàng hay chưa', ['product_detail' => true]],
    ['what can I browse in this market right now', ['catalog_list' => true]],
    ['liệt kê hết đồ còn được bán nhé', ['catalog_list' => true]],
    ['kiểm tra giúp lần đặt trước của tài khoản này', ['order_read' => true]],
    ['recommend a stock market investment', ['unsupported' => true]],
    ['advise me on investing in a crypto token', ['unsupported' => true]],
    ['which medicine treats a lung infection', ['unsupported' => true]],
    ['write a Java backend for a school project', ['unsupported' => true]],
    ['mức cước đưa hàng về nhà bây giờ là bao nhiêu', ['shipping_value' => true, 'knowledge_topic' => null]],
    ['có những nhóm thực phẩm nào để khách lựa chọn', ['catalog_list' => true]],
    ['Trà Xanh is it still sellable', ['product_detail' => true]],
    ['mở mục giỏ và đừng thêm sản phẩm', ['cart_read' => true, 'cart_action' => false]],
    ['xem lịch sử đặt hàng của mình', ['order_read' => true]],
    ['how should a buyer review an online food purchase', ['knowledge_topic' => 'ordering']],
    ['hàng hỏng thì chính sách hoàn tiền bao lâu', ['knowledge_topic' => 'returns']],
    ['can you solve a geometry exercise', ['unsupported' => true]],
    ['gửi một gói hàng về nhà đang thu bao nhiêu tiền', ['shipping_value' => true, 'knowledge_topic' => null]],
    ['could you prepare two Mì Soba in my shopping basket', ['cart_action' => true, 'cart_read' => false]],
    ['Bạn có muốn mua Cam Tươi không?', ['operation' => 'mutate', 'cart_action' => true, 'product_detail' => false]],
    ['Món ấy hiện còn mua được chứ', ['product_detail' => true, 'reference_required' => true]],
    ['which group is that item in', ['product_detail' => true, 'reference_required' => true]],
    ['Cháo Yến Mạch đang bán với giá nào', ['general_chat' => false, 'product_detail' => true]],
]);

it('extracts bounded topic selections without widening product or mutation requests', function (string $message, ?string $topic) {
    expect((new ChatConceptExtractor)->extract($message)['topic_selection'])->toBe($topic);
})->with([
    ['hỏi về sản phẩm', 'product'],
    ['về sản phẩm đi', 'product'],
    ['thông tin về sản phẩm', 'product'],
    ['hoi ve san pham', 'product'],
    ['hỏi về giỏ hàng', 'cart'],
    ['hoi ve gio hang', 'cart'],
    ['hỏi về đơn hàng', 'order'],
    ['hỏi về thanh toán', 'payment'],
    ['hỏi về giao hàng', 'shipping'],
    ['hỏi về chính sách cửa hàng', 'policy'],
    ['giải thích thông tin cửa hàng', 'policy'],
    ['sản phẩm này còn hàng không?', null],
    ['thêm 2 Cam Tươi vào giỏ', null],
    ['xóa giỏ hàng', null],
    ['đánh dấu đơn đã thanh toán', null],
    ['order', null],
    ['payment', null],
]);
