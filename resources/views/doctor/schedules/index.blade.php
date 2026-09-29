@extends('layouts.layout')

@section('title', 'Doctor Schedule')

@section('content')

{{-- Page Header --}}
<div class="d-flex align-items-sm-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0">Doctor Schedule</h4>
        <p class="text-muted mb-0">Manage your consultation availability, working hours, and time slots.</p>
    </div>
</div>


<div class="row">
    {{-- Add Availability Form --}}
    <div class="col-xl-4 d-flex">
        <div class="card shadow-sm flex-fill w-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="fw-bold mb-0"><i class="ti ti-calendar-plus me-1 text-primary"></i>Add New Availability</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('doctor.schedule.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Available Date</label>
                        <input type="date" name="available_date"
                            class="form-control @error('available_date') is-invalid @enderror"
                            value="{{ old('available_date') }}" min="{{ date('Y-m-d') }}" required>
                        @error('available_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Start Time</label>
                            <input type="time" name="start_time"
                                class="form-control @error('start_time') is-invalid @enderror"
                                value="{{ old('start_time') }}" required>
                            @error('start_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">End Time</label>
                            <input type="time" name="end_time"
                                class="form-control @error('end_time') is-invalid @enderror"
                                value="{{ old('end_time') }}" required>
                            @error('end_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ti ti-plus me-1"></i>Save Schedule Slot
                    </button>
                </form>

                <hr class="my-4">

                <h6 class="fw-bold mb-2">Calendar Preview</h6>
                <div id="doctor-schedules-calendar" style="min-height: 280px;"></div>
            </div>
        </div>
    </div>

    {{-- Schedule Slots List & Table --}}
    <div class="col-xl-8 d-flex">
        <div class="card shadow-sm flex-fill w-100">
            <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0"><i class="ti ti-clock text-primary me-1"></i>Active Schedule Slots</h5>
                <span class="badge badge-soft-primary fs-12">{{ $schedules->total() }} Slots Total</span>
            </div>
            <div class="card-body">
                <div class="table-responsive table-nowrap">
                    <table class="table border align-middle">
                        <thead class="thead-light">
                            <tr>
                                <th>Date</th>
                                <th>Time Slot</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($schedules as $schedule)
                                @php
                                    $isToday = $schedule->available_date->isToday();
                                    $isPast  = $schedule->available_date->isPast() && !$isToday;
                                    $startDateTime = \Carbon\Carbon::parse(
                                        $schedule->available_date->format('Y-m-d') . ' ' .
                                        \Carbon\Carbon::parse($schedule->start_time)->format('H:i:s')
                                    );
                                    if ($isToday) {
                                        $subtext = 'Today';
                                    } elseif ($schedule->available_date->isTomorrow()) {
                                        $subtext = 'Tomorrow';
                                    } else {
                                        $subtext = $startDateTime->diffForHumans();
                                    }
                                @endphp
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="avatar avatar-sm me-2 rounded-circle bg-light d-inline-flex align-items-center justify-content-center flex-shrink-0">
                                                <i class="ti ti-calendar text-primary fs-16"></i>
                                            </span>
                                            <div>
                                                <h6 class="fs-14 mb-0 fw-semibold">{{ $schedule->available_date->format('D, d M Y') }}</h6>
                                                <small class="text-muted">{{ $subtext }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark">
                                            {{ $schedule->formatted_time_slot }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($isToday)
                                            <span class="badge bg-success fw-medium">Today</span>
                                        @elseif($isPast)
                                            <span class="badge bg-secondary fw-medium">Past</span>
                                        @else
                                            <span class="badge bg-primary fw-medium">Upcoming</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1 edit-schedule-btn"
                                            data-id="{{ $schedule->id }}"
                                            data-date="{{ $schedule->available_date->format('Y-m-d') }}"
                                            data-start="{{ \Carbon\Carbon::parse($schedule->start_time)->format('H:i') }}"
                                            data-end="{{ \Carbon\Carbon::parse($schedule->end_time)->format('H:i') }}"
                                            title="Edit Schedule">
                                            <i class="ti ti-pencil"></i> Edit
                                        </button>

                                        <form action="{{ route('doctor.schedules.destroy', $schedule) }}" method="POST" class="d-inline"
                                            onsubmit="return confirm('Are you sure you want to delete this schedule slot?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Schedule">
                                                <i class="ti ti-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i class="ti ti-calendar-off fs-36 d-block mb-2 text-light-emphasis"></i>
                                        No availability schedules added yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $schedules->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

{{-- EDIT SCHEDULE MODAL --}}
<div class="modal fade" id="editScheduleModal" tabindex="-1" aria-labelledby="editScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title text-white" id="editScheduleModalLabel"><i class="ti ti-pencil me-1"></i>Edit Schedule Slot</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editScheduleForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Available Date</label>
                        <input type="date" name="available_date" id="edit_available_date" class="form-control" min="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Start Time</label>
                            <input type="time" name="start_time" id="edit_start_time" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">End Time</label>
                            <input type="time" name="end_time" id="edit_end_time" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Update Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@php
    $doctorScheduleEvents = ($allSchedules ?? collect())->map(function ($schedule) {
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
document.addEventListener('DOMContentLoaded', function () {
    // FullCalendar init
    const scheduleCalendar = new FullCalendar.Calendar(
        document.getElementById('doctor-schedules-calendar'), {
            initialView: 'dayGridMonth',
            headerToolbar: { left: 'prev,next', center: 'title', right: 'today' },
            height: 'auto',
            dayMaxEvents: true,
            events: @json($doctorScheduleEvents),
        }
    );
    scheduleCalendar.render();

    // Edit Schedule Modal Handler
    const editModal = new bootstrap.Modal(document.getElementById('editScheduleModal'));
    const editForm = document.getElementById('editScheduleForm');
    const editDate = document.getElementById('edit_available_date');
    const editStart = document.getElementById('edit_start_time');
    const editEnd = document.getElementById('edit_end_time');

    document.querySelectorAll('.edit-schedule-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const date = this.getAttribute('data-date');
            const start = this.getAttribute('data-start');
            const end = this.getAttribute('data-end');

            editForm.action = `/doctor/schedules/${id}`;
            editDate.value = date;
            editStart.value = start;
            editEnd.value = end;

            editModal.show();
        });
    });
});
</script>
@endsection
