<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your OTP</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;">
                    <tr>
                        <td style="padding:24px 32px;border-bottom:3px solid #1B5275;">
                            @if(!empty($logoBase64))
                                <img src="{{ $logoBase64 }}" alt="Shree Axar Ltd" style="display:block;max-height:44px;max-width:200px;margin-bottom:12px;">
                            @endif
                            <h1 style="margin:0;font-size:20px;color:#1B5275;">Password Reset Code</h1>
                            <p style="margin:4px 0 0;font-size:13px;color:#6b7280;">Shree Axar ERP</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;text-align:center;">
                            <p style="margin:0 0 18px;font-size:16px;line-height:24px;color:#374151;">Use the following one-time password to continue:</p>
                            <div style="display:inline-block;letter-spacing:8px;font-size:32px;font-weight:bold;color:#1B5275;background:#f9fafb;border:2px solid #1B5275;border-radius:8px;padding:14px 22px;">{{ $otp }}</div>
                            <p style="margin:24px 0 0;font-size:13px;color:#6b7280;line-height:20px;">If you did not request this code, please ignore this email.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f9fafb;padding:16px 32px;text-align:center;color:#9ca3af;font-size:12px;border-top:1px solid #e5e7eb;">
                            Shree Axar Ltd · Automated message, do not reply.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
