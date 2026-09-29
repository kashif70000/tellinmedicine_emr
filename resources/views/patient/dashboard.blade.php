@extends('layouts.layout')

@section('title', 'Patient Dashboard')

@section('content')

{{-- Page Header --}}
<div class="d-flex align-items-sm-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0">Patient Dashboard</h4>
        <p class="text-muted mb-0">Welcome, {{ $user->name }}</p>
    </div>
    <div class="d-flex align-items-center flex-wrap gap-2">
        <a href="{{ route('appointments.create') }}" class="btn btn-primary d-inline-flex align-items-center">
            <i class="ti ti-plus me-1"></i>New Appointment
        </a>
    </div>
</div>

{{-- ===== TOP 4 STAT CARDS ===== --}}
<div class="row">
    <div class="col-xl-3 col-md-6 d-flex">
        <div class="card flex-fill w-100 shadow-sm">
           <div class="card-body">
              <div class="d-flex align-items-center mb-3">
                <span class="avatar bg-primary rounded-circle fs-20 d-inline-flex flex-shrink-0">
                    <i class="ti ti-calendar-heart"></i>
                </span>
                <div class="ms-2">
                    <p class="mb-1 text-truncate">Total Appointments</p>
                    <h3 class="fw-bold mb-0">{{ $stats['total_appointments'] ?? 0 }}</h3>
                </div>
              </div>
              <p class="fs-13 mb-0 text-muted">All time bookings</p>
           </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 d-flex">
        <div class="card flex-fill w-100 shadow-sm">
           <div class="card-body">
              <div class="d-flex align-items-center mb-3">
                <span class="avatar bg-success rounded-circle fs-20 d-inline-flex flex-shrink-0">
                    <i class="ti ti-video"></i>
                </span>
                <div class="ms-2">
                    <p class="mb-1 text-truncate">Online Consultations</p>
                    <h3 class="fw-bold mb-0">{{ $stats['online_consultations'] ?? 0 }}</h3>
                </div>
              </div>
              <p class="fs-13 mb-0 text-muted">Video call sessions</p>
           </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 d-flex">
        <div class="card flex-fill w-100 shadow-sm">
           <div class="card-body">
              <div class="d-flex align-items-center mb-3">
                <span class="avatar bg-danger rounded-circle fs-20 d-inline-flex flex-shrink-0">
                    <i class="ti ti-heart-rate-monitor"></i>
                </span>
                <div class="ms-2">
                    <p class="mb-1 text-truncate">Blood Pressure</p>
                    <h3 class="fw-bold mb-0">{{ $stats['blood_pressure'] ?? '120/80' }}</h3>
                </div>
              </div>
              <p class="fs-13 mb-0 text-muted">mmHg (last recorded)</p>
           </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 d-flex">
        <div class="card flex-fill w-100 shadow-sm">
           <div class="card-body">
              <div class="d-flex align-items-center mb-3">
                <span class="avatar bg-warning rounded-circle fs-20 d-inline-flex flex-shrink-0">
                    <i class="ti ti-heart"></i>
                </span>
                <div class="ms-2">
                    <p class="mb-1 text-truncate">Heart Rate</p>
                    <h3 class="fw-bold mb-0">{{ $stats['heart_rate'] ?? 72 }}</h3>
                </div>
              </div>
              <p class="fs-13 mb-0 text-muted">bpm (last recorded)</p>
           </div>
        </div>
    </div>
</div>

{{-- ===== UPCOMING CALLS BANNER (only if there's a live/upcoming call) ===== --}}
@if(isset($stats['active_call_appointment']))
    @php $activeAppt = $stats['active_call_appointment']; @endphp
    <div class="alert alert-success d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3" role="alert">
        <div class="d-flex align-items-center gap-2">
            <i class="ti ti-video fs-20"></i>
            <div>
                <strong>Active Call!</strong>
                {{ $activeAppt->doctor->display_name }} is waiting —
                {{ $activeAppt->appointment_date->format('d M') }}
                {{ $activeAppt->formatted_time }}
            </div>
        </div>
        <a href="{{ route('appointments.call', $activeAppt->id) }}" class="btn btn-success btn-sm">
            <i class="ti ti-video me-1"></i>Join Now
        </a>
    </div>
@endif

{{-- ===== MY DOCTORS + PRESCRIPTIONS + RECENT ACTIVITY ===== --}}
<div class="row">
    {{-- My Doctors --}}
    <div class="col-xl-4 col-lg-6 d-flex">
        <div class="card shadow-sm flex-fill w-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0">My Doctors</h5>
            </div>
            <div class="card-body">
                @forelse(($stats['my_doctors'] ?? collect()) as $doctor)
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center">
                            <span class="avatar me-2 flex-shrink-0 bg-light rounded-circle d-inline-flex align-items-center justify-content-center">
                                <i class="ti ti-stethoscope text-primary fs-18"></i>
                            </span>
                            <div>
                                <h6 class="fs-14 mb-1 text-truncate fw-semibold">{{ $doctor->display_name ?? 'Doctor' }}</h6>
                                <p class="mb-0 fs-13 text-muted text-truncate">{{ $doctor->specialization ?? 'General' }}</p>
                            </div>
                        </div>
                        <span class="badge fw-medium badge-soft-danger border border-danger flex-shrink-0">
                            {{ $doctor->appointments_count ?? 0 }} Bookings
                        </span>
                    </div>
                @empty
                    <p class="text-center text-muted py-3">No doctors found</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Prescriptions --}}
    <div class="col-xl-4 col-lg-6 d-flex">
        <div class="card shadow-sm flex-fill w-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0">My Prescriptions</h5>
            </div>
            <div class="card-body">
                @forelse(($stats['prescriptions'] ?? collect()) as $rx)
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center flex-shrink-0">
                            <span class="avatar me-2 flex-shrink-0 bg-light rounded-circle d-inline-flex align-items-center justify-content-center">
                                <i class="ti ti-file-description text-body fs-18"></i>
                            </span>
                            <div>
                                <h6 class="fs-14 mb-1 text-truncate fw-semibold">
                                    {{ $rx->doctor->display_name ?? 'Doctor' }}
                                </h6>
                                <p class="mb-0 fs-12 text-muted">{{ $rx->created_at->format('d M Y') }}</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <a href="{{ route('prescriptions.show', $rx->id) }}"
                               class="btn btn-outline-white d-inline-flex align-items-center shadow-sm p-1">
                                <i class="ti ti-eye"></i>
                            </a>
                        </div>
                    </div>
                @empty
                    <p class="text-center text-muted py-3">No prescriptions found</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Recent Appointments / Activity --}}
    <div class="col-xl-4 d-flex">
        <div class="card shadow-sm flex-fill w-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0">Upcoming Appointments</h5>
                <a href="{{ route('appointments.index') }}" class="btn btn-sm btn-light">All</a>
            </div>
            <div class="card-body p-0">
                @forelse(($stats['upcoming_appointments'] ?? collect()) as $appt)
                    <div class="p-3 border-bottom">
                        <div class="d-flex align-items-center mb-1">
                            <span class="avatar avatar-sm me-2 bg-light rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0">
                                <i class="ti ti-stethoscope text-primary fs-14"></i>
                            </span>
                            <div>
                                <h6 class="fs-14 fw-semibold mb-0">{{ $appt->doctor->display_name ?? 'Doctor' }}</h6>
                                <p class="mb-0 fs-12 text-muted">{{ $appt->doctor->specialization ?? '' }}</p>
                            </div>
                        </div>
                        <p class="fs-12 text-muted mb-2">
                            <i class="ti ti-calendar me-1"></i>{{ $appt->appointment_date->format('d M Y') }}
                            <i class="ti ti-clock ms-2 me-1"></i>{{ $appt->formatted_time }}
                        </p>
                        @php
                            $patLive = $appt->status === 'approved' && $appt->call_started_at && \Carbon\Carbon::now()->lt($appt->call_started_at->addSeconds(1800));
                        @endphp
                        @if($patLive)
                            <a href="{{ route('appointments.call', $appt->id) }}" class="btn btn-success btn-sm w-100">
                                <i class="ti ti-video me-1"></i>Join Video Call
                            </a>
                        @elseif($appt->payment_status !== 'paid')
                            <a href="{{ route('appointments.show', $appt) }}" class="btn btn-warning btn-sm w-100">
                                <i class="ti ti-credit-card me-1"></i>Pay Now
                            </a>
                        @else
                            <a href="{{ route('appointments.show', $appt) }}" class="btn btn-outline-white btn-sm w-100">
                                <i class="ti ti-eye me-1"></i>View Details
                            </a>
                        @endif
                    </div>
                @empty
                    <div class="p-4 text-center text-muted">
                        <i class="ti ti-calendar-off fs-36 d-block mb-2 text-light-emphasis"></i>
                        No upcoming appointments
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ===== VITALS ===== --}}
<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="fw-bold mb-0">Vitals</h5>
    </div>
    <div class="card-body">
        <div class="row row-gap-3 row-cols-2 row-cols-sm-3 row-cols-xl-6">
            <div class="col d-flex">
                <div class="p-3 border shadow-sm flex-fill w-100 rounded-2">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-primary rounded-circle flex-shrink-0">
                            <img src="{{ asset('assets/img/icons/weight.svg') }}" alt="Weight" class="w-auto h-auto">
                        </span>
                        <div class="ms-1">
                            <p class="mb-1">Weight</p>
                            <p class="text-truncate mb-0"><span class="fs-18 fw-bold text-dark">{{ $stats['weight'] ?? '—' }}</span> Kg</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col d-flex">
                <div class="p-3 border shadow-sm flex-fill w-100 rounded-2">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-primary rounded-circle flex-shrink-0">
                            <img src="{{ asset('assets/img/icons/rotate-left.svg') }}" alt="Height" class="w-auto h-auto">
                        </span>
                        <div class="ms-1">
                            <p class="mb-1">Height</p>
                            <p class="text-truncate mb-0"><span class="fs-18 fw-bold text-dark">{{ $stats['height'] ?? '—' }}</span> Cm</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col d-flex">
                <div class="p-3 border shadow-sm flex-fill w-100 rounded-2">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-primary rounded-circle flex-shrink-0">
                            <img src="{{ asset('assets/img/icons/user-cirlce-add.svg') }}" alt="BMI" class="w-auto h-auto">
                        </span>
                        <div class="ms-1">
                            <p class="mb-1">BMI</p>
                            <p class="text-truncate mb-0"><span class="fs-18 fw-bold text-dark">{{ $stats['bmi'] ?? '—' }}</span> kg/cm</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col d-flex">
                <div class="p-3 border shadow-sm flex-fill w-100 rounded-2">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-primary rounded-circle flex-shrink-0">
                            <img src="{{ asset('assets/img/icons/driver-2.svg') }}" alt="Pulse" class="w-auto h-auto">
                        </span>
                        <div class="ms-1">
                            <p class="mb-1">Pulse</p>
                            <p class="text-truncate mb-0"><span class="fs-18 fw-bold text-dark">{{ $stats['pulse'] ?? '—' }}</span></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col d-flex">
                <div class="p-3 border shadow-sm flex-fill w-100 rounded-2">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-primary rounded-circle flex-shrink-0">
                            <img src="{{ asset('assets/img/icons/wind.svg') }}" alt="SPO2" class="w-auto h-auto">
                        </span>
                        <div class="ms-1">
                            <p class="mb-1">SPO2</p>
                            <p class="text-truncate mb-0"><span class="fs-18 fw-bold text-dark">{{ $stats['spo2'] ?? '—' }}</span>%</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col d-flex">
                <div class="p-3 border shadow-sm flex-fill w-100 rounded-2">
                    <div class="d-flex align-items-center">
                        <span class="avatar bg-primary rounded-circle flex-shrink-0">
                            <img src="{{ asset('assets/img/icons/sun.svg') }}" alt="Temp" class="w-auto h-auto">
                        </span>
                        <div class="ms-1">
                            <p class="mb-1 text-truncate">Temperature</p>
                            <p class="text-truncate mb-0"><span class="fs-18 fw-bold text-dark">{{ $stats['temperature'] ?? '—' }}</span> F</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ===== RECENT TRANSACTIONS ===== --}}
<div class="row">
    <div class="col-12 d-flex">
        <div class="card shadow-sm flex-fill w-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0">Recent Appointments</h5>
                <a href="{{ route('appointments.index') }}" class="btn btn-sm btn-light">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive table-nowrap">
                    <table class="table border align-middle">
                        <thead class="thead-light">
                            <tr>
                                <th>Doctor</th>
                                <th>Date &amp; Time</th>
                                <th>Fees</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($stats['recent_appointments'] ?? collect()) as $appt)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="avatar avatar-sm me-2 bg-light rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0">
                                                <i class="ti ti-stethoscope text-primary fs-14"></i>
                                            </span>
                                            <div>
                                                <h6 class="fs-14 mb-0 fw-semibold">{{ $appt->doctor->display_name ?? 'Doctor' }}</h6>
                                                <p class="mb-0 fs-12 text-muted">{{ $appt->doctor->specialization ?? '' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="fs-13">
                                        {{ $appt->appointment_date?->format('d M Y') }}<br>
                                        <span class="text-muted">{{ $appt->formatted_time }}</span>
                                    </td>
                                    <td class="fw-semibold text-dark">${{ number_format($appt->fee_snapshot ?? 0, 2) }}</td>
                                    <td>
                                        @if($appt->payment_status === 'paid')
                                            <span class="badge badge-soft-success border border-success fw-medium">Paid</span>
                                        @elseif($appt->payment_status === 'refunded')
                                            <span class="badge badge-soft-warning border border-warning fw-medium">Refunded</span>
                                        @else
                                            <span class="badge badge-soft-danger border border-danger fw-medium">Unpaid</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($appt->status === 'approved')
                                            <span class="badge bg-success fw-medium">Approved</span>
                                        @elseif($appt->status === 'cancelled')
                                            <span class="badge bg-danger fw-medium">Cancelled</span>
                                        @elseif($appt->status === 'completed')
                                            <span class="badge bg-info fw-medium">Completed</span>
                                        @else
                                            <span class="badge bg-warning fw-medium">Pending</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $rLive = $appt->call_started_at && \Carbon\Carbon::now()->lt($appt->call_started_at->addSeconds(1800));
                                        @endphp
                                        @if($rLive)
                                            <a href="{{ route('appointments.call', $appt->id) }}" class="btn btn-sm btn-success">
                                                <i class="ti ti-video me-1"></i>Join
                                            </a>
                                        @else
                                            <a href="{{ route('appointments.show', $appt) }}" class="btn btn-sm btn-light">
                                                <i class="ti ti-eye"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No appointments yet</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


@endsection
