@extends('layouts.layout')

@section('title', 'Edit Doctor')

@section('content')
<div class="card">
    <div class="card-header">
        <i class="ti ti-pencil"></i> Edit Doctor
    </div>
    <div class="card-body">
        <form action="{{ route('doctors.update', $doctor->id) }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-3">
                <label for="user_id" class="form-label">User ID *</label>
                <input type="number" class="form-control @error('user_id') is-invalid @enderror" id="user_id" name="user_id" value="{{ old('user_id', $doctor->user_id) }}" required>
                @error('user_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="specialization" class="form-label">Specialization *</label>
                <input type="text" class="form-control @error('specialization') is-invalid @enderror" id="specialization" name="specialization" value="{{ old('specialization', $doctor->specialization) }}" required>
                @error('specialization') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="experience" class="form-label">Experience (Years) *</label>
                <input type="number" class="form-control @error('experience') is-invalid @enderror" id="experience" name="experience" value="{{ old('experience', $doctor->experience) }}" min="0" required>
                @error('experience') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="fees" class="form-label">Consultation Fees *</label>
                <input type="number" step="0.01" class="form-control @error('fees') is-invalid @enderror" id="fees" name="fees" value="{{ old('fees', $doctor->fees) }}" required>
                @error('fees') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="is_verified" class="form-label">
                    <input type="checkbox" id="is_verified" name="is_verified" value="1" {{ old('is_verified', $doctor->is_verified) ? 'checked' : '' }}>
                    Verified
                </label>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="ti ti-device-storage"></i> Update</button>
                <a href="{{ route('doctors.index') }}" class="btn btn-secondary"><i class="ti ti-x"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
