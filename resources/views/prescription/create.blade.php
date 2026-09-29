@extends('layouts.layout')

@section('title', 'Add Prescription')

@section('content')
<div class="card">
    <div class="card-header">
        <i class="ti ti-plus"></i> Add New Prescription
    </div>
    <div class="card-body">
        <form action="{{ route('prescriptions.store') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label for="appointment_id" class="form-label">Appointment ID *</label>
                <input type="number" class="form-control @error('appointment_id') is-invalid @enderror" id="appointment_id" name="appointment_id" value="{{ old('appointment_id') }}" required>
                @error('appointment_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="doctor_id" class="form-label">Doctor ID *</label>
                <input type="number" class="form-control @error('doctor_id') is-invalid @enderror" id="doctor_id" name="doctor_id" value="{{ old('doctor_id') }}" required>
                @error('doctor_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="patient_id" class="form-label">Patient ID *</label>
                <input type="number" class="form-control @error('patient_id') is-invalid @enderror" id="patient_id" name="patient_id" value="{{ old('patient_id') }}" required>
                @error('patient_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="notes" class="form-label">Notes *</label>
                <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="4" required>{{ old('notes') }}</textarea>
                @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="medicines" class="form-label">Medicines (JSON Array) *</label>
                <textarea class="form-control @error('medicines') is-invalid @enderror" id="medicines" name="medicines" rows="4" required>{{ old('medicines') }}</textarea>
                @error('medicines') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <small>[{"name":"Aspirin","dosage":"500mg","frequency":"2x daily"}]</small>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="ti ti-device-storage"></i> Save</button>
                <a href="{{ route('prescriptions.index') }}" class="btn btn-secondary"><i class="ti ti-x"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
