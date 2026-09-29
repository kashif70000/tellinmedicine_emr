@extends('layouts.layout')

@section('title', 'View Payment')

@section('content')
<div class="card">
    <div class="card-header">
        <i class="ti ti-eye"></i> Payment Details
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>ID:</strong> {{ $payment->id }}</p>
                <p><strong>Appointment:</strong> #{{ $payment->appointment_id }}</p>
                <p><strong>Patient:</strong> {{ $payment->appointment->patient->user->name }}</p>
                <p><strong>Doctor:</strong> {{ $payment->appointment->doctor->user->name }}</p>
            </div>
            <div class="col-md-6">
                <p><strong>Amount:</strong> <strong style="font-size: 1.5rem; color: #667eea;">${{ number_format($payment->amount, 2) }}</strong></p>
                <p><strong>Method:</strong> <span class="badge bg-secondary">{{ ucfirst($payment->method) }}</span></p>
                <p>
                    <strong>Status:</strong>
                    @if($payment->status == 'paid')
                        <span class="badge bg-success">Paid</span>
                    @elseif($payment->status == 'unpaid')
                        <span class="badge bg-warning">Unpaid</span>
                    @else
                        <span class="badge bg-danger">Failed</span>
                    @endif
                </p>
            </div>
        </div>

        @if($payment->transaction_id)
            <hr>
            <p><strong>Transaction ID:</strong> {{ $payment->transaction_id }}</p>
        @endif

        <hr>

        <div class="d-flex gap-2">
            <a href="{{ route('payments.edit', $payment->id) }}" class="btn btn-primary"><i class="ti ti-pencil"></i> Edit</a>
            <a href="{{ route('payments.index') }}" class="btn btn-secondary"><i class="ti ti-arrow-left"></i> Back</a>
        </div>
    </div>
</div>
@endsection
