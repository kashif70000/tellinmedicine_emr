<header class="navbar-header">
            <div class="page-container topbar-menu">
                <div class="d-flex align-items-center gap-2">

                    {{-- <!-- Logo -->
                    <a href="{{ route('dashboard') }}" class="logo">

                        <!-- Logo Normal -->
                        <span class="logo-light">
                            <span class="logo-lg"><img src="{{ \App\Models\Setting::getLogo('normal') }}" alt="logo"></span>
                        </span>

                        <!-- Logo Dark -->
                        <span class="dark-logo">
                            <span class="logo-lg"><img src="{{ \App\Models\Setting::getLogo('dark') }}" alt="dark logo"></span>
                            <span class="logo-sm"><img src="{{ \App\Models\Setting::getLogo('small') }}" alt="small dark logo"></span>
                        </span>
                    </a> --}}

                    <!-- Sidebar Mobile Button -->
                    <a id="mobile_btn" class="mobile-btn" href="#sidebar">
                        <i class="ti ti-menu-deep fs-24"></i>
                    </a>

                    <button class="sidenav-toggle-btn btn border-0 p-0 active" id="toggle_btn2"> 
                        <i class="ti ti-arrow-right"></i>
                    </button> 
					
                    <!-- Search -->
                    <div class="me-auto d-flex align-items-center header-search d-lg-flex d-none">
                        <!-- Search -->
                        <div class="input-icon-start position-relative me-2" @if(auth()->check() && auth()->user()->role === 'doctor') id="doctorSearchWrapper" style="min-width: 280px;" @endif>
                            <span class="input-icon-addon">
                                <i class="ti ti-search"></i>
                            </span>
                           <input type="text" 
                                  class="form-control shadow-sm" 
                                  @if(auth()->check() && auth()->user()->role === 'doctor')
                                      id="doctorPatientSearchInput" 
                                      placeholder="Search patients..." 
                                      autocomplete="off"
                                  @else
                                      placeholder="Search"
                                  @endif
                           >
                           <span class="input-icon-addon text-dark shadow fs-18 d-inline-flex p-0 header-search-icon" style="right: 6px; left: auto !important;"><i class="ti ti-command"></i></span>

                           @if(auth()->check() && auth()->user()->role === 'doctor')
                               <div id="doctorPatientSearchResults" class="dropdown-menu shadow-lg p-2 position-absolute w-100" style="display: none; top: 100%; left: 0; min-width: 340px; max-height: 380px; overflow-y: auto; z-index: 99999; margin-top: 6px; border-radius: 8px; background-color: #ffffff; border: 1px solid #e2e8f0;">
                               </div>
                           @endif
                        </div>
                        <!-- /Search -->
                    </div>
					
                </div>

                <div class="d-flex align-items-center">
				
                    <!-- Search for Mobile -->
                    <div class="header-item d-flex d-lg-none me-2">
                        <button class="topbar-link btn btn-icon" data-bs-toggle="modal" data-bs-target="#searchModal" type="button">
                            <i class="ti ti-search fs-16"></i>
                        </button>
                    </div>
					
                    <!-- AI Assistance -->
					{{-- <a href="javascript:void(0);" class="btn btn-liner-gradient me-3 d-lg-flex d-none">AI Assistance<i class="ti ti-chart-bubble-filled ms-1"></i></a> --}}
                    <!-- AI Assistance -->

                    <!-- Appointment -->
                    @if(auth()->user()->role === 'patient')
						<div class="header-item">
							<div class="dropdown me-2">
								<a href="{{ route('appointments.create') }}" class="btn topbar-link"><i class="ti ti-calendar-due"></i></a>
     					</div>

						</div>
                    @endif

                    <!-- Settings -->
                    <div class="header-item">
                        <div class="dropdown me-2">
                            <a href="{{ route('profile.show', auth()->user()->ensureUuid()) }}" class="btn topbar-link"><i class="ti ti-settings-2"></i></a>
						</div>
					</div>
						
					
                    <!-- Light/Dark Mode Button -->
                    <div class="header-item d-none d-sm-flex me-2">
                        <button class="topbar-link btn btn-icon topbar-link" id="light-dark-mode" type="button">
                            <i class="ti ti-moon fs-16"></i>
                        </button>
                    </div>
                    
					
					<!-- Notification Dropdown -->
                    <div class="header-item">
						<div class="dropdown me-3">
						
							<button class="topbar-link btn btn-icon topbar-link dropdown-toggle drop-arrow-none" data-bs-toggle="dropdown" data-bs-offset="0,24" type="button" aria-haspopup="false" aria-expanded="false">
								<i class="ti ti-bell-check fs-16 animate-ring"></i>
								<span class="notification-badge"></span>
							</button>
							
							<div class="dropdown-menu p-0 dropdown-menu-end dropdown-menu-lg" style="min-height: 300px;">
							
								<div class="p-2 border-bottom">
									<div class="row align-items-center">
										<div class="col">
											<h6 class="m-0 fs-16 fw-semibold"> Notifications</h6>
										</div>
									</div>
								</div>
								
								<!-- Notification Body -->
								<div class="notification-body position-relative z-2 rounded-0" data-simplebar>
								 
									<!-- Item-->
									<div class="dropdown-item notification-item py-3 text-wrap border-bottom" id="notification-1">
										<div class="d-flex">
											<div class="me-2 position-relative flex-shrink-0">
												<img src="assets/img/doctors/doctor-01.jpg" class="avatar-md rounded-circle" alt="">
											</div>
											<div class="flex-grow-1">
												<p class="mb-0 fw-medium text-dark">Dr. Smith</p>
												<p class="mb-1 text-wrap">
													updated the <span class="fw-medium text-dark">surgery</span> schedule. 
												</p>
												<div class="d-flex justify-content-between align-items-center">
													<span class="fs-12"><i class="ti ti-clock me-1"></i>4 min ago</span>
													<div class="notification-action d-flex align-items-center float-end gap-2">
														<a href="javascript:void(0);" class="notification-read rounded-circle bg-danger" data-bs-toggle="tooltip" title="" data-bs-original-title="Make as Read" aria-label="Make as Read"></a>
														<button class="btn rounded-circle p-0" data-dismissible="#notification-1">
															<i class="ti ti-x"></i>
														</button>
													</div>
												</div>
											</div>
										</div>
									</div>
							
									<!-- Item-->
									<div class="dropdown-item notification-item py-3 text-wrap border-bottom" id="notification-2">
										<div class="d-flex">
											<div class="me-2 position-relative flex-shrink-0">
												<img src="assets/img/doctors/doctor-06.jpg" class="avatar-md rounded-circle" alt="">
											</div>
											<div class="flex-grow-1">
												<p class="mb-0 fw-medium text-dark">Dr. Patel</p>
												<p class="mb-1 text-wrap">
                                                    completed a <span class="fw-medium text-dark">follow-up</span> report for patient <span class="fw-medium text-dark">Emily</span>.
												</p>
												<div class="d-flex justify-content-between align-items-center">
													<span class="fs-12"><i class="ti ti-clock me-1"></i>8 min ago</span>
													<div class="notification-action d-flex align-items-center float-end gap-2">
														<a href="javascript:void(0);" class="notification-read rounded-circle bg-danger" data-bs-toggle="tooltip" title="" data-bs-original-title="Make as Read" aria-label="Make as Read"></a>
														<button class="btn rounded-circle p-0" data-dismissible="#notification-2">
															<i class="ti ti-x"></i>
														</button>
													</div>
												</div>
											</div>
										</div>
									</div>
									
									<!-- Item-->
									<div class="dropdown-item notification-item py-3 text-wrap border-bottom" id="notification-3">
										<div class="d-flex">
											<div class="me-2 position-relative flex-shrink-0">
												<img src="assets/img/doctors/doctor-02.jpg" class="avatar-md rounded-circle" alt="">
											</div>
											<div class="flex-grow-1">
												<p class="mb-0 fw-medium text-dark">Emily</p>
												<p class="mb-1 text-wrap">
                                                    booked an appointment with <span class="fw-medium text-dark">Dr. Patel</span> for <span class="fw-medium text-dark">April 15</span>
												</p>
												<div class="d-flex justify-content-between align-items-center">
													<span class="fs-12"><i class="ti ti-clock me-1"></i>15 min ago</span>
													<div class="notification-action d-flex align-items-center float-end gap-2">
														<a href="javascript:void(0);" class="notification-read rounded-circle bg-danger" data-bs-toggle="tooltip" title="" data-bs-original-title="Make as Read" aria-label="Make as Read"></a>
														<button class="btn rounded-circle p-0" data-dismissible="#notification-3">
															<i class="ti ti-x"></i>
														</button>
													</div>
												</div>
											</div>
										</div>
									</div>
									
									<!-- Item-->
									<div class="dropdown-item notification-item py-3 text-wrap" id="notification-4">
										<div class="d-flex">
											<div class="me-2 position-relative flex-shrink-0">
												<img src="assets/img/doctors/doctor-07.jpg" class="avatar-md rounded-circle" alt="">
											</div>
											<div class="flex-grow-1">
												<p class="mb-0 fw-medium text-dark">Amelia</p>
												<p class="mb-1 text-wrap">
                                                    completed the <span class="fw-medium text-dark">pre-visit</span> health questionnaire.
												</p>
												<div class="d-flex justify-content-between align-items-center">
													<span class="fs-12"><i class="ti ti-clock me-1"></i>20 min ago</span>
													<div class="notification-action d-flex align-items-center float-end gap-2">
														<a href="javascript:void(0);" class="notification-read rounded-circle bg-danger" data-bs-toggle="tooltip" title="" data-bs-original-title="Make as Read" aria-label="Make as Read"></a>
														<button class="btn rounded-circle p-0" data-dismissible="#notification-4">
															<i class="ti ti-x"></i>
														</button>
													</div>
												</div>
											</div>
										</div>
									</div>
									 
								</div>
								
								<!-- View All-->
								<div class="p-2 rounded-bottom border-top text-center">
									<a href="#" class="text-center text-decoration-underline fs-14 mb-0">
										View All Notifications
									</a>
								</div>
								
							</div>
						</div>
					</div>
					
					<!-- User Dropdown -->
					<div class="dropdown profile-dropdown d-flex align-items-center justify-content-center">
                       <a href="javascript:void(0);"
   class="topbar-link dropdown-toggle drop-arrow-none"
   data-bs-toggle="dropdown">

    <div class="d-flex flex-column text-end">
        <span class="fw-semibold text-dark">
            {{ auth()->user()->name ?? 'User' }}
        </span>

        <small class="text-muted" style="font-size: 11px;">
            {{ ucfirst(auth()->user()->role ?? 'guest') }}
        </small>
    </div>

</a>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-md p-2">

                            <div class="d-flex align-items-center bg-light rounded-3 p-2 mb-2">
                                <img src="{{ auth()->user()->profile_image_url }}" class="rounded-circle" width="42" height="42" alt="">
                                <div class="ms-2">
                                    <p class="fw-medium text-dark mb-0">{{ auth()->user()->name ?? 'User' }}</p>
                                    <span class="d-block fs-13 text-capitalize">{{ auth()->user()->role ?? 'guest' }}</span>
                                </div>
                            </div>

                            <!-- Item-->
                            @if(auth()->user()->role === 'admin')
                                @php
                                    $admin = \App\Models\Admin::where('user_id', auth()->user()->id)->first();
                                @endphp
                                <a href="{{ route('profile.show', auth()->user()->ensureUuid()) }}" class="dropdown-item">
                                    <i class="ti ti-user-circle me-1 align-middle"></i>
                                    <span class="align-middle">Profile Settings</span>
                                </a>
                            @elseif(auth()->user()->role === 'doctor')
                                @php
                                    $doctor = \App\Models\Doctor::where('user_id', auth()->user()->id)->first();
                                @endphp
                                <a href="{{ route('profile.show', auth()->user()->ensureUuid()) }}" class="dropdown-item">
                                    <i class="ti ti-user-circle me-1 align-middle"></i>
                                    <span class="align-middle">Profile Settings</span>
                                </a>
                            @elseif(auth()->user()->role === 'patient')
                                @php
                                    $patient = \App\Models\Patient::where('user_id', auth()->user()->id)->first();
                                @endphp
                                <a href="{{ route('profile.show', auth()->user()->ensureUuid()) }}" class="dropdown-item">
                                    <i class="ti ti-user-circle me-1 align-middle"></i>
                                    <span class="align-middle">Profile Settings</span>
                                </a>
                            @else
                                <a href="#" class="dropdown-item">
                                    <i class="ti ti-user-circle me-1 align-middle"></i>
                                    <span class="align-middle">Profile Settings</span>
                                </a>
                            @endif

                            <!-- Item-->
                            <a href="#" class="dropdown-item">
                                <i class="ti ti-settings me-1 align-middle"></i>
                                <span class="align-middle">Account Settings</span>
                            </a>

                            <!-- item -->
                            <div class="form-check form-switch form-check-reverse d-flex align-items-center justify-content-between dropdown-item mb-0">
                                <label class="form-check-label" for="notify"><i class="ti ti-bell me-1"></i>Notifications</label>
                                <input class="form-check-input me-0" type="checkbox" role="switch" id="notify">
                            </div>

                            <!-- Item-->
                            {{-- <a href="{{ route('payments.index') }}" class="dropdown-item">
                                <i class="ti ti-transition-right me-1 align-middle"></i>
                                <span class="align-middle">Transactions</span>
                            </a> --}}



                            <!-- Item-->
                            <div class="pt-2 mt-2 border-top">
                                <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="dropdown-item text-danger">
                                    <i class="ti ti-logout me-1 fs-17 align-middle"></i>
                                    <span class="align-middle">Log Out</span>
                                </a>
                                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                    @csrf
                                </form>
						
                </div>
            </div>
</header>

@if(auth()->check() && auth()->user()->role === 'patient')
<div id="patientActiveCallBanner" class="position-fixed top-0 end-0 p-3" style="z-index: 9999; display: none;">
    <div class="toast show align-items-center text-white bg-primary border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body p-3">
                <i class="ti ti-video me-2 fs-18 align-middle"></i>
                <span id="patientActiveCallMsg">Dr. Doctor has started your appointment.</span>
                <div class="mt-2 pt-2 border-top border-white-50">
                    <a href="#" id="patientActiveCallLink" class="btn btn-light btn-sm fw-bold">Click Here to Join</a>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="document.getElementById('patientActiveCallBanner').style.display='none'"></button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.location.pathname.includes('/join-call')) {
        return; // already on call page
    }

    function checkActiveCall() {
        fetch("{{ route('patient.active-call-check') }}", {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            const banner = document.getElementById('patientActiveCallBanner');
            const msgEl = document.getElementById('patientActiveCallMsg');
            const linkEl = document.getElementById('patientActiveCallLink');

            if (data && data.active_call) {
                msgEl.textContent = `Dr. ${data.active_call.doctor_name} has started your appointment.`;
                linkEl.href = data.active_call.join_url;
                banner.style.display = 'block';
            } else {
                banner.style.display = 'none';
            }
        })
        .catch(err => console.warn('Active call check error:', err));
    }

    checkActiveCall();
    setInterval(checkActiveCall, 10000);
});
</script>
@endif

@if(auth()->check() && auth()->user()->role === 'doctor')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchEndpoint = "{{ route('doctor.patients.search') }}";

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function initDoctorSearch(inputEl, resultsContainerEl) {
        if (!inputEl || !resultsContainerEl) return;

        let debounceTimer = null;
        let currentAbortController = null;
        let selectedIndex = -1;

        function closeDropdown() {
            resultsContainerEl.style.display = 'none';
            resultsContainerEl.innerHTML = '';
            selectedIndex = -1;
        }

        function showLoading() {
            resultsContainerEl.innerHTML = `
                <div class="p-3 text-center text-muted fs-13">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    Searching patients...
                </div>
            `;
            resultsContainerEl.style.display = 'block';
        }

        function showNoResults() {
            resultsContainerEl.innerHTML = `
                <div class="p-3 text-center text-muted fs-13">
                    <i class="ti ti-user-x fs-20 d-block mb-1 text-secondary opacity-75"></i>
                    No patients found
                </div>
            `;
            resultsContainerEl.style.display = 'block';
        }

        function showError() {
            resultsContainerEl.innerHTML = `
                <div class="p-2 text-center text-danger fs-12">
                    <i class="ti ti-alert-circle me-1"></i> Unable to search patients. Please try again.
                </div>
            `;
            resultsContainerEl.style.display = 'block';
        }

        function renderResults(patients) {
            if (!patients || patients.length === 0) {
                showNoResults();
                return;
            }

            let html = '';
            patients.forEach((patient, idx) => {
                const subDetails = [patient.email, patient.phone].filter(Boolean).map(escapeHtml).join(' • ');

                html += `
                    <a href="${escapeHtml(patient.profile_url)}" class="dropdown-item p-2 rounded d-flex align-items-center gap-2 text-decoration-none doctor-search-result-item" data-index="${idx}">
                        <img src="${escapeHtml(patient.profile_image)}" alt="${escapeHtml(patient.name)}" class="rounded-circle flex-shrink-0" style="width: 36px; height: 36px; object-fit: cover; border: 1px solid #e2e8f0;">
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-semibold text-dark text-truncate fs-13">${escapeHtml(patient.name)}</div>
                            <div class="text-muted fs-12 text-truncate">${subDetails || 'No contact info'}</div>
                        </div>
                    </a>
                `;
            });

            resultsContainerEl.innerHTML = html;
            resultsContainerEl.style.display = 'block';
            selectedIndex = -1;
        }

        function executeSearch(query) {
            const cleanQuery = query.trim();
            if (cleanQuery.length < 2) {
                closeDropdown();
                return;
            }

            if (currentAbortController) {
                currentAbortController.abort();
            }
            currentAbortController = new AbortController();

            showLoading();

            const url = `${searchEndpoint}?q=${encodeURIComponent(cleanQuery)}`;
            fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: currentAbortController.signal
            })
            .then(res => {
                if (!res.ok) throw new Error('Search network response was not ok');
                return res.json();
            })
            .then(data => {
                renderResults(data.results || []);
            })
            .catch(err => {
                if (err.name === 'AbortError') return;
                console.warn('Doctor patient search error:', err);
                showError();
            });
        }

        inputEl.addEventListener('input', function (e) {
            clearTimeout(debounceTimer);
            const val = e.target.value;
            if (val.trim().length < 2) {
                if (currentAbortController) currentAbortController.abort();
                closeDropdown();
                return;
            }
            debounceTimer = setTimeout(() => {
                executeSearch(val);
            }, 300);
        });

        inputEl.addEventListener('keydown', function (e) {
            const items = resultsContainerEl.querySelectorAll('.doctor-search-result-item');
            if (!items.length || resultsContainerEl.style.display === 'none') {
                if (e.key === 'Escape') {
                    closeDropdown();
                }
                return;
            }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = (selectedIndex + 1) % items.length;
                updateHighlight(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = (selectedIndex - 1 + items.length) % items.length;
                updateHighlight(items);
            } else if (e.key === 'Enter') {
                if (selectedIndex >= 0 && items[selectedIndex]) {
                    e.preventDefault();
                    window.location.href = items[selectedIndex].getAttribute('href');
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                closeDropdown();
                inputEl.blur();
            }
        });

        function updateHighlight(items) {
            items.forEach((item, idx) => {
                if (idx === selectedIndex) {
                    item.classList.add('active', 'bg-light');
                    item.scrollIntoView({ block: 'nearest' });
                } else {
                    item.classList.remove('active', 'bg-light');
                }
            });
        }

        // Close on click outside
        document.addEventListener('click', function (e) {
            if (!inputEl.contains(e.target) && !resultsContainerEl.contains(e.target)) {
                closeDropdown();
            }
        });
    }

    // Initialize Desktop Top Search
    initDoctorSearch(
        document.getElementById('doctorPatientSearchInput'),
        document.getElementById('doctorPatientSearchResults')
    );

    // Initialize Mobile Modal Search
    initDoctorSearch(
        document.getElementById('doctorMobileSearchInput'),
        document.getElementById('doctorMobileSearchResults')
    );
});
</script>
@endif

