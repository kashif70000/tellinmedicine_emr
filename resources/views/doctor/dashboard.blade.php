@extends('layouts.layout')

@section('title', 'Doctor Dashboard')

@section('content')

{{-- Page Header --}}
<div class="d-flex align-items-sm-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0">Doctor Dashboard</h4>
        <p class="text-muted mb-0">Welcome back, Dr. {{ $user->name }}</p>
    </div>
    <div class="d-flex align-items-center flex-wrap gap-2">
        <a href="{{ route('doctor.appointments') }}" class="btn btn-primary d-inline-flex align-items-center">
            <i class="ti ti-plus me-1"></i>My Appointments
        </a>
        <a href="#availability-card" class="btn btn-outline-white bg-white d-inline-flex align-items-center">
            <i class="ti ti-calendar-time me-1"></i>Schedule Availability
        </a>
    </div>
</div>

{{-- ===== TOP 3 STAT CARDS ===== --}}
<div class="row">
    <div class="col-xl-4 d-flex">
        <div class="card shadow-sm flex-fill w-100">
           <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <p class="mb-1">Total Appointments</p>
                        <div class="d-flex align-items-center gap-1">
                            <h3 class="fw-bold mb-0">{{ $stats['total_appointments'] }}</h3>
                        </div>
                    </div>
                    <span class="avatar border border-primary text-primary rounded-2 flex-shrink-0">
                        <i class="ti ti-calendar-heart fs-20"></i>
                    </span>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge badge-soft-success border border-success fw-medium">{{ $stats['approved_appointments'] }} Approved</span>
                    <span class="badge badge-soft-warning border border-warning fw-medium">{{ $stats['pending_appointments'] }} Pending</span>
                </div>
           </div>
        </div>
    </div>
    <div class="col-xl-4 d-flex">
        <div class="card shadow-sm flex-fill w-100">
           <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <p class="mb-1">Total Patients</p>
                        <h3 class="fw-bold mb-0">{{ $stats['total_patients'] }}</h3>
                    </div>
                    <span class="avatar border border-success text-success rounded-2 flex-shrink-0">
                        <i class="ti ti-user-heart fs-20"></i>
                    </span>
                </div>
                <p class="fs-13 text-muted mb-0">Unique patients booked with you</p>
           </div>
        </div>
    </div>
    <div class="col-xl-4 d-flex">
        <div class="card shadow-sm flex-fill w-100">
           <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <p class="mb-1">Cancelled Appointments</p>
                        <h3 class="fw-bold mb-0">{{ $stats['cancelled_appointments'] }}</h3>
                    </div>
                    <span class="avatar border border-danger text-danger rounded-2 flex-shrink-0">
                        <i class="ti ti-versions fs-20"></i>
                    </span>
                </div>
                <p class="fs-13 text-muted mb-0">Total cancelled bookings</p>
           </div>
        </div>
    </div>
</div>

{{-- ===== MINI STAT TILES (6 across) ===== --}}
<div class="row row-cols-2 row-cols-sm-3 row-cols-xl-6">
    <div class="col">
        <div class="card shadow-sm">
            <div class="card-body">
                <span class="avatar bg-primary rounded-2 fs-20 d-inline-flex mb-2"><i class="ti ti-user"></i></span>
                <p class="mb-1 text-truncate">Total Patients</p>
                <h3 class="fw-bold mb-1">{{ $stats['total_patients'] }}</h3>
                <p class="mb-0 text-success fs-12">All time</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card shadow-sm">
            <div class="card-body">
                <span class="avatar bg-secondary rounded-2 fs-20 d-inline-flex mb-2"><i class="ti ti-video"></i></span>
                <p class="mb-1 text-truncate">Video Calls</p>
                <h3 class="fw-bold mb-1">{{ $stats['video_consultations'] }}</h3>
                <p class="mb-0 text-danger fs-12">Completed</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card shadow-sm">
            <div class="card-body">
                <span class="avatar bg-success rounded-2 fs-20 d-inline-flex mb-2"><i class="ti ti-calendar-up"></i></span>
                <p class="mb-1 text-truncate">Rescheduled</p>
                <h3 class="fw-bold mb-1">{{ $stats['rescheduled'] }}</h3>
                <p class="mb-0 text-success fs-12">Updated</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card shadow-sm">
            <div class="card-body">
                <span class="avatar bg-danger rounded-2 fs-20 d-inline-flex mb-2"><i class="ti ti-checklist"></i></span>
                <p class="mb-1 text-truncate">Pre-Visit</p>
                <h3 class="fw-bold mb-1">{{ $stats['pre_visit_bookings'] }}</h3>
                <p class="mb-0 text-success fs-12">Bookings</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card shadow-sm">
            <div class="card-body">
                <span class="avatar bg-info rounded-2 fs-20 d-inline-flex mb-2"><i class="ti ti-calendar-share"></i></span>
                <p class="mb-1 text-truncate">Walkin</p>
                <h3 class="fw-bold mb-1">{{ $stats['walkin_bookings'] }}</h3>
                <p class="mb-0 text-success fs-12">Bookings</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card shadow-sm">
            <div class="card-body">
                <span class="avatar bg-soft-success text-success rounded-2 fs-20 d-inline-flex mb-2"><i class="ti ti-carousel-vertical"></i></span>
                <p class="mb-1 text-truncate">Follow Ups</p>
                <h3 class="fw-bold mb-1">{{ $stats['follow_ups'] }}</h3>
                <p class="mb-0 text-success fs-12">Scheduled</p>
            </div>
        </div>
    </div>
</div>

{{-- ===== UPCOMING APPOINTMENTS + RECENT TABLE ===== --}}
<div class="row">
    {{-- Upcoming --}}
    <div class="col-xl-4 d-flex">
        <div class="card shadow-sm flex-fill w-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0 text-truncate">Upcoming Appointments</h5>
                <a href="{{ route('doctor.appointments') }}" class="btn btn-sm btn-light">All</a>
            </div>
            <div class="card-body p-0">
                @forelse(($stats['upcoming_appointments'] ?? collect()) as $appt)
                    <div class="p-3 border-bottom">
                        <div class="d-flex align-items-center mb-2">
                            <span class="avatar avatar-sm me-2 bg-light rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0">
                                <i class="ti ti-user fs-16 text-primary"></i>
                            </span>
                            <div>
                                <h6 class="fs-14 fw-semibold mb-0">{{ $appt->patient->user->name ?? 'Patient' }}</h6>
                                <p class="mb-0 fs-12 text-muted">#AP{{ str_pad($appt->id, 6, '0', STR_PAD_LEFT) }}</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 mb-2 fs-12 text-muted flex-wrap">
                            <span><i class="ti ti-calendar me-1"></i>{{ $appt->appointment_date->format('D, d M Y') }}</span>
                            <span><i class="ti ti-clock me-1"></i>{{ $appt->formatted_time }}</span>
                        </div>
                        @php
                            $apptLive = $appt->status === 'approved' && $appt->call_started_at && \Carbon\Carbon::now()->lt($appt->call_started_at->addSeconds(1800));
                        @endphp
                        <div class="d-flex gap-2">
                            @if($appt->status === 'approved' && $appt->payment_status === 'paid' && !$appt->call_started_at)
                                <form action="{{ route('doctor.appointments.start-call', $appt->id) }}" method="POST" class="flex-fill">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm w-100">
                                        <i class="ti ti-video me-1"></i>Start Call
                                    </button>
                                </form>
                            @elseif($apptLive)
                                <a href="{{ route('appointments.call', $appt->id) }}" class="btn btn-success btn-sm flex-fill">
                                    <i class="ti ti-video me-1"></i>Rejoin Call
                                </a>
                            @else
                                <a href="{{ route('appointments.show', $appt) }}" class="btn btn-outline-white btn-sm flex-fill">
                                    <i class="ti ti-eye me-1"></i>View
                                </a>
                            @endif
                        </div>
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

    {{-- Recent Appointments Table --}}
    <div class="col-xl-8 d-flex">
        <div class="card shadow-sm flex-fill w-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0">Recent Appointments</h5>
                <a href="{{ route('doctor.appointments') }}" class="btn btn-sm btn-light">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive table-nowrap">
                    <table class="table border align-middle">
                        <thead class="thead-light">
                            <tr>
                                <th>Patient</th>
                                <th>Date &amp; Time</th>
                                <th>Fee</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($stats['recent_appointments'] ?? collect()) as $appointment)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="avatar avatar-sm me-2 bg-light rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0">
                                                <i class="ti ti-user text-primary fs-14"></i>
                                            </span>
                                            <div>
                                                <h6 class="fs-14 mb-0 fw-semibold">{{ $appointment->patient->user->name ?? 'Patient' }}</h6>
                                                <p class="mb-0 fs-12 text-muted">{{ $appointment->patient->user->email ?? '' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="fs-13">
                                        {{ $appointment->appointment_date?->format('d M Y') }}<br>
                                        <span class="text-muted">{{ $appointment->formatted_time }}</span>
                                    </td>
                                    <td class="fw-semibold text-dark">${{ number_format($appointment->fee_snapshot ?? 0, 2) }}</td>
                                    <td>
                                        @if($appointment->status === 'approved')
                                            <span class="badge bg-success fw-medium">Approved</span>
                                        @elseif($appointment->status === 'cancelled')
                                            <span class="badge bg-danger fw-medium">Cancelled</span>
                                        @elseif($appointment->status === 'completed')
                                            <span class="badge bg-info fw-medium">Completed</span>
                                        @else
                                            <span class="badge bg-warning fw-medium">Pending</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($appointment->status === 'pending')
                                            <div class="d-flex gap-1">
                                                <form action="{{ route('doctor.appointments.approve', $appointment) }}" method="POST">
                                                    @csrf
                                                    <button class="btn btn-sm btn-success" title="Approve"><i class="ti ti-check"></i></button>
                                                </form>
                                                <form action="{{ route('doctor.appointments.reject', $appointment) }}" method="POST">
                                                    @csrf
                                                    <button class="btn btn-sm btn-danger" title="Reject"><i class="ti ti-x"></i></button>
                                                </form>
                                            </div>
                                        @elseif($appointment->status === 'approved' && $appointment->payment_status === 'paid')
                                            @php $live2 = $appointment->status === 'approved' && $appointment->call_started_at && \Carbon\Carbon::now()->lt($appointment->call_started_at->addSeconds(1800)); @endphp
                                            @if(!$appointment->call_started_at)
                                                <form action="{{ route('doctor.appointments.start-call', $appointment->id) }}" method="POST">
                                                    @csrf
                                                    <button class="btn btn-sm btn-primary">
                                                        <i class="ti ti-video me-1"></i>Start
                                                    </button>
                                                </form>
                                            @elseif($live2)
                                                <a href="{{ route('appointments.call', $appointment->id) }}" class="btn btn-sm btn-success">
                                                    <i class="ti ti-video me-1"></i>Join
                                                </a>
                                            @else
                                                <a href="{{ route('appointments.show', $appointment) }}" class="btn btn-sm btn-light"><i class="ti ti-eye"></i></a>
                                            @endif
                                        @else
                                            <a href="{{ route('appointments.show', $appointment) }}" class="btn btn-sm btn-light"><i class="ti ti-eye"></i></a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No recent appointments</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ===== MY PATIENTS + AVAILABILITY ===== --}}
<div class="row">
    <div class="col-xl-7 d-flex">
        <div class="card shadow-sm flex-fill w-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0">My Patients</h5>
                <a href="{{ route('doctor.my-patients') }}" class="btn btn-sm btn-light">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive table-nowrap">
                    <table class="table border align-middle">
                        <thead class="thead-light">
                            <tr>
                                <th>Patient</th>
                                <th>Email</th>
                                <th>Visits</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($stats['my_patients'] ?? collect()) as $patient)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="avatar avatar-sm me-2 bg-light rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0">
                                                <i class="ti ti-user text-primary fs-14"></i>
                                            </span>
                                            <span class="fw-semibold fs-14">{{ $patient->user->name ?? 'Patient' }}</span>
                                        </div>
                                    </td>
                                    <td class="fs-13 text-muted">{{ $patient->user->email ?? 'N/A' }}</td>
                                    <td>
                                        <span class="badge badge-soft-primary border border-primary fw-medium">
                                            {{ $patient->appointment_history_count ?? 0 }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('patients.show', $patient) }}" class="btn btn-sm btn-light">
                                            <i class="ti ti-eye me-1"></i>View
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No patients have booked with you yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-5 d-flex" id="availability-card">
        <div class="card shadow-sm flex-fill w-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0">Schedule Availability</h5>
                <a href="{{ route('doctor.schedules.index') }}" class="btn btn-sm btn-light">Manage All</a>
            </div>
            <div class="card-body">
                <form action="{{ route('doctor.schedule.store') }}" method="POST" class="mb-4">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Available Date</label>
                        <input type="date" name="available_date"
                            class="form-control @error('available_date') is-invalid @enderror"
                            value="{{ old('available_date') }}" min="{{ date('Y-m-d') }}" required>
                        @error('available_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Start Time</label>
                            <input type="time" name="start_time"
                                class="form-control @error('start_time') is-invalid @enderror"
                                value="{{ old('start_time') }}" required>
                            @error('start_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label">End Time</label>
                            <input type="time" name="end_time"
                                class="form-control @error('end_time') is-invalid @enderror"
                                value="{{ old('end_time') }}" required>
                            @error('end_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-3">
                        <i class="ti ti-plus me-1"></i>Add Availability
                    </button>
                </form>

                <div id="doctor-dashboard-calendar" style="min-height: 280px;"></div>

                <div class="mt-3">
                    @forelse(($doctorSchedules ?? collect())->take(5) as $schedule)
                        <div class="d-flex align-items-center justify-content-between mb-2 border-bottom pb-2">
                            <div>
                                <p class="text-dark fw-semibold mb-0 fs-13">{{ $schedule->available_date->format('D, d M') }}</p>
                                <p class="mb-0 fs-12 text-muted d-inline-flex align-items-center">
                                    <i class="ti ti-clock me-1"></i>
                                    {{ $schedule->formatted_time_slot }}
                                </p>
                            </div>
                            <form action="{{ route('doctor.schedules.destroy', $schedule) }}" method="POST" onsubmit="return confirm('Delete this schedule slot?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-link text-danger p-0 border-0" title="Delete Slot">
                                    <i class="ti ti-trash fs-16"></i>
                                </button>
                            </form>
                        </div>
                    @empty
                        <p class="text-muted fs-13 mb-0">No availability added yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@php
    $doctorScheduleEvents = ($doctorSchedules ?? collect())->map(function ($schedule) {
        $date = $schedule->available_date->format('Y-m-d');
        return [
            'title' => \Carbon\Carbon::parse($schedule->start_time)->format('g:i A') . ' – ' . \Carbon\Carbon::parse($schedule->end_time)->format('g:i A'),
            'start' => $date . 'T' . \Carbon\Carbon::parse($schedule->start_time)->format('H:i:s'),
            'end'   => $date . 'T' . \Carbon\Carbon::parse($schedule->end_time)->format('H:i:s'),
        ];
    })->values();
@endphp

@section('scripts')
<script src="{{ asset('assets/plugins/fullcalendar/index.global.min.js') }}"></script>
<script>
    const scheduleCalendar = new FullCalendar.Calendar(
        document.getElementById('doctor-dashboard-calendar'), {
            initialView: 'dayGridMonth',
            headerToolbar: { left: 'prev,next', center: 'title', right: 'today' },
            height: 'auto',
            dayMaxEvents: true,
            events: @json($doctorScheduleEvents),
        }
    );
    scheduleCalendar.render();
</script>
@endsection
