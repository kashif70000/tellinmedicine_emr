@extends('layouts.layout')

@section('title', 'Payments')

@section('content')
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <span><i class="ti ti-credit-card"></i> Payment Management</span>
            <a href="{{ route('payments.create') }}" class="btn btn-sm btn-light"><i class="ti ti-plus"></i> Add Payment</a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Appointment</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td>{{ $payment->id }}</td>
                            <td>#{{ $payment->appointment_id }}</td>
                            <td><strong>${{ number_format($payment->amount, 2) }}</strong></td>
                            <td><span class="badge bg-secondary">{{ ucfirst($payment->method) }}</span></td>
                            <td>
                                @if($payment->status == 'paid')
                                    <span class="badge bg-success">Paid</span>
                                @elseif($payment->status == 'unpaid')
                                    <span class="badge bg-warning">Unpaid</span>
                                @else
                                    <span class="badge bg-danger">Failed</span>
                                @endif
                            </td>
                            <td>{{ $payment->created_at->format('M d, Y') }}</td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="{{ route('payments.show', $payment->id) }}" class="btn btn-info"><i class="ti ti-eye"></i></a>
                                    <a href="{{ route('payments.edit', $payment->id) }}" class="btn btn-warning"><i class="ti ti-pencil"></i></a>
                                    <form action="{{ route('payments.destroy', $payment->id) }}" method="POST" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure?')"><i class="ti ti-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No payments found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $payments->links() }}
    </div>
</div>
@endsection
