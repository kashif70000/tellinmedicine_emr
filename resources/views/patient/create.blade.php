@extends('layouts.layout')

@section('title', 'Add Patient')

@section('content')
<div class="card">
    <div class="card-header">
        <i class="ti ti-plus"></i> Add New Patient
    </div>
    <div class="card-body">
        <form action="{{ route('patients.store') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label for="user_id" class="form-label">User ID *</label>
                <input type="number" class="form-control @error('user_id') is-invalid @enderror" id="user_id" name="user_id" value="{{ old('user_id') }}" required>
                @error('user_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="age" class="form-label">Age *</label>
                <input type="number" class="form-control @error('age') is-invalid @enderror" id="age" name="age" value="{{ old('age') }}" min="0" max="150" required>
                @error('age') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="gender" class="form-label">Gender *</label>
                <select class="form-control @error('gender') is-invalid @enderror" id="gender" name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="male" {{ old('gender') == 'male' ? 'selected' : '' }}>Male</option>
                    <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>Female</option>
                    <option value="other" {{ old('gender') == 'other' ? 'selected' : '' }}>Other</option>
                </select>
                @error('gender') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="blood_group" class="form-label">Blood Group *</label>
                <input type="text" class="form-control @error('blood_group') is-invalid @enderror" id="blood_group" name="blood_group" value="{{ old('blood_group') }}" placeholder="e.g., O+" required>
                @error('blood_group') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="is_payment_method_verified" class="form-label">
                    <input type="checkbox" id="is_payment_method_verified" name="is_payment_method_verified" value="1" {{ old('is_payment_method_verified') ? 'checked' : '' }}>
                    Payment Method Verified
                </label>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="ti ti-device-storage"></i> Save</button>
                <a href="{{ route('patients.index') }}" class="btn btn-secondary"><i class="ti ti-x"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
