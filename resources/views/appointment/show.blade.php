@extends('layouts.layout')

@section('title', 'Appointment Details')

@section('content')

{{-- Page Header --}}
<div class="d-flex align-items-sm-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0">
            Appointment #AP{{ str_pad($appointment->id, 6, '0', STR_PAD_LEFT) }}
            @if($appointment->status === 'approved')
                <span class="badge bg-success fs-13 fw-medium ms-2">Approved</span>
            @elseif($appointment->status === 'completed')
                <span class="badge bg-primary fs-13 fw-medium ms-2"><i class="ti ti-circle-check me-1"></i>Completed</span>
            @elseif($appointment->status === 'cancelled' || $appointment->status === 'rejected')
                <span class="badge bg-danger fs-13 fw-medium ms-2">Cancelled</span>
            @else
                <span class="badge bg-warning fs-13 fw-medium ms-2">Pending</span>
            @endif

            @if($appointment->payment_status === 'paid')
                <span class="badge bg-soft-success border border-success text-success fs-13 fw-medium ms-1"><i class="ti ti-shield-check me-1"></i>Paid</span>
            @else
                <span class="badge bg-soft-warning border border-warning text-warning fs-13 fw-medium ms-1">Unpaid</span>
            @endif
        </h4>
        <p class="text-muted mb-0">Consultation summary and details.</p>
    </div>

    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('appointments.index') }}" class="btn btn-outline-primary">
            <i class="ti ti-arrow-left me-1"></i>Back to Appointments
        </a>

        @if(auth()->user()->role === 'admin')
            <a href="{{ route('appointments.edit', $appointment->id) }}" class="btn btn-primary">
                <i class="ti ti-pencil me-1"></i>Edit
            </a>
        @endif

        @if(in_array(auth()->user()->role, ['admin', 'doctor'], true) && $appointment->status === 'pending')
            <form action="{{ route('doctor.appointments.approve', $appointment) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-success">
                    <i class="ti ti-check me-1"></i>Approve
                </button>
            </form>
            <form action="{{ route('doctor.appointments.reject', $appointment) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-danger">
                    <i class="ti ti-x me-1"></i>Reject
                </button>
            </form>
        @endif
    </div>
</div>

@php
    $appointmentScheduledAt = \Carbon\Carbon::parse(
        $appointment->appointment_date->format('Y-m-d') . ' ' .
        \Carbon\Carbon::parse($appointment->appointment_time)->format('H:i:s')
    );
    $earliestStart = $appointmentScheduledAt->copy()->subMinutes(5);
    $sessionEndTime = $appointmentScheduledAt->copy()->addMinutes(30);
    $now = \Carbon\Carbon::now();
    $isWithinTimeWindow = $now->gte($earliestStart) && $now->lte($sessionEndTime);

    $callActive = $appointment->status === 'approved'
        && $appointment->call_started_at
        && \Carbon\Carbon::now()->lt($appointment->call_started_at->addSeconds(1800));

    $canJoinCall = $appointment->status === 'approved'
        && $appointment->payment_status === 'paid'
        && $callActive
        && (
            (auth()->user()->role === 'doctor' && $appointment->doctor->user_id === auth()->id())
            || (auth()->user()->role === 'patient' && $appointment->patient->user_id === auth()->id())
        );
@endphp

{{-- Active Call Rejoin Alert --}}
@if($canJoinCall)
    <div class="alert alert-success d-flex align-items-center justify-content-between p-3 mb-4 shadow-sm">
        <div class="d-flex align-items-center">
            <span class="avatar avatar-md bg-success text-white rounded-circle me-3 d-inline-flex align-items-center justify-content-center">
                <i class="ti ti-video fs-20"></i>
            </span>
            <div>
                <h6 class="fs-15 fw-bold mb-1">Consultation Call is Active!</h6>
                <p class="mb-0 fs-13">The video room is live. Click to enter the session.</p>
            </div>
        </div>
        <a href="{{ route('appointments.call', $appointment->id) }}" class="btn btn-light fw-bold text-success shadow-sm">
            <i class="ti ti-video me-1"></i>{{ auth()->user()->role === 'doctor' ? 'Rejoin Call' : 'Join Call' }}
        </a>
    </div>
@endif

{{-- Time availability alert for Doctor --}}
@if(auth()->user()->role === 'doctor' 
    && $appointment->doctor->user_id === auth()->id() 
    && $appointment->status === 'approved' 
    && $appointment->payment_status === 'paid' 
    && !$appointment->call_started_at)
    <div class="alert alert-info d-flex align-items-center p-3 mb-4 shadow-sm">
        <i class="ti ti-clock-play fs-24 me-3 text-info"></i>
        <div>
            <h6 class="fs-14 fw-semibold mb-0">Scheduled Video Call Window</h6>
            <p class="mb-0 fs-13">Available between <strong>{{ $earliestStart->format('g:i A') }}</strong> and <strong>{{ $sessionEndTime->format('g:i A') }}</strong>.</p>
        </div>
    </div>
@endif

{{-- TOP OVERVIEW CARDS GRID --}}
<div class="row">
    {{-- Doctor Info Card --}}
    <div class="col-md-6 col-lg-3 d-flex">
        <div class="card shadow-sm flex-fill w-100 mb-4">
            <div class="card-body p-3">
                <div class="d-flex align-items-center mb-3">
                    <span class="avatar avatar-md rounded-circle bg-soft-primary text-primary me-3 d-inline-flex align-items-center justify-content-center flex-shrink-0">
                        <i class="ti ti-stethoscope fs-22"></i>
                    </span>
                    <div class="overflow-hidden">
                        <p class="text-muted mb-0 fs-12 uppercase fw-semibold">Doctor</p>
                        <h6 class="fs-15 fw-bold mb-0 text-truncate">Dr. {{ $appointment->doctor->user->name ?? 'Doctor' }}</h6>
                    </div>
                </div>
                <div class="fs-13 text-muted border-top pt-2 mt-2">
                    <p class="mb-1"><i class="ti ti-briefcase me-1 text-primary"></i>{{ $appointment->doctor->specialization ?? 'General Practitioner' }}</p>
                    <p class="mb-0"><i class="ti ti-mail me-1 text-primary"></i>{{ $appointment->doctor->user->email ?? 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Patient Info Card --}}
    <div class="col-md-6 col-lg-3 d-flex">
        <div class="card shadow-sm flex-fill w-100 mb-4">
            <div class="card-body p-3">
                <div class="d-flex align-items-center mb-3">
                    <span class="avatar avatar-md rounded-circle bg-soft-success text-success me-3 d-inline-flex align-items-center justify-content-center flex-shrink-0">
                        <i class="ti ti-user-heart fs-22"></i>
                    </span>
                    <div class="overflow-hidden">
                        <p class="text-muted mb-0 fs-12 uppercase fw-semibold">Patient</p>
                        <h6 class="fs-15 fw-bold mb-0 text-truncate">{{ $appointment->patient->user->name ?? 'Patient' }}</h6>
                    </div>
                </div>
                <div class="fs-13 text-muted border-top pt-2 mt-2">
                    <p class="mb-1"><i class="ti ti-mail me-1 text-success"></i>{{ $appointment->patient->user->email ?? 'N/A' }}</p>
                    <p class="mb-0"><i class="ti ti-id me-1 text-success"></i>ID: #PAT{{ str_pad($appointment->patient->id ?? 0, 5, '0', STR_PAD_LEFT) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Schedule & Date Card --}}
    <div class="col-md-6 col-lg-3 d-flex">
        <div class="card shadow-sm flex-fill w-100 mb-4">
            <div class="card-body p-3">
                <div class="d-flex align-items-center mb-3">
                    <span class="avatar avatar-md rounded-circle bg-soft-info text-info me-3 d-inline-flex align-items-center justify-content-center flex-shrink-0">
                        <i class="ti ti-calendar-event fs-22"></i>
                    </span>
                    <div>
                        <p class="text-muted mb-0 fs-12 uppercase fw-semibold">Date & Time</p>
                        <h6 class="fs-14 fw-bold mb-0">{{ $appointment->appointment_date->format('D, d M Y') }}</h6>
                    </div>
                </div>
                <div class="fs-13 text-muted border-top pt-2 mt-2">
                    <p class="mb-1"><i class="ti ti-clock me-1 text-info"></i>{{ $appointment->formatted_time }}</p>
                    <p class="mb-0"><i class="ti ti-currency-dollar me-1 text-info"></i>Fee: ${{ number_format($appointment->fee_snapshot ?? $appointment->doctor->fees ?? 0, 2) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Consultation Duration Card --}}
    <div class="col-md-6 col-lg-3 d-flex">
        <div class="card shadow-sm flex-fill w-100 mb-4">
            <div class="card-body p-3">
                <div class="d-flex align-items-center mb-3">
                    <span class="avatar avatar-md rounded-circle bg-soft-warning text-warning me-3 d-inline-flex align-items-center justify-content-center flex-shrink-0">
                        <i class="ti ti-hourglass-high fs-22"></i>
                    </span>
                    <div>
                        <p class="text-muted mb-0 fs-12 uppercase fw-semibold">Duration Tracked</p>
                        <h6 class="fs-14 fw-bold mb-0 text-dark">
                            {{ $appointment->status === 'completed' || $appointment->duration_seconds ? $appointment->formatted_duration : 'Not Started' }}
                        </h6>
                    </div>
                </div>
                <div class="fs-13 text-muted border-top pt-2 mt-2">
                    @if($appointment->call_started_at)
                        <p class="mb-1"><i class="ti ti-player-play me-1 text-warning"></i>Started: {{ $appointment->call_started_at->format('g:i A') }}</p>
                    @else
                        <p class="mb-1"><i class="ti ti-circle-x me-1 text-muted"></i>Session inactive</p>
                    @endif
                    @if($appointment->completed_at)
                        <p class="mb-0"><i class="ti ti-player-stop me-1 text-warning"></i>Ended: {{ $appointment->completed_at->format('g:i A') }}</p>
                    @else
                        <p class="mb-0"><i class="ti ti-minus me-1 text-muted"></i>Pending end</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if($appointment->notes)
    <div class="card shadow-sm mb-4">
        <div class="card-body p-3">
            <h6 class="fw-bold mb-2"><i class="ti ti-notes text-primary me-1"></i>Appointment Notes</h6>
            <p class="mb-0 text-muted fs-14">{{ $appointment->notes }}</p>
        </div>
    </div>
@endif

{{-- PRESCRIPTION SECTION --}}
@if($appointment->prescription)
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between">
            <h5 class="fw-bold mb-0 text-primary">
                <i class="ti ti-prescription me-1"></i>Consultation Prescription
            </h5>
            <a href="{{ route('prescriptions.show', $appointment->prescription->id) }}" class="btn btn-sm btn-outline-primary">
                <i class="ti ti-printer me-1"></i>View & Print Prescription
            </a>
        </div>
        <div class="card-body">
            @if($appointment->prescription->diagnosis)
                <div class="mb-3 p-3 bg-light rounded-3">
                    <span class="badge bg-primary mb-2">Diagnosis</span>
                    <h6 class="fs-14 fw-semibold text-dark mb-0">{{ $appointment->prescription->diagnosis }}</h6>
                </div>
            @endif

            @if(!empty($appointment->prescription->medicines))
                <h6 class="fw-bold mb-2 fs-14">Prescribed Medicines</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm border align-middle">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Medicine Name</th>
                                <th>Dosage</th>
                                <th>Intake Method</th>
                                <th>Dose Timing</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($appointment->prescription->medicines as $idx => $med)
                                <tr>
                                    <td>{{ $idx + 1 }}</td>
                                    <td class="fw-semibold text-dark">{{ $med['name'] ?? 'N/A' }}</td>
                                    <td><span class="badge bg-soft-info text-info border border-info">{{ $med['dosage'] ?? 'N/A' }}</span></td>
                                    <td><span class="badge bg-soft-primary text-primary border border-primary">{{ $med['intake'] ?? $med['instructions'] ?? 'N/A' }}</span></td>
                                    <td><span class="badge bg-soft-success text-success border border-success">{{ $med['duration'] ?? 'N/A' }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if($appointment->prescription->notes)
                <div class="p-3 border rounded-3 bg-light-50">
                    <strong class="fs-13 text-dark mb-1 d-block"><i class="ti ti-info-circle me-1"></i>Doctor's Notes / Instructions:</strong>
                    <p class="mb-0 fs-13 text-muted">{{ $appointment->prescription->notes }}</p>
                </div>
            @endif
        </div>
    </div>
@endif

{{-- PATIENT RATING & REVIEW SECTION --}}
@if($appointment->rating)
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-bottom">
            <h5 class="fw-bold mb-0 text-warning">
                <i class="ti ti-star me-1"></i>Patient Review & Rating
            </h5>
        </div>
        <div class="card-body">
            <div class="d-flex align-items-center mb-2">
                <div class="me-2 text-warning fs-18">
                    @for($i = 1; $i <= 5; $i++)
                        @if($i <= $appointment->rating->rating)
                            <i class="ti ti-star-filled"></i>
                        @else
                            <i class="ti ti-star"></i>
                        @endif
                    @endfor
                </div>
                <span class="fw-bold fs-15 text-dark me-2">{{ number_format($appointment->rating->rating, 1) }} / 5.0</span>
                <span class="text-muted fs-13">by {{ $appointment->patient->user->name ?? 'Patient' }}</span>
            </div>
            @if($appointment->rating->review)
                <blockquote class="blockquote fs-14 bg-light p-3 rounded-3 mb-0 border-start border-warning border-4">
                    <p class="mb-0 text-dark fst-italic">"{{ $appointment->rating->review }}"</p>
                </blockquote>
            @endif
        </div>
    </div>
@endif

{{-- STRIPE PAYMENT FORM (IF UNPAID PATIENT) --}}
@if(
    $appointment->status === 'approved' &&
    $appointment->payment_status !== 'paid' &&
    auth()->user()->role === 'patient'
)
    <div class="card shadow-sm border-warning mb-4">
        <div class="card-header bg-soft-warning">
            <h5 class="fw-bold mb-0 text-warning"><i class="ti ti-credit-card me-1"></i>Complete Payment</h5>
        </div>
        <div class="card-body">
            <p class="text-muted fs-14">Please complete the fee payment of <strong>${{ number_format($appointment->fee_snapshot ?? $appointment->doctor->fees, 2) }}</strong> to unlock the video consultation call.</p>
            <div class="mb-3">
                <label class="form-label fw-semibold">Card Details</label>
                <div id="card-element" class="form-control p-3"></div>
            </div>

            <button class="btn btn-primary px-4 fw-bold" onclick="payNow({{ $appointment->id }})">
                <i class="ti ti-lock me-1"></i>Pay Now (${{ number_format($appointment->fee_snapshot ?? $appointment->doctor->fees, 2) }})
            </button>
        </div>
    </div>
@endif

@endsection

@section('scripts')
<script>
let stripe = Stripe("{{ config('services.stripe.key') }}");
let elements = stripe.elements();
let card = null;

document.addEventListener("DOMContentLoaded", function () {
    const el = document.getElementById("card-element");
    if (el) {
        card = elements.create("card");
        card.mount("#card-element");
    }
});

async function payNow(appointmentId) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    if (!card) {
        Swal.fire({
            icon: 'error',
            title: 'Stripe Not Ready',
            text: 'Card form not loaded. Refresh page.'
        });
        return;
    }

    try {
        const res = await fetch(`/appointments/${appointmentId}/payment-intent`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-CSRF-TOKEN": csrfToken
            }
        });

        const data = await res.json();

        if (!res.ok) {
            Swal.fire({
                icon: 'error',
                title: 'Payment Failed',
                text: data.error || "Payment intent failed"
            });
            return;
        }

        const result = await stripe.confirmCardPayment(data.clientSecret, {
            payment_method: {
                card: card
            }
        });

        if (result.error) {
            Swal.fire({
                icon: 'error',
                title: 'Stripe Error',
                text: result.error.message
            });
            return;
        }

        if (result.paymentIntent.status === "succeeded") {
            const confirmRes = await fetch(`/appointments/payment/confirm`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-CSRF-TOKEN": csrfToken
                },
                body: JSON.stringify({
                    payment_intent_id: result.paymentIntent.id
                })
            });

            const confirmData = await confirmRes.json();

            if (confirmData.error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Confirmation Failed',
                    text: confirmData.error
                });
                return;
            }

            Swal.fire({
                icon: 'success',
                title: 'Payment Successful',
                text: 'Your payment has been completed successfully!'
            }).then(() => {
                location.reload();
            });
        }
    } catch (err) {
        console.error(err);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Payment failed. Check console.'
        });
    }
}
</script>
@endsection