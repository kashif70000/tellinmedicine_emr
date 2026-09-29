@extends('layouts.layout')

@section('title', 'Prescriptions')

@section('content')

@php $role = auth()->user()->role; @endphp

{{-- ── Page Header ──────────────────────────────────────────────────────── --}}
<div class="d-flex align-items-sm-center flex-sm-row flex-column gap-2 pb-3 mb-3 border-1 border-bottom">
    <div class="flex-grow-1">
        <h4 class="fw-bold mb-0">
            <i class="ti ti-prescription me-2 text-primary"></i>
            @if($role === 'admin') All Prescriptions
            @elseif($role === 'doctor') My Prescriptions
            @else My Prescriptions
            @endif
        </h4>
        <p class="text-muted mb-0 fs-13">
            @if($role === 'admin') View all patient prescriptions across the system.
            @elseif($role === 'doctor') Prescriptions you have written during consultations.
            @else Prescriptions issued to you by your doctors.
            @endif
        </p>
    </div>
</div>

{{-- ── Table ────────────────────────────────────────────────────────────── --}}
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-nowrap mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Prescription ID</th>
                        @if($role !== 'doctor')
                            <th>Doctor</th>
                        @endif
                        @if($role !== 'patient')
                            <th>Patient</th>
                        @endif
                        <th>Diagnosis</th>
                        <th>Prescribed On</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($prescriptions as $rx)
                    <tr>
                        <td>
                            <a href="{{ route('prescriptions.show', $rx->id) }}" class="fw-semibold text-primary">
                                #PRE{{ str_pad($rx->id, 4, '0', STR_PAD_LEFT) }}
                            </a>
                        </td>

                        @if($role !== 'doctor')
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="avatar avatar-sm rounded-circle bg-primary-subtle text-primary flex-shrink-0 me-2 d-inline-flex align-items-center justify-content-center">
                                        <i class="ti ti-stethoscope fs-14"></i>
                                    </span>
                                    <div>
                                        <span class="fw-semibold text-dark d-block">
                                            {{ optional($rx->doctor)->display_name ?? '—' }}
                                        </span>
                                        <span class="fs-12 text-muted">
                                            {{ optional($rx->doctor)->specialization ?? '' }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                        @endif

                        @if($role !== 'patient')
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="avatar avatar-sm rounded-circle bg-success-subtle text-success flex-shrink-0 me-2 d-inline-flex align-items-center justify-content-center">
                                        <i class="ti ti-user-heart fs-14"></i>
                                    </span>
                                    <span class="fw-semibold text-dark">
                                        {{ optional(optional($rx->patient)->user)->name ?? '—' }}
                                    </span>
                                </div>
                            </td>
                        @endif

                        <td>
                            @if(!empty($rx->diagnosis))
                                <span class="text-truncate d-inline-block" style="max-width:180px;" title="{{ $rx->diagnosis }}">
                                    {{ $rx->diagnosis }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>

                        <td>
                            {{ $rx->created_at->format('d M Y') }}
                        </td>

                        <td>
                            <span class="badge badge-soft-success d-inline-flex align-items-center border border-success">
                                <i class="ti ti-point-filled me-1"></i>Issued
                            </span>
                        </td>

                        <td class="text-end">
                            <a href="{{ route('prescriptions.show', $rx->id) }}"
                               class="btn btn-sm btn-light border d-inline-flex align-items-center gap-1">
                                <i class="ti ti-eye"></i> View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <span class="avatar avatar-xl rounded-circle bg-light mb-3 d-inline-flex align-items-center justify-content-center">
                                <i class="ti ti-prescription fs-28 text-muted"></i>
                            </span>
                            <p class="text-muted mb-0">No prescriptions found.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($prescriptions->hasPages())
        <div class="card-footer border-top d-flex justify-content-end">
            {{ $prescriptions->links() }}
        </div>
    @endif
</div>
@endsection
