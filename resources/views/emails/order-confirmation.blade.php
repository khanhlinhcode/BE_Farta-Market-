<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Xác nhận đơn hàng #{{ $order->id }} — Farta Market</title>
</head>
<body style="margin:0;background:#f4f7f6;color:#202522;font-family:Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7f6;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width:640px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e6ecea;">
                    <tr>
                        <td style="background:#00917c;color:#ffffff;padding:24px 28px;">
                            <div style="font-size:28px;font-weight:800;letter-spacing:.2px;">Farta Market</div>
                            <div style="font-size:14px;margin-top:6px;">Thực phẩm tươi sạch cho gia đình</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <h1 style="font-size:22px;margin:0 0 12px;color:#202522;">Cảm ơn bạn đã đặt hàng</h1>
                            <p style="font-size:15px;line-height:1.6;margin:0 0 18px;color:#475467;">
                                Farta Market đã nhận đơn hàng <strong>#{{ $order->id }}</strong>. Chúng tôi sẽ liên hệ xác nhận và giao hàng trong thời gian sớm nhất.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:20px 0;">
                                <tr>
                                    <td style="padding:10px 0;color:#667085;">Mã đơn hàng</td>
                                    <td align="right" style="padding:10px 0;font-weight:700;">#{{ $order->id }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0;color:#667085;border-top:1px solid #edf2f7;">Địa chỉ giao hàng</td>
                                    <td align="right" style="padding:10px 0;border-top:1px solid #edf2f7;">{{ $order->address }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0;color:#667085;border-top:1px solid #edf2f7;">Điện thoại</td>
                                    <td align="right" style="padding:10px 0;border-top:1px solid #edf2f7;">{{ $order->phone }}</td>
                                </tr>
                            </table>

                            <h2 style="font-size:18px;margin:24px 0 12px;color:#202522;">Sản phẩm đã đặt</h2>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                                @foreach ($order->details as $detail)
                                    @php
                                        $image = $detail->product?->img;
                                        $imageUrl = $image && \Illuminate\Support\Str::startsWith($image, ['http://', 'https://'])
                                            ? $image
                                            : ($image ? asset(ltrim($image, '/')) : null);
                                    @endphp
                                    <tr>
                                        <td style="width:72px;padding:12px 10px 12px 0;border-top:1px solid #edf2f7;">
                                            @if ($imageUrl)
                                                <img src="{{ $imageUrl }}" alt="{{ $detail->product_name }}" width="64" height="64" style="display:block;width:64px;height:64px;object-fit:contain;background:#f3f6fa;border-radius:8px;">
                                            @else
                                                <div style="width:64px;height:64px;background:#f3f6fa;border-radius:8px;"></div>
                                            @endif
                                        </td>
                                        <td style="padding:12px 10px;border-top:1px solid #edf2f7;">
                                            <div style="font-weight:700;color:#202522;">{{ $detail->product_name }}</div>
                                            <div style="font-size:13px;color:#667085;margin-top:4px;">SL: {{ $detail->quantity }}</div>
                                        </td>
                                        <td align="right" style="padding:12px 0;border-top:1px solid #edf2f7;">
                                            <div style="font-weight:700;">{{ number_format((float) $detail->unit_price, 0, ',', '.') }}đ</div>
                                            <div style="font-size:13px;color:#667085;margin-top:4px;">{{ number_format((float) $detail->line_total, 0, ',', '.') }}đ</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:18px;border-collapse:collapse;">
                                <tr>
                                    <td style="padding:16px 0;border-top:2px solid #00917c;font-size:18px;font-weight:800;">Tổng tiền</td>
                                    <td align="right" style="padding:16px 0;border-top:2px solid #00917c;font-size:18px;font-weight:800;color:#00917c;">{{ number_format((float) $total, 0, ',', '.') }}đ</td>
                                </tr>
                            </table>

                            <div style="margin:24px 0;">
                                <a href="{{ $trackingUrl }}" style="background:#00917c;color:#ffffff;display:inline-block;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:700;">
                                    Theo dõi đơn hàng
                                </a>
                            </div>

                            <p style="font-size:14px;line-height:1.6;color:#667085;margin:0;">
                                Cần hỗ trợ? Gọi hotline <strong>{{ $hotline }}</strong> hoặc phản hồi email này để được Farta Market hỗ trợ.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
