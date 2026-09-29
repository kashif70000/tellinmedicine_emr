@extends('layouts.layout')

@section('title', 'Edit Appointment')

@section('content')
<div class="card">
    <div class="card-header">
        <i class="ti ti-pencil"></i> Edit Appointment
    </div>
    <div class="card-body">
        <form action="{{ route('appointments.update', $appointment->id) }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-3">
                <label for="patient_id" class="form-label">Patient *</label>
                <input type="number" class="form-control @error('patient_id') is-invalid @enderror" id="patient_id" name="patient_id" value="{{ old('patient_id', $appointment->patient_id) }}" required>
                @error('patient_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="doctor_id" class="form-label">Doctor *</label>
                <input type="number" class="form-control @error('doctor_id') is-invalid @enderror" id="doctor_id" name="doctor_id" value="{{ old('doctor_id', $appointment->doctor_id) }}" required>
                @error('doctor_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="appointment_date" class="form-label">Date *</label>
                <input type="date" class="form-control @error('appointment_date') is-invalid @enderror" id="appointment_date" name="appointment_date" value="{{ old('appointment_date', $appointment->appointment_date->format('Y-m-d')) }}" required>
                @error('appointment_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="appointment_time" class="form-label">Time *</label>
                <input type="time" class="form-control @error('appointment_time') is-invalid @enderror" id="appointment_time" name="appointment_time" value="{{ old('appointment_time', \Carbon\Carbon::parse($appointment->appointment_time)->format('H:i')) }}" required>
                @error('appointment_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-control" id="status" name="status">
                    <option value="pending" {{ old('status', $appointment->status) == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ old('status', $appointment->status) == 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="cancelled" {{ old('status', $appointment->status) == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="notes" class="form-label">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes', $appointment->notes) }}</textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="ti ti-device-storage"></i> Update</button>
                <a href="{{ route('appointments.index') }}" class="btn btn-secondary"><i class="ti ti-x"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
