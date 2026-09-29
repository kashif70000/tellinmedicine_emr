@extends('layouts.layout')

@section('title', 'Add Payment')

@section('content')
<div class="card">
    <div class="card-header">
        <i class="ti ti-plus"></i> Add New Payment
    </div>
    <div class="card-body">
        <form action="{{ route('payments.store') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label for="appointment_id" class="form-label">Appointment ID *</label>
                <input type="number" class="form-control @error('appointment_id') is-invalid @enderror" id="appointment_id" name="appointment_id" value="{{ old('appointment_id') }}" required>
                @error('appointment_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="amount" class="form-label">Amount *</label>
                <input type="number" step="0.01" class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" value="{{ old('amount') }}" required>
                @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="method" class="form-label">Payment Method *</label>
                <select class="form-control @error('method') is-invalid @enderror" id="method" name="method" required>
                    <option value="">Select Method</option>
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="online">Online</option>
                </select>
                @error('method') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-control" id="status" name="status">
                    <option value="unpaid">Unpaid</option>
                    <option value="paid">Paid</option>
                    <option value="failed">Failed</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="transaction_id" class="form-label">Transaction ID</label>
                <input type="text" class="form-control" id="transaction_id" name="transaction_id" value="{{ old('transaction_id') }}">
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="ti ti-device-storage"></i> Save</button>
                <a href="{{ route('payments.index') }}" class="btn btn-secondary"><i class="ti ti-x"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
