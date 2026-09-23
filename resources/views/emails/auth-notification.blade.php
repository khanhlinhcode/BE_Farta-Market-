<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $title }} — Farta Market</title>
</head>
<body style="margin:0;background:#f2f6f5;color:#202522;font-family:Arial,'Helvetica Neue',sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $preheader }}</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" bgcolor="#f2f6f5" style="width:100%;background:#f2f6f5;">
        <tr>
            <td align="center" style="padding:36px 14px;">
                <table role="presentation" width="620" cellspacing="0" cellpadding="0" bgcolor="#ffffff" style="width:100%;max-width:620px;background:#ffffff;border:1px solid #dfe8e5;border-radius:18px;overflow:hidden;box-shadow:0 12px 30px rgba(31,70,62,.08);">
                    <tr>
                        <td height="6" bgcolor="#008874" style="height:6px;background:#008874;font-size:0;line-height:0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="padding:22px 30px;border-bottom:1px solid #e6ecea;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td width="48" valign="middle">
                                        <div style="width:44px;height:44px;border-radius:12px;background:#008874;color:#ffffff;font-size:22px;line-height:44px;text-align:center;font-weight:800;">F</div>
                                    </td>
                                    <td valign="middle" style="padding-left:12px;">
                                        <div style="font-size:20px;line-height:1.2;font-weight:800;color:#202522;letter-spacing:.1px;">Farta Market</div>
                                        <div style="font-size:12px;line-height:1.5;margin-top:3px;color:#667085;">Tươi sạch mỗi ngày</div>
                                    </td>
                                    <td align="right" valign="middle">
                                        <span style="display:inline-block;padding:7px 10px;border-radius:999px;background:#eef8f5;color:#007a68;font-size:10px;line-height:1;font-weight:800;letter-spacing:1px;">EMAIL BẢO MẬT</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" bgcolor="#f0f8f6" style="padding:36px 30px;background:#f0f8f6;border-bottom:1px solid #e2efeb;">
                            <div style="width:62px;height:62px;border-radius:50%;background:#008874;color:#ffffff;font-size:22px;line-height:62px;text-align:center;font-weight:800;letter-spacing:1px;">{{ $visual }}</div>
                            <div style="font-size:11px;line-height:1.4;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;color:#007a68;margin-top:20px;">{{ $eyebrow }}</div>
                            <h1 style="font-size:28px;line-height:1.25;margin:8px 0 12px;color:#202522;font-weight:800;">{{ $title }}</h1>
                            <p style="font-size:15px;line-height:1.7;margin:0 auto;color:#475467;max-width:480px;">{{ $intro }}</p>

                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:26px auto 0;">
                                <tr>
                                    <td align="center" bgcolor="#008874" style="border-radius:8px;">
                                        <a href="{{ $actionUrl }}" style="display:inline-block;padding:14px 25px;color:#ffffff;text-decoration:none;font-size:15px;line-height:1.4;font-weight:800;">{{ $actionText }} &nbsp;→</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 30px 30px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" bgcolor="#f7faf9" style="background:#f7faf9;border:1px solid #e6ecea;border-radius:10px;">
                                <tr>
                                    <td width="46" align="center" valign="middle" style="color:#008874;font-size:18px;font-weight:800;">i</td>
                                    <td style="padding:14px 16px 14px 0;font-size:14px;line-height:1.6;color:#35534d;">{{ $notice }}</td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:20px;">
                                <tr>
                                    <td width="26" valign="top" style="color:#008874;font-size:16px;line-height:1.5;">✓</td>
                                    <td style="font-size:14px;line-height:1.65;color:#667085;">{{ $securityNote }}</td>
                                </tr>
                            </table>

                            <p style="font-size:12px;line-height:1.65;margin:22px 0 0;padding-top:18px;border-top:1px solid #e6ecea;color:#7b8784;">
                                Nút không hoạt động?
                                <a href="{{ $actionUrl }}" style="color:#007a68;text-decoration:underline;font-weight:700;">Mở liên kết bảo mật trong trình duyệt</a>.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" bgcolor="#fbfcfc" style="border-top:1px solid #e6ecea;padding:20px 30px;background:#fbfcfc;font-size:12px;line-height:1.65;color:#7b8784;">
                            Đây là email tự động. Farta Market không bao giờ yêu cầu bạn gửi mật khẩu hoặc mã xác thực qua email.
                        </td>
                    </tr>
                </table>
                <p style="font-size:12px;line-height:1.6;margin:17px 0 0;color:#7b8784;">© {{ date('Y') }} Farta Market &nbsp;·&nbsp; fartamarket.company</p>
            </td>
        </tr>
    </table>
</body>
</html>
