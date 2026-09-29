@extends('layouts.layout')

@section('title', 'Edit Payment')

@section('content')
<div class="card">
    <div class="card-header">
        <i class="ti ti-pencil"></i> Edit Payment
    </div>
    <div class="card-body">
        <form action="{{ route('payments.update', $payment->id) }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-3">
                <label for="appointment_id" class="form-label">Appointment ID *</label>
                <input type="number" class="form-control @error('appointment_id') is-invalid @enderror" id="appointment_id" name="appointment_id" value="{{ old('appointment_id', $payment->appointment_id) }}" required>
                @error('appointment_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="amount" class="form-label">Amount *</label>
                <input type="number" step="0.01" class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" value="{{ old('amount', $payment->amount) }}" required>
                @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="method" class="form-label">Payment Method *</label>
                <select class="form-control @error('method') is-invalid @enderror" id="method" name="method" required>
                    <option value="cash" {{ old('method', $payment->method) == 'cash' ? 'selected' : '' }}>Cash</option>
                    <option value="card" {{ old('method', $payment->method) == 'card' ? 'selected' : '' }}>Card</option>
                    <option value="online" {{ old('method', $payment->method) == 'online' ? 'selected' : '' }}>Online</option>
                </select>
                @error('method') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-control" id="status" name="status">
                    <option value="unpaid" {{ old('status', $payment->status) == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                    <option value="paid" {{ old('status', $payment->status) == 'paid' ? 'selected' : '' }}>Paid</option>
                    <option value="failed" {{ old('status', $payment->status) == 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="transaction_id" class="form-label">Transaction ID</label>
                <input type="text" class="form-control" id="transaction_id" name="transaction_id" value="{{ old('transaction_id', $payment->transaction_id) }}">
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="ti ti-device-storage"></i> Update</button>
                <a href="{{ route('payments.index') }}" class="btn btn-secondary"><i class="ti ti-x"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
