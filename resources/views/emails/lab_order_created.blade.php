<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New Lab Order</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f8fafc; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .header { background: #2563eb; color: #fff; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; }
        .body { padding: 24px; }
        .section { margin-bottom: 20px; }
        .section-title { font-weight: bold; color: #0f172a; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .test-item { background: #eff6ff; border-radius: 6px; padding: 10px 14px; margin-bottom: 6px; display: flex; justify-content: space-between; }
        .test-name { font-weight: bold; color: #1d4ed8; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .info-table td { padding: 6px 0; font-size: 14px; }
        .info-table .label { color: #64748b; width: 40%; }
        .info-table .val { font-weight: 500; color: #1e293b; }
        .footer { background: #f8fafc; padding: 16px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Laboratory Test Order</h1>
            <p style="margin: 4px 0 0 0; opacity: 0.9; font-size: 14px;">Healthcare Clinical Workspace</p>
        </div>
        <div class="body">
            <p>Dear <strong>{{ $patient->user?->name ?? 'Patient' }}</strong>,</p>
            <p><strong>{{ $doctor->display_name ?? 'Doctor' }}</strong> has placed a new laboratory test order for you.</p>

            <table class="info-table">
                <tr>
                    <td class="label">Requisition Reference:</td>
                    <td class="val">{{ $labOrder->reference_number ?: ('LAB#' . $labOrder->id) }}</td>
                </tr>
                <tr>
                    <td class="label">Date Ordered:</td>
                    <td class="val">{{ $labOrder->created_at->format('M d, Y') }}</td>
                </tr>
                <tr>
                    <td class="label">Destination Laboratory:</td>
                    <td class="val">
                        <strong>{{ $labOrder->destination_laboratory_name }}</strong><br>
                        <small style="color: #64748b;">
                            {{ $labOrder->laboratory_address_snapshot }}
                            @if($labOrder->laboratory_city_snapshot)
                                , {{ $labOrder->laboratory_city_snapshot }}, {{ $labOrder->laboratory_state_snapshot }} {{ $labOrder->laboratory_postal_code_snapshot }}
                            @endif
                            @if($labOrder->laboratory_phone_snapshot)
                                <br>Phone: {{ $labOrder->laboratory_phone_snapshot }}
                            @endif
                        </small>
                    </td>
                </tr>
                <tr>
                    <td class="label">Ordered Tests:</td>
                    <td class="val">{{ $labOrder->items->count() }} test(s)</td>
                </tr>
            </table>

            <div class="section">
                <div class="section-title">Tests Ordered</div>
                @foreach($labOrder->items as $item)
                    <div class="test-item">
                        <div>
                            <span class="test-name">{{ $item->test_name_snapshot }}</span>
                            @if($item->short_name_snapshot)
                                <span style="color: #64748b; font-size: 12px;">({{ $item->short_name_snapshot }})</span>
                            @endif
                            @if($item->category_snapshot)
                                <div style="font-size: 12px; color: #64748b;">Category: {{ $item->category_snapshot }}</div>
                            @endif
                        </div>
                        @if($item->loinc_code_snapshot)
                            <div style="font-size: 12px; color: #475569; font-family: monospace;">
                                LOINC: {{ $item->loinc_code_snapshot }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if(!empty($labOrder->clinical_notes))
                <div class="section">
                    <div class="section-title">Clinical Notes / Instructions</div>
                    <p style="font-size: 13px; color: #334155; margin: 0; background: #fafafa; padding: 10px; border-left: 3px solid #2563eb;">
                        {{ $labOrder->clinical_notes }}
                    </p>
                </div>
            @endif

            <p style="font-size: 13px; color: #64748b; margin-top: 24px;">
                A PDF requisition copy of this lab order is attached to this email. You can present it at the laboratory for specimen collection.
            </p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Preclinic Healthcare Platform. All rights reserved.
        </div>
    </div>
</body>
</html>
