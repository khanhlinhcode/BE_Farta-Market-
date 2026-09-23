<?php

return [
    'intent' => [
        ['query' => 'Gợi ý món nhẹ cho buổi sáng', 'expected' => 'product_search'],
        ['query' => 'Tìm đồ uống dưới 100k', 'expected' => 'product_search'],
        ['query' => 'Cam Tươi còn chính xác bao nhiêu?', 'expected' => 'product_detail'],
        ['query' => 'Trà Nhẹ giá bao nhiêu?', 'expected' => 'product_detail'],
        ['query' => 'Trong giỏ của tôi có gì?', 'expected' => 'cart_query'],
        ['query' => 'Trong giỏ món nào hết hàng?', 'expected' => 'cart_query'],
        ['query' => 'Thêm 2 Cam vào giỏ', 'expected' => 'cart_action_request'],
        ['query' => 'Buy one tea and add it to cart', 'expected' => 'cart_action_request'],
        ['query' => 'Đơn gần nhất của tôi', 'expected' => 'order_query'],
        ['query' => 'Order #42 status', 'expected' => 'order_query'],
        ['query' => 'Xin chào', 'expected' => 'general_chat'],
        ['query' => 'Thank you', 'expected' => 'general_chat'],
        ['query' => 'Đánh dấu đơn đã thanh toán', 'expected' => 'unsupported'],
        ['query' => 'Refund order #3', 'expected' => 'unsupported'],
    ],
    'retrieval' => [
        ['query' => 'gợi ý đồ uống thanh nhẹ buổi sáng', 'relevant' => ['tra-nhe']],
        ['query' => 'món yến mạch mang đi làm', 'relevant' => ['banh-yen-mach']],
        ['query' => 'nước trái cây vitamin cam', 'relevant' => ['nuoc-cam']],
        ['query' => 'cà phê đậm cho buổi sáng', 'relevant' => ['ca-phe-den']],
        ['query' => 'bánh ngọt chocolate', 'relevant' => ['banh-chocolate']],
        ['query' => 'đồ ăn nhẹ ít ngọt', 'relevant' => ['banh-yen-mach']],
    ],
];
