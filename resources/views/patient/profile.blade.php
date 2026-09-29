@extends('layouts.layout')

@section('title', 'Patient Profile')

@section('content')

<div class="content" id="profilePage">

    <!-- Page Header -->
    <div class="mb-3 border-bottom pb-3">
        <h4 class="fw-bold mb-0">Profile Settings</h4>
    </div>
    <!-- End Page Header -->

    <div class="card">
        <div class="card-body p-0">
            <div class="settings-wrapper d-flex">

                <div class="card flex-fill mb-0 border-0 bg-light-500 shadow-none">
                    <div class="card-header border-bottom px-0 mx-3">
                        <h5 class="fw-bold">Basic Information</h5>
                    </div>
                    <div class="card-body px-0 mx-3">
                        <form action="{{ route('profile.update', $patient->user->uuid) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            <!-- start row -->
                            <div class="row border-bottom mb-3">
                                <div class="col-lg-12">

                                    <!-- Profile Image -->
                                    <div class="row align-items-center mb-3">
                                        <div class="col-lg-2">
                                            <label class="form-label mb-0">Profile Image<span class="text-danger ms-1">*</span></label>
                                        </div>
                                        <div class="col-lg-10">
                                            <div class="profile-container">
                                                <img src="{{ auth()->user()->profile_image_url }}" alt="Profile" id="profilePreview">
                                                <div class="overlay-btn">
                                                    <a href="javascript:void(0);" class="text-white" id="uploadTrigger">
                                                        <i class="ti ti-photo fs-10"></i>
                                                    </a>
                                                </div>
                                                <input type="file" name="profile_image" id="profileUpload" accept="image/*" style="display: none;">
                                            </div>
                                            @error('profile_image')
                                                <div class="text-danger small mt-2">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <!-- end row -->

                                </div>
                                <div class="col-lg-6">

                                    <!-- Name Field -->
                                    <div class="row align-items-center mb-3">
                                        <div class="col-lg-4">
                                            <label class="form-label mb-0">Name<span class="text-danger ms-1">*</span></label>
                                        </div>
                                        <div class="col-lg-8">
                                            <input type="text" class="form-control" value="{{ $patient->user->name }}" disabled>
                                        </div>
                                    </div>

                                </div>
                                <div class="col-lg-6">

                                    <!-- Email Field -->
                                    <div class="row align-items-center mb-3">
                                        <div class="col-lg-4">
                                            <label class="form-label mb-0">Email<span class="text-danger ms-1">*</span></label>
                                        </div>
                                        <div class="col-lg-8">
                                            <input type="email" class="form-control" value="{{ $patient->user->email }}" disabled>
                                        </div>
                                    </div>

                                </div>
                                <div class="col-lg-6">

                                    <!-- Phone Field -->
                                    <div class="row align-items-center mb-3">
                                        <div class="col-lg-4">
                                            <label class="form-label mb-0">Phone Number<span class="text-danger ms-1">*</span></label>
                                        </div>
                                        <div class="col-lg-8">
                                            <input type="text" class="form-control" name="phone" value="{{ $patient->phone }}">
                                        </div>
                                    </div>

                                </div>
                                <div class="col-lg-6">

                                    <!-- Age Field -->
                                    <div class="row align-items-center mb-3">
                                        <div class="col-lg-4">
                                            <label class="form-label mb-0">Age<span class="text-danger ms-1">*</span></label>
                                        </div>
                                        <div class="col-lg-8">
                                            <input type="number" class="form-control" name="age" value="{{ $patient->age }}" min="0" max="150">
                                        </div>
                                    </div>

                                </div>
                            </div>
                            <!-- end row -->

                            <!-- Medical Information -->
                            <div class="row border-bottom mb-3">
                                <div class="mb-3">
                                    <h5 class="fw-bold mb-0">Medical Information</h5>
                                </div>
                                <div class="col-lg-6">

                                    <!-- Gender Field -->
                                    <div class="row align-items-center mb-3">
                                        <div class="col-lg-4">
                                            <label class="form-label mb-0">Gender</label>
                                        </div>
                                        <div class="col-lg-8">
                                            <select class="form-control" name="gender">
                                                <option value="male" {{ $patient->gender === 'male' ? 'selected' : '' }}>Male</option>
                                                <option value="female" {{ $patient->gender === 'female' ? 'selected' : '' }}>Female</option>
                                                <option value="other" {{ $patient->gender === 'other' ? 'selected' : '' }}>Other</option>
                                            </select>
                                        </div>
                                    </div>

                                </div>
                                <div class="col-lg-6">

                                    <!-- Blood Group Field -->
                                    <div class="row align-items-center mb-3">
                                        <div class="col-lg-4">
                                            <label class="form-label mb-0">Blood Group</label>
                                        </div>
                                        <div class="col-lg-8">
                                            <select class="form-control" name="blood_group">
                                                <option value="A+" {{ $patient->blood_group === 'A+' ? 'selected' : '' }}>A+</option>
                                                <option value="A-" {{ $patient->blood_group === 'A-' ? 'selected' : '' }}>A-</option>
                                                <option value="B+" {{ $patient->blood_group === 'B+' ? 'selected' : '' }}>B+</option>
                                                <option value="B-" {{ $patient->blood_group === 'B-' ? 'selected' : '' }}>B-</option>
                                                <option value="AB+" {{ $patient->blood_group === 'AB+' ? 'selected' : '' }}>AB+</option>
                                                <option value="AB-" {{ $patient->blood_group === 'AB-' ? 'selected' : '' }}>AB-</option>
                                                <option value="O+" {{ $patient->blood_group === 'O+' ? 'selected' : '' }}>O+</option>
                                                <option value="O-" {{ $patient->blood_group === 'O-' ? 'selected' : '' }}>O-</option>
                                            </select>
                                        </div>
                                    </div>

                                </div>
                                <div class="col-lg-6">

                                    <!-- DOB Field -->
                                    <div class="row align-items-center mb-3">
                                        <div class="col-lg-4">
                                            <label class="form-label mb-0">Date of Birth</label>
                                        </div>
                                        <div class="col-lg-8">
                                            <input type="date" class="form-control" name="dob" value="{{ $patient->dob }}">
                                        </div>
                                    </div>

                                </div>
                                <div class="col-lg-6">

                                    <!-- Verification Status -->
                                    <div class="row align-items-center mb-3">
                                        <div class="col-lg-4">
                                            <label class="form-label mb-0">Verification Status</label>
                                        </div>
                                        <div class="col-lg-8">
                                            <span class="badge bg-{{ $patient->is_payment_method_verified ? 'success' : 'warning' }}">
                                                {{ $patient->is_payment_method_verified ? 'Verified' : 'Not Verified' }}
                                            </span>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            <!-- end row -->

                            <!-- Address Information -->
                            <div class="row border-bottom mb-3">
                                <div class="mb-3">
                                    <h5 class="fw-bold mb-0">Address Information</h5>
                                </div>
                                <div class="col-lg-12">

                                    <!-- Address Field -->
                                    <div class="row align-items-center mb-3">
                                        <div class="col-lg-2">
                                            <label class="form-label mb-0">Address</label>
                                        </div>
                                        <div class="col-lg-10">
                                            <textarea class="form-control" name="address" rows="3">{{ $patient->address }}</textarea>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            <!-- end row -->

                            <!-- Preferred Pharmacy & Laboratory Information -->
                            <div class="row border-bottom mb-4">
                                <div class="mb-3">
                                    <h5 class="fw-bold mb-1">Preferred Healthcare Locations</h5>
                                    <p class="text-muted fs-13 mb-0">Set your default preferred pharmacy for prescriptions and preferred laboratory for diagnostic orders.</p>
                                </div>

                                <!-- Preferred Pharmacy Card -->
                                <div class="col-lg-6 mb-3">
                                    <div class="card border shadow-none h-100 mb-0 bg-white">
                                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3">
                                            <span class="fw-semibold text-dark"><i class="ti ti-pill text-primary me-1"></i> Preferred Pharmacy</span>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-11">Prescription Default</span>
                                        </div>
                                        <div class="card-body p-3" id="preferredPharmacyCardBody">
                                            @php $pharmacy = $patient->preferredPharmacy; @endphp
                                            @if($pharmacy)
                                                <div id="preferredPharmacySelected">
                                                    <h6 class="fw-bold text-dark mb-1" id="pharmacyNameDisplay">{{ $pharmacy->display_name ?: $pharmacy->name }}</h6>
                                                    <p class="text-muted fs-13 mb-1" id="pharmacyAddressDisplay">
                                                        <i class="ti ti-map-pin fs-14 me-1 text-secondary"></i>
                                                        {{ $pharmacy->street_address ? $pharmacy->street_address . ', ' : '' }}{{ $pharmacy->city ? $pharmacy->city . ', ' : '' }}{{ $pharmacy->state }} {{ $pharmacy->postal_code }}
                                                    </p>
                                                    @if($pharmacy->phone)
                                                        <p class="text-muted fs-13 mb-3" id="pharmacyPhoneDisplay">
                                                            <i class="ti ti-phone fs-14 me-1 text-secondary"></i>{{ $pharmacy->phone }}
                                                        </p>
                                                    @else
                                                        <p class="text-muted fs-13 mb-3 d-none" id="pharmacyPhoneDisplay"></p>
                                                    @endif
                                                    <div class="d-flex gap-2">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#pharmacySearchModal">
                                                            <i class="ti ti-edit me-1"></i> Change Pharmacy
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" id="btnRemovePharmacy">
                                                            <i class="ti ti-trash me-1"></i> Remove
                                                        </button>
                                                    </div>
                                                </div>
                                            @else
                                                <div id="preferredPharmacyEmpty" class="text-center py-3">
                                                    <div class="mb-2">
                                                        <i class="ti ti-building-store fs-32 text-muted opacity-50"></i>
                                                    </div>
                                                    <p class="text-muted fs-13 mb-3">No preferred pharmacy selected.</p>
                                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#pharmacySearchModal">
                                                        <i class="ti ti-plus me-1"></i> Select Pharmacy
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Preferred Laboratory Card -->
                                <div class="col-lg-6 mb-3">
                                    <div class="card border shadow-none h-100 mb-0 bg-white">
                                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3">
                                            <span class="fw-semibold text-dark"><i class="ti ti-flask text-info me-1"></i> Preferred Laboratory</span>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle fs-11">Lab Order Default</span>
                                        </div>
                                        <div class="card-body p-3" id="preferredLaboratoryCardBody">
                                            @php $lab = $patient->preferredLaboratory; @endphp
                                            @if($lab)
                                                <div id="preferredLaboratorySelected">
                                                    <div class="d-flex align-items-center gap-2 mb-1">
                                                        <h6 class="fw-bold text-dark mb-0" id="labNameDisplay">{{ $lab->display_name ?: $lab->name }}</h6>
                                                        @if($lab->category)
                                                            <span class="badge bg-secondary-subtle text-secondary border fs-10" id="labCategoryDisplay">{{ $lab->category }}</span>
                                                        @else
                                                            <span class="badge bg-secondary-subtle text-secondary border fs-10 d-none" id="labCategoryDisplay"></span>
                                                        @endif
                                                    </div>
                                                    <p class="text-muted fs-13 mb-1" id="labAddressDisplay">
                                                        <i class="ti ti-map-pin fs-14 me-1 text-secondary"></i>
                                                        {{ $lab->street_address ? $lab->street_address . ', ' : '' }}{{ $lab->city ? $lab->city . ', ' : '' }}{{ $lab->state }} {{ $lab->postal_code }}
                                                    </p>
                                                    @if($lab->phone)
                                                        <p class="text-muted fs-13 mb-3" id="labPhoneDisplay">
                                                            <i class="ti ti-phone fs-14 me-1 text-secondary"></i>{{ $lab->phone }}
                                                        </p>
                                                    @else
                                                        <p class="text-muted fs-13 mb-3 d-none" id="labPhoneDisplay"></p>
                                                    @endif
                                                    <div class="d-flex gap-2">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#laboratorySearchModal">
                                                            <i class="ti ti-edit me-1"></i> Change Laboratory
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" id="btnRemoveLab">
                                                            <i class="ti ti-trash me-1"></i> Remove
                                                        </button>
                                                    </div>
                                                </div>
                                            @else
                                                <div id="preferredLaboratoryEmpty" class="text-center py-3">
                                                    <div class="mb-2">
                                                        <i class="ti ti-microscope fs-32 text-muted opacity-50"></i>
                                                    </div>
                                                    <p class="text-muted fs-13 mb-3">No preferred laboratory selected.</p>
                                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#laboratorySearchModal">
                                                        <i class="ti ti-plus me-1"></i> Select Laboratory
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- end row -->

                            <div class="d-flex align-items-center justify-content-end">
                                <a href="{{ route('dashboard') }}" class="btn btn-light me-3">Cancel</a>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>

<!-- Pharmacy Search Modal -->
<div class="modal fade" id="pharmacySearchModal" tabindex="-1" aria-labelledby="pharmacySearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold" id="pharmacySearchModalLabel">
                    <i class="ti ti-pill text-primary me-1"></i> Select Preferred Pharmacy
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Search Pharmacies</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="ti ti-search"></i></span>
                        <input type="text" class="form-control" id="pharmacySearchInput" placeholder="Search by name, street address, city, ZIP code, or phone..." autocomplete="off">
                        <button class="btn btn-outline-secondary" type="button" id="pharmacySearchClearBtn" style="display: none;">
                            <i class="ti ti-x"></i>
                        </button>
                    </div>
                    <div class="form-text text-muted">Type at least 2 characters to search the pharmacy directory.</div>
                </div>

                <div id="pharmacySearchResultsContainer" class="mt-3" style="min-height: 200px; max-height: 380px; overflow-y: auto;">
                    <div class="text-center text-muted py-5" id="pharmacySearchPlaceholder">
                        <i class="ti ti-search fs-32 text-muted opacity-50 mb-2"></i>
                        <p class="mb-0">Start typing to search available pharmacies...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Laboratory Search Modal -->
<div class="modal fade" id="laboratorySearchModal" tabindex="-1" aria-labelledby="laboratorySearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold" id="laboratorySearchModalLabel">
                    <i class="ti ti-flask text-info me-1"></i> Select Preferred Laboratory
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Search Laboratories</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="ti ti-search"></i></span>
                        <input type="text" class="form-control" id="laboratorySearchInput" placeholder="Search by laboratory name, category, city, ZIP code, or phone..." autocomplete="off">
                        <button class="btn btn-outline-secondary" type="button" id="laboratorySearchClearBtn" style="display: none;">
                            <i class="ti ti-x"></i>
                        </button>
                    </div>
                    <div class="form-text text-muted">Type at least 2 characters to search diagnostic laboratories.</div>
                </div>

                <div id="laboratorySearchResultsContainer" class="mt-3" style="min-height: 200px; max-height: 380px; overflow-y: auto;">
                    <div class="text-center text-muted py-5" id="laboratorySearchPlaceholder">
                        <i class="ti ti-search fs-32 text-muted opacity-50 mb-2"></i>
                        <p class="mb-0">Start typing to search available laboratories...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    // Toast notification helper
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });

    // Helper to safely escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ==========================================
    // PHARMACY SEARCH & SELECTION LOGIC
    // ==========================================
    let pharmacyDebounceTimer = null;
    const $pharmacyInput = $('#pharmacySearchInput');
    const $pharmacyClearBtn = $('#pharmacySearchClearBtn');
    const $pharmacyResultsContainer = $('#pharmacySearchResultsContainer');

    $pharmacyInput.on('input', function() {
        const query = $(this).val().trim();
        $pharmacyClearBtn.toggle(query.length > 0);

        clearTimeout(pharmacyDebounceTimer);

        if (query.length < 2) {
            $pharmacyResultsContainer.html(`
                <div class="text-center text-muted py-5">
                    <i class="ti ti-search fs-32 text-muted opacity-50 mb-2"></i>
                    <p class="mb-0">${query.length === 1 ? 'Please type at least 2 characters to search' : 'Start typing to search available pharmacies...'}</p>
                </div>
            `);
            return;
        }

        $pharmacyResultsContainer.html(`
            <div class="text-center py-5">
                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                <span class="text-muted">Searching pharmacies...</span>
            </div>
        `);

        pharmacyDebounceTimer = setTimeout(function() {
            $.ajax({
                url: "{{ route('patient.pharmacies.search') }}",
                type: 'GET',
                data: { q: query },
                dataType: 'json',
                success: function(response) {
                    const results = response.results || [];
                    if (results.length === 0) {
                        $pharmacyResultsContainer.html(`
                            <div class="text-center text-muted py-5">
                                <i class="ti ti-building-store fs-32 text-muted opacity-50 mb-2"></i>
                                <p class="mb-0">No pharmacies found matching "${escapeHtml(query)}".</p>
                            </div>
                        `);
                        return;
                    }

                    let html = '<div class="list-group list-group-flush">';
                    results.forEach(function(item) {
                        const addressParts = [];
                        if (item.street_address) addressParts.push(item.street_address);
                        if (item.city) addressParts.push(item.city);
                        if (item.state) addressParts.push(item.state);
                        if (item.postal_code) addressParts.push(item.postal_code);
                        const fullAddress = addressParts.join(', ');

                        html += `
                            <div class="list-group-item p-3 border rounded mb-2 d-flex justify-content-between align-items-center hover-bg-light">
                                <div class="me-3">
                                    <h6 class="fw-bold text-dark mb-1">${escapeHtml(item.name)}</h6>
                                    <p class="text-muted fs-13 mb-1">
                                        <i class="ti ti-map-pin fs-14 me-1 text-secondary"></i>${escapeHtml(fullAddress || 'Address not specified')}
                                    </p>
                                    ${item.phone ? `<p class="text-muted fs-13 mb-0"><i class="ti ti-phone fs-14 me-1 text-secondary"></i>${escapeHtml(item.phone)}</p>` : ''}
                                </div>
                                <div>
                                    <button type="button" class="btn btn-sm btn-primary btn-select-pharmacy" data-id="${item.id}" data-name="${escapeHtml(item.name)}" data-address="${escapeHtml(fullAddress)}" data-phone="${escapeHtml(item.phone || '')}">
                                        Select
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    $pharmacyResultsContainer.html(html);
                },
                error: function() {
                    $pharmacyResultsContainer.html(`
                        <div class="text-center text-danger py-4">
                            <i class="ti ti-alert-triangle fs-32 mb-2"></i>
                            <p class="mb-0">Unable to search pharmacies right now. Please try again.</p>
                        </div>
                    `);
                }
            });
        }, 300);
    });

    $pharmacyClearBtn.on('click', function() {
        $pharmacyInput.val('').trigger('input').focus();
    });

    // Select Pharmacy
    $(document).on('click', '.btn-select-pharmacy', function() {
        const $btn = $(this);
        const pharmacyId = $btn.data('id');
        const originalText = $btn.html();

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span>');

        $.ajax({
            url: "{{ route('patient.preferred-pharmacy.store') }}",
            type: 'POST',
            data: {
                _token: csrfToken,
                pharmacy_id: pharmacyId
            },
            dataType: 'json',
            success: function(response) {
                const pharmacy = response.pharmacy;
                const addressParts = [];
                if (pharmacy.street_address) addressParts.push(pharmacy.street_address);
                if (pharmacy.city) addressParts.push(pharmacy.city);
                if (pharmacy.state) addressParts.push(pharmacy.state);
                if (pharmacy.postal_code) addressParts.push(pharmacy.postal_code);
                const fullAddress = addressParts.join(', ');

                $('#preferredPharmacyCardBody').html(`
                    <div id="preferredPharmacySelected">
                        <h6 class="fw-bold text-dark mb-1" id="pharmacyNameDisplay">${escapeHtml(pharmacy.name)}</h6>
                        <p class="text-muted fs-13 mb-1" id="pharmacyAddressDisplay">
                            <i class="ti ti-map-pin fs-14 me-1 text-secondary"></i>${escapeHtml(fullAddress)}
                        </p>
                        ${pharmacy.phone ? `<p class="text-muted fs-13 mb-3" id="pharmacyPhoneDisplay"><i class="ti ti-phone fs-14 me-1 text-secondary"></i>${escapeHtml(pharmacy.phone)}</p>` : '<p class="text-muted fs-13 mb-3 d-none" id="pharmacyPhoneDisplay"></p>'}
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#pharmacySearchModal">
                                <i class="ti ti-edit me-1"></i> Change Pharmacy
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="btnRemovePharmacy">
                                <i class="ti ti-trash me-1"></i> Remove
                            </button>
                        </div>
                    </div>
                `);

                const modal = bootstrap.Modal.getInstance(document.getElementById('pharmacySearchModal'));
                if (modal) modal.hide();

                Toast.fire({
                    icon: 'success',
                    title: 'Preferred pharmacy saved successfully.'
                });
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(originalText);
                const errorMsg = xhr.responseJSON?.message || 'Failed to update preferred pharmacy.';
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMsg
                });
            }
        });
    });

    // Remove Pharmacy
    $(document).on('click', '#btnRemovePharmacy', function() {
        Swal.fire({
            title: 'Remove Preferred Pharmacy?',
            text: 'Are you sure you want to remove your preferred pharmacy selection?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, remove it'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "{{ route('patient.preferred-pharmacy.destroy') }}",
                    type: 'DELETE',
                    data: { _token: csrfToken },
                    dataType: 'json',
                    success: function() {
                        $('#preferredPharmacyCardBody').html(`
                            <div id="preferredPharmacyEmpty" class="text-center py-3">
                                <div class="mb-2">
                                    <i class="ti ti-building-store fs-32 text-muted opacity-50"></i>
                                </div>
                                <p class="text-muted fs-13 mb-3">No preferred pharmacy selected.</p>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#pharmacySearchModal">
                                    <i class="ti ti-plus me-1"></i> Select Pharmacy
                                </button>
                            </div>
                        `);

                        Toast.fire({
                            icon: 'success',
                            title: 'Preferred pharmacy removed.'
                        });
                    },
                    error: function() {
                        Swal.fire('Error', 'Unable to remove preferred pharmacy.', 'error');
                    }
                });
            }
        });
    });

    // ==========================================
    // LABORATORY SEARCH & SELECTION LOGIC
    // ==========================================
    let labDebounceTimer = null;
    const $labInput = $('#laboratorySearchInput');
    const $labClearBtn = $('#laboratorySearchClearBtn');
    const $labResultsContainer = $('#laboratorySearchResultsContainer');

    $labInput.on('input', function() {
        const query = $(this).val().trim();
        $labClearBtn.toggle(query.length > 0);

        clearTimeout(labDebounceTimer);

        if (query.length < 2) {
            $labResultsContainer.html(`
                <div class="text-center text-muted py-5">
                    <i class="ti ti-search fs-32 text-muted opacity-50 mb-2"></i>
                    <p class="mb-0">${query.length === 1 ? 'Please type at least 2 characters to search' : 'Start typing to search available laboratories...'}</p>
                </div>
            `);
            return;
        }

        $labResultsContainer.html(`
            <div class="text-center py-5">
                <div class="spinner-border spinner-border-sm text-info me-2" role="status"></div>
                <span class="text-muted">Searching laboratories...</span>
            </div>
        `);

        labDebounceTimer = setTimeout(function() {
            $.ajax({
                url: "{{ route('patient.laboratories.search') }}",
                type: 'GET',
                data: { q: query },
                dataType: 'json',
                success: function(response) {
                    const results = response.results || [];
                    if (results.length === 0) {
                        $labResultsContainer.html(`
                            <div class="text-center text-muted py-5">
                                <i class="ti ti-microscope fs-32 text-muted opacity-50 mb-2"></i>
                                <p class="mb-0">No laboratories found matching "${escapeHtml(query)}".</p>
                            </div>
                        `);
                        return;
                    }

                    let html = '<div class="list-group list-group-flush">';
                    results.forEach(function(item) {
                        const addressParts = [];
                        if (item.street_address) addressParts.push(item.street_address);
                        if (item.city) addressParts.push(item.city);
                        if (item.state) addressParts.push(item.state);
                        if (item.postal_code) addressParts.push(item.postal_code);
                        const fullAddress = addressParts.join(', ');

                        html += `
                            <div class="list-group-item p-3 border rounded mb-2 d-flex justify-content-between align-items-center hover-bg-light">
                                <div class="me-3">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h6 class="fw-bold text-dark mb-0">${escapeHtml(item.name)}</h6>
                                        ${item.category ? `<span class="badge bg-secondary-subtle text-secondary border fs-10">${escapeHtml(item.category)}</span>` : ''}
                                    </div>
                                    <p class="text-muted fs-13 mb-1">
                                        <i class="ti ti-map-pin fs-14 me-1 text-secondary"></i>${escapeHtml(fullAddress || 'Address not specified')}
                                    </p>
                                    ${item.phone ? `<p class="text-muted fs-13 mb-0"><i class="ti ti-phone fs-14 me-1 text-secondary"></i>${escapeHtml(item.phone)}</p>` : ''}
                                </div>
                                <div>
                                    <button type="button" class="btn btn-sm btn-info text-white btn-select-lab" data-id="${item.id}" data-name="${escapeHtml(item.name)}" data-category="${escapeHtml(item.category || '')}" data-address="${escapeHtml(fullAddress)}" data-phone="${escapeHtml(item.phone || '')}">
                                        Select
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    $labResultsContainer.html(html);
                },
                error: function() {
                    $labResultsContainer.html(`
                        <div class="text-center text-danger py-4">
                            <i class="ti ti-alert-triangle fs-32 mb-2"></i>
                            <p class="mb-0">Unable to search laboratories right now. Please try again.</p>
                        </div>
                    `);
                }
            });
        }, 300);
    });

    $labClearBtn.on('click', function() {
        $labInput.val('').trigger('input').focus();
    });

    // Select Laboratory
    $(document).on('click', '.btn-select-lab', function() {
        const $btn = $(this);
        const labId = $btn.data('id');
        const originalText = $btn.html();

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span>');

        $.ajax({
            url: "{{ route('patient.preferred-laboratory.store') }}",
            type: 'POST',
            data: {
                _token: csrfToken,
                laboratory_id: labId
            },
            dataType: 'json',
            success: function(response) {
                const lab = response.laboratory;
                const addressParts = [];
                if (lab.street_address) addressParts.push(lab.street_address);
                if (lab.city) addressParts.push(lab.city);
                if (lab.state) addressParts.push(lab.state);
                if (lab.postal_code) addressParts.push(lab.postal_code);
                const fullAddress = addressParts.join(', ');

                $('#preferredLaboratoryCardBody').html(`
                    <div id="preferredLaboratorySelected">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h6 class="fw-bold text-dark mb-0" id="labNameDisplay">${escapeHtml(lab.name)}</h6>
                            ${lab.category ? `<span class="badge bg-secondary-subtle text-secondary border fs-10" id="labCategoryDisplay">${escapeHtml(lab.category)}</span>` : '<span class="badge bg-secondary-subtle text-secondary border fs-10 d-none" id="labCategoryDisplay"></span>'}
                        </div>
                        <p class="text-muted fs-13 mb-1" id="labAddressDisplay">
                            <i class="ti ti-map-pin fs-14 me-1 text-secondary"></i>${escapeHtml(fullAddress)}
                        </p>
                        ${lab.phone ? `<p class="text-muted fs-13 mb-3" id="labPhoneDisplay"><i class="ti ti-phone fs-14 me-1 text-secondary"></i>${escapeHtml(lab.phone)}</p>` : '<p class="text-muted fs-13 mb-3 d-none" id="labPhoneDisplay"></p>'}
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#laboratorySearchModal">
                                <i class="ti ti-edit me-1"></i> Change Laboratory
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="btnRemoveLab">
                                <i class="ti ti-trash me-1"></i> Remove
                            </button>
                        </div>
                    </div>
                `);

                const modal = bootstrap.Modal.getInstance(document.getElementById('laboratorySearchModal'));
                if (modal) modal.hide();

                Toast.fire({
                    icon: 'success',
                    title: 'Preferred laboratory saved successfully.'
                });
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(originalText);
                const errorMsg = xhr.responseJSON?.message || 'Failed to update preferred laboratory.';
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMsg
                });
            }
        });
    });

    // Remove Laboratory
    $(document).on('click', '#btnRemoveLab', function() {
        Swal.fire({
            title: 'Remove Preferred Laboratory?',
            text: 'Are you sure you want to remove your preferred laboratory selection?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, remove it'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "{{ route('patient.preferred-laboratory.destroy') }}",
                    type: 'DELETE',
                    data: { _token: csrfToken },
                    dataType: 'json',
                    success: function() {
                        $('#preferredLaboratoryCardBody').html(`
                            <div id="preferredLaboratoryEmpty" class="text-center py-3">
                                <div class="mb-2">
                                    <i class="ti ti-microscope fs-32 text-muted opacity-50"></i>
                                </div>
                                <p class="text-muted fs-13 mb-3">No preferred laboratory selected.</p>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#laboratorySearchModal">
                                    <i class="ti ti-plus me-1"></i> Select Laboratory
                                </button>
                            </div>
                        `);

                        Toast.fire({
                            icon: 'success',
                            title: 'Preferred laboratory removed.'
                        });
                    },
                    error: function() {
                        Swal.fire('Error', 'Unable to remove preferred laboratory.', 'error');
                    }
                });
            }
        });
    });
});
</script>
@endpush
