@extends('layouts.layout')

@section('title', 'Payment Verification')

@section('content')

<div class="content">
    <div class="container">

        <!-- Header -->
        <div class="mb-4">
            <h4 class="fw-bold">Payment Verification</h4>
            <p class="text-muted">Verify your account to unlock booking and doctor access.</p>
        </div>

        <!-- Error Alert -->
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">

                <div class="row g-4">

                    <!-- Card Preview -->
                    <div class="col-lg-5">
                        <div class="p-4 rounded-4 text-white"
                             style="background: linear-gradient(135deg, #4e73df, #224abe); min-height:200px;">
                            <p class="mb-4">Secure Payment</p>
                            <h5 class="mb-1">{{ auth()->user()->name }}</h5>
                            <p class="mb-0">**** **** **** ****</p>

                            <div class="d-flex justify-content-between mt-4">
                                <small>Powered by Stripe</small>
                                <i class="ti ti-credit-card"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Form -->
                    <div class="col-lg-7">
                      <form id="payment-form">

                            @csrf

                            <!-- Cardholder -->
                            <div class="mb-3">
                                <label class="form-label">Cardholder Name</label>
                                <input type="text" id="cardholder-name" class="form-control" placeholder="Full Name">
                            </div>

                            <!-- Stripe Card Element (ALL CARD DETAILS HERE) -->
                            <div class="mb-3">
                                <label class="form-label">Card Details</label>
                                <div id="card-element" class="form-control p-3"></div>
                                <div id="card-errors" class="text-danger mt-2"></div>
                            </div>

                            <p class="text-muted small">
                                🔒 Secure payment handled by Stripe. Your card details are never stored on our server.
                            </p>

                            <div class="d-flex justify-content-between mt-4">

                                <a href="{{ route('dashboard') }}" class="btn btn-outline-primary">
                                    Back
                                </a>

                                <button type="button" id="pay-btn" class="btn btn-primary">
                                    Verify & Pay
                                </button>

                            </div>

                        </form>
                    </div>

                </div>

            </div>
        </div>

    </div>
</div>

@endsection


@section('scripts')

<script src="https://js.stripe.com/v3/"></script>

<script>
const stripe = Stripe("{{ config('services.stripe.key') }}");
const elements = stripe.elements();
const card = elements.create('card');
card.mount('#card-element');

const btn = document.getElementById('pay-btn');
const errorBox = document.getElementById('card-errors');

// Show card errors
card.on('change', function (event) {
    errorBox.textContent = event.error ? event.error.message : '';
});

btn.addEventListener('click', async function () {

    const name = document.getElementById("cardholder-name").value;

    // validation
    if (!name) {
        errorBox.textContent = "Cardholder name is required";
        return;
    }

    btn.disabled = true;
    btn.innerText = "Processing...";

    try {
        // 1. Create PaymentIntent (REGISTER SOURCE)
        const res = await fetch("/stripe/create-intent", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            body: JSON.stringify({
                source: "register"
            })
        });

        const data = await res.json();

        if (!res.ok || !data.clientSecret) {
            throw new Error(data.error || "Failed to create payment intent");
        }

        // 2. Confirm payment with Stripe
        const result = await stripe.confirmCardPayment(data.clientSecret, {
            payment_method: {
                card: card,
                billing_details: {
                    name: name
                }
            }
        });

        if (result.error) {
            errorBox.textContent = result.error.message;
            btn.disabled = false;
            btn.innerText = "Verify & Pay";
            return;
        }

        // 3. Success case
       if (result.paymentIntent.status === "succeeded" ||
            result.paymentIntent.status === "requires_capture") {

            await fetch("/mark-verified", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}"
                }
            });

            window.location.href = "/dashboard?verified=1";
        }

    } catch (error) {
        console.error(error);
        errorBox.textContent = "Payment failed. Try again.";
        btn.disabled = false;
        btn.innerText = "Verify & Pay";
    }
});
</script>

@endsection