@extends('layouts.layout')

@section('title', 'Lab Order Details')

@section('styles')
<style>
    @media print {
        .no-print { display: none !important; }
        .card { box-shadow: none !important; border: none !important; }
    }
</style>
@endsection

@section('content')

@php
    $patientObj = $labOrder->patient ?? ($patient ?? null);
    $doctorObj = $labOrder->doctor ?? ($doctor ?? null);
@endphp

{{-- ── Back + Actions Bar ───────────────────────────────────────────────── --}}
<div class="d-flex align-items-sm-center flex-sm-row flex-column mb-4 gap-2 no-print">
    <div class="flex-grow-1">
        @if($patientObj)
            <a href="{{ route('patients.show', $patientObj->getRouteKey()) }}" class="btn btn-light border d-inline-flex align-items-center gap-1">
                <i class="ti ti-chevron-left"></i> Back to Patient Profile
            </a>
        @else
            <a href="{{ route('patients.index') }}" class="btn btn-light border d-inline-flex align-items-center gap-1">
                <i class="ti ti-chevron-left"></i> Patients
            </a>
        @endif
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-dark d-inline-flex align-items-center gap-1">
            <i class="ti ti-printer"></i> Print
        </button>
    </div>
</div>

{{-- ── Lab Order Card ──────────────────────────────────────────────────── --}}
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card shadow-sm" id="laborder-print">
            <div class="card-body">

                {{-- ── Header: Logo + Lab Order ID ─────────────────────────── --}}
                <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                    <div class="invoice-logo">
                        <img src="{{ \App\Models\Setting::getLogo('normal') }}" class="logo-white" alt="{{ \App\Models\Setting::getSiteName() }}" style="height:40px;">
                        <img src="{{ \App\Models\Setting::getLogo('dark') }}" class="logo-dark" alt="{{ \App\Models\Setting::getSiteName() }}" style="height:40px;">
                    </div>
                    <span class="badge bg-info-subtle text-info-emphasis fs-13 fw-medium border border-info py-1 px-2">
                        {{ $labOrder->reference_number ?: ('#LAB' . str_pad($labOrder->id, 4, '0', STR_PAD_LEFT)) }}
                    </span>
                </div>

                {{-- ── Doctor Info + Order Meta ────────────────────────────── --}}
                <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3 flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-xxl rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center flex-shrink-0">
                            <i class="ti ti-stethoscope fs-24"></i>
                        </span>
                        <div>
                            <h6 class="text-dark fw-semibold mb-1">
                                {{ optional($doctorObj)->display_name ?? '—' }}
                            </h6>
                            <p class="mb-1 text-muted">
                                {{ optional($doctorObj)->specialization ?? 'General Practice' }}
                            </p>
                            @if(optional($doctorObj)->license_number)
                                <p class="mb-0 fs-12 text-muted">
                                    Lic: {{ $doctorObj->license_number }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="text-lg-end">
                        <p class="text-dark mb-1">
                            Ordered on:
                            <span class="text-body">
                                {{ $labOrder->created_at ? $labOrder->created_at->format('d M Y') : '—' }}
                            </span>
                        </p>
                        <p class="text-dark mb-1">
                            Requisition Type:
                            <span class="text-body">Clinical Lab Diagnostics</span>
                        </p>
                        <p class="text-dark mb-0">
                            Status:
                            <span class="badge bg-info-subtle text-info fs-12">{{ ucfirst($labOrder->status ?? 'ordered') }}</span>
                        </p>
                    </div>
                </div>

                {{-- ── Performing Laboratory (If assigned) ─────────────────── --}}
                @if($labOrder->destination_laboratory_name || $labOrder->laboratory_id)
                    <div class="mb-3">
                        <h6 class="mb-2 fs-14 fw-medium">Performing Laboratory</h6>
                        <div class="px-3 py-2 bg-light rounded d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <h6 class="m-0 fw-semibold fs-15 text-dark">
                                    {{ $labOrder->destination_laboratory_name }}
                                </h6>
                                @if($labOrder->laboratory_address_snapshot)
                                    <small class="text-muted">
                                        {{ $labOrder->laboratory_address_snapshot }}
                                        @if($labOrder->laboratory_city_snapshot)
                                            , {{ $labOrder->laboratory_city_snapshot }}, {{ $labOrder->laboratory_state_snapshot }} {{ $labOrder->laboratory_postal_code_snapshot }}
                                        @endif
                                    </small>
                                @endif
                            </div>
                            @if($labOrder->laboratory_phone_snapshot)
                                <span class="text-muted fs-13"><i class="ti ti-phone me-1"></i>{{ $labOrder->laboratory_phone_snapshot }}</span>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- ── Patient Details ──────────────────────────────────────── --}}
                <div class="mb-3">
                    <h6 class="mb-2 fs-14 fw-medium">Patient Details</h6>
                    <div class="px-3 py-2 bg-light rounded d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h6 class="m-0 fw-semibold fs-16">
                            {{ optional(optional($patientObj)->user)->name ?? '—' }}
                        </h6>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            @if(optional($patientObj)->age)
                                <p class="mb-0 text-dark">
                                    {{ $patientObj->age }}Y
                                    @if(optional($patientObj)->gender)
                                        / {{ ucfirst($patientObj->gender) }}
                                    @endif
                                </p>
                            @endif
                            @if(optional($patientObj)->blood_group)
                                <p class="mb-0 text-dark">
                                    <span class="text-muted">Blood:</span>
                                    {{ $patientObj->blood_group }}
                                </p>
                            @endif
                            <p class="mb-0 text-dark">
                                Patient ID
                                <span class="text-muted">
                                    PT{{ str_pad(optional($patientObj)->id ?? $labOrder->patient_id, 4, '0', STR_PAD_LEFT) }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                {{-- ── Diagnostic Tests Table ───────────────────────────────── --}}
                <div class="mb-4">
                    <h6 class="mb-3 fs-16 fw-bold text-center">Laboratory Order Details</h6>
                    @php $items = $labOrder->items ?? collect(); @endphp
                    @if($items->count() > 0)
                        <div class="table-responsive border bg-white rounded">
                            <table class="table table-nowrap mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>SNO</th>
                                        <th>Test Name</th>
                                        <th>Category</th>
                                        <th>LOINC Code</th>
                                        <th>Specimen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $idx => $item)
                                        <tr>
                                            <td>{{ str_pad($idx + 1, 2, '0', STR_PAD_LEFT) }}</td>
                                            <td class="fw-semibold">
                                                {{ $item->test_name_snapshot }}
                                                @if($item->short_name_snapshot)
                                                    <span class="text-muted fs-12 fw-normal d-block">({{ $item->short_name_snapshot }})</span>
                                                @endif
                                            </td>
                                            <td><span class="badge bg-light text-dark border">{{ $item->category_snapshot ?? 'General' }}</span></td>
                                            <td class="font-monospace fs-12 text-secondary">{{ $item->loinc_code_snapshot ?? '—' }}</td>
                                            <td>{{ $item->specimen_snapshot ?? 'Routine' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-3 text-muted border rounded">
                            <i class="ti ti-flask fs-24 mb-1 d-block"></i>
                            No tests listed.
                        </div>
                    @endif
                </div>

                {{-- ── Clinical Notes / Instructions ────────────────────────── --}}
                @if($labOrder->clinical_notes)
                    <div class="pb-3 mb-3 border-bottom">
                        <h6 class="mb-1 fs-16 fw-semibold">Clinical Notes / Laboratory Instructions</h6>
                        <p class="mb-0">{{ $labOrder->clinical_notes }}</p>
                    </div>
                @endif

                {{-- ── Footer: Doctor Signature + Dates ────────────────────── --}}
                <div class="pb-3 mb-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <p class="mb-1 text-muted fs-13">
                            Ordered: {{ $labOrder->created_at->format('d M Y, h:i A') }}
                        </p>
                        <span class="badge badge-soft-success d-inline-flex align-items-center">
                            <i class="ti ti-point-filled me-1"></i>Ordered
                        </span>
                    </div>
                    <div class="text-end">
                        <h6 class="fs-14 fw-semibold mb-0">
                            {{ optional($doctorObj)->display_name ?? '—' }}
                        </h6>
                        <p class="fs-13 fw-normal text-muted mb-0">
                            {{ optional($doctorObj)->specialization ?? '' }}
                        </p>
                    </div>
                </div>

                {{-- ── Print / Back Buttons ─────────────────────────────────── --}}
                <div class="text-center d-flex align-items-center justify-content-center gap-2 no-print">
                    <button onclick="window.print()" class="btn btn-dark d-inline-flex align-items-center gap-1">
                        <i class="ti ti-printer"></i> Print
                    </button>
                    @if($patientObj)
                        <a href="{{ route('patients.show', $patientObj->getRouteKey()) }}" class="btn btn-light border d-inline-flex align-items-center gap-1">
                            <i class="ti ti-arrow-left"></i> Back to Patient
                        </a>
                    @endif
                </div>

            </div>
        </div>
    </div>
</div>

@endsection
