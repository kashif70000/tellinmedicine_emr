<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New Prescription</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f8fafc; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .header { background: #0d9488; color: #fff; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; }
        .body { padding: 24px; }
        .section { margin-bottom: 20px; }
        .section-title { font-weight: bold; color: #0f172a; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .med-item { background: #f1f5f9; border-radius: 6px; padding: 12px; margin-bottom: 8px; }
        .med-name { font-weight: bold; color: #0d9488; }
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
            <h1>New Prescription</h1>
            <p style="margin: 4px 0 0 0; opacity: 0.9; font-size: 14px;">Healthcare Clinical Workspace</p>
        </div>
        <div class="body">
            <p>Dear <strong>{{ $patient->user?->name ?? 'Patient' }}</strong>,</p>
            <p><strong>{{ $doctor->display_name ?? 'Doctor' }}</strong> has issued a new medication prescription for you.</p>

            <table class="info-table">
                <tr>
                    <td class="label">Prescription Reference:</td>
                    <td class="val">{{ $prescription->reference_number ?: ('RX#' . $prescription->id) }}</td>
                </tr>
                <tr>
                    <td class="label">Date:</td>
                    <td class="val">{{ $prescription->created_at->format('M d, Y') }}</td>
                </tr>
                <tr>
                    <td class="label">Destination Pharmacy:</td>
                    <td class="val">
                        <strong>{{ $prescription->destination_pharmacy_name }}</strong><br>
                        <small style="color: #64748b;">
                            {{ $prescription->pharmacy_address_snapshot }}
                            @if($prescription->pharmacy_city_snapshot)
                                , {{ $prescription->pharmacy_city_snapshot }}, {{ $prescription->pharmacy_state_snapshot }} {{ $prescription->pharmacy_postal_code_snapshot }}
                            @endif
                            @if($prescription->pharmacy_phone_snapshot)
                                <br>Phone: {{ $prescription->pharmacy_phone_snapshot }}
                            @endif
                        </small>
                    </td>
                </tr>
                <tr>
                    <td class="label">Total Medications:</td>
                    <td class="val">{{ $prescription->medication_count }}</td>
                </tr>
            </table>

            <div class="section">
                <div class="section-title">Prescribed Medications</div>
                @if(is_array($prescription->medicines))
                    @foreach($prescription->medicines as $med)
                        @php
                            $name = $med['name'] ?? 'Medication';
                            $strength = $med['strength'] ?? ($med['dosage'] ?? '');
                            $form = $med['dosage_form'] ?? '';
                            $freq = $med['frequency'] ?? '';
                            $timing = is_array($med['timing'] ?? null) ? implode(', ', $med['timing']) : ($med['timing'] ?? '');
                            $intake = $med['intake'] ?? ($med['instructions'] ?? '');
                            $duration = $med['duration'] ?? '';
                            $quantity = $med['quantity'] ?? '';
                            $unit = $med['unit'] ?? '';
                            $refills = $med['refills'] ?? 0;
                            $directions = $med['directions'] ?? ($med['notes'] ?? '');
                        @endphp
                        <div class="med-item">
                            <div class="med-name">{{ $name }}</div>
                            @if($strength || $form)
                                <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                                    {{ implode(' • ', array_filter([$strength, $form])) }}
                                </div>
                            @endif
                            @if($directions)
                                <div style="font-size: 13px; color: #1e293b; font-weight: 500; margin-top: 4px;">
                                    <strong>Sig:</strong> {{ $directions }}
                                </div>
                            @endif
                            <div style="font-size: 13px; color: #475569; margin-top: 4px;">
                                @if($freq) <span><strong>Frequency:</strong> {{ $freq }}</span> @endif
                                @if($timing) <span> &bull; <strong>Timing:</strong> {{ $timing }}</span> @endif
                                @if($intake) <span> &bull; <strong>Intake:</strong> {{ $intake }}</span> @endif
                                @if($duration) <span> &bull; <strong>Duration:</strong> {{ $duration }}</span> @endif
                                @if($quantity) <span> &bull; <strong>Dispense:</strong> {{ $quantity }} {{ $unit ?: 'units' }}</span> @endif
                                @if(isset($refills)) <span> &bull; <strong>Refills:</strong> {{ $refills }}</span> @endif
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            @if(!empty($prescription->notes))
                <div class="section">
                    <div class="section-title">Clinical Notes / Instructions</div>
                    <p style="font-size: 13px; color: #334155; margin: 0; background: #fafafa; padding: 10px; border-left: 3px solid #0d9488;">
                        {{ $prescription->notes }}
                    </p>
                </div>
            @endif

            <p style="font-size: 13px; color: #64748b; margin-top: 24px;">
                A PDF copy of this prescription is attached to this email for your records.
            </p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Preclinic Healthcare Platform. All rights reserved.
        </div>
    </div>
</body>
</html>
