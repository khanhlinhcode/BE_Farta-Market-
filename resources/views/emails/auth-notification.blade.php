<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $title }} — Farta Market</title>
</head>
<body style="margin:0;background:#f7faf9;color:#1c1c1c;font-family:'Be Vietnam Pro',Arial,'Helvetica Neue',sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $preheader }}</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" bgcolor="#f7faf9" style="width:100%;background:#f7faf9;">
        <tr>
            <td align="center" style="padding:40px 14px;">
                <table role="presentation" width="620" cellspacing="0" cellpadding="0" bgcolor="#ffffff" style="width:100%;max-width:620px;background:#ffffff;border:1px solid #e6ecea;border-radius:14px;overflow:hidden;">
                    <tr>
                        <td bgcolor="#007a68" style="padding:24px 32px;background:#007a68;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td width="48" valign="middle">
                                        <div style="width:44px;height:44px;border-radius:10px;background:#ffffff;color:#007a68;font-size:21px;line-height:44px;text-align:center;font-weight:800;">F</div>
                                    </td>
                                    <td valign="middle" style="padding-left:12px;">
                                        <div style="font-size:20px;line-height:1.2;font-weight:800;color:#ffffff;">Farta Market</div>
                                        <div style="font-size:12px;line-height:1.5;margin-top:3px;color:#d9f3ed;">Tươi sạch mỗi ngày</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" bgcolor="#007a68" style="padding:16px 32px 40px;background:#007a68;">
                            <h1 style="font-size:30px;line-height:1.25;margin:0 0 14px;color:#ffffff;font-weight:800;">{{ $title }}</h1>
                            <p style="font-size:15px;line-height:1.75;margin:0 auto;color:#e2f5f1;max-width:500px;">{{ $intro }}</p>

                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:28px auto 0;">
                                <tr>
                                    <td align="center" bgcolor="#ffffff" style="border-radius:8px;">
                                        <a href="{{ $actionUrl }}" style="display:inline-block;padding:14px 28px;color:#007a68;text-decoration:none;font-size:15px;line-height:1.4;font-weight:800;">{{ $actionText }}</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" bgcolor="#f7faf9" style="background:#f7faf9;border-radius:12px;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <div style="font-size:12px;line-height:1.4;font-weight:800;color:#008874;margin-bottom:6px;">Thông tin liên kết</div>
                                        <div style="font-size:14px;line-height:1.65;color:#252525;">{{ $notice }}</div>
                                    </td>
                                </tr>
                            </table>

                            <p style="font-size:14px;line-height:1.7;margin:22px 0 0;color:#667085;">{{ $securityNote }}</p>

                            <p style="font-size:12px;line-height:1.65;margin:24px 0 0;padding-top:20px;border-top:1px solid #e6ecea;color:#667085;">
                                Nút không hoạt động?
                                <a href="{{ $actionUrl }}" style="color:#007a68;text-decoration:underline;text-underline-offset:3px;font-weight:700;">Mở liên kết bảo mật</a>.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" bgcolor="#f7faf9" style="border-top:1px solid #e6ecea;padding:22px 32px;background:#f7faf9;font-size:12px;line-height:1.7;color:#667085;">
                            Đây là email tự động từ Farta Market.<br>
                            Chúng tôi không bao giờ yêu cầu mật khẩu hoặc mã xác thực qua email.
                        </td>
                    </tr>
                </table>
                <p style="font-size:12px;line-height:1.6;margin:18px 0 0;color:#667085;">© {{ date('Y') }} Farta Market · fartamarket.company</p>
            </td>
        </tr>
    </table>
</body>
</html>
