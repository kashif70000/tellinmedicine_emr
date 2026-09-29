@extends('layouts.layout')

@section('title', 'Add Invoice')

@section('content')
<div class="card">
    <div class="card-header">
        <i class="ti ti-plus"></i> Add New Invoice
    </div>
    <div class="card-body">
        <form action="{{ route('invoices.store') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label for="patient_id" class="form-label">Patient ID *</label>
                <input type="number" class="form-control @error('patient_id') is-invalid @enderror" id="patient_id" name="patient_id" value="{{ old('patient_id') }}" required>
                @error('patient_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="total_amount" class="form-label">Total Amount *</label>
                <input type="number" step="0.01" class="form-control @error('total_amount') is-invalid @enderror" id="total_amount" name="total_amount" value="{{ old('total_amount') }}" required>
                @error('total_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="issued_date" class="form-label">Issued Date *</label>
                <input type="date" class="form-control @error('issued_date') is-invalid @enderror" id="issued_date" name="issued_date" value="{{ old('issued_date') }}" required>
                @error('issued_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-control" id="status" name="status">
                    <option value="pending">Pending</option>
                    <option value="paid">Paid</option>
                </select>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="ti ti-device-storage"></i> Save</button>
                <a href="{{ route('invoices.index') }}" class="btn btn-secondary"><i class="ti ti-x"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
