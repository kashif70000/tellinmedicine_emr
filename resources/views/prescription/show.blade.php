@extends('layouts.layout')

@section('title', 'Prescription Details')

@section('styles')
<style>
    @media print {
        .no-print { display: none !important; }
        .card { box-shadow: none !important; border: none !important; }
    }
</style>
@endsection

@section('content')

{{-- ── Back + Actions Bar ───────────────────────────────────────────────── --}}
<div class="d-flex align-items-sm-center flex-sm-row flex-column mb-4 gap-2 no-print">
    <div class="flex-grow-1">
        <a href="{{ route('prescriptions.index') }}" class="btn btn-light border d-inline-flex align-items-center gap-1">
            <i class="ti ti-chevron-left"></i> Prescriptions
        </a>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-dark d-inline-flex align-items-center gap-1">
            <i class="ti ti-printer"></i> Print
        </button>
    </div>
</div>

{{-- ── Prescription Card ───────────────────────────────────────────────── --}}
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card shadow-sm" id="prescription-print">
            <div class="card-body">

                {{-- ── Header: Logo + Prescription ID ──────────────────────── --}}
                <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                    <div class="invoice-logo">
                        <img src="{{ \App\Models\Setting::getLogo('normal') }}" class="logo-white" alt="{{ \App\Models\Setting::getSiteName() }}" style="height:40px;">
                        <img src="{{ \App\Models\Setting::getLogo('dark') }}" class="logo-dark" alt="{{ \App\Models\Setting::getSiteName() }}" style="height:40px;">
                    </div>
                    <span class="badge bg-info-subtle text-info-emphasis fs-13 fw-medium border border-primary py-1 px-2">
                        {{ $prescription->reference_number ?: ('#PRE' . str_pad($prescription->id, 4, '0', STR_PAD_LEFT)) }}
                    </span>
                </div>

                {{-- ── Doctor Info + Appointment Meta ──────────────────────── --}}
                <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3 flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-xxl rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center flex-shrink-0">
                            <i class="ti ti-stethoscope fs-24"></i>
                        </span>
                        <div>
                            <h6 class="text-dark fw-semibold mb-1">
                                {{ optional($prescription->doctor)->display_name ?? '—' }}
                            </h6>
                            <p class="mb-1 text-muted">
                                {{ optional($prescription->doctor)->specialization ?? 'General Practice' }}
                            </p>
                            @if(optional($prescription->doctor)->license_number)
                                <p class="mb-0 fs-12 text-muted">
                                    Lic: {{ $prescription->doctor->license_number }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="text-lg-end">
                        <p class="text-dark mb-1">
                            Prescribed on:
                            <span class="text-body">
                                {{ $prescription->created_at ? $prescription->created_at->format('d M Y') : (\Carbon\Carbon::parse(optional($prescription->appointment)->appointment_date)->format('d M Y') ?? '—') }}
                            </span>
                        </p>
                        <p class="text-dark mb-1">
                            Consultation:
                            <span class="text-body">{{ $prescription->appointment_id ? 'Video / Online' : 'Clinical Workspace' }}</span>
                        </p>
                        @if($prescription->appointment_id)
                            <p class="text-dark mb-0">
                                Appointment:
                                <span class="text-body">
                                    #APT{{ str_pad($prescription->appointment_id, 4, '0', STR_PAD_LEFT) }}
                                </span>
                            </p>
                        @endif
                    </div>
                </div>

                {{-- ── Destination Pharmacy (If assigned) ──────────────────── --}}
                @if($prescription->pharmacy_name_snapshot || $prescription->pharmacy_id)
                    <div class="mb-3">
                        <h6 class="mb-2 fs-14 fw-medium">Destination Pharmacy</h6>
                        <div class="px-3 py-2 bg-light rounded d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <h6 class="m-0 fw-semibold fs-15 text-dark">
                                    {{ $prescription->destination_pharmacy_name }}
                                </h6>
                                @if($prescription->pharmacy_address_snapshot)
                                    <small class="text-muted">
                                        {{ $prescription->pharmacy_address_snapshot }}
                                        @if($prescription->pharmacy_city_snapshot)
                                            , {{ $prescription->pharmacy_city_snapshot }}, {{ $prescription->pharmacy_state_snapshot }} {{ $prescription->pharmacy_postal_code_snapshot }}
                                        @endif
                                    </small>
                                @endif
                            </div>
                            @if($prescription->pharmacy_phone_snapshot)
                                <span class="text-muted fs-13"><i class="ti ti-phone me-1"></i>{{ $prescription->pharmacy_phone_snapshot }}</span>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- ── Patient Details ──────────────────────────────────────── --}}
                <div class="mb-3">
                    <h6 class="mb-2 fs-14 fw-medium">Patient Details</h6>
                    <div class="px-3 py-2 bg-light rounded d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h6 class="m-0 fw-semibold fs-16">
                            {{ optional(optional($prescription->patient)->user)->name ?? '—' }}
                        </h6>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            @if(optional($prescription->patient)->age)
                                <p class="mb-0 text-dark">
                                    {{ $prescription->patient->age }}Y
                                    @if(optional($prescription->patient)->gender)
                                        / {{ ucfirst($prescription->patient->gender) }}
                                    @endif
                                </p>
                            @endif
                            @if(optional($prescription->patient)->blood_group)
                                <p class="mb-0 text-dark">
                                    <span class="text-muted">Blood:</span>
                                    {{ $prescription->patient->blood_group }}
                                </p>
                            @endif
                            <p class="mb-0 text-dark">
                                Patient ID
                                <span class="text-muted">
                                    PT{{ str_pad($prescription->patient_id, 4, '0', STR_PAD_LEFT) }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                {{-- ── Diagnosis ────────────────────────────────────────────── --}}
                @if($prescription->diagnosis)
                    <div class="mb-3 pb-3 border-bottom">
                        <h6 class="mb-2 fs-14 fw-semibold">Diagnosis</h6>
                        <p class="mb-0">{{ $prescription->diagnosis }}</p>
                    </div>
                @endif

                {{-- ── Medicines Table ──────────────────────────────────────── --}}
                <div class="mb-4">
                    <h6 class="mb-3 fs-16 fw-bold text-center">Prescribed Medications</h6>
                    @php $medicines = is_array($prescription->medicines) ? $prescription->medicines : []; @endphp
                    @if(count($medicines) > 0)
                        <div class="table-responsive border bg-white rounded">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Medication & Form</th>
                                        <th>Instructions / Sig</th>
                                        <th>Frequency & Timing</th>
                                        <th>Dispense / Refills</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($medicines as $idx => $med)
                                        @php
                                            $name = $med['name'] ?? '—';
                                            $strength = $med['strength'] ?? ($med['dosage'] ?? '');
                                            $form = $med['dosage_form'] ?? '';
                                            $route = $med['route'] ?? '';
                                            $freq = $med['frequency'] ?? '';
                                            $timing = is_array($med['timing'] ?? null) ? implode(', ', $med['timing']) : ($med['timing'] ?? '');
                                            $intake = $med['intake'] ?? ($med['instructions'] ?? '');
                                            $duration = $med['duration'] ?? '';
                                            $quantity = $med['quantity'] ?? '';
                                            $unit = $med['unit'] ?? '';
                                            $refills = $med['refills'] ?? 0;
                                            $directions = $med['directions'] ?? ($med['notes'] ?? '');
                                            $substitutions = isset($med['substitutions_allowed']) ? $med['substitutions_allowed'] : true;
                                        @endphp
                                        <tr>
                                            <td class="text-muted">{{ str_pad($idx + 1, 2, '0', STR_PAD_LEFT) }}</td>
                                            <td>
                                                <div class="fw-bold text-dark fs-14">{{ $name }}</div>
                                                @if($strength || $form)
                                                    <div class="text-muted fs-12">
                                                        {{ implode(' • ', array_filter([$strength, $form, $route])) }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                @if($directions)
                                                    <div class="text-dark fs-13">{{ $directions }}</div>
                                                @endif
                                                @if($intake)
                                                    <div class="text-muted fs-12"><i class="ti ti-info-circle me-1"></i>{{ $intake }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                @if($freq)
                                                    <div class="fw-semibold text-primary fs-13">{{ $freq }}</div>
                                                @endif
                                                @if($timing)
                                                    <div class="text-muted fs-12">Timing: {{ $timing }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                @if($quantity || $duration)
                                                    <div class="fs-13 text-dark fw-medium">
                                                        {{ $quantity ? $quantity . ' ' . ($unit ?: 'units') : '' }}
                                                        {{ $duration ? '(' . $duration . ')' : '' }}
                                                    </div>
                                                @else
                                                    <span class="text-muted fs-13">—</span>
                                                @endif
                                                <div class="text-muted fs-11">
                                                    Refills: {{ $refills }} &bull; {{ $substitutions ? 'Substitutions OK' : 'Dispense as Written' }}
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-3 text-muted border rounded">
                            <i class="ti ti-pill fs-24 mb-1 d-block"></i>
                            No medicines listed.
                        </div>
                    @endif
                </div>

                {{-- ── Notes / Advice ──────────────────────────────────────── --}}
                @if($prescription->notes)
                    <div class="pb-3 mb-3 border-bottom">
                        <h6 class="mb-1 fs-16 fw-semibold">Advice / Notes</h6>
                        <p class="mb-0">{{ $prescription->notes }}</p>
                    </div>
                @endif

                {{-- ── Footer: Doctor Signature + Dates ────────────────────── --}}
                <div class="pb-3 mb-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <p class="mb-1 text-muted fs-13">
                            Issued: {{ $prescription->created_at->format('d M Y, h:i A') }}
                        </p>
                        <span class="badge badge-soft-success d-inline-flex align-items-center">
                            <i class="ti ti-point-filled me-1"></i>Issued
                        </span>
                    </div>
                    <div class="text-end">
                        <h6 class="fs-14 fw-semibold mb-0">
                            {{ optional($prescription->doctor)->display_name ?? '—' }}
                        </h6>
                        <p class="fs-13 fw-normal text-muted mb-0">
                            {{ optional($prescription->doctor)->specialization ?? '' }}
                        </p>
                    </div>
                </div>

                {{-- ── Print/Download Buttons ───────────────────────────────── --}}
                <div class="text-center d-flex align-items-center justify-content-center gap-2 no-print">
                    <button onclick="window.print()" class="btn btn-dark d-inline-flex align-items-center gap-1">
                        <i class="ti ti-printer"></i> Print
                    </button>
                    <a href="{{ route('prescriptions.index') }}" class="btn btn-light border d-inline-flex align-items-center gap-1">
                        <i class="ti ti-arrow-left"></i> Back to List
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

@endsection

