<!DOCTYPE html>
<html lang="en">

<head>

    <!-- Meta Tags -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Register | {{ \App\Models\Setting::getSiteName() }}</title>
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

    <style>
        .register-path-card {
            transition: transform .25s ease, box-shadow .25s ease;
        }
        .register-path-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 24px 50px rgba(24, 70, 126, .08);
        }
        .register-path-card .icon-box {
            width: 58px;
            height: 58px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: .85rem;
            background: rgba(20, 116, 255, .08);
        }
    </style>

</head>

<body>

    <!-- Begin Wrapper -->
    <div class="main-wrapper auth-bg position-relative overflow-hidden">

        <!-- Start Content -->
        <div class="container-fuild position-relative z-1">
            <div class="w-100 overflow-hidden position-relative flex-wrap d-block vh-100">

                <!-- start row -->
                <div class="row justify-content-center align-items-center vh-100 overflow-auto flex-wrap py-3">
                    <div class="col-xl-6 col-lg-7 col-md-9 mx-auto">
                        <div class="text-center mb-4">
                            <img src="{{ \App\Models\Setting::getLogo('normal') }}" class="img-fluid mb-3" alt="Logo" style="max-height: 60px;">
                            <h5 class="mb-2 fs-20 fw-bold">Create your account</h5>
                            <p class="text-muted mb-0">Choose the registration flow that fits your role and continue with a guided setup.</p>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <a href="{{ url('/register/patient') }}" class="card register-path-card h-100 text-decoration-none text-dark border-1 shadow-md rounded-3 p-4 d-flex flex-column justify-content-between" role="button">
                                    <div>
                                        <div class="icon-box mb-3 text-primary">
                                            <i class="ti ti-user fs-24"></i>
                                        </div>
                                        <h5 class="fs-18 fw-semibold mb-2">Register as Patient</h5>
                                        <p class="text-muted mb-0">Simple patient onboarding with personal details and secure payment step.</p>
                                    </div>
                                    <div class="mt-4">
                                        <span class="btn btn-outline-primary w-100">Continue</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="{{ url('/register/doctor') }}" class="card register-path-card h-100 text-decoration-none text-dark border-1 shadow-md rounded-3 p-4 d-flex flex-column justify-content-between" role="button">
                                    <div>
                                        <div class="icon-box mb-3 text-success">
                                            <i class="ti ti-stethoscope fs-24"></i>
                                        </div>
                                        <h5 class="fs-18 fw-semibold mb-2">Register as Doctor</h5>
                                        <p class="text-muted mb-0">Doctor verification flow with certification upload and review status.</p>
                                    </div>
                                    <div class="mt-4">
                                        <span class="btn btn-outline-success w-100">Continue</span>
                                    </div>
                                </a>
                            </div>
                        </div>

                        {{-- <div class="text-center mt-4">
                            <h6 class="fw-normal fs-14 text-dark mb-0">Already have an account?
                                <a href="{{ route('login') }}" class="hover-a">Login</a>
                            </h6>
                        </div> --}}
                        <p class="text-dark text-center mt-4">Copyright &copy; {{ date('Y') }} - {{ \App\Models\Setting::getSiteName() }}.</p>
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

</body>
</html>
