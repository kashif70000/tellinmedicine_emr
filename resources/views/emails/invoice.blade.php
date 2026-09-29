<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            margin: 0;
            padding: 24px 12px;
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #4b5563;
        }

        table {
            border-spacing: 0;
            border-collapse: collapse;
        }

        .wrapper {
            width: 100%;
            background-color: #f4f6f9;
        }

        .container {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            background-color: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }

        .content {
            padding: 32px;
        }

        .border-bottom {
            border-bottom: 1px solid #e5e7eb;
        }

        .pb-3 {
            padding-bottom: 18px;
        }

        .mb-3 {
            margin-bottom: 18px;
        }

        .mb-4 {
            margin-bottom: 24px;
        }

        .logo {
            height: 40px;
            display: block;
        }

        .text-end {
            text-align: right;
        }

        .badge {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            border: 1px solid {{ $invoice->status === 'paid' ? '#22c55e' : '#f59e0b' }};
            color: {{ $invoice->status === 'paid' ? '#15803d' : '#b45309' }};
            background-color: {{ $invoice->status === 'paid' ? '#f0fdf4' : '#fffbeb' }};
        }

        .heading {
            margin: 0 0 10px;
            font-size: 16px;
            font-weight: 700;
            color: #111827;
        }

        .text-body {
            margin: 0 0 8px;
            font-size: 14px;
            line-height: 1.6;
            color: #4b5563;
        }

        .text-dark {
            color: #111827;
            font-weight: 600;
        }

        .text-muted {
            color: #6b7280;
            font-size: 13px;
        }

        .meta-col {
            width: 33.33%;
            vertical-align: top;
            padding-right: 18px;
        }

        .meta-col:last-child {
            padding-right: 0;
        }

        .meta-col.right {
            text-align: right;
        }

        .table-wrap {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }

        .service-table {
            width: 100%;
        }

        .service-table th {
            background-color: #f8fafc;
            color: #111827;
            font-size: 13px;
            font-weight: 700;
            text-align: left;
            padding: 12px 14px;
            border-bottom: 1px solid #e5e7eb;
        }

        .service-table td {
            padding: 14px;
            font-size: 14px;
            color: #4b5563;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .service-table tr:last-child td {
            border-bottom: none;
        }

        .service-name {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 4px;
        }

        .amount {
            text-align: right;
            white-space: nowrap;
        }

        .summary-label,
        .summary-value {
            font-size: 14px;
            padding: 0 0 10px;
        }

        .summary-value {
            text-align: right;
            color: #111827;
            font-weight: 600;
        }

        .summary-total td {
            border-top: 1px solid #e5e7eb;
            padding-top: 12px;
            font-size: 18px;
            font-weight: 700;
        }

        .summary-total .summary-value {
            color: #2563eb;
        }

        .footer-note {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.6;
        }

        @media only screen and (max-width: 700px) {
            .content {
                padding: 22px !important;
            }

            .stack,
            .stack tbody,
            .stack tr,
            .stack td {
                display: block !important;
                width: 100% !important;
            }

            .meta-col,
            .meta-col.right,
            .text-end {
                text-align: left !important;
                padding-right: 0 !important;
            }

            .mobile-gap {
                padding-bottom: 18px !important;
            }
        }
    </style>
</head>
<body>
    @php
        $settingLogo = \App\Models\Setting::getLogo('normal');
        $settingFavicon = \App\Models\Setting::getFavicon();
        $logoSource = $settingLogo ?: $settingFavicon;
        $siteName = \App\Models\Setting::getSiteName();
    @endphp

    <table class="wrapper" role="presentation" width="100%" style="width: 100%; background-color: #f4f6f9;">
        <tr>
            <td align="center" style="padding: 0;">
                <table class="container" role="presentation" width="100%" style="width: 100%; max-width: 900px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;">
                    <tr>
                        <td class="content" style="padding: 32px;">
                            <table role="presentation" width="100%" class="border-bottom pb-3 mb-3" style="width: 100%; border-bottom: 1px solid #e5e7eb; padding-bottom: 18px; margin-bottom: 18px;">
                                <tr>
                                    <td valign="middle">
                                        <img src="{{ $logoSource }}" alt="{{ $siteName }} logo" class="logo" style="height: 40px; display: block;">
                                    </td>
                                    <td valign="middle" class="text-end" style="text-align: right;">
                                        <span class="badge" style="display: inline-block; padding: 8px 14px; border-radius: 999px; font-size: 12px; font-weight: 700; line-height: 1; border: 1px solid {{ $invoice->status === 'paid' ? '#22c55e' : '#f59e0b' }}; color: {{ $invoice->status === 'paid' ? '#15803d' : '#b45309' }}; background-color: {{ $invoice->status === 'paid' ? '#f0fdf4' : '#fffbeb' }};">{{ ucfirst($invoice->status) }}</span>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" class="stack border-bottom mb-4" style="width: 100%; border-bottom: 1px solid #e5e7eb; padding-bottom: 18px; margin-bottom: 24px;">
                                <tr>
                                    <td class="meta-col mobile-gap" style="width: 33.33%; vertical-align: top; padding-right: 18px;">
                                        <h5 class="heading" style="margin: 0 0 10px; font-size: 16px; font-weight: 700; color: #111827;">Invoice Details</h5>
                                        <p class="text-body" style="margin: 0 0 8px; font-size: 14px; line-height: 1.6; color: #4b5563;">
                                            Invoice Number:
                                            <span class="text-dark" style="color: #111827; font-weight: 600;">{{ $invoice->invoice_number ?? '#INV' . str_pad($invoice->id, 4, '0', STR_PAD_LEFT) }}</span>
                                        </p>
                                        <p class="text-body" style="margin: 0 0 8px; font-size: 14px; line-height: 1.6; color: #4b5563;">
                                            Issued On:
                                            <span class="text-dark" style="color: #111827; font-weight: 600;">{{ $invoice->issued_date ? $invoice->issued_date->format('d M Y') : '—' }}</span>
                                        </p>
                                        <p class="text-body" style="margin: 0; font-size: 14px; line-height: 1.6; color: #4b5563;">
                                            Appointment:
                                            <span class="text-dark" style="color: #111827; font-weight: 600;">#APT{{ str_pad($invoice->appointment_id, 4, '0', STR_PAD_LEFT) }}</span>
                                        </p>
                                    </td>
                                    <td class="meta-col mobile-gap" style="width: 33.33%; vertical-align: top; padding-right: 18px;">
                                        <h5 class="heading" style="margin: 0 0 10px; font-size: 16px; font-weight: 700; color: #111827;">Invoice From</h5>
                                        <p class="text-body text-dark" style="margin: 0 0 8px; font-size: 14px; line-height: 1.6; color: #111827; font-weight: 600;">{{ $siteName }} - Medical Platform</p>
                                        <p class="text-body" style="margin: 0 0 8px; font-size: 14px; line-height: 1.6; color: #4b5563;">Dr. {{ optional(optional(optional($invoice->appointment)->doctor)->user)->name ?? '—' }}</p>
                                        <p class="text-body text-muted" style="margin: 0; font-size: 13px; line-height: 1.6; color: #6b7280;">{{ optional(optional($invoice->appointment)->doctor)->specialization ?? '' }}</p>
                                    </td>
                                    <td class="meta-col right" style="width: 33.33%; vertical-align: top; text-align: right;">
                                        <h5 class="heading" style="margin: 0 0 10px; font-size: 16px; font-weight: 700; color: #111827;">Invoice To</h5>
                                        <p class="text-body text-dark" style="margin: 0 0 8px; font-size: 14px; line-height: 1.6; color: #111827; font-weight: 600;">{{ optional(optional($invoice->patient)->user)->name ?? '—' }}</p>
                                        <p class="text-body" style="margin: 0 0 8px; font-size: 14px; line-height: 1.6; color: #4b5563;">{{ optional(optional($invoice->patient)->user)->email ?? '' }}</p>
                                        @if(optional($invoice->patient)->phone)
                                            <p class="text-body text-muted" style="margin: 0; font-size: 13px; line-height: 1.6; color: #6b7280;">{{ $invoice->patient->phone }}</p>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            <div class="mb-4" style="margin-bottom: 24px;">
                                <h6 class="heading" style="margin: 0 0 10px; font-size: 16px; font-weight: 700; color: #111827;">Service Items</h6>
                                <div class="table-wrap" style="border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden;">
                                    <table class="service-table" role="presentation" style="width: 100%; border-collapse: collapse;">
                                        <thead>
                                            <tr>
                                                <th style="background-color: #f8fafc; color: #111827; font-size: 13px; font-weight: 700; text-align: left; padding: 12px 14px; border-bottom: 1px solid #e5e7eb;">#</th>
                                                <th style="background-color: #f8fafc; color: #111827; font-size: 13px; font-weight: 700; text-align: left; padding: 12px 14px; border-bottom: 1px solid #e5e7eb;">Description</th>
                                                <th style="background-color: #f8fafc; color: #111827; font-size: 13px; font-weight: 700; text-align: left; padding: 12px 14px; border-bottom: 1px solid #e5e7eb;">Date</th>
                                                <th class="amount" style="background-color: #f8fafc; color: #111827; font-size: 13px; font-weight: 700; text-align: right; padding: 12px 14px; border-bottom: 1px solid #e5e7eb;">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td style="padding: 14px; font-size: 14px; color: #4b5563; border-bottom: 1px solid #e5e7eb; vertical-align: top;">1</td>
                                                <td style="padding: 14px; font-size: 14px; color: #4b5563; border-bottom: 1px solid #e5e7eb; vertical-align: top;">
                                                    <span class="service-name" style="display: block; font-size: 14px; font-weight: 600; color: #111827; margin-bottom: 4px;">Online Medical Consultation</span>
                                                    <span class="text-muted" style="color: #6b7280; font-size: 13px; line-height: 1.6;">
                                                        Dr. {{ optional(optional(optional($invoice->appointment)->doctor)->user)->name ?? '' }}
                                                        @if(optional(optional($invoice->appointment)->doctor)->specialization)
                                                            - {{ $invoice->appointment->doctor->specialization }}
                                                        @endif
                                                    </span>
                                                </td>
                                                <td style="padding: 14px; font-size: 14px; color: #4b5563; border-bottom: 1px solid #e5e7eb; vertical-align: top;">{{ optional(optional($invoice->appointment)->appointment_date)?->format('d M Y') ?? '—' }}</td>
                                                <td class="amount text-dark" style="padding: 14px; font-size: 14px; color: #111827; font-weight: 600; border-bottom: 1px solid #e5e7eb; vertical-align: top; text-align: right; white-space: nowrap;">${{ number_format($invoice->total_amount, 2) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <table role="presentation" width="100%" class="stack border-bottom mb-3" style="width: 100%; border-bottom: 1px solid #e5e7eb; padding-bottom: 18px; margin-bottom: 18px;">
                                <tr>
                                    <td class="mobile-gap" valign="top" style="width: 50%; padding-right: 18px;">
                                        @if($invoice->payment)
                                            <h6 class="heading" style="margin: 0 0 10px; font-size: 16px; font-weight: 700; color: #111827;">Payment Method</h6>
                                            <p class="text-body" style="margin: 0; font-size: 14px; line-height: 1.6; color: #4b5563;">
                                                Method:
                                                <span class="text-dark" style="color: #111827; font-weight: 600;">Stripe</span>
                                            </p>
                                        @endif
                                    </td>
                                    <td valign="top" style="width: 50%;">
                                        <table role="presentation" width="100%" style="width: 100%; border-collapse: collapse;">
                                            <tr>
                                                <td class="summary-label" style="font-size: 14px; padding: 0 0 10px; color: #4b5563;">Subtotal</td>
                                                <td class="summary-value" style="font-size: 14px; padding: 0 0 10px; text-align: right; color: #111827; font-weight: 600;">${{ number_format($invoice->total_amount, 2) }}</td>
                                            </tr>
                                            <tr class="summary-total">
                                                <td class="summary-label" style="border-top: 1px solid #e5e7eb; padding-top: 12px; font-size: 18px; font-weight: 700; color: #111827;">Total (USD)</td>
                                                <td class="summary-value" style="border-top: 1px solid #e5e7eb; padding-top: 12px; font-size: 18px; font-weight: 700; text-align: right; color: #2563eb;">${{ number_format($invoice->total_amount, 2) }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" class="stack border-bottom" style="padding-bottom: 18px; margin-bottom: 18px;">
                                <tr>
                                    <td class="mobile-gap" valign="top" style="width: 50%; padding-right: 18px;">
                                        <h6 class="heading" style="margin: 0 0 10px; font-size: 16px; font-weight: 700; color: #111827;">Terms &amp; Conditions</h6>
                                        <p class="footer-note" style="margin: 0;">
                                            All charges are final and include applicable service fees.
                                            For queries, contact support.
                                        </p>
                                    </td>
                                    <td valign="top" class="text-end" style="width: 50%;">
                                        <p class="footer-note" style="margin: 0 0 8px;">Issued on {{ $invoice->created_at->format('d M Y, h:i A') }}</p>
                                        <span class="badge" style="display: inline-block; padding: 8px 14px; border-radius: 999px; font-size: 12px; font-weight: 700; line-height: 1; border: 1px solid {{ $invoice->status === 'paid' ? '#22c55e' : '#f59e0b' }}; color: {{ $invoice->status === 'paid' ? '#15803d' : '#b45309' }}; background-color: {{ $invoice->status === 'paid' ? '#f0fdf4' : '#fffbeb' }};">{{ ucfirst($invoice->status) }}</span>
                                    </td>
                                </tr>
                            </table>

                            <p class="footer-note" style="margin: 0; text-align: center;">
                                   Please visit again for more details.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

                       