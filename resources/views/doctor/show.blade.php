@extends('layouts.layout')

@section('title', 'View Doctor')

@php
    $patientIsVerified = auth()->user()->role === 'patient' && ($patient?->is_payment_method_verified ?? false);
    $bookingAllowed = $canBookAppointment ?? false;
    $scheduleApiUrl = route('doctor.schedule.show', $doctor);
@endphp

@section('styles')
<style>
    .doctor-profile-card,
    .calendar-shell,
    #appointment-booking-card {
        border: 0;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
    }
    .doctor-profile-card .card-header,
    .calendar-shell .card-header,
    #appointment-booking-card .card-header {
        background: linear-gradient(135deg, #f8fbff, #eef6ff);
        border-bottom: 1px solid rgba(148, 163, 184, 0.15);
        font-weight: 700;
        color: #0f172a;
        padding: 1rem 1.25rem;
    }
    .doctor-profile-card .card-body,
    .calendar-shell .card-body,
    #appointment-booking-card .card-body {
        padding: 1.4rem;
    }
    .doctor-meta-grid {
        row-gap: 1rem;
    }
    .meta-item {
        padding: 1rem 1.1rem;
        border-radius: 18px;
        background: linear-gradient(180deg, #ffffff, #f8fafc);
        border: 1px solid rgba(226, 232, 240, 0.9);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        height: 100%;
    }
    .meta-label {
        display: block;
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #64748b;
        margin-bottom: .35rem;
    }
    .meta-value {
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.45;
    }
    .availability-strip {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: .9rem;
    }
    .availability-chip {
        padding: 1rem;
        border-radius: 18px;
        background: linear-gradient(180deg, #ffffff, #f8fafc);
        border: 1px solid rgba(203, 213, 225, 0.8);
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
    }
    .availability-chip-date {
        font-weight: 700;
        color: #0f172a;
        margin-bottom: .5rem;
    }
    .availability-chip-time {
        color: #475569;
        font-size: .92rem;
    }
    .availability-empty {
        padding: 1rem 1.1rem;
        border-radius: 18px;
        background: rgba(148, 163, 184, 0.1);
        color: #64748b;
    }
    .booking-summary {
        padding: 1rem 1.1rem;
        border-radius: 18px;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.08), rgba(34, 197, 94, 0.08));
        border: 1px solid rgba(96, 165, 250, 0.18);
        color: #0f172a;
        font-weight: 600;
    }
    .booking-summary strong {
        display: block;
        font-size: .8rem;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #64748b;
        margin-bottom: .35rem;
    }
    #time-slot-buttons .btn {
        border-radius: 12px;
        padding: .6rem .95rem;
        transition: transform .2s ease, box-shadow .2s ease;
    }
    #time-slot-buttons .btn:hover {
        transform: scale(1.05);
        box-shadow: 0 12px 24px rgba(37, 99, 235, 0.16);
    }
    .booking-actions .btn {
        border-radius: 12px;
        padding: .72rem 1rem;
    }
    .calendar-shell {
        position: relative;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .calendar-shell .card-body {
        padding: 1rem;
    }
    .calendar-helper {
        margin-bottom: .9rem;
        padding: .85rem 1rem;
        border-radius: 16px;
        background: rgba(59, 130, 246, 0.08);
        color: #1d4ed8;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: .5rem;
    }
    #doctor-view-calendar .fc {
        --fc-border-color: transparent;
        --fc-page-bg-color: transparent;
        --fc-neutral-bg-color: transparent;
        --fc-today-bg-color: rgba(59, 130, 246, 0.08);
    }
    #doctor-view-calendar .fc-toolbar.fc-header-toolbar {
        margin-bottom: 1rem;
        gap: .75rem;
        flex-wrap: wrap;
    }
    #doctor-view-calendar .fc .fc-toolbar-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: #0f172a;
    }
    #doctor-view-calendar .fc .fc-button {
        border-radius: 12px;
        border: 0;
        background: #e2e8f0;
        color: #334155;
        box-shadow: none;
        transition: all .2s ease;
    }
    #doctor-view-calendar .fc .fc-button:hover,
    #doctor-view-calendar .fc .fc-button:focus {
        background: #cbd5e1;
        color: #0f172a;
        box-shadow: none;
    }
    #doctor-view-calendar .fc .fc-daygrid-day-frame {
        min-height: 110px;
        padding: .45rem;
    }
    #doctor-view-calendar .fc .fc-daygrid-day-top {
        justify-content: flex-end;
    }
    #doctor-view-calendar .fc .fc-daygrid-day-number {
        width: 34px;
        height: 34px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #334155;
        font-weight: 700;
        transition: all .2s ease;
    }
    #doctor-view-calendar .fc {
        --fc-border-color: #e2e8f0;
        --fc-page-bg-color: transparent;
        --fc-neutral-bg-color: transparent;
        --fc-today-bg-color: rgba(59, 130, 246, 0.06);
    }
    #doctor-view-calendar .fc-toolbar.fc-header-toolbar {
        margin-bottom: 0.75rem;
        gap: .5rem;
        flex-wrap: wrap;
    }
    #doctor-view-calendar .fc .fc-toolbar-title {
        font-size: 1.05rem;
        font-weight: 700;
        color: #0f172a;
    }
    #doctor-view-calendar .fc .fc-button {
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #334155;
        padding: 0.35rem 0.65rem;
        font-size: 0.78rem;
        font-weight: 600;
        box-shadow: none;
        transition: all .2s ease;
    }
    #doctor-view-calendar .fc .fc-button:hover {
        background: #f1f5f9;
        color: #0f172a;
    }
    #doctor-view-calendar .fc .fc-button-primary:not(:disabled).fc-button-active,
    #doctor-view-calendar .fc .fc-button-primary:not(:disabled):active {
        background: #2563eb;
        border-color: #2563eb;
        color: #fff;
    }
    #doctor-view-calendar .fc .fc-daygrid-day {
        padding: 2px;
    }
    #doctor-view-calendar .fc .fc-daygrid-day-top {
        justify-content: flex-end;
    }
    #doctor-view-calendar .fc .fc-daygrid-day-number {
        width: 26px;
        height: 26px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #334155;
        font-weight: 700;
        font-size: 0.8rem;
        text-decoration: none !important;
    }
    #doctor-view-calendar .fc .fc-daygrid-day-frame {
        min-height: 65px;
        padding: 4px;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        position: relative;
    }
    #doctor-view-calendar .fc .fc-day-other .fc-daygrid-day-frame {
        opacity: .45;
    }
    #doctor-view-calendar .fc-day-available .fc-daygrid-day-frame {
        background: #f0f9ff;
        border-color: #7dd3fc;
        cursor: pointer;
    }
    #doctor-view-calendar .fc-day-available .fc-daygrid-day-number {
        color: #0284c7;
        background: #e0f2fe;
    }
    #doctor-view-calendar .fc-day-unavailable .fc-daygrid-day-frame {
        opacity: .55;
    }
    #doctor-view-calendar .fc-day-selected .fc-daygrid-day-frame {
        background: #0284c7 !important;
        border-color: #0284c7 !important;
        box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
    }
    #doctor-view-calendar .fc-day-selected .fc-daygrid-day-number {
        color: #fff !important;
        background: rgba(255, 255, 255, 0.25) !important;
    }
    #doctor-view-calendar .fc-day-selected .fc-daygrid-event,
    #doctor-view-calendar .fc-day-selected .fc-event-title,
    #doctor-view-calendar .fc-day-selected .fc-event-time {
        color: #fff !important;
    }
    #doctor-view-calendar .fc .fc-daygrid-event,
    #doctor-view-calendar .fc .fc-daygrid-event-harness {
        display: none !important;
    }
    :root[data-bs-theme="dark"] .doctor-profile-card,
    :root[data-bs-theme="dark"] .calendar-shell,
    :root[data-bs-theme="dark"] #appointment-booking-card {
        background: #03041a;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
    }
    :root[data-bs-theme="dark"] .doctor-profile-card .card-header,
    :root[data-bs-theme="dark"] .calendar-shell .card-header,
    :root[data-bs-theme="dark"] #appointment-booking-card .card-header {
        background: linear-gradient(135deg, rgba(10, 14, 35, 0.98), rgba(20, 28, 45, 0.95));
        border-bottom-color: rgba(159, 172, 191, 0.14);
        color: #e6eefc;
    }
    :root[data-bs-theme="dark"] .meta-item,
    :root[data-bs-theme="dark"] .availability-chip,
    :root[data-bs-theme="dark"] .booking-summary {
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.02));
        border-color: rgba(159, 172, 191, 0.14);
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.22);
    }
    :root[data-bs-theme="dark"] .meta-label,
    :root[data-bs-theme="dark"] .booking-summary strong,
    :root[data-bs-theme="dark"] .availability-chip-time,
    :root[data-bs-theme="dark"] #slot-help-text {
        color: #9facbf !important;
    }
    :root[data-bs-theme="dark"] .meta-value,
    :root[data-bs-theme="dark"] .availability-chip-date,
    :root[data-bs-theme="dark"] .calendar-helper,
    :root[data-bs-theme="dark"] #doctor-view-calendar .fc .fc-toolbar-title,
    :root[data-bs-theme="dark"] h6.fw-semibold,
    :root[data-bs-theme="dark"] .form-label {
        color: #e6eefc;
    }
    :root[data-bs-theme="dark"] .availability-empty {
        background: rgba(148, 163, 184, 0.12);
        color: #9facbf;
    }
    :root[data-bs-theme="dark"] .booking-summary {
        border-color: rgba(96, 165, 250, 0.2);
        color: #e6eefc;
    }
    :root[data-bs-theme="dark"] .calendar-shell {
        background: linear-gradient(180deg, #03041a 0%, #08101f 100%);
    }
    :root[data-bs-theme="dark"] .calendar-helper {
        background: rgba(59, 130, 246, 0.14);
        color: #93c5fd;
    }
    :root[data-bs-theme="dark"] #doctor-view-calendar .fc .fc-button {
        background: rgba(148, 163, 184, 0.12);
        color: #cbd5e1;
    }
    :root[data-bs-theme="dark"] #doctor-view-calendar .fc .fc-button:hover,
    :root[data-bs-theme="dark"] #doctor-view-calendar .fc .fc-button:focus {
        background: rgba(148, 163, 184, 0.2);
        color: #f8fafc;
    }
    :root[data-bs-theme="dark"] #doctor-view-calendar .fc .fc-daygrid-day-number {
        color: #cbd5e1;
    }
    :root[data-bs-theme="dark"] #doctor-view-calendar .fc .fc-daygrid-day-frame {
        background: rgba(255, 255, 255, 0.03);
        border-color: rgba(159, 172, 191, 0.14);
    }
    :root[data-bs-theme="dark"] #doctor-view-calendar .fc .fc-daygrid-day:not(.fc-day-other):hover .fc-daygrid-day-frame {
        box-shadow: 0 16px 30px rgba(0, 0, 0, 0.28);
    }
    :root[data-bs-theme="dark"] #doctor-view-calendar .fc-day-available .fc-daygrid-day-frame {
        background: linear-gradient(180deg, rgba(12, 21, 41, 0.96), rgba(10, 33, 59, 0.96));
        border-color: rgba(96, 165, 250, 0.32);
    }
    :root[data-bs-theme="dark"] #doctor-view-calendar .fc-day-unavailable .fc-daygrid-day-frame {
        background: rgba(255, 255, 255, 0.02);
        opacity: .52;
    }
    :root[data-bs-theme="dark"] #doctor-view-calendar .fc .fc-daygrid-event {
        background: rgba(59, 130, 246, 0.18);
        color: #93c5fd;
    }
    :root[data-bs-theme="dark"] hr {
        border-color: rgba(159, 172, 191, 0.14);
    }
    @media (max-width: 767.98px) {
        .doctor-profile-card .card-body,
        .calendar-shell .card-body,
        #appointment-booking-card .card-body {
            padding: 1rem;
        }
        .availability-strip {
            grid-template-columns: 1fr;
        }
    }
    /* 🔥 Allow full event text (no cut-off) */
#doctor-view-calendar .fc .fc-daygrid-event {
    white-space: normal !important;
    overflow: visible !important;
    display: block !important;
}

/* 🔥 Prevent clipping inside day cell */
#doctor-view-calendar .fc .fc-daygrid-day-frame {
    overflow: visible !important;
}

/* 🔥 Allow event container to expand */
#doctor-view-calendar .fc .fc-daygrid-day-events {
    overflow: visible !important;
    max-height: none !important;
}

/* 🔥 Improve readability */
#doctor-view-calendar .fc-event-title,
#doctor-view-calendar .fc-event-time {
    font-size: 0.75rem;
    line-height: 1.2;
}
</style>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-7 d-flex">
        <div class="card flex-fill doctor-profile-card">
            <div class="card-header">
                <i class="ti ti-eye"></i> Doctor Details
            </div>
            <div class="card-body">
                <div class="row doctor-meta-grid">
                    <div class="col-md-6">
                        <div class="meta-item">
                            <span class="meta-label">Doctor ID</span>
                            <div class="meta-value">{{ $doctor->id }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="meta-item">
                            <span class="meta-label">Doctor Name</span>
                            <div class="meta-value">{{ $doctor->user->name }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="meta-item">
                            <span class="meta-label">Email Address</span>
                            <div class="meta-value">{{ $doctor->user->email }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="meta-item">
                            <span class="meta-label">Specialization</span>
                            <div class="meta-value">{{ $doctor->specialization }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="meta-item">
                            <span class="meta-label">Clinic</span>
                            <div class="meta-value">{{ $doctor->clinic_name ?: 'N/A' }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="meta-item">
                            <span class="meta-label">Experience</span>
                            <div class="meta-value">{{ $doctor->experience }} years</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="meta-item">
                            <span class="meta-label">Consultation Fees</span>
                            <div class="meta-value">${{ number_format($doctor->fees, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="meta-item">
                            <span class="meta-label">Address</span>
                            <div class="meta-value">{{ $doctor->address ?: 'N/A' }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="meta-item">
                            <span class="meta-label">Verification Status</span>
                            <div class="meta-value">
                            @if($doctor->is_verified)
                                <span class="badge bg-success"><i class="ti ti-check"></i> Verified</span>
                            @else
                                <span class="badge bg-warning">Pending Verification</span>
                            @endif
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr>

                <h6 class="fw-semibold">Upcoming Availability</h6>
                <div class="availability-strip">
                    @forelse($doctor->schedules->take(8) as $schedule)
                        <div class="availability-chip">
                            <div class="availability-chip-date">{{ $schedule->available_date->format('M d, Y') }}</div>
                            <div class="availability-chip-time">
                                <i class="ti ti-clock-hour-4 me-1"></i>
                                {{ $schedule->formatted_time_slot }}
                            </div>
                        </div>
                    @empty
                        <div class="availability-empty">No availability added yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-5 d-flex">
        <div class="card flex-fill calendar-shell">
            <div class="card-header">
                <i class="ti ti-calendar-event"></i> Availability Calendar
            </div>
            <div class="card-body">
                <div class="calendar-helper">
                    <i class="ti ti-pointer-heart"></i>
                    Select a highlighted day to load available appointment slots.
                </div>
                <div id="doctor-view-calendar" style="min-height: 380px;"></div>
            </div>
        </div>
    </div>
</div>

@if(auth()->user()->role === 'patient')
    <div class="row mt-4">
        <div class="col-12">
            <div class="card" id="appointment-booking-card">
                <div class="card-header">
                    <i class="ti ti-calendar-plus"></i> Appointment Booking
                </div>
                <div class="card-body">
                    @if(! $doctor->is_verified)
                        <div class="alert alert-warning mb-3">
                            This doctor is not verified yet, so appointments are currently blocked.
                        </div>
                    @elseif(! $patientIsVerified)
                        <div class="alert alert-warning mb-3">
                            Your patient account must be verified before you can book an appointment.
                        </div>
                    @endif

                    <form action="{{ route('appointments.book') }}" method="POST" id="doctor-booking-form">
                        @csrf
                        <input type="hidden" name="doctor_id" value="{{ $doctor->id }}">

                        <div class="row g-3">
                            <div class="col-lg-4">
                                <label class="form-label">Consultation Fee</label>
                                <div class="booking-summary">
                                    <strong>Consultation Fee</strong>
                                    ${{ number_format($doctor->fees, 2) }}
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <label for="appointment_date" class="form-label">Appointment Date *</label>
                                <input
                                    type="date"
                                    class="form-control @error('appointment_date') is-invalid @enderror"
                                    id="appointment_date"
                                    name="appointment_date"
                                    value="{{ old('appointment_date') }}"
                                    {{ $bookingAllowed ? '' : 'disabled' }}
                                    required
                                >
                                @error('appointment_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-4">
                                <label for="appointment_time" class="form-label">Time Slot *</label>
                                <select
                                    class="form-control @error('appointment_time') is-invalid @enderror"
                                    id="appointment_time"
                                    name="appointment_time"
                                    {{ $bookingAllowed ? '' : 'disabled' }}
                                    required
                                >
                                    <option value="">Select a date first</option>
                                </select>
                                @error('appointment_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-2 mb-2" id="time-slot-buttons"></div>
                                <small class="text-muted" id="slot-help-text">Select one of the highlighted calendar dates to load open slots.</small>
                            </div>
                            <div class="col-12">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                                @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-3 flex-wrap booking-actions">
                            <button type="submit" class="btn btn-primary" {{ $bookingAllowed ? '' : 'disabled' }}>
                                <i class="ti ti-calendar-check"></i> Confirm Booking
                            </button>

                            <a href="{{ route('appointments.index') }}" class="btn btn-secondary"><i class="ti ti-arrow-left"></i> My Appointments</a>
                           <a href="{{ route('doctors.index') }}" class="btn btn-secondary"><i class="ti ti-arrow-left"></i> Back</a>

                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- Doctor Reviews Section --}}
@include('components.doctor-reviews', ['doctorId' => $doctor->id])

@endsection

@section('scripts')
    <script src="{{ asset('assets/plugins/fullcalendar/index.global.min.js') }}"></script>
    <script>
        const doctorScheduleUrl = @json($scheduleApiUrl);
        const bookingAllowed = @json($bookingAllowed);
        const dateInput = document.getElementById('appointment_date');
        const timeSelect = document.getElementById('appointment_time');
        const slotButtons = document.getElementById('time-slot-buttons');
        const slotHelpText = document.getElementById('slot-help-text');
        const oldDate = @json(old('appointment_date'));
        const oldTime = @json(old('appointment_time'));
        let allowedDates = [];
        let selectedCalendarDate = oldDate || null;

        function updateSelectedCalendarDay(calendarRoot) {
            if (!calendarRoot) {
                return;
            }

            calendarRoot.querySelectorAll('.fc-day-selected').forEach(function (element) {
                element.classList.remove('fc-day-selected');
            });

            if (!selectedCalendarDate) {
                return;
            }

            const selectedCell = calendarRoot.querySelector('[data-date="' + selectedCalendarDate + '"]');
            if (selectedCell) {
                selectedCell.classList.add('fc-day-selected');
            }
        }

        function formatTimeLabel(slot) {
            if (!slot) return '';
            const parts = slot.split(':');
            const h = parseInt(parts[0], 10);
            const m = parts[1] || '00';
            const ampm = h >= 12 ? 'PM' : 'AM';
            const h12 = h % 12 === 0 ? 12 : h % 12;
            return `${h12}:${m} ${ampm}`;
        }

        function renderSlotButtons(slots) {
            if (!slotButtons) {
                return;
            }

            slotButtons.innerHTML = '';

            slots.forEach(function (slot) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-sm ' + (oldTime === slot || timeSelect.value === slot ? 'btn-primary' : 'btn-outline-primary');
                button.textContent = formatTimeLabel(slot);
                button.dataset.slot = slot;
                button.addEventListener('click', function () {
                    timeSelect.value = slot;
                    renderSlotButtons(slots);
                });
                slotButtons.appendChild(button);
            });
        }

        async function loadSlots(date) {
            if (!bookingAllowed || !date) {
                return;
            }

            const response = await fetch(doctorScheduleUrl + '?date=' + encodeURIComponent(date));
            const data = await response.json();
            const slots = data.slots || [];

            timeSelect.innerHTML = slots.length
                ? '<option value="">Select time slot</option>'
                : '<option value="">No slots available</option>';

            slots.forEach(function (slot) {
                const option = document.createElement('option');
                option.value = slot;
                option.textContent = formatTimeLabel(slot);
                option.selected = oldTime === slot;
                timeSelect.appendChild(option);
            });

            renderSlotButtons(slots);
            slotHelpText.textContent = slots.length
                ? 'Choose a listed time slot to auto-fill the booking form.'
                : 'No open time slots remain on this date.';
        }

        async function loadSchedule() {
            const response = await fetch(doctorScheduleUrl);
            const data = await response.json();

            allowedDates = data.dates || [];

            const calendar = new FullCalendar.Calendar(document.getElementById('doctor-view-calendar'), {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next',
                    center: 'title',
                    right: 'today'
                },
                height: 'auto',
                dayMaxEvents: true,

                    events: data.events || [],

                    eventContent: function(arg) {
                        return {
                            html: `
                                <div style="font-size:12px; font-weight:600;">
                                    🕒 
                                </div>
                            `
                        };
                    },

                    dayCellDidMount: function(info) {
                        info.el.classList.add(
                            allowedDates.includes(info.dateStr)
                                ? 'fc-day-available'
                                : 'fc-day-unavailable'
                        );
                    },

                    datesSet: function() {
                        updateSelectedCalendarDay(document.getElementById('doctor-view-calendar'));
                    },

                    dateClick: function(info) {
                        if (!bookingAllowed || !allowedDates.includes(info.dateStr)) {
                            return;
                        }

                        if (dateInput) {
                            dateInput.value = info.dateStr;
                        }

                        selectedCalendarDate = info.dateStr;
                        updateSelectedCalendarDay(document.getElementById('doctor-view-calendar'));
                        loadSlots(info.dateStr);
                    }
                });

             calendar.render();

            if (dateInput) {
                dateInput.min = new Date().toISOString().split('T')[0];
                dateInput.addEventListener('change', function () {
                    if (dateInput.value && !allowedDates.includes(dateInput.value)) {
                        dateInput.value = '';
                        selectedCalendarDate = null;
                        updateSelectedCalendarDay(document.getElementById('doctor-view-calendar'));
                        slotHelpText.textContent = 'Choose one of the highlighted calendar dates.';
                        timeSelect.innerHTML = '<option value="">Select an available date</option>';
                        slotButtons.innerHTML = '';
                        return;
                    }

                    selectedCalendarDate = dateInput.value || null;
                    updateSelectedCalendarDay(document.getElementById('doctor-view-calendar'));
                    loadSlots(dateInput.value);
                });

                if (oldDate) {
                    dateInput.value = oldDate;
                    selectedCalendarDate = oldDate;
                    loadSlots(oldDate);
                }
            }
        }

        loadSchedule();
    </script>
@endsection
