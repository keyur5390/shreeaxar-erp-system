<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation {{ $quotation->quotation_number }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:24px 32px;border-bottom:3px solid #1B5275;background:#ffffff;">
                            @if(!empty($logoBase64))
                                <img src="{{ $logoBase64 }}" alt="Shree Axar Ltd" style="display:block;max-height:48px;max-width:220px;margin-bottom:14px;">
                            @endif
                            <h1 style="margin:0;font-size:20px;line-height:28px;color:#1B5275;">Quotation {{ $quotation->quotation_number }}</h1>
                            <p style="margin:4px 0 0;font-size:13px;color:#6b7280;">Shree Axar Furniture</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 18px;font-size:16px;line-height:24px;">Dear Customer,</p>
                            <div style="font-size:15px;line-height:24px;color:#374151;margin-bottom:24px;">{!! nl2br(e($body)) !!}</div>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:24px 0;background:#f9fafb;border:1px solid #e5e7eb;">
                                <tr>
                                    <td style="padding:12px 16px;font-weight:bold;border-bottom:1px solid #e5e7eb;color:#374151;">Quotation Number</td>
                                    <td style="padding:12px 16px;border-bottom:1px solid #e5e7eb;text-align:right;color:#1B5275;font-weight:bold;">{{ $quotation->quotation_number }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;font-weight:bold;border-bottom:1px solid #e5e7eb;color:#374151;">Quotation Date</td>
                                    <td style="padding:12px 16px;border-bottom:1px solid #e5e7eb;text-align:right;">{{ optional($quotation->quotation_date)->format('d M Y') ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;font-weight:bold;color:#374151;">Total Amount (RWF)</td>
                                    <td style="padding:12px 16px;text-align:right;color:#7A1B5D;font-weight:bold;">{{ number_format((float) $quotation->total_amount, 2) }}</td>
                                </tr>
                            </table>
                            <p style="margin:0 0 20px;font-size:15px;line-height:24px;">Please find the quotation PDF attached for your review.</p>
                            <p style="margin:0;font-size:15px;line-height:24px;">Regards,<br><strong>Shree Axar Team</strong></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f9fafb;padding:18px 32px;text-align:center;color:#6b7280;font-size:12px;line-height:18px;border-top:1px solid #e5e7eb;">
                            Shree Axar Ltd · Near Flyover, Kicukiro Kigali Centre, Kigali, Rwanda<br>
                            <span style="color:#9ca3af;">This is an automated message from Shree Axar ERP.</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
