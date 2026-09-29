@extends('layouts.layout')

@section('title', 'Admin Dashboard')

@php
    $appointmentStatusChart = $stats['appointment_status_chart'] ?? [
        'completedCount' => 0,
        'pendingCount' => 0,
        'cancelledCount' => 0,
        'ongoingCount' => 0,
        'totalAppointments' => 0,
    ];

    $appointmentStatusChartJs = [
        'labels' => ['Completed', 'Pending', 'Cancelled', 'Ongoing'],
        'values' => [
            (int) ($appointmentStatusChart['completedCount'] ?? 0),
            (int) ($appointmentStatusChart['pendingCount'] ?? 0),
            (int) ($appointmentStatusChart['cancelledCount'] ?? 0),
            (int) ($appointmentStatusChart['ongoingCount'] ?? 0),
        ],
        'totalAppointments' => (int) ($appointmentStatusChart['totalAppointments'] ?? 0),
    ];

    $appointmentOverviewItems = [
        [
            'label' => 'Completed Appointments',
            'count' => (int) ($stats['completed_appointments'] ?? 0),
            'total' => (int) ($stats['total_appointments'] ?? 0),
            'color' => 'success',
        ],
        [
            'label' => 'Pending Appointments',
            'count' => (int) ($stats['pending_appointments'] ?? 0),
            'total' => (int) ($stats['total_appointments'] ?? 0),
            'color' => 'warning',
        ],
        [
            'label' => 'Cancelled Appointments',
            'count' => (int) ($stats['cancelled_appointments'] ?? 0),
            'total' => (int) ($stats['total_appointments'] ?? 0),
            'color' => 'danger',
        ],
        [
            'label' => 'Active Appointments',
            'count' => (int) ($stats['ongoing_appointments'] ?? 0),
            'total' => (int) ($stats['total_appointments'] ?? 0),
            'color' => 'primary',
        ],
        [
            'label' => "Today's Appointments",
            'count' => (int) ($stats['today_appointments'] ?? 0),
            'total' => (int) ($stats['total_appointments'] ?? 0),
            'color' => 'info',
        ],
        [
            'label' => 'Outstanding Invoices',
            'count' => (int) ($stats['unpaid_invoices_count'] ?? 0),
            'total' => (int) ($stats['total_invoices'] ?? 0),
            'color' => 'secondary',
        ],
        [
            'label' => 'Paid Payments',
            'count' => (int) ($stats['paid_payments_count'] ?? 0),
            'total' => (int) ($stats['total_payments_count'] ?? 0),
            'color' => 'success',
        ],
        [
            'label' => 'Prescriptions Issued',
            'count' => (int) ($stats['total_prescriptions'] ?? 0),
            'total' => max((int) ($stats['total_appointments'] ?? 0), 1),
            'color' => 'dark',
        ],
    ];
@endphp

@push('styles')
<style>
    .appointment-status-card {
        border: 1px solid var(--bs-border-color, #e9ecef);
        background: var(--bs-body-bg, #fff);
    }

    .appointment-status-card .card-header {
        border-bottom: 0;
        background: transparent;
        padding-bottom: 0;
    }

    .appointment-status-subtitle {
        color: var(--bs-secondary-color, #6c757d);
        font-size: 0.875rem;
    }

    /* Stacked layout: chart on top, metrics grid below */
    .appointment-status-grid {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    .appointment-status-chart-shell {
        position: relative;
        padding: 1rem;
        border-radius: 1rem;
        border: 1px solid var(--bs-border-color, #e9ecef);
        background: linear-gradient(180deg, rgba(13, 110, 253, 0.07), rgba(13, 110, 253, 0.02));
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.28);
    }

    .appointment-status-chart-canvas {
        width: 100%;
        height: 220px !important;
    }

    /* 2x2 grid of metric cards */
    .appointment-status-metrics {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }

    .appointment-status-metric {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.85rem 1rem;
        border-radius: 0.875rem;
        border: 1px solid var(--bs-border-color, #e9ecef);
        background: var(--bs-light, #f8f9fa);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        min-width: 0;
    }

    .appointment-status-metric:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.6rem 1rem rgba(15, 23, 42, 0.08);
    }

    :root[data-bs-theme="dark"] .appointment-status-card {
        background: transparent;
    }

    :root[data-bs-theme="dark"] .appointment-status-chart-shell {
        background: linear-gradient(180deg, rgba(59, 130, 246, 0.16), rgba(10, 14, 35, 0.72));
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
    }

    :root[data-bs-theme="dark"] .appointment-status-metric {
        background: rgba(255, 255, 255, 0.03);
        border-color: rgba(159, 172, 191, 0.18);
    }

    :root[data-bs-theme="dark"] .appointment-status-metric:hover {
        box-shadow: 0 0.8rem 1.4rem rgba(0, 0, 0, 0.28);
    }

    :root[data-bs-theme="dark"] .appointment-status-dot {
        box-shadow: 0 0 0 0.28rem rgba(255, 255, 255, 0.05);
    }

    .appointment-status-metric-main {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        min-width: 0;
        overflow: hidden;
    }

    .appointment-status-dot {
        width: 0.75rem;
        height: 0.75rem;
        border-radius: 50%;
        flex-shrink: 0;
        box-shadow: 0 0 0 0.25rem rgba(0, 0, 0, 0.04);
    }

    .appointment-status-dot.completed { background: #22c55e; }
    .appointment-status-dot.pending   { background: #f59e0b; }
    .appointment-status-dot.cancelled { background: #ef4444; }
    .appointment-status-dot.ongoing   { background: #3b82f6; }

    .appointment-status-label {
        display: block;
        color: var(--bs-body-color, #212529);
        font-weight: 600;
        font-size: 0.875rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .appointment-status-meta {
        display: block;
        color: var(--bs-secondary-color, #6c757d);
        font-size: 0.75rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .appointment-status-value {
        color: var(--bs-body-color, #212529);
        font-size: 1.15rem;
        font-weight: 700;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .appointment-status-empty-note {
        margin: 0.15rem 0 0;
        color: var(--bs-secondary-color, #6c757d);
        font-size: 0.85rem;
        grid-column: 1 / -1;
    }

    @media (max-width: 575.98px) {
        .appointment-status-metrics {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')

<!-- Page Header -->
<div class="d-flex align-items-sm-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="text-muted mb-0">Welcome back, {{ auth()->user()->name }}!</h4>
    </div>
    <div class="d-flex align-items-center flex-wrap gap-2">
        {{-- <a href="{{ route('appointments.index') }}" class="btn btn-primary d-inline-flex align-items-center">
            <i class="ti ti-plus me-1"></i> Appointments
        </a> --}}
        {{-- <a href="" class="btn btn-outline-white bg-white d-inline-flex align-items-center">
            <i class="ti ti-users me-1"></i>Manage Admins
        </a> --}}
    </div>
</div>
<!-- End Page Header -->

<!-- Dashboard Statistics -->
<div class="row mb-4">
    <!-- Total Doctors Card -->
    <div class="col-xl-3 col-md-6">
        <div class="position-relative border card rounded-2 shadow-sm">
            <img src="{{ asset('assets/img/bg/bg-01.svg') }}" alt="bg" class="position-absolute start-0 top-0">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2 justify-content-between">
                    <span class="avatar bg-primary rounded-circle"><i class="ti ti-user-plus fs-24"></i></span>
                    <div class="text-end">
                        <span class="badge px-2 py-1 fs-12 fw-medium d-inline-flex mb-1 bg-success">+12%</span>
                        <p class="fs-13 mb-0">This Month</p>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="mb-1 fw-semibold">Total Doctors</p>
                        <h3 class="fw-bold mb-0">{{ \App\Models\Doctor::count() }}</h3>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="{{ route('doctors.index') }}" class="btn btn-sm btn-outline-primary w-100">
                        <i class="ti ti-arrow-right me-1"></i>View Doctors
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Patients Card -->
    <div class="col-xl-3 col-md-6">
        <div class="position-relative border card rounded-2 shadow-sm">
            <img src="{{ asset('assets/img/bg/bg-02.svg') }}" alt="bg" class="position-absolute start-0 top-0">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2 justify-content-between">
                    <span class="avatar bg-danger rounded-circle"><i class="ti ti-user-heart fs-24"></i></span>
                    <div class="text-end">
                        <span class="badge px-2 py-1 fs-12 fw-medium d-inline-flex mb-1 bg-success">+8%</span>
                        <p class="fs-13 mb-0">This Month</p>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="mb-1 fw-semibold">Total Patients</p>
                        <h3 class="fw-bold mb-0">{{ \App\Models\Patient::count() }}</h3>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="{{ route('patients.index') }}" class="btn btn-sm btn-outline-danger w-100">
                        <i class="ti ti-arrow-right me-1"></i>View Patients
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Appointments Card -->
    <div class="col-xl-3 col-md-6">
        <div class="position-relative border card rounded-2 shadow-sm">
            <img src="{{ asset('assets/img/bg/bg-03.svg') }}" alt="bg" class="position-absolute start-0 top-0">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2 justify-content-between">
                    <span class="avatar bg-info rounded-circle"><i class="ti ti-calendar-check fs-24"></i></span>
                    <div class="text-end">
                        <span class="badge px-2 py-1 fs-12 fw-medium d-inline-flex mb-1 bg-warning">-5%</span>
                        <p class="fs-13 mb-0">This Month</p>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="mb-1 fw-semibold">Total Appointments</p>
                        <h3 class="fw-bold mb-0">{{ \App\Models\Appointment::count() }}</h3>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="{{ route('appointments.index') }}" class="btn btn-sm btn-outline-info w-100">
                        <i class="ti ti-arrow-right me-1"></i>View Appointments
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Revenue Card -->
    <div class="col-xl-3 col-md-6">
        <div class="position-relative border card rounded-2 shadow-sm">
            <img src="{{ asset('assets/img/bg/bg-04.svg') }}" alt="bg" class="position-absolute start-0 top-0">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2 justify-content-between">
                    <span class="avatar bg-success rounded-circle"><i class="ti ti-cards fs-24"></i></span>
                    <div class="text-end">
                        <span class="badge px-2 py-1 fs-12 fw-medium d-inline-flex mb-1 bg-success">+15%</span>
                        <p class="fs-13 mb-0">This Month</p>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="mb-1 fw-semibold">Total Revenue</p>
                        <h3 class="fw-bold mb-0">${{ number_format(\App\Models\Payment::sum('amount') ?? 0, 2) }}</h3>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="{{ route('payments.index') }}" class="btn btn-sm btn-outline-success w-100">
                        <i class="ti ti-arrow-right me-1"></i>View Payments
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- End Dashboard Statistics -->

<!-- Appointment Status & Recent Activities -->
<div class="row mb-4">
    <!-- Appointment Status -->
    <div class="col-lg-6 d-flex">
        <div class="card shadow-sm appointment-status-card h-100 w-100">
            <div class="card-header">
                <h5 class="fw-bold mb-1">Appointment Status</h5>
                <p class="appointment-status-subtitle mb-0">Overview of current appointment distribution</p>
            </div>
            <div class="card-body">
                <div class="appointment-status-grid">
                    <div class="appointment-status-chart-shell">
                        <canvas id="appointmentStatusChart" class="appointment-status-chart-canvas" aria-label="Appointment status doughnut chart"></canvas>
                    </div>

                    <div class="appointment-status-metrics">
                        <div class="appointment-status-metric">
                            <div class="appointment-status-metric-main">
                                <span class="appointment-status-dot completed"></span>
                                <div>
                                    <span class="appointment-status-label">Completed</span>
                                    <span class="appointment-status-meta">Consultations finished</span>
                                </div>
                            </div>
                            <span class="appointment-status-value">{{ $appointmentStatusChart['completedCount'] }}</span>
                        </div>

                        <div class="appointment-status-metric">
                            <div class="appointment-status-metric-main">
                                <span class="appointment-status-dot pending"></span>
                                <div>
                                    <span class="appointment-status-label">Pending</span>
                                    <span class="appointment-status-meta">Awaiting confirmation</span>
                                </div>
                            </div>
                            <span class="appointment-status-value">{{ $appointmentStatusChart['pendingCount'] }}</span>
                        </div>

                        <div class="appointment-status-metric">
                            <div class="appointment-status-metric-main">
                                <span class="appointment-status-dot cancelled"></span>
                                <div>
                                    <span class="appointment-status-label">Cancelled</span>
                                    <span class="appointment-status-meta">Closed without consultation</span>
                                </div>
                            </div>
                            <span class="appointment-status-value">{{ $appointmentStatusChart['cancelledCount'] }}</span>
                        </div>

                        <div class="appointment-status-metric">
                            <div class="appointment-status-metric-main">
                                <span class="appointment-status-dot ongoing"></span>
                                <div>
                                    <span class="appointment-status-label">Ongoing</span>
                                    <span class="appointment-status-meta">Approved or active sessions</span>
                                </div>
                            </div>
                            <span class="appointment-status-value">{{ $appointmentStatusChart['ongoingCount'] }}</span>
                        </div>

                        @if ($appointmentStatusChart['totalAppointments'] === 0)
                            <p class="appointment-status-empty-note">No appointments yet. This chart will update automatically as soon as records are created.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

<div class="col-lg-6 d-flex">
    <div class="card shadow-sm h-100 w-100">
        <div class="card-header">
            <h5 class="fw-bold mb-0">System Overview</h5>
        </div>
        <div class="card-body">
            @foreach($appointmentOverviewItems as $item)
                @php
                    $percentage = $item['total'] > 0 ? min(($item['count'] / $item['total']) * 100, 100) : 0;
                @endphp
                <div class="mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fw-semibold">{{ $item['label'] }}</span>
                        <span class="badge bg-{{ $item['color'] }}">{{ $item['count'] }}</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-{{ $item['color'] }}" style="width: {{ $percentage }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
</div>
<!-- End Appointment Status & Recent Activities -->

<!-- Recent Appointments -->
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0">Recent Appointments</h5>
                <a href="{{ route('appointments.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="py-3">Patient</th>
                            <th class="py-3">Doctor</th>
                            <th class="py-3">Date & Time</th>
                            <th class="py-3">Status</th>
                            <th class="py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($stats['recent_appointments'] ?? collect()) as $appointment)
                            <tr>
                                <td class="py-3">
                                    @php
                                        $patientName = $appointment->patient?->user?->name ?? $appointment->patient?->name ?? 'N/A';
                                        $doctorName = $appointment->doctor?->user?->name ?? $appointment->doctor?->name ?? 'N/A';
                                        $appointmentDateTime = null;

                                        if ($appointment->appointment_date) {
                                            $appointmentDateTime = \Carbon\Carbon::parse($appointment->appointment_date);

                                            if ($appointment->appointment_time) {
                                                $timeValue = $appointment->appointment_time instanceof \Carbon\CarbonInterface
                                                    ? $appointment->appointment_time->format('H:i:s')
                                                    : \Carbon\Carbon::parse($appointment->appointment_time)->format('H:i:s');

                                                $appointmentDateTime->setTimeFromTimeString($timeValue);
                                            }
                                        }
                                    @endphp
                                    <div class="d-flex align-items-center">
                                        <span class="avatar bg-light-primary rounded-circle me-2" style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;">
                                            {{ strtoupper(substr($patientName, 0, 1)) }}
                                        </span>
                                        <span>{{ $patientName }}</span>
                                    </div>
                                </td>
                                <td class="py-3">{{ $doctorName }}</td>
                                <td class="py-3">
                                    @if($appointmentDateTime)
                                        {{ $appointmentDateTime->format('M d, Y g:i A') }}
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="py-3">
                                    @php
                                        $statusColors = [
                                            'pending' => 'warning',
                                            'completed' => 'success',
                                            'cancelled' => 'danger',
                                            'rescheduled' => 'info',
                                            'approved' => 'primary',
                                            'ongoing' => 'primary',
                                        ];
                                        $statusColor = $statusColors[$appointment->status] ?? 'secondary';
                                    @endphp
                                    <span class="badge bg-{{ $statusColor }}">{{ ucfirst($appointment->status) }}</span>
                                </td>
                                <td class="py-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('appointments.show', $appointment) }}" class="btn btn-outline-primary" title="View">
                                            <i class="ti ti-eye"></i>
                                        </a>
                                        <a href="{{ route('appointments.edit', $appointment) }}" class="btn btn-outline-secondary" title="Edit">
                                            <i class="ti ti-pencil"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <p class="text-muted">No appointments found</p>
                                    <a href="{{ route('appointments.create') }}" class="btn btn-sm btn-primary">Create First Appointment</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Management Links -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="fw-bold mb-0">Management Sections</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href"" class="card text-decoration-none border-0 shadow-sm">
                            <div class="card-body text-center py-4">
                                <span class="avatar bg-light-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 45px; height: 45px;">
                                    <i class="ti ti-users fs-20 text-primary"></i>
                                </span>
                                <h6 class="fw-semibold mb-1">Admins</h6>
                                <p class="text-muted mb-0 fs-13">{{ \App\Models\Admin::count() }} Total</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="{{ route('doctors.index') }}" class="card text-decoration-none border-0 shadow-sm">
                            <div class="card-body text-center py-4">
                                <span class="avatar bg-light-success rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 45px; height: 45px;">
                                    <i class="ti ti-user-plus fs-20 text-success"></i>
                                </span>
                                <h6 class="fw-semibold mb-1">Doctors</h6>
                                <p class="text-muted mb-0 fs-13">{{ \App\Models\Doctor::count() }} Total</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="{{ route('patients.index') }}" class="card text-decoration-none border-0 shadow-sm">
                            <div class="card-body text-center py-4">
                                <span class="avatar bg-light-danger rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 45px; height: 45px;">
                                    <i class="ti ti-user-heart fs-20 text-danger"></i>
                                </span>
                                <h6 class="fw-semibold mb-1">Patients</h6>
                                <p class="text-muted mb-0 fs-13">{{ \App\Models\Patient::count() }} Total</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="{{ route('payments.index') }}" class="card text-decoration-none border-0 shadow-sm">
                            <div class="card-body text-center py-4">
                                <span class="avatar bg-light-warning rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 45px; height: 45px;">
                                    <i class="ti ti-cards fs-20 text-warning"></i>
                                </span>
                                <h6 class="fw-semibold mb-1">Payments</h6>
                                <p class="text-muted mb-0 fs-13">{{ \App\Models\Payment::count() }} Total</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="{{ route('prescriptions.index') }}" class="card text-decoration-none border-0 shadow-sm">
                            <div class="card-body text-center py-4">
                                <span class="avatar bg-light-info rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 45px; height: 45px;">
                                    <i class="ti ti-prescription fs-20 text-info"></i>
                                </span>
                                <h6 class="fw-semibold mb-1">Prescriptions</h6>
                                <p class="text-muted mb-0 fs-13">{{ \App\Models\Prescription::count() }} Total</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="{{ route('invoices.index') }}" class="card text-decoration-none border-0 shadow-sm">
                            <div class="card-body text-center py-4">
                                <span class="avatar bg-light-success rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 45px; height: 45px;">
                                    <i class="ti ti-file-invoice fs-20 text-success"></i>
                                </span>
                                <h6 class="fw-semibold mb-1">Invoices</h6>
                                <p class="text-muted mb-0 fs-13">{{ \App\Models\Invoice::count() }} Total</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="{{ route('ratings.index') }}" class="card text-decoration-none border-0 shadow-sm">
                            <div class="card-body text-center py-4">
                                <span class="avatar bg-light-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 45px; height: 45px;">
                                    <i class="ti ti-star fs-20 text-primary"></i>
                                </span>
                                <h6 class="fw-semibold mb-1">Ratings</h6>
                                <p class="text-muted mb-0 fs-13">{{ \App\Models\Rating::count() }} Total</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="{{ route('appointments.index') }}" class="card text-decoration-none border-0 shadow-sm">
                            <div class="card-body text-center py-4">
                                <span class="avatar bg-light-danger rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 45px; height: 45px;">
                                    <i class="ti ti-calendar-check fs-20 text-danger"></i>
                                </span>
                                <h6 class="fw-semibold mb-1">Appointments</h6>
                                <p class="text-muted mb-0 fs-13">{{ \App\Models\Appointment::count() }} Total</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const chartCanvas = document.getElementById('appointmentStatusChart');
        const themeRoot = document.documentElement;

        if (!chartCanvas || typeof Chart === 'undefined') {
            return;
        }

        const chartData = @json($appointmentStatusChartJs);
        let appointmentChart = null;

        function getThemeColors() {
            const computedStyle = getComputedStyle(themeRoot);
            const isDark = themeRoot.getAttribute('data-bs-theme') === 'dark';

            return {
                isDark,
                textColor: computedStyle.getPropertyValue('--bs-body-color').trim() || '#212529',
                mutedColor: computedStyle.getPropertyValue('--bs-secondary-color').trim() || '#6c757d',
                borderColor: computedStyle.getPropertyValue('--bs-body-bg').trim() || '#ffffff',
                tooltipBackground: isDark ? 'rgba(3, 4, 26, 0.96)' : 'rgba(15, 23, 42, 0.92)',
                tooltipBody: isDark ? '#d9e2f1' : '#e2e8f0',
            };
        }

        function createCenterTextPlugin(colors) {
            return {
                id: 'centerTextPlugin',
                afterDraw(chart) {
                    const meta = chart.getDatasetMeta(0);
                    if (!meta || !meta.data || !meta.data.length) {
                        return;
                    }

                    const centerPoint = meta.data[0];
                    const ctx = chart.ctx;

                    ctx.save();
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';

                    ctx.fillStyle = colors.mutedColor;
                    ctx.font = '600 13px sans-serif';
                    ctx.fillText('Total', centerPoint.x, centerPoint.y - 16);

                    ctx.fillStyle = colors.textColor;
                    ctx.font = '700 28px sans-serif';
                    ctx.fillText(String(chartData.totalAppointments), centerPoint.x, centerPoint.y + 12);

                    ctx.restore();
                }
            };
        }

        function renderAppointmentChart() {
            const colors = getThemeColors();

            if (appointmentChart) {
                appointmentChart.destroy();
            }

            appointmentChart = new Chart(chartCanvas, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.values,
                        backgroundColor: ['#22c55e', '#f59e0b', '#ef4444', '#3b82f6'],
                        borderColor: colors.borderColor,
                        borderWidth: 6,
                        hoverOffset: 10,
                        spacing: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    animation: {
                        animateRotate: true,
                        animateScale: true,
                        duration: 1000,
                        easing: 'easeOutQuart',
                    },
                    interaction: {
                        mode: 'nearest',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: colors.mutedColor,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                boxWidth: 10,
                                boxHeight: 10,
                                padding: 18,
                                font: {
                                    size: 12,
                                    weight: '600',
                                },
                            }
                        },
                        tooltip: {
                            backgroundColor: colors.tooltipBackground,
                            titleColor: '#ffffff',
                            bodyColor: colors.tooltipBody,
                            padding: 12,
                            cornerRadius: 12,
                            displayColors: true,
                            callbacks: {
                                label: function (context) {
                                    const value = context.parsed || 0;
                                    return ' ' + context.label + ': ' + value + ' appointment' + (value === 1 ? '' : 's');
                                }
                            }
                        }
                    }
                },
                plugins: [createCenterTextPlugin(colors)]
            });
        }

        renderAppointmentChart();

        const themeObserver = new MutationObserver(function (mutations) {
            const themeChanged = mutations.some(function (mutation) {
                return mutation.type === 'attributes' && mutation.attributeName === 'data-bs-theme';
            });

            if (themeChanged) {
                renderAppointmentChart();
            }
        });

        themeObserver.observe(themeRoot, {
            attributes: true,
            attributeFilter: ['data-bs-theme']
        });
    });
</script>
@endpush

