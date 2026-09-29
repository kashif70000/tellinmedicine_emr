<!DOCTYPE html>
<html lang="en">

<head>

    <!-- Meta Tags -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Login | {{ \App\Models\Setting::getSiteName() }}</title>
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
        /* Disable browser-native password reveal button (e.g. Microsoft Edge) */
        input::-ms-reveal,
        input::-ms-clear,
        input::-webkit-contacts-auto-fill-button,
        input::-webkit-credentials-auto-fill-button {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
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
                    <div class="col-lg-4 mx-auto">
                        <form method="POST" action="{{ route('login') }}" class="d-flex justify-content-center align-items-center">
                            @csrf
                            <div class="d-flex flex-column justify-content-lg-center p-4 p-lg-0 pb-0 flex-fill">
                                <div class="mx-auto mb-4 text-center">
                                    <img src="{{ \App\Models\Setting::getLogo('normal') }}" class="img-fluid" alt="Logo" style="max-height: 60px;">
                                </div>
                                <div class="card border-1 p-lg-3 shadow-md rounded-3 mb-4">
                                    <div class="card-body">
                                        <div class="text-center mb-3">
                                            <h5 class="mb-1 fs-20 fw-bold">Login</h5>
                                            <p class="mb-0">Please enter below details to access the dashboard</p>
                                        </div>

                                        @if ($errors->any())
                                            <div class="alert alert-danger">
                                                <ul class="mb-0">
                                                    @foreach ($errors->all() as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        <div class="mb-3">
                                            <label class="form-label">Email Address</label>
                                            <div class="input-group">
                                                <span class="input-group-text border-end-0 bg-white">
                                                    <i class="ti ti-mail fs-14 text-dark"></i>
                                                </span>
                                                <input type="email" name="email" value="{{ old('email') }}" class="form-control border-start-0 ps-0 @error('email') is-invalid @enderror" placeholder="Enter Email Address" required autocomplete="email" autofocus>
                                            </div>
                                            @error('email')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Password</label>
                                            <div class="position-relative">
                                                <div class="pass-group input-group position-relative border rounded">
                                                    <span class="input-group-text bg-white border-0">
                                                        <i class="ti ti-lock text-dark fs-14"></i>
                                                    </span>
                                                    <input type="password" name="password" class="pass-input form-control ps-0 border-0 @error('password') is-invalid @enderror" placeholder="****************" required autocomplete="current-password">
                                                    <span class="input-group-text bg-white border-0">
                                                        <i class="ti toggle-password ti-eye-off text-dark fs-14"></i>
                                                    </span>
                                                </div>
                                            </div>
                                            @error('password')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            
                                           
                                        </div>
                                        <div class="mb-2">
                                            <button type="submit" class="btn bg-primary text-white w-100">Login</button>
                                        </div>
                                        {{-- 
                                        <div class="login-or position-relative mb-3">
											<span class="span-or">OR</span>
										</div>
                                        <div class="mb-2">
											<div class="d-flex align-items-center justify-content-center flex-wrap">
												<div class="text-center me-2 flex-fill">
													<a href="{{ url('/auth/facebook?type=login') }}" class="br-10 p-1 btn btn-outline-light border d-flex align-items-center justify-content-center">
														<img class="img-fluid m-1" src="assets/img/icons/facebook-logo.svg" alt="Facebook">
													</a>
												</div>
												<div class="text-center me-2 flex-fill">
													<a href="{{ url('/auth/google?type=login') }}" class="br-10 p-1 btn btn-outline-light border d-flex align-items-center justify-content-center">
														<img class="img-fluid m-1" src="assets/img/icons/google-logo.svg" alt="Google">
													</a>
												</div>
											</div>
										</div>
                                        --}}
                                       
                                        <div class="text-center">
                                            <h6 class="fw-normal fs-14 text-dark mb-0">Don’t have an account yet?
                                                <a href="{{ route('register') }}" class="hover-a"> Register</a>
                                            </h6>
                                        </div>
                                    </div><!-- end card body -->
                                </div><!-- end card -->
                            </div>
                        </form>
                        <p class="text-dark text-center">Copyright &copy; {{ date('Y') }} - {{ \App\Models\Setting::getSiteName() }}.</p>
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
