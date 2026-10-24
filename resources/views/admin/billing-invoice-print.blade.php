@php
    use App\Support\JalaliDate;
    use App\Support\Money;
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ __('admin.billing.official_invoice') }} {{ $invoice->number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Vazirmatn, Tahoma, sans-serif; color: #111827; margin: 0; padding: 32px; font-size: 13px; background: #f3f4f6; }
        .sheet { max-width: 900px; margin: 0 auto; background: #fff; padding: 32px; border: 1px solid #e5e7eb; }
        h1 { text-align: center; font-size: 18px; margin: 0 0 4px; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 16px; color: #374151; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: right; vertical-align: top; }
        th { background: #f9fafb; font-weight: 600; }
        .party th { width: 22%; }
        .section { background: #e5e7eb; font-weight: 700; text-align: center; }
        .num { text-align: left; direction: ltr; white-space: nowrap; }
        .total td { font-weight: 700; }
        .stamp { display: flex; justify-content: space-between; margin-top: 48px; }
        .stamp div { width: 45%; text-align: center; border-top: 1px dashed #9ca3af; padding-top: 8px; color: #6b7280; }
        .actions { text-align: center; margin-bottom: 16px; }
        .actions button { font: inherit; padding: 6px 20px; cursor: pointer; }
        @media print { body { background: #fff; padding: 0; } .sheet { border: 0; padding: 0; } .actions { display: none; } }
    </style>
</head>
<body>
<div class="actions"><button type="button" onclick="window.print()">{{ __('admin.billing.print') }}</button></div>
<div class="sheet">
    <h1>{{ __('admin.billing.official_invoice') }}</h1>
    <div class="meta">
        <span>{{ __('admin.billing.invoice_number') }}: <strong>{{ JalaliDate::persianDigits($invoice->number) }}</strong></span>
        <span>{{ __('admin.billing.issued_at') }}: {{ JalaliDate::date($invoice->issued_at) }}</span>
        <span>{{ __('admin.billing.due_at') }}: {{ JalaliDate::date($invoice->due_at) }}</span>
    </div>

    @foreach (['seller' => $seller, 'buyer' => $buyer] as $role => $party)
        <table class="party">
            <tr><td colspan="4" class="section">{{ __('admin.billing.'.$role) }}</td></tr>
            <tr>
                <th>{{ __('admin.billing.legal_name') }}</th><td>{{ $party['name'] ?? '—' }}</td>
                <th>{{ __('admin.billing.national_id') }}</th><td>{{ filled($party['national_id'] ?? null) ? JalaliDate::persianDigits($party['national_id']) : '—' }}</td>
            </tr>
            <tr>
                <th>{{ __('admin.billing.economic_code') }}</th><td>{{ filled($party['economic_code'] ?? null) ? JalaliDate::persianDigits($party['economic_code']) : '—' }}</td>
                <th>{{ __('admin.billing.postal_code') }}</th><td>{{ filled($party['postal_code'] ?? null) ? JalaliDate::persianDigits($party['postal_code']) : '—' }}</td>
            </tr>
            <tr>
                <th>{{ __('admin.billing.billing_address') }}</th><td>{{ $party['address'] ?? '—' }}</td>
                <th>{{ __('admin.billing.billing_phone') }}</th><td>{{ filled($party['phone'] ?? null) ? JalaliDate::persianDigits($party['phone']) : '—' }}</td>
            </tr>
        </table>
    @endforeach

    <table>
        <thead>
            <tr>
                <th style="width: 6%">#</th>
                <th>{{ __('admin.billing.description') }}</th>
                <th style="width: 10%">{{ __('admin.billing.quantity') }}</th>
                <th style="width: 18%">{{ __('admin.billing.unit_price') }}</th>
                <th style="width: 18%">{{ __('admin.billing.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>{{ JalaliDate::persianDigits($loop->iteration) }}</td>
                    <td>{{ $line->description }}</td>
                    <td>{{ JalaliDate::persianDigits($line->quantity) }}</td>
                    <td class="num">{{ Money::format($line->unit_price_rials) }}</td>
                    <td class="num">{{ Money::format($line->amount_rials) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="4">{{ __('admin.billing.subtotal') }}</td><td class="num">{{ Money::format($invoice->subtotal_rials) }}</td></tr>
            <tr><td colspan="4">{{ __('admin.billing.discount') }}</td><td class="num">{{ Money::format($invoice->discount_rials) }}</td></tr>
            <tr><td colspan="4">{{ __('admin.billing.tax_with_rate', ['rate' => JalaliDate::persianDigits((string) (float) $invoice->tax_rate)]) }}</td><td class="num">{{ Money::format($invoice->tax_rials) }}</td></tr>
            <tr class="total"><td colspan="4">{{ __('admin.billing.total') }}</td><td class="num">{{ Money::format($invoice->total_rials) }}</td></tr>
            <tr><td colspan="4">{{ __('admin.billing.balance') }}</td><td class="num">{{ Money::format($invoice->balanceRials()) }}</td></tr>
        </tfoot>
    </table>

    @if (filled($invoice->notes))
        <p>{{ __('admin.billing.notes') }}: {{ $invoice->notes }}</p>
    @endif

    <div class="stamp">
        <div>{{ __('admin.billing.seller_signature') }}</div>
        <div>{{ __('admin.billing.buyer_signature') }}</div>
    </div>
</div>
</body>
</html>
