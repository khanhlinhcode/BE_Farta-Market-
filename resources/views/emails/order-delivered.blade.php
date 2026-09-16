<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Đơn hàng đã giao</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2933; line-height: 1.5;">
    <h1 style="color: #0b8f79;">Farta Market</h1>
    <p>Đơn hàng #{{ $order->id }} đã được giao thành công.</p>
    <p>Cảm ơn bạn đã mua sắm tại Farta Market. Bạn có thể xem lại đơn hàng và đánh giá sản phẩm tại:</p>
    <p>
        <a href="{{ $trackingUrl }}" style="color: #0b8f79;">{{ $trackingUrl }}</a>
    </p>
    <p>Hotline hỗ trợ: 0977-232-232</p>
</body>
</html>
