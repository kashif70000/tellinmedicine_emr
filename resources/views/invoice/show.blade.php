@extends('layouts.layout')

@section('title', 'Invoice Details')

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
        <a href="{{ route('invoices.index') }}" class="btn btn-light border d-inline-flex align-items-center gap-1">
            <i class="ti ti-chevron-left"></i> Invoices
        </a>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-dark d-inline-flex align-items-center gap-1">
            <i class="ti ti-printer"></i> Print
        </button>
    </div>
</div>

{{-- ── Invoice Card ─────────────────────────────────────────────────────── --}}
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card shadow-sm" id="invoice-print">
            <div class="card-body">

                {{-- ── Header: Logo + Invoice Number ───────────────────────── --}}
                <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                    <div class="invoice-logo">
                        <img src="{{ \App\Models\Setting::getLogo('normal') }}" class="logo-white" alt="{{ \App\Models\Setting::getSiteName() }}" style="height:40px;">
                        <img src="{{ \App\Models\Setting::getLogo('dark') }}" class="logo-dark" alt="{{ \App\Models\Setting::getSiteName() }}" style="height:40px;">
                    </div>
                    <div class="text-end">
                        <span class="badge badge-soft-{{ $invoice->status === 'paid' ? 'success' : 'warning' }} fs-12 d-inline-flex align-items-center border border-{{ $invoice->status === 'paid' ? 'success' : 'warning' }}">
                            <i class="ti ti-point-filled me-1"></i>
                            {{ ucfirst($invoice->status) }}
                        </span>
                    </div>
                </div>

                {{-- ── Invoice Meta (3-column) ──────────────────────────────── --}}
                <div class="row pb-3 border-bottom mb-4">
                    <div class="col-lg-4">
                        <h5 class="mb-2 fs-16 fw-bold">Invoice Details</h5>
                        <p class="text-body mb-1">
                            Invoice Number:
                            <span class="text-dark fw-semibold">
                                {{ $invoice->invoice_number ?? '#INV' . str_pad($invoice->id, 4, '0', STR_PAD_LEFT) }}
                            </span>
                        </p>
                        <p class="text-body mb-1">
                            Issued On:
                            <span class="text-dark">
                                {{ $invoice->issued_date ? $invoice->issued_date->format('d M Y') : '—' }}
                            </span>
                        </p>
                        <p class="text-body mb-0">
                            Appointment:
                            <span class="text-dark">
                                #APT{{ str_pad($invoice->appointment_id, 4, '0', STR_PAD_LEFT) }}
                            </span>
                        </p>
                    </div>

                    <div class="col-lg-4">
                        <h5 class="mb-2 fs-16 fw-bold">Invoice From</h5>
                        <p class="text-dark fw-medium mb-1">{{ \App\Models\Setting::getSiteName() }} – Medical Platform</p>
                        <p class="text-body mb-1">
                            Dr. {{ optional(optional(optional($invoice->appointment)->doctor)->user)->name ?? '—' }}
                        </p>
                        <p class="text-muted mb-0 fs-13">
                            {{ optional(optional($invoice->appointment)->doctor)->specialization ?? '' }}
                        </p>
                    </div>

                    <div class="col-lg-4 text-lg-end">
                        <h5 class="mb-2 fs-16 fw-bold">Invoice To</h5>
                        <p class="text-dark fw-medium mb-1">
                            {{ optional(optional($invoice->patient)->user)->name ?? '—' }}
                        </p>
                        <p class="text-body mb-1">
                            {{ optional(optional($invoice->patient)->user)->email ?? '' }}
                        </p>
                        @if(optional($invoice->patient)->phone)
                            <p class="text-muted mb-0 fs-13">{{ $invoice->patient->phone }}</p>
                        @endif
                    </div>
                </div>

                {{-- ── Service Items ────────────────────────────────────────── --}}
                <div class="mb-4">
                    <h6 class="mb-3 fs-16 fw-bold">Service Items</h6>
                    <div class="table-responsive border bg-white rounded">
                        <table class="table table-nowrap mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Description</th>
                                    <th>Date</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td>
                                    <td>
                                        <span class="fw-semibold">Online Medical Consultation</span>
                                        <span class="d-block fs-12 text-muted">
                                            Dr. {{ optional(optional(optional($invoice->appointment)->doctor)->user)->name ?? '' }}
                                            @if(optional(optional($invoice->appointment)->doctor)->specialization)
                                                – {{ $invoice->appointment->doctor->specialization }}
                                            @endif
                                        </span>
                                    </td>
                                    <td>
                                        {{ optional(optional($invoice->appointment)->appointment_date)?->format('d M Y') ?? '—' }}
                                    </td>
                                    <td class="text-end fw-semibold">
                                        ${{ number_format($invoice->total_amount, 2) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- ── Amount Summary ───────────────────────────────────────── --}}
                <div class="row pb-3 mb-3 border-bottom">
                    <div class="col-lg-6">
                        @if(optional($invoice->payment)->transaction_id)
                            <div>
                                <h6 class="mb-2 fs-14 fw-semibold">Payment Reference</h6>
                                <p class="text-body mb-1">
                                    Transaction ID:
                                    <span class="text-dark fw-medium">
                                        {{ $invoice->payment->transaction_id ?? $invoice->payment->payment_intent_id ?? '—' }}
                                    </span>
                                </p>
                                <p class="text-body mb-0">
                                    Method:
                                    <span class="text-dark">{{ ucfirst($invoice->payment->method ?? 'Card') }}</span>
                                </p>
                            </div>
                        @endif
                    </div>
                    <div class="col-lg-6">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fs-14 fw-medium text-body">Subtotal</h6>
                            <h6 class="fs-14 fw-semibold text-dark">${{ number_format($invoice->total_amount, 2) }}</h6>
                        </div>
                        <div class="d-flex align-items-center justify-content-between border-top pt-2 mt-1">
                            <h6 class="fs-18 fw-bold">Total (USD)</h6>
                            <h6 class="fs-18 fw-bold text-primary">${{ number_format($invoice->total_amount, 2) }}</h6>
                        </div>
                    </div>
                </div>

                {{-- ── Footer: Terms + Issued stamp ────────────────────────── --}}
                <div class="pb-3 mb-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h6 class="mb-1 fs-14 fw-semibold">Terms & Conditions</h6>
                        <p class="mb-0 text-muted fs-13">
                            All charges are final and include applicable service fees.
                            For queries, contact support.
                        </p>
                    </div>
                    <div class="text-end">
                        <p class="mb-1 text-muted fs-12">Issued on {{ $invoice->created_at->format('d M Y, h:i A') }}</p>
                        <span class="badge badge-soft-{{ $invoice->status === 'paid' ? 'success' : 'warning' }} fs-12 d-inline-flex align-items-center">
                            <i class="ti ti-point-filled me-1"></i>{{ ucfirst($invoice->status) }}
                        </span>
                    </div>
                </div>

                {{-- ── Print/Back Buttons ───────────────────────────────────── --}}
                <div class="text-center d-flex align-items-center justify-content-center gap-2 no-print">
                    <button onclick="window.print()" class="btn btn-dark d-inline-flex align-items-center gap-1">
                        <i class="ti ti-printer"></i> Print
                    </button>
                    <a href="{{ route('invoices.index') }}" class="btn btn-light border d-inline-flex align-items-center gap-1">
                        <i class="ti ti-arrow-left"></i> Back to List
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

@endsection

