@extends('layouts.layout')

@section('title', 'Invoices')

@section('content')

@php $role = auth()->user()->role; @endphp

{{-- ── Page Header ──────────────────────────────────────────────────────── --}}
<div class="d-flex align-items-sm-center flex-sm-row flex-column gap-2 pb-3 mb-3 border-1 border-bottom">
    <div class="flex-grow-1">
        <h4 class="fw-bold mb-0">
            <i class="ti ti-file-invoice me-2 text-primary"></i>
            @if($role === 'admin') All Invoices
            @elseif($role === 'doctor') Patient Invoices
            @else My Invoices
            @endif
            <span class="badge badge-soft-primary border border-primary fw-medium ms-2 fs-12 pt-1 px-2">
                Total: {{ $invoices->total() }}
            </span>
        </h4>
        <p class="text-muted mb-0 fs-13">
            @if($role === 'admin') All payment invoices across the system.
            @elseif($role === 'doctor') Invoices for your patients' consultations.
            @else Invoices issued for your consultations.
            @endif
        </p>
    </div>
</div>

{{-- ── Table ────────────────────────────────────────────────────────────── --}}
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive table-nowrap">
            <table class="table border mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice ID</th>
                        @if($role !== 'patient')
                            <th>Patient</th>
                        @endif
                        @if($role !== 'doctor')
                            <th>Doctor</th>
                        @endif
                        <th>Appointment</th>
                        <th>Issued Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($invoices as $invoice)
                    <tr>
                        <td>
                            <a href="{{ route('invoices.show', $invoice->id) }}" class="fw-semibold text-primary">
                                {{ $invoice->invoice_number ?? '#INV' . str_pad($invoice->id, 4, '0', STR_PAD_LEFT) }}
                            </a>
                        </td>

                        @if($role !== 'patient')
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="avatar avatar-sm rounded-circle bg-success-subtle text-success flex-shrink-0 me-2 d-inline-flex align-items-center justify-content-center">
                                        <i class="ti ti-user-heart fs-14"></i>
                                    </span>
                                    <span class="fw-semibold text-dark">
                                        {{ optional(optional($invoice->patient)->user)->name ?? '—' }}
                                    </span>
                                </div>
                            </td>
                        @endif

                        @if($role !== 'doctor')
                            <td>
                                <span class="fw-semibold text-dark">
                                    Dr. {{ optional(optional(optional($invoice->appointment)->doctor)->user)->name ?? '—' }}
                                </span>
                            </td>
                        @endif

                        <td>
                            @if($invoice->appointment_id)
                                <a href="{{ route('appointments.show', $invoice->appointment_id) }}"
                                   class="text-primary">
                                    #APT{{ str_pad($invoice->appointment_id, 4, '0', STR_PAD_LEFT) }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>

                        <td>
                            {{ $invoice->issued_date ? $invoice->issued_date->format('d M Y') : '—' }}
                        </td>

                        <td>
                            <strong>${{ number_format($invoice->total_amount, 2) }}</strong>
                        </td>

                        <td>
                            @if($invoice->status === 'paid')
                                <span class="badge badge-soft-success d-inline-flex align-items-center">
                                    <i class="ti ti-point-filled me-1"></i>Paid
                                </span>
                            @else
                                <span class="badge badge-soft-warning d-inline-flex align-items-center">
                                    <i class="ti ti-point-filled me-1"></i>Pending
                                </span>
                            @endif
                        </td>

                        <td class="text-end">
                            <a href="{{ route('invoices.show', $invoice->id) }}"
                               class="btn btn-sm btn-light border d-inline-flex align-items-center gap-1">
                                <i class="ti ti-eye"></i> View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <span class="avatar avatar-xl rounded-circle bg-light mb-3 d-inline-flex align-items-center justify-content-center">
                                <i class="ti ti-file-invoice fs-28 text-muted"></i>
                            </span>
                            <p class="text-muted mb-0">No invoices found.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($invoices->hasPages())
        <div class="card-footer border-top d-flex justify-content-end">
            {{ $invoices->links() }}
        </div>
    @endif
</div>

@endsection

