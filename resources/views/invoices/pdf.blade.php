<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            margin: 0;
            padding: 24px;
            background: #f8fafc;
            font-family: DejaVu Sans, sans-serif;
            color: #4b5563;
            font-size: 13px;
        }

        .card {
            width: 100%;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 28px;
        }

        .header-table,
        .meta-table,
        .payment-table,
        .footer-table,
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table {
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 20px;
        }

        .header-table td {
            padding: 0 0 18px;
            vertical-align: top;
        }

        .brand {
            font-size: 24px;
            font-weight: bold;
            color: #111827;
        }

        .brand-subtitle {
            margin-top: 4px;
            color: #6b7280;
            font-size: 11px;
        }

        .text-right {
            text-align: right;
        }

        .badge {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: bold;
            border: 1px solid {{ $invoice->status === 'paid' ? '#22c55e' : '#f59e0b' }};
            color: {{ $invoice->status === 'paid' ? '#15803d' : '#b45309' }};
            background: {{ $invoice->status === 'paid' ? '#f0fdf4' : '#fffbeb' }};
        }

        .meta-table {
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 22px;
        }

        .meta-table td {
            width: 33.33%;
            vertical-align: top;
            padding: 0 10px 20px 0;
        }

        .section-title {
            margin: 0 0 10px;
            font-size: 15px;
            font-weight: bold;
            color: #111827;
        }

        .line {
            margin: 0 0 8px;
            line-height: 1.6;
        }

        .strong {
            color: #111827;
            font-weight: bold;
        }

        .muted {
            color: #6b7280;
            font-size: 12px;
        }

        .table-box {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 22px;
        }

        .items-table thead {
            background: #f8fafc;
        }

        .items-table th,
        .items-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        .items-table tbody tr:last-child td {
            border-bottom: none;
        }

        .amount {
            text-align: right;
        }

        .service-name {
            display: block;
            font-weight: bold;
            color: #111827;
            margin-bottom: 3px;
        }

        .payment-table {
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 20px;
        }

        .payment-table td {
            width: 50%;
            vertical-align: top;
            padding: 0 0 20px;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table td {
            padding: 0 0 10px;
        }

        .summary-total td {
            border-top: 1px solid #e5e7eb;
            padding-top: 12px;
            font-size: 16px;
            font-weight: bold;
            color: #111827;
        }

        .summary-total .amount {
            color: #2563eb;
        }

        .footer-table {
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 18px;
        }

        .footer-table td {
            width: 50%;
            vertical-align: top;
            padding: 0 0 18px;
        }

        .mini-badge {
            display: inline-block;
            margin-top: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: bold;
            color: {{ $invoice->status === 'paid' ? '#15803d' : '#b45309' }};
            background: {{ $invoice->status === 'paid' ? '#f0fdf4' : '#fffbeb' }};
        }

        .note {
            color: #6b7280;
            text-align: center;
            font-size: 12px;
        }
    </style>
</head>
<body>
        @php
            $siteName = \App\Models\Setting::getSiteName();
            $logoPath = \App\Models\Setting::get('site_logo');
            $fullLogoPath = (!empty($logoPath) && file_exists(storage_path('app/public/' . $logoPath)))
                ? storage_path('app/public/' . $logoPath)
                : public_path('assets/img/logo.svg');
        @endphp
        <table class="header-table">
            <tr>
                <td>
                    @if(file_exists($fullLogoPath))
                        <img src="{{ $fullLogoPath }}" alt="{{ $siteName }}" style="height: 35px; max-width: 180px;">
                    @else
                        <div class="brand">{{ $siteName }}</div>
                    @endif
                    <div class="brand-subtitle">Medical Platform Invoice</div>
                </td>
                <td class="text-right">
                    <span class="badge">{{ ucfirst($invoice->status) }}</span>
                </td>
            </tr>
        </table>

        <table class="meta-table">
            <tr>
                <td>
                    <div class="section-title">Invoice Details</div>
                    <p class="line">Invoice Number: <span class="strong">{{ $invoice->invoice_number ?? '#INV' . str_pad($invoice->id, 4, '0', STR_PAD_LEFT) }}</span></p>
                    <p class="line">Issued On: <span class="strong">{{ $invoice->issued_date ? $invoice->issued_date->format('d M Y') : '—' }}</span></p>
                    <p class="line">Appointment: <span class="strong">#APT{{ str_pad($invoice->appointment_id, 4, '0', STR_PAD_LEFT) }}</span></p>
                </td>
                <td>
                    <div class="section-title">Invoice From</div>
                    <p class="line strong">{{ $siteName }} - Medical Platform</p>
                    <p class="line">Dr. {{ optional(optional($doctor)->user)->name ?? optional(optional(optional($invoice->appointment)->doctor)->user)->name ?? '—' }}</p>
                    <p class="line muted">{{ optional($doctor)->specialization ?? optional(optional($invoice->appointment)->doctor)->specialization ?? '' }}</p>
                </td>
                <td class="text-right">
                    <div class="section-title">Invoice To</div>
                    <p class="line strong">{{ optional(optional($patient)->user)->name ?? optional(optional($invoice->patient)->user)->name ?? '—' }}</p>
                    <p class="line">{{ optional(optional($patient)->user)->email ?? optional(optional($invoice->patient)->user)->email ?? '' }}</p>
                    @if(optional($patient)->phone || optional($invoice->patient)->phone)
                        <p class="line muted">{{ optional($patient)->phone ?? $invoice->patient->phone }}</p>
                    @endif
                </td>
            </tr>
        </table>

        <div class="section-title">Service Items</div>
        <div class="table-box">
            <table class="items-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Description</th>
                        <th>Date</th>
                        <th class="amount">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>
                            <span class="service-name">Online Medical Consultation</span>
                            <span class="muted">
                                Dr. {{ optional(optional($doctor)->user)->name ?? optional(optional(optional($invoice->appointment)->doctor)->user)->name ?? '' }}
                                @if(optional($doctor)->specialization || optional(optional($invoice->appointment)->doctor)->specialization)
                                    - {{ optional($doctor)->specialization ?? $invoice->appointment->doctor->specialization }}
                                @endif
                            </span>
                        </td>
                        <td>{{ optional($appointment)->appointment_date?->format('d M Y') ?? optional(optional($invoice->appointment)->appointment_date)?->format('d M Y') ?? '—' }}</td>
                        <td class="amount strong">${{ number_format($invoice->total_amount, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <table class="payment-table">
            <tr>
                <td>
                    @if(optional($invoice->payment)->transaction_id || optional($invoice->payment)->payment_intent_id)
                        <div class="section-title">Payment Reference</div>
                        <p class="line">Transaction ID: <span class="strong">{{ $invoice->payment->transaction_id ?? $invoice->payment->payment_intent_id ?? '—' }}</span></p>
                        <p class="line">Method: <span class="strong">{{ ucfirst($invoice->payment->method ?? 'Card') }}</span></p>
                    @endif
                </td>
                <td>
                    <table class="summary-table">
                        <tr>
                            <td>Subtotal</td>
                            <td class="amount strong">${{ number_format($invoice->total_amount, 2) }}</td>
                        </tr>
                        <tr class="summary-total">
                            <td>Total (USD)</td>
                            <td class="amount">${{ number_format($invoice->total_amount, 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="footer-table">
            <tr>
                <td>
                    <div class="section-title">Terms & Conditions</div>
                    <p class="line muted">All charges are final and include applicable service fees. For queries, contact support.</p>
                </td>
                <td class="text-right">
                    <p class="line muted">Issued on {{ $invoice->created_at->format('d M Y, h:i A') }}</p>
                    <span class="mini-badge">{{ ucfirst($invoice->status) }}</span>
                </td>
            </tr>
        </table>

        <p class="note">This PDF copy matches the invoice details layout shown inside {{ $siteName }}.</p>
    </div>
</body>
</html>
