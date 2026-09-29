@extends('layouts.layout')

@section('title', 'Book Appointment')

@php
    $patientVerified = $patient?->is_payment_method_verified ?? false;
@endphp

@section('styles')
<style>
    .booking-card,
    .booking-calendar-card {
        border: 0;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
    }
    .booking-card .card-header,
    .booking-calendar-card .card-header {
        background: linear-gradient(135deg, #f8fbff, #eef6ff);
        border-bottom: 1px solid rgba(148, 163, 184, 0.15);
        font-weight: 700;
        color: #0f172a;
        padding: 1rem 1.25rem;
    }
    .booking-card .card-body,
    .booking-calendar-card .card-body {
        padding: 1.4rem;
    }
    .booking-form .form-control,
    .booking-form .form-select {
        border-radius: 14px;
        min-height: 48px;
        border-color: #dbe3ef;
        box-shadow: none;
    }
    .booking-form textarea.form-control {
        min-height: auto;
    }
    .field-panel {
        padding: 1rem 1.1rem;
        border-radius: 18px;
        background: linear-gradient(180deg, #ffffff, #f8fafc);
        border: 1px solid rgba(226, 232, 240, 0.95);
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.04);
    }
    .fee-display-card {
        padding: 1rem 1.1rem;
        border-radius: 18px;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.08), rgba(16, 185, 129, 0.08));
        border: 1px solid rgba(96, 165, 250, 0.18);
        color: #0f172a;
        font-weight: 700;
    }
    .calendar-tip {
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
    #time-slot-buttons .btn {
        border-radius: 12px;
        padding: .58rem .95rem;
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
    #doctor-availability-calendar .fc {
        --fc-border-color: #e2e8f0;
        --fc-page-bg-color: transparent;
        --fc-neutral-bg-color: transparent;
        --fc-today-bg-color: rgba(59, 130, 246, 0.06);
    }
    #doctor-availability-calendar .fc-toolbar.fc-header-toolbar {
        margin-bottom: 0.75rem;
        gap: .5rem;
        flex-wrap: wrap;
    }
    #doctor-availability-calendar .fc .fc-toolbar-title {
        font-size: 1.05rem;
        font-weight: 700;
        color: #0f172a;
    }
    #doctor-availability-calendar .fc .fc-button {
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
    #doctor-availability-calendar .fc .fc-button:hover {
        background: #f1f5f9;
        color: #0f172a;
    }
    #doctor-availability-calendar .fc .fc-button-primary:not(:disabled).fc-button-active,
    #doctor-availability-calendar .fc .fc-button-primary:not(:disabled):active {
        background: #2563eb;
        border-color: #2563eb;
        color: #fff;
    }
    #doctor-availability-calendar .fc .fc-daygrid-day {
        padding: 2px;
    }
    #doctor-availability-calendar .fc .fc-daygrid-day-top {
        justify-content: flex-end;
    }
    #doctor-availability-calendar .fc .fc-daygrid-day-number {
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
    #doctor-availability-calendar .fc .fc-daygrid-day-frame {
        min-height: 65px;
        padding: 4px;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        position: relative;
    }
    #doctor-availability-calendar .fc .fc-day-other .fc-daygrid-day-frame {
        opacity: .45;
    }
    #doctor-availability-calendar .fc-day-available .fc-daygrid-day-frame {
        background: #f0f9ff;
        border-color: #7dd3fc;
        cursor: pointer;
    }
    #doctor-availability-calendar .fc-day-available .fc-daygrid-day-number {
        color: #0284c7;
        background: #e0f2fe;
    }
    #doctor-availability-calendar .fc-day-unavailable .fc-daygrid-day-frame {
        opacity: .55;
    }
    #doctor-availability-calendar .fc-day-selected .fc-daygrid-day-frame {
        background: #0284c7 !important;
        border-color: #0284c7 !important;
        box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
    }
    #doctor-availability-calendar .fc-day-selected .fc-daygrid-day-number {
        color: #fff !important;
        background: rgba(255, 255, 255, 0.25) !important;
    }
    #doctor-availability-calendar .fc-day-selected .fc-daygrid-event,
    #doctor-availability-calendar .fc-day-selected .fc-event-title,
    #doctor-availability-calendar .fc-day-selected .fc-event-time {
        color: #fff !important;
    }
    #doctor-availability-calendar .fc .fc-daygrid-event,
    #doctor-availability-calendar .fc .fc-daygrid-event-harness {
        display: none !important;
    }
    :root[data-bs-theme="dark"] .booking-card,
    :root[data-bs-theme="dark"] .booking-calendar-card {
        background: #03041a;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
    }
    :root[data-bs-theme="dark"] .booking-card .card-header,
    :root[data-bs-theme="dark"] .booking-calendar-card .card-header {
        background: linear-gradient(135deg, rgba(10, 14, 35, 0.98), rgba(20, 28, 45, 0.95));
        border-bottom-color: rgba(159, 172, 191, 0.14);
        color: #e6eefc;
    }
    :root[data-bs-theme="dark"] .booking-form .form-control,
    :root[data-bs-theme="dark"] .booking-form .form-select {
        background-color: rgba(255, 255, 255, 0.03);
        border-color: rgba(159, 172, 191, 0.16);
        color: #e6eefc;
    }
    :root[data-bs-theme="dark"] .booking-form select.form-control,
    :root[data-bs-theme="dark"] .booking-form select.form-select {
        background-color: #0f172a !important;
        color: #e6eefc !important;
    }
    :root[data-bs-theme="dark"] .booking-form select.form-control option,
    :root[data-bs-theme="dark"] .booking-form select.form-select option {
        background-color: #0f172a;
        color: #e6eefc;
    }
    :root[data-bs-theme="dark"] .booking-form .form-control::placeholder,
    :root[data-bs-theme="dark"] .booking-form .form-select::placeholder {
        color: #9facbf;
    }
    :root[data-bs-theme="dark"] .field-panel,
    :root[data-bs-theme="dark"] .fee-display-card {
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.02));
        border-color: rgba(159, 172, 191, 0.14);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.22);
        color: #e6eefc;
    }
    :root[data-bs-theme="dark"] .calendar-tip,
    :root[data-bs-theme="dark"] .form-label {
        color: #e6eefc;
    }
    :root[data-bs-theme="dark"] .calendar-tip {
        background: rgba(59, 130, 246, 0.14);
    }
    :root[data-bs-theme="dark"] #date-help-text {
        color: #9facbf !important;
    }
    :root[data-bs-theme="dark"] #doctor-availability-calendar .fc .fc-toolbar-title {
        color: #e6eefc;
    }
    :root[data-bs-theme="dark"] #doctor-availability-calendar .fc .fc-button {
        background: rgba(148, 163, 184, 0.12);
        color: #cbd5e1;
    }
    :root[data-bs-theme="dark"] #doctor-availability-calendar .fc .fc-button:hover,
    :root[data-bs-theme="dark"] #doctor-availability-calendar .fc .fc-button:focus {
        background: rgba(148, 163, 184, 0.2);
        color: #f8fafc;
    }
    :root[data-bs-theme="dark"] #doctor-availability-calendar .fc .fc-daygrid-day-number {
        color: #cbd5e1;
    }
    :root[data-bs-theme="dark"] #doctor-availability-calendar .fc .fc-daygrid-day-frame {
        background: rgba(255, 255, 255, 0.03);
        border-color: rgba(159, 172, 191, 0.14);
    }
    :root[data-bs-theme="dark"] #doctor-availability-calendar .fc .fc-daygrid-day:not(.fc-day-other):hover .fc-daygrid-day-frame {
        box-shadow: 0 16px 30px rgba(0, 0, 0, 0.28);
    }
    :root[data-bs-theme="dark"] #doctor-availability-calendar .fc-day-available .fc-daygrid-day-frame {
        background: linear-gradient(180deg, rgba(12, 21, 41, 0.96), rgba(10, 33, 59, 0.96));
        border-color: rgba(96, 165, 250, 0.32);
    }
    :root[data-bs-theme="dark"] #doctor-availability-calendar .fc-day-unavailable .fc-daygrid-day-frame {
        background: rgba(255, 255, 255, 0.02);
    }
    :root[data-bs-theme="dark"] #doctor-availability-calendar .fc .fc-daygrid-event {
        background: rgba(59, 130, 246, 0.18);
        color: #93c5fd;
    }
    @media (max-width: 767.98px) {
        .booking-card .card-body,
        .booking-calendar-card .card-body {
            padding: 1rem;
        }
    }
</style>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-7 d-flex">
        <div class="card flex-fill booking-card">
            <div class="card-header">
                <i class="ti ti-plus"></i> Book Appointment
            </div>
            <div class="card-body">
                @if(! $patientVerified)
                    <div class="alert alert-warning">
                        Your patient account must be verified before you can book an appointment.
                    </div>
                @endif

                <form action="{{ route('appointments.book') }}" method="POST" id="appointment-booking-form" class="booking-form">
                    @csrf
                    <div class="mb-3 field-panel">
                        <label for="doctor_id" class="form-label">Doctor *</label>
                        <select class="form-control @error('doctor_id') is-invalid @enderror" id="doctor_id" name="doctor_id" {{ $patientVerified ? '' : 'disabled' }}>
                            <option value="">Select doctor</option>
                            @foreach($doctors as $doctor)
                                <option
                                    value="{{ $doctor->id }}"
                                    data-fee="{{ $doctor->fees }}"
                                    data-schedule-url="{{ route('doctor.schedule.show', $doctor) }}"
                                    {{ (string) old('doctor_id', $selectedDoctor?->id) === (string) $doctor->id ? 'selected' : '' }}
                                >
                                    {{ $doctor->user->name }} - {{ $doctor->specialization }}
                                </option>
                            @endforeach
                        </select>
                        @error('doctor_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Consultation Fee</label>
                        <div class="fee-display-card" id="doctor-fee-display">
                            {{ $selectedDoctor ? '$' . number_format($selectedDoctor->fees, 2) : 'Select a doctor to view fees' }}
                        </div>
                    </div>

                    <div class="mb-3 field-panel">
                        <label for="appointment_date" class="form-label">Available Date *</label>
                        <input type="date" class="form-control @error('appointment_date') is-invalid @enderror" id="appointment_date" name="appointment_date" value="{{ old('appointment_date') }}" {{ $patientVerified ? '' : 'disabled' }}>
                        <small class="text-muted" id="date-help-text">Only schedule dates added by the doctor can be selected.</small>
                        @error('appointment_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3 field-panel">
                        <label for="appointment_time" class="form-label">Time Slot *</label>
                        <select class="form-control @error('appointment_time') is-invalid @enderror" id="appointment_time" name="appointment_time" {{ $patientVerified ? '' : 'disabled' }}>
                            <option value="">Select a date first</option>
                        </select>
                        <div class="d-flex flex-wrap gap-2 mt-2" id="time-slot-buttons"></div>
                        @error('appointment_time') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3 field-panel">
                        <label for="subtitle_language" class="form-label">Preferred Subtitle Language</label>
                        <select class="form-control @error('subtitle_language') is-invalid @enderror" id="subtitle_language" name="subtitle_language" {{ $patientVerified ? '' : 'disabled' }}>
                            @foreach(config('subtitles.languages', []) as $code => $lang)
                                <option value="{{ $code }}" {{ old('subtitle_language', config('subtitles.default')) === $code ? 'selected' : '' }}>
                                    {{ $lang['name'] }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Live subtitles will translate spoken speech into your selected language during the call.</small>
                        @error('subtitle_language') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3 field-panel">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex gap-2 flex-wrap booking-actions">
                        <button type="submit" class="btn btn-primary" {{ $patientVerified ? '' : 'disabled' }}>
                            <i class="ti ti-calendar-check"></i> Confirm Booking
                        </button>
                        <a href="{{ route('appointments.index') }}" class="btn btn-secondary"><i class="ti ti-arrow-left"></i> Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-5 d-flex">
        <div class="card flex-fill booking-calendar-card">
            <div class="card-header">
                <i class="ti ti-calendar-event"></i> Availability Calendar
            </div>
            <div class="card-body">
                <div class="calendar-tip">
                    <i class="ti ti-pointer-heart"></i>
                    Pick one of the highlighted days to reveal available time slots.
                </div>
                <div id="doctor-availability-calendar" style="min-height: 380px;"></div>
            </div>
        </div>
       
    </div>
</div>
@endsection

@section('scripts')
    <script src="{{ asset('assets/plugins/fullcalendar/index.global.min.js') }}"></script>
    <script>
        const doctorSelect = document.getElementById('doctor_id');
        const dateInput = document.getElementById('appointment_date');
        const timeSelect = document.getElementById('appointment_time');
        const feeDisplay = document.getElementById('doctor-fee-display');
        const dateHelpText = document.getElementById('date-help-text');
        const slotButtons = document.getElementById('time-slot-buttons');
        const patientVerified = @json($patientVerified);
        const oldDate = @json(old('appointment_date'));
        const oldTime = @json(old('appointment_time'));
        let allowedDates = [];
        let currentScheduleUrl = null;
        let currentCalendar = null;
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

        function updateFeeDisplay() {
            const selectedOption = doctorSelect.options[doctorSelect.selectedIndex];
            const fee = selectedOption ? selectedOption.dataset.fee : null;
            feeDisplay.textContent = fee ? '$' + Number(fee).toFixed(2) : 'Select a doctor to view fees';
        }

        function renderSlotButtons(slots) {
            slotButtons.innerHTML = '';

            slots.forEach(function (slot) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-sm ' + (timeSelect.value === slot ? 'btn-primary' : 'btn-outline-primary');
                button.textContent = formatTimeLabel(slot);
                button.addEventListener('click', function () {
                    timeSelect.value = slot;
                    renderSlotButtons(slots);
                });
                slotButtons.appendChild(button);
            });
        }

        async function loadSlots() {
            if (!currentScheduleUrl || !dateInput.value || !patientVerified) {
                timeSelect.innerHTML = '<option value="">Select a date first</option>';
                slotButtons.innerHTML = '';
                return;
            }

            if (!allowedDates.includes(dateInput.value)) {
                timeSelect.innerHTML = '<option value="">Selected date is unavailable</option>';
                slotButtons.innerHTML = '';
                return;
            }

            const response = await fetch(currentScheduleUrl + '?date=' + encodeURIComponent(dateInput.value));
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
        }

        async function loadDoctorCalendar() {
            const selectedOption = doctorSelect.options[doctorSelect.selectedIndex];

            if (!selectedOption || !selectedOption.value) {
                allowedDates = [];
                currentScheduleUrl = null;
                selectedCalendarDate = null;
                updateFeeDisplay();
                timeSelect.innerHTML = '<option value="">Select a date first</option>';
                slotButtons.innerHTML = '';
                if (currentCalendar) {
                    currentCalendar.removeAllEvents();
                    updateSelectedCalendarDay(document.getElementById('doctor-availability-calendar'));
                }
                return;
            }

            currentScheduleUrl = selectedOption.dataset.scheduleUrl;
            updateFeeDisplay();

            const response = await fetch(currentScheduleUrl);
            const data = await response.json();

            allowedDates = data.dates || [];

            if (!currentCalendar) {
                currentCalendar = new FullCalendar.Calendar(document.getElementById('doctor-availability-calendar'), {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next',
                        center: 'title',
                        right: 'today'
                    },
                    height: 'auto',
                    dayMaxEvents: true,
                    dayCellDidMount: function(info) {
                        info.el.classList.add(allowedDates.includes(info.dateStr) ? 'fc-day-available' : 'fc-day-unavailable');
                    },
                    datesSet: function() {
                        updateSelectedCalendarDay(document.getElementById('doctor-availability-calendar'));
                    },
                    events: data.events || [],
                    dateClick: function(info) {
                        if (!allowedDates.includes(info.dateStr)) {
                            return;
                        }

                        dateInput.value = info.dateStr;
                        selectedCalendarDate = info.dateStr;
                        updateSelectedCalendarDay(document.getElementById('doctor-availability-calendar'));
                        loadSlots();
                    }
                });

                currentCalendar.render();
            } else {
                currentCalendar.removeAllEvents();
                (data.events || []).forEach(function (event) {
                    currentCalendar.addEvent(event);
                });
                setTimeout(function () {
                    document.querySelectorAll('#doctor-availability-calendar [data-date]').forEach(function (cell) {
                        const date = cell.getAttribute('data-date');
                        cell.classList.remove('fc-day-available', 'fc-day-unavailable');
                        cell.classList.add(allowedDates.includes(date) ? 'fc-day-available' : 'fc-day-unavailable');
                    });
                    updateSelectedCalendarDay(document.getElementById('doctor-availability-calendar'));
                }, 0);
            }

            dateHelpText.textContent = allowedDates.length
                ? 'Only dates with availability are allowed.'
                : 'This doctor has not added any available dates yet.';

            if (dateInput.value && !allowedDates.includes(dateInput.value)) {
                dateInput.value = '';
                selectedCalendarDate = null;
                timeSelect.innerHTML = '<option value="">Select an available date</option>';
                slotButtons.innerHTML = '';
            }
        }

        doctorSelect.addEventListener('change', async function () {
            await loadDoctorCalendar();
            await loadSlots();
        });

        dateInput.addEventListener('change', function () {
            if (dateInput.value && !allowedDates.includes(dateInput.value)) {
                dateInput.value = '';
                selectedCalendarDate = null;
                updateSelectedCalendarDay(document.getElementById('doctor-availability-calendar'));
                dateHelpText.textContent = 'Choose one of the highlighted dates from the calendar.';
                timeSelect.innerHTML = '<option value="">Select an available date</option>';
                slotButtons.innerHTML = '';
                return;
            }

            selectedCalendarDate = dateInput.value || null;
            updateSelectedCalendarDay(document.getElementById('doctor-availability-calendar'));
            loadSlots();
        });

        if (patientVerified) {
            dateInput.min = new Date().toISOString().split('T')[0];
        }

        if (doctorSelect.value) {
            loadDoctorCalendar().then(function () {
                if (oldDate) {
                    dateInput.value = oldDate;
                    selectedCalendarDate = oldDate;
                }
                return loadSlots();
            });
        } else {
            currentCalendar = new FullCalendar.Calendar(document.getElementById('doctor-availability-calendar'), {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next',
                    center: 'title',
                    right: 'today'
                },
                height: 'auto',
                dayMaxEvents: true,
                events: []
            });

            currentCalendar.render();
        }



    </script>
@endsection
