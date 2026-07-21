<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quotation {{ $quotation->quotation_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #1f2937;
            margin: 0;
            padding: 24px;
        }
        .header {
            width: 100%;
            border-bottom: 2px solid #1B5275;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .header td { vertical-align: top; }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #1B5275;
            margin: 8px 0 6px;
        }
        .muted { color: #6b7280; line-height: 1.5; }
        .title {
            font-size: 22px;
            font-weight: bold;
            color: #7A1B5D;
            text-align: right;
            margin: 0;
        }
        .meta-table, .items-table, .totals-table, .bank-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .meta-table td {
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
        }
        .meta-table .label {
            width: 35%;
            background: #f9fafb;
            font-weight: bold;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #1B5275;
            margin: 18px 0 8px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .items-table th {
            background: #1B5275;
            color: #ffffff;
            padding: 8px 6px;
            font-size: 10px;
            text-align: left;
        }
        .items-table td {
            border: 1px solid #e5e7eb;
            padding: 7px 6px;
            vertical-align: top;
        }
        .items-table tbody tr {
            page-break-inside: avoid;
        }
        .two-col {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .two-col td {
            width: 50%;
            vertical-align: top;
            padding-right: 12px;
        }
        .items-table .num { text-align: right; white-space: nowrap; }
        .items-table .image-cell { text-align: center; width: 52px; }
        .items-table .product-image {
            max-width: 44px;
            max-height: 44px;
            object-fit: contain;
        }
        .items-table tbody tr:nth-child(even) { background: #f9fafb; }
        .totals-table td {
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
        }
        .totals-table .label {
            text-align: right;
            font-weight: bold;
            width: 75%;
            background: #f9fafb;
        }
        .totals-table .grand {
            font-size: 13px;
            font-weight: bold;
            color: #7A1B5D;
        }
        .notes {
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            padding: 10px 12px;
            line-height: 1.5;
            white-space: pre-wrap;
        }
        .footer {
            margin-top: 24px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #9ca3af;
            font-size: 9px;
        }
        .logo { max-height: 60px; max-width: 200px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 55%;">
                @if(!empty($logoPath))
                    <img src="{{ $logoPath }}" alt="{{ $company->name }}" class="logo"><br>
                @endif
                <p class="company-name">{{ $company->name }}</p>
                <div class="muted">
                    @if($company->address){{ $company->address }}<br>@endif
                    @if($company->phone)Tel: {{ $company->phone }}<br>@endif
                    @if($company->email)Email: {{ $company->email }}<br>@endif
                    @if($company->website)Web: {{ $company->website }}<br>@endif
                    @if($company->tin_number)TIN: {{ $company->tin_number }}@endif
                    @if($company->vat_number) | VAT: {{ $company->vat_number }}@endif
                </div>
            </td>
            <td style="width: 45%; text-align: right;">
                <p class="title">QUOTATION</p>
                <div class="muted" style="margin-top: 8px;">
                    <strong>{{ $quotation->quotation_number }}</strong><br>
                    Date: {{ optional($quotation->quotation_date)->format('d M Y') }}<br>
                    Expiry: {{ optional($quotation->expiry_date)->format('d M Y') }}<br>
                    Status: {{ $quotation->status?->name ?? 'N/A' }}
                </div>
            </td>
        </tr>
    </table>

    <table class="two-col">
        <tr>
            <td>
                <div class="section-title">Bill To</div>
                <table class="meta-table">
                    <tr>
                        <td class="label">Company</td>
                        <td>{{ $quotation->customer?->company_name ?? 'N/A' }}</td>
                    </tr>
                    @if($quotation->customer?->tin_number)
                        <tr>
                            <td class="label">TIN</td>
                            <td>{{ $quotation->customer->tin_number }}</td>
                        </tr>
                    @endif
                    @if($quotation->customer?->email)
                        <tr>
                            <td class="label">Email</td>
                            <td>{{ $quotation->customer->email }}</td>
                        </tr>
                    @endif
                    @if($quotation->customer?->contact_number)
                        <tr>
                            <td class="label">Contact</td>
                            <td>{{ $quotation->customer->contact_number }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="label">Authorized By</td>
                        <td>
                            {{ trim(($quotation->authorizedBy?->first_name ?? '').' '.($quotation->authorizedBy?->last_name ?? '')) ?: 'N/A' }}
                        </td>
                    </tr>
                </table>
            </td>
            <td>
                @if($bank)
                    <div class="section-title">Bank Details</div>
                    <table class="meta-table">
                        <tr><td class="label">Bank</td><td>{{ $bank['bank_name'] ?? '—' }}</td></tr>
                        <tr><td class="label">Account Holder</td><td>{{ $bank['account_holder_name'] ?? '—' }}</td></tr>
                        <tr><td class="label">Account Number</td><td>{{ $bank['account_number'] ?? '—' }}</td></tr>
                        @if(!empty($bank['branch_name']))
                            <tr><td class="label">Branch</td><td>{{ $bank['branch_name'] }}</td></tr>
                        @endif
                        @if(!empty($bank['swift_code']))
                            <tr><td class="label">SWIFT</td><td>{{ $bank['swift_code'] }}</td></tr>
                        @endif
                    </table>
                @endif
            </td>
        </tr>
    </table>

    <div class="section-title">Line Items</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                @if($hasItemImages)
                    <th style="width: 6%;">Image</th>
                @endif
                <th>Description</th>
                <th style="width: 8%;">Unit</th>
                <th style="width: 12%;" class="num">Rate ({{ $quotation->currency_snapshot['code'] ?? $quotation->currency?->code ?? 'RWF' }})</th>
                <th style="width: 8%;" class="num">Qty</th>
                @if($hasDiscount)
                    <th style="width: 10%;" class="num">Disc %</th>
                @endif
                <th style="width: 14%;" class="num">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($quotation->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    @if($hasItemImages)
                        <td class="image-cell">
                            @if(!empty($itemImages[$item->id]))
                                <img src="{{ $itemImages[$item->id] }}" alt="" class="product-image">
                            @else
                                —
                            @endif
                        </td>
                    @endif
                    <td>
                        @if($item->product_id === null && $item->product === null)
                            <strong>[Product Deleted]</strong><br>
                        @elseif($item->product)
                            <strong>{{ $item->product->title }}</strong><br>
                        @endif
                        {{ $item->description }}
                    </td>
                    <td>{{ $item->unit }}</td>
                    <td class="num">{{ number_format((float) $item->rate, 2) }}</td>
                    <td class="num">{{ (int) $item->quantity }}</td>
                    @if($hasDiscount)
                        <td class="num">
                            @if((float) $item->discount_rate > 0)
                                {{ number_format((float) $item->discount_rate, 2) }}
                            @else
                                —
                            @endif
                        </td>
                    @endif
                    <td class="num">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $itemColumnCount }}" style="text-align: center; color: #6b7280;">No line items.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals-table" style="width: 42%; margin-left: auto;">
        <tr>
            <td class="label">Subtotal</td>
            <td class="num">{{ number_format((float) $quotation->sub_total, 2) }}</td>
        </tr>
        @if($hasDiscount)
            <tr>
                <td class="label">Discount</td>
                <td class="num">{{ number_format((float) $quotation->discount_amount, 2) }}</td>
            </tr>
        @endif
        @if($quotation->exclude_vat)
            <tr>
                <td class="label">VAT</td>
                <td class="num">Excluded</td>
            </tr>
        @else
            <tr>
                <td class="label">VAT ({{ number_format((float) $quotation->vat_rate, 2) }}%)</td>
                <td class="num">{{ number_format((float) $quotation->vat_amount, 2) }}</td>
            </tr>
        @endif
        <tr>
            <td class="label grand">Grand Total ({{ $quotation->currency_snapshot['code'] ?? $quotation->currency?->code ?? 'RWF' }})</td>
            <td class="num grand">{{ number_format((float) $quotation->total_amount, 2) }}</td>
        </tr>
    </table>

    <div class="notes" style="margin-top: -8px; margin-bottom: 16px;">
        <strong>Amount in words:</strong> {{ $amountInWords }} Only
    </div>

    @if($quotation->terms_conditions)
        <div class="section-title">Terms &amp; Conditions</div>
        <div class="notes">{{ $quotation->terms_conditions }}</div>
    @endif

    @if($quotation->notes)
        <div class="section-title">Notes</div>
        <div class="notes">{{ $quotation->notes }}</div>
    @endif

    <div class="footer">
        {{ $company->name }} · {{ now()->format('d M Y H:i') }}
    </div>
</body>
</html>
