<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $title }} — Farta Market</title>
</head>
<body style="margin:0;background:#f4f7f6;color:#202522;font-family:Arial,'Helvetica Neue',sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $preheader }}</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;background:#f4f7f6;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #e6ecea;border-radius:14px;overflow:hidden;">
                    <tr>
                        <td style="background:#008874;padding:24px 28px;color:#ffffff;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td width="48" valign="middle">
                                        <div style="width:42px;height:42px;border-radius:12px;background:#ffffff;color:#008874;font-size:22px;line-height:42px;text-align:center;font-weight:800;">F</div>
                                    </td>
                                    <td valign="middle" style="padding-left:12px;">
                                        <div style="font-size:21px;line-height:1.2;font-weight:800;letter-spacing:.1px;">Farta Market</div>
                                        <div style="font-size:13px;line-height:1.5;margin-top:3px;color:#ddf5ef;">Thực phẩm tươi sạch cho gia đình</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:34px 28px 28px;">
                            <div style="font-size:12px;line-height:1.4;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;color:#008874;">{{ $eyebrow }}</div>
                            <h1 style="font-size:26px;line-height:1.28;margin:9px 0 12px;color:#202522;font-weight:800;">{{ $title }}</h1>
                            <p style="font-size:15px;line-height:1.7;margin:0;color:#475467;">{{ $intro }}</p>

                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:26px 0 22px;">
                                <tr>
                                    <td align="center" bgcolor="#008874" style="border-radius:8px;">
                                        <a href="{{ $actionUrl }}" style="display:inline-block;padding:13px 22px;color:#ffffff;text-decoration:none;font-size:15px;line-height:1.4;font-weight:800;">{{ $actionText }}</a>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef8f5;border-left:3px solid #008874;border-radius:8px;">
                                <tr>
                                    <td style="padding:14px 16px;font-size:14px;line-height:1.6;color:#35534d;">{{ $notice }}</td>
                                </tr>
                            </table>

                            <p style="font-size:14px;line-height:1.65;margin:22px 0 0;color:#667085;">{{ $securityNote }}</p>
                            <p style="font-size:12px;line-height:1.6;margin:24px 0 0;color:#7b8784;word-break:break-all;">
                                Nếu nút không hoạt động, hãy sao chép liên kết này vào trình duyệt:<br>
                                <a href="{{ $actionUrl }}" style="color:#007a68;text-decoration:underline;">{{ $actionUrl }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top:1px solid #e6ecea;padding:20px 28px;background:#fbfcfc;font-size:12px;line-height:1.6;color:#7b8784;">
                            Email tự động từ Farta Market. Vì lý do bảo mật, chúng tôi không bao giờ yêu cầu bạn gửi mật khẩu qua email.
                        </td>
                    </tr>
                </table>
                <p style="font-size:12px;line-height:1.5;margin:16px 0 0;color:#7b8784;">© {{ date('Y') }} Farta Market</p>
            </td>
        </tr>
    </table>
</body>
</html>
