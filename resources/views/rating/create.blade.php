@extends('layouts.layout')

@section('title', 'Add Rating')

@section('content')
<div class="card">
    <div class="card-header">
        <i class="ti ti-plus"></i> Add New Rating
    </div>
    <div class="card-body">
        <form action="{{ route('ratings.store') }}" method="POST">
            @csrf
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
                <label for="rating" class="form-label">Rating (1-5) *</label>
                <select class="form-control @error('rating') is-invalid @enderror" id="rating" name="rating" required>
                    <option value="">Select Rating</option>
                    <option value="1">1 - Poor</option>
                    <option value="2">2 - Fair</option>
                    <option value="3">3 - Good</option>
                    <option value="4">4 - Very Good</option>
                    <option value="5">5 - Excellent</option>
                </select>
                @error('rating') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="review" class="form-label">Review</label>
                <textarea class="form-control @error('review') is-invalid @enderror" id="review" name="review" rows="4">{{ old('review') }}</textarea>
                @error('review') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="ti ti-device-storage"></i> Save</button>
                <a href="{{ route('ratings.index') }}" class="btn btn-secondary"><i class="ti ti-x"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
