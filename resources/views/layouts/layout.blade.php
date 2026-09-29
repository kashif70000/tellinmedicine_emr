
<!DOCTYPE html>
<html lang="en">
<head>

	<!-- Meta Tags -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@hasSection('title')@yield('title') - {{ \App\Models\Setting::getSiteName() }}@else{{ \App\Models\Setting::getSiteName() }}@endif</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="author" content="Dreams Technologies">
	<meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Favicon -->
    <link rel="shortcut icon" href="{{ \App\Models\Setting::getFavicon() }}">

    <!-- Apple Icon -->
    <link rel="apple-touch-icon" href="{{ \App\Models\Setting::getFavicon() }}">

    <!-- Theme Config Js -->
    <script src="{{ asset('assets/js/theme-script.js') }}" type="text/javascript"></script>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">

    <!-- Datetimepicker CSS -->
	<link rel="stylesheet" href="{{ asset('assets/css/bootstrap-datetimepicker.min.css') }}">
    
    <!-- Daterangepikcer CSS -->
	<link rel="stylesheet" href="{{ asset('assets/plugins/daterangepicker/daterangepicker.css') }}">

    <!-- Fontawesome CSS -->
	<link rel="stylesheet" href="{{ asset('assets/plugins/fontawesome/css/fontawesome.min.css') }}">
	<link rel="stylesheet" href="{{ asset('assets/plugins/fontawesome/css/all.min.css') }}">

    <!-- Tabler Icon CSS -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/tabler-icons/tabler-icons.min.css') }}">

    <!-- Simplebar CSS -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/simplebar/simplebar.min.css') }}">

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- Main CSS -->
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}" id="app-style">

    @yield('styles')
    @stack('styles')
</head>

<body>
    <a href="https://preclinic.dreamstechnologies.com/cdn-cgi/content?id=BPNxI9u6Y0hUBp2eLLT84wXhAyUA9__uqK.QrLhfxbw-1775226633.2858348-1.0.1.1-x9WZV70IJejipAjoWDePJ8e2KoNxdwGtvx_rN.VPVNg" aria-hidden="true" rel="nofollow noopener" style="display: none !important; visibility: hidden !important"></a>

    <!-- Begin Wrapper -->
    <div class="main-wrapper">

        <!-- Topbar Start -->
        @include('layouts._partials.topbar')
        <!-- Topbar End -->

        <!-- Search Modal -->
        <div class="modal fade" id="searchModal">
            <div class="modal-dialog modal-lg">
                <div class="modal-content bg-transparent">
                    <div class="card shadow-none mb-0">
                        <div class="px-3 py-2 d-flex flex-row align-items-center" id="search-top">
                            <i class="ti ti-search fs-22"></i>
                            <input type="search" 
                                   class="form-control border-0" 
                                   @if(auth()->check() && auth()->user()->role === 'doctor')
                                       id="doctorMobileSearchInput" 
                                       placeholder="Search patients..." 
                                       autocomplete="off"
                                   @else
                                       placeholder="Search"
                                   @endif
                            >
                            <button type="button" class="btn p-0" data-bs-dismiss="modal" aria-label="Close"><i class="ti ti-x fs-22"></i></button>
                        </div>
                        @if(auth()->check() && auth()->user()->role === 'doctor')
                            <div id="doctorMobileSearchResults" class="p-2 border-top bg-white" style="display: none; max-height: 320px; overflow-y: auto;"></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidenav Menu Start -->
        @include('layouts._partials.sidebar')
        <!-- Sidenav Menu End -->

        <!-- ========================
			Start Page Content
		========================= -->
         
        <div class="page-wrapper">

            <!-- Start Content -->
            <div class="content pb-0">
                @if(Auth::check() && Auth::user()->role === 'patient' && !optional(Auth::user()->patient)->is_payment_method_verified)
                    <div class="alert alert-danger rounded-3 mb-4 d-flex align-items-start" role="alert">
                        <i class="ti ti-alert-circle fs-4 me-3"></i>
                        <div>
                            <strong>Payment verification pending.</strong> Your patient account is registered, but payment verification is still off. You can complete it now.
                               <a href="{{ route('patient.payment.page') }}"
                                    class="btn btn-sm btn-outline-light ms-2 py-1 px-2"
                                    style="font-size: 12px;">
                                        Verify Now
                                </a>
                        </div>
                    </div>
                @endif

                 @if(Auth::check() && Auth::user()->role === 'doctor')

    @php
        $doctor = Auth::user()->doctor;
    @endphp

    @if($doctor)

        @if($doctor->verification_status === 'not_submitted')

            <div class="alert alert-danger rounded-3 mb-4 d-flex align-items-start">
                <i class="ti ti-alert-circle fs-4 me-3"></i>
                <div>
                    <strong>Certificate verification pending!</strong>
                    You have not submitted your certificate yet.

                    <a href="{{ url('/register/doctor?mode=dashboard') }}"
                       class="btn btn-sm btn-outline-light ms-2 px-2">
                        Verify Now
                    </a>
                </div>
            </div>

        @elseif($doctor->verification_status === 'pending')

            <div class="alert alert-warning rounded-3 mb-4 d-flex align-items-start">
                <i class="ti ti-alert-triangle fs-4 me-3"></i>
                <div>
                    <strong>Certificate is under review.</strong>
                    Please wait for admin approval.
                </div>
            </div>

        @elseif($doctor->verification_status === 'rejected')

            <div class="alert alert-danger rounded-3 mb-4 d-flex align-items-start">
                <i class="ti ti-circle-x fs-4 me-3"></i>
                <div>
                    <strong>Certificate rejected.</strong>
                    Please resubmit correct documents.

                    <a href="{{ url('/register/doctor?mode=dashboard') }}"
                       class="btn btn-sm btn-outline-light ms-2 px-2">
                        Resubmit
                    </a>
                </div>
            </div>

        @endif

@endif

@endif
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4" role="alert">
                        <i class="ti ti-check me-1"></i>{{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
                        <i class="ti ti-alert-triangle me-1"></i>{{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger rounded-3 mb-4">
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @yield('content')
            </div>
            <!-- End Content -->

            <!-- Footer Start -->
            @include('layouts._partials.footer')
            <!-- Footer End -->

        </div>

        <!-- ========================
			End Page Content
		========================= -->

    </div>
    <!-- End Wrapper -->

    <!-- jQuery -->
    <script src="{{ asset('assets/js/jquery-3.7.1.min.js') }}" type="text/javascript"></script>

    <!-- Bootstrap Core JS -->
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}" type="text/javascript"></script>    

	<!-- Simplebar JS -->
	<script src="{{ asset('assets/plugins/simplebar/simplebar.min.js') }}" type="text/javascript"></script>

    <!-- Chart JS -->
    <script src="{{ asset('assets/plugins/apexchart/apexcharts.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/plugins/apexchart/chart-data.js') }}" type="text/javascript"></script>
    
	<!-- Daterangepikcer JS -->
	<script src="{{ asset('assets/js/moment.min.js') }}" type="text/javascript"></script>
	<script src="{{ asset('assets/plugins/daterangepicker/daterangepicker.js') }}" type="text/javascript"></script>
	<script src="{{ asset('assets/js/bootstrap-datetimepicker.min.js') }}" type="text/javascript"></script>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js" type="text/javascript"></script>

    <!-- Main JS -->
    <script src="https://js.stripe.com/v3/"></script>
    <script src="{{ asset('assets/js/script.js') }}" type="text/javascript"></script>
    @yield('scripts')
    @stack('scripts')

<!-- Mirrored from preclinic.dreamstechnologies.com/html/index.html by HTTrack Website Copier/3.x [XR&CO'2014], Fri, 03 Apr 2026 14:31:44 GMT -->
</html>
