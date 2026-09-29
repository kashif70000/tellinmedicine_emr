<!DOCTYPE html>
<html lang="en">

<head>

    <!-- Meta Tags -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Patient Registration | {{ \App\Models\Setting::getSiteName() }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Dreams Technologies">

    <!-- Favicon -->
    <link rel="shortcut icon" href="{{ \App\Models\Setting::getFavicon() }}">

    <!-- Apple Icon -->
    <link rel="apple-touch-icon" href="{{ \App\Models\Setting::getFavicon() }}">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">

    <!-- Tabler Icon CSS -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/tabler-icons/tabler-icons.min.css') }}">

    <!-- Simplebar CSS -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/simplebar/simplebar.min.css') }}">

    <!-- Fontawesome CSS -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/fontawesome/css/fontawesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/plugins/fontawesome/css/all.min.css') }}">

    <!-- Main CSS -->
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}" id="app-style">

    

</head>

<body>

    <!-- Begin Wrapper -->
    <div class="main-wrapper auth-bg position-relative overflow-hidden">

        <!-- Start Content -->
        <div class="container-fuild position-relative z-1">
            <div class="w-100 overflow-hidden position-relative flex-wrap d-block vh-100">

                <!-- start row -->
                <div class="row justify-content-center align-items-center vh-100 overflow-auto flex-wrap py-3">
                    <div class="col-xl-8 col-lg-9 col-md-10 mx-auto">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h5 class="mb-1 fs-20 fw-bold">Patient Registration</h5>
                                <p class="text-muted mb-0">Complete your patient profile in two easy steps.</p>
                            </div>
                            <a href="{{ route('login') }}" class="btn bg-primary text-white"><--- Back to Login</a>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <div class="step-pill active">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="step-number">1</span>
                                        <div>
                                            <h6 class="mb-0">General Info</h6>
                                        </div>
                                    </div>
                                    <p class="mb-0 text-muted">Personal details required for your patient account.</p>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="step-pill" id="patient-step-2-pill">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="step-number">2</span>
                                        <div>
                                            <h6 class="mb-0">Payment</h6>
                                        </div>
                                    </div>
                                    <p class="mb-0 text-muted">Stripe payment information.</p>
                                </div>
                            </div>
                        </div>

                        {{-- 
                             <div class="mb-3">
                                    <div class="d-flex align-items-center justify-content-center gap-3 flex-wrap">

                                        <!-- Facebook Button -->
                                        <a href="{{ url('/auth/facebook?role=patient') }}"
                                        class="btn social-btn fb-btn d-flex align-items-center justify-content-center px-5 py-3 rounded-pill"
                                        style="min-width: 260px;">
                                            <img src="/assets/img/icons/facebook-logo.svg" class="me-2 bg-white rounded-circle p-1" width="24" alt="Facebook">
                                            <span class="fw-medium">Continue with Facebook</span>
                                        </a>

                                        <!-- Google Button -->
                                        <a href="{{ url('/auth/google?role=patient') }}"
                                        class="btn social-btn google-btn d-flex align-items-center justify-content-center px-5 py-3 rounded-pill"
                                        style="min-width: 260px;">
                                            <img src="/assets/img/icons/google-logo.svg" class="me-2 bg-white rounded-circle p-1" width="24" alt="Google">
                                            <span class="fw-medium">Continue with Google</span>
                                        </a>

                             </div>

                                <!-- Divider -->
                                <div class="d-flex align-items-center my-3">
                                    <hr class="flex-grow-1">
                                    <span class="mx-2 text-muted">or</span>
                                    <hr class="flex-grow-1">
                                </div>
                            </div>
                        --}}

                        <div class="card border-1 p-lg-4 shadow-md rounded-3 position-relative mb-4">
                            <div id="patient-loader" class="loader-overlay d-none">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div id="patient-step-1" class="form-step active">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0 bg-white">
                                                <i class="ti ti-user fs-14 text-dark"></i>
                                            </span>
                                            <input id="patient-name" type="text" class="form-control border-start-0 ps-0" placeholder="Full Name" required>
                                            <div class="invalid-feedback">This field is required</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0 bg-white">
                                                <i class="ti ti-mail fs-14 text-dark"></i>
                                            </span>
                                            <input id="patient-email" type="email" class="form-control border-start-0 ps-0" placeholder="Email Address" required>
                                            <div class="invalid-feedback">This field is required</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Password</label>
                                        <div class="input-group has-validation">
                                            <span class="input-group-text border-end-0 bg-white rounded-0">
                                                <i class="ti ti-lock fs-14 text-dark"></i>
                                            </span>
                                            <input id="patient-password" type="password" class="form-control border-start-0 border-end-0 ps-0 rounded-0" placeholder="Password" required>
                                            <button class="input-group-text bg-white password-toggle rounded-0" type="button" data-password-toggle="patient-password" aria-label="Toggle password visibility">
                                                <i class="ti ti-eye-off"></i>
                                            </button>
                                            <div class="invalid-feedback">This field is required</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Confirm Password</label>
                                        <div class="input-group has-validation">
                                            <span class="input-group-text border-end-0 bg-white rounded-0">
                                                <i class="ti ti-lock fs-14 text-dark"></i>
                                            </span>
                                            <input id="patient-password-confirm" type="password" class="form-control border-start-0 border-end-0 ps-0 rounded-0" placeholder="Confirm Password" required>
                                            <button class="input-group-text bg-white password-toggle rounded-0" type="button" data-password-toggle="patient-password-confirm" aria-label="Toggle confirm password visibility">
                                                <i class="ti ti-eye-off"></i>
                                            </button>
                                            <div class="invalid-feedback">This field is required</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0 bg-white">
                                                <i class="ti ti-phone fs-14 text-dark"></i>
                                            </span>
                                            <input id="patient-phone" type="tel" class="form-control border-start-0 ps-0" placeholder="Phone Number" required >
                                            <div class="invalid-feedback">This field is required</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Age</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0 bg-white">
                                                <i class="ti ti-calendar fs-14 text-dark"></i>
                                            </span>
                                            <input id="patient-dob" type="text" class="form-control border-start-0 ps-0" placeholder="Age" required>
                                            <div class="invalid-feedback">This field is required</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Gender</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0 bg-white">
                                                <i class="ti ti-user fs-14 text-dark"></i>
                                            </span>
                                            <select id="patient-gender" class="form-select border-start-0 ps-0" required>
                                                <option value="">Choose...</option>
                                                <option value="male">Male</option>
                                                <option value="female">Female</option>
                                                <option value="other">Other</option>
                                            </select>
                                            <div class="invalid-feedback">This field is required</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Blood Group</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0 bg-white">
                                                <i class="ti ti-droplet fs-14 text-dark"></i>
                                            </span>
                                            <select id="patient-blood-group" class="form-select border-start-0 ps-0" required>
                                                <option value="">Choose...</option>
                                                <option value="A+">A+</option>
                                                <option value="A-">A-</option>
                                                <option value="B+">B+</option>
                                                <option value="B-">B-</option>
                                                <option value="AB+">AB+</option>
                                                <option value="AB-">AB-</option>
                                                <option value="O+">O+</option>
                                                <option value="O-">O-</option>
                                            </select>
                                            <div class="invalid-feedback">This field is required</div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0 bg-white">
                                                <i class="ti ti-map-pin fs-14 text-dark"></i>
                                            </span>
                                            <input id="patient-address" type="text" class="form-control border-start-0 ps-0" placeholder="Street, City, Country" required>
                                            <div class="invalid-feedback">This field is required</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end mt-4">
                                    <button id="patient-next" type="button" class="btn bg-primary text-white">Next</button>
                                </div>
                            </div>

                            <div id="patient-step-2" class="form-step">
                                <div class="row g-3">
                                    <div class="col-lg-5">
                                        <div class="payment-card text-white">
                                            <div class="card-chip"></div>
                                            <p class="mb-4">Secure payment preview</p>
                                            <h5 class="mb-1">John Patient</h5>
                                            <p class="mb-0">**** **** **** 4242</p>
                                            <div class="d-flex align-items-center justify-content-between mt-4">
                                                <small>Expiry 09/28</small>
                                                <div class="payment-card-icons">
                                                    <i class="ti ti-credit-card"></i>
                                                    <i class="ti ti-credit-card"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-7">
                                        <div class="row g-3">
                                            <div class="col-12">
                                            <label class="form-label">Cardholder Name</label>
                                            <input type="text" id="cardholder-name" class="form-control" placeholder="Full Name">
                                        </div>
                                            <div class="col-12">
                                                <label class="form-label">Card Details</label>
                                                <div id="card-element" class="form-control p-3"></div>
                                                <div id="card-errors" class="text-danger mt-2"></div>
                                            </div>
                                           
                                        </div>
                                        <p class="text-muted small mt-3"><i class="ti ti-lock me-2"></i>Secure payment details only used for demonstration and will not be processed.</p>
                                    </div>
                                </div>
                                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 mt-4">
                                    <button id="patient-back" type="button" class="btn btn-outline-primary">Back</button>
                                    <div class="d-flex gap-2">
                                        <button id="patient-skip" type="button" class="btn btn-outline-primary">Skip for Now</button>
                                        <button id="patient-submit" type="button" class="btn bg-primary text-white">Submit</button>
                                    </div>
                                </div>
                            </div>

                            <div id="patient-success" class="form-feedback text-center p-5 d-none">
                                <div class="mb-4">
                                    <span class="step-card-icon bg-success text-white"><i class="ti ti-check"></i></span>
                                </div>
                                <h5 class="fw-bold mb-2">Registration ready</h5>
                                <p class="text-muted mb-4">Your patient registration flow is complete. You can return to login and proceed.</p>
                                <a href="{{ route('login') }}" class="btn bg-primary text-white">Back to Login</a>
                            </div>
                        </div>

                    </div><!-- end col -->
                </div>
                <!-- end row -->
            </div>
        </div>
        <!-- End Content -->

        <img src="{{ asset('assets/img/auth/auth-bg-top.png') }}" alt="" class="img-fluid element-01">
        <img src="{{ asset('assets/img/auth/auth-bg-bot.png') }}" alt="" class="img-fluid element-02">

    </div>
    <!-- End Wrapper -->

    <!-- jQuery -->
    <script src="{{ asset('assets/js/jquery-3.7.1.min.js') }}" type="text/javascript"></script>

    <!-- Bootstrap Core JS -->
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}" type="text/javascript"></script>

    <!-- Main JS -->
    <script src="{{ asset('assets/js/script.js') }}" type="text/javascript"></script>

   <script src="https://js.stripe.com/v3/"></script>

<script>
const stripe = Stripe("{{ config('services.stripe.key') }}");
const elements = stripe.elements();
const card = elements.create('card');
card.mount('#card-element');

// Show card errors
card.on('change', function (event) {
    document.getElementById('card-errors').textContent =
        event.error ? event.error.message : '';
});

// CSRF token (FIXED)
const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

// Button click
document.getElementById('pay-btn').addEventListener('click', async function () {

    const name = document.getElementById("cardholder-name").value;

    // Validation
    if (!name) {
        document.getElementById('card-errors').textContent = "Cardholder name is required";
        return;
    }
    const btn = this;

    btn.disabled = true;
    btn.innerText = "Processing...";

    try {

        // 1. Create PaymentIntent (REGISTER route)
        const res = await fetch("/stripe/register-intent", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrf
            },
            body: JSON.stringify({
                source: "register"
            })
        });

        const data = await res.json();

        if (!data.clientSecret) {
            throw new Error(data.error || "Failed to create payment intent");
        }

        // 2. Confirm card payment
        const result = await stripe.confirmCardPayment(data.clientSecret, {
            payment_method: {
                card: card,
                billing_details: {
                    name: name
                }
            }
        });

        // 3. Handle error
        if (result.error) {
            document.getElementById('card-errors').textContent = result.error.message;
            btn.disabled = false;
            btn.innerText = "Verify & Pay";
            return;
        }

        // 4. Success case
        if (
            result.paymentIntent &&
            (result.paymentIntent.status === "succeeded" ||
             result.paymentIntent.status === "requires_capture")
        ) {

            // Mark verified (REGISTER SAFE ROUTE)
            await fetch("/register-mark-verified", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrf
                },
                body: JSON.stringify({
                    source: "register"
                })
            });

            // Redirect
            window.location.href = "{{ route('dashboard') }}?verified=1";
        }

    } catch (error) {
        console.error(error);
        document.getElementById('card-errors').textContent =
            "Something went wrong. Please try again.";

        btn.disabled = false;
        btn.innerText = "Verify & Pay";
    }
});




</script>

</body>
</html>
