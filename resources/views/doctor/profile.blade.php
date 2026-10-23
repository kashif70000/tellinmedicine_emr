@extends('layouts.layout')

@section('title', 'Doctor Profile')

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
                        <form action="{{ route('profile.update', $doctor->user->uuid) }}" method="POST" enctype="multipart/form-data">
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
                                            <input type="text" class="form-control" value="{{ $doctor->user->name }}" disabled>
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
                                            <input type="email" class="form-control" value="{{ $doctor->user->email }}" disabled>
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
                                            <input type="text" class="form-control" name="phone" value="{{ $doctor->phone }}">
                                        </div>
                                    </div>

                                </div>
                                <div class="col-lg-6">

                                    <!-- Specialization Field -->
                                    <div class="row align-items-center mb-3">
                                        <div class="col-lg-4">
                                            <label class="form-label mb-0">Specialization<span class="text-danger ms-1">*</span></label>
                                        </div>
                                        <div class="col-lg-8">
                                            <input type="text" class="form-control" name="specialization" value="{{ $doctor->specialization }}">
                                        </div>
                                    </div>

                                </div>
                            </div>
                            <!-- end row -->

                            <!-- Professional Information -->
                            <div class="row border-bottom mb-3">
                                <div class="mb-3">
                                    <h5 class="fw-bold mb-0">Professional Information</h5>
                                </div>
                                <div class="col-lg-6">

                                    <!-- Experience Field -->
                                    <div class="row align-items-center mb-3">
                                        <div class="col-lg-4">
                                            <label class="form-label mb-0">Experience (Years)</label>
                                        </div>
                                        <div class="col-lg-8">
                                            <input type="number" class="form-control" name="experience" value="{{ $doctor->experience }}" min="0">
                                        </div>
                                    </div>

                                </div>
                                <div class="col-lg-6">

                                    <!-- Fees Field -->
                                    <div class="row align-items-center mb-3">
                                        <div class="col-lg-4">
                                            <label class="form-label mb-0">Consultation Fees</label>
                                        </div>
                                        <div class="col-lg-8">
                                            <input type="number" class="form-control" name="fees" value="{{ $doctor->fees }}" min="0" step="0.01">
                                        </div>
                                    </div>

                                </div>
                                <div class="col-lg-6">

                                    <!-- Clinic Name Field -->
                                    <div class="row align-items-center mb-3">
                                        <div class="col-lg-4">
                                            <label class="form-label mb-0">Clinic Name</label>
                                        </div>
                                        <div class="col-lg-8">
                                            <input type="text" class="form-control" name="clinic_name" value="{{ $doctor->clinic_name }}">
                                        </div>
                                    </div>

                                </div>
                                <div class="col-lg-6">

                                    <!-- Address Field -->
                                    <div class="row align-items-center mb-3">
                                        <div class="col-lg-4">
                                            <label class="form-label mb-0">Clinic Address</label>
                                        </div>
                                        <div class="col-lg-8">
                                            <input type="text" class="form-control" name="address" value="{{ $doctor->address }}">
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
                                            <span class="badge bg-{{ $doctor->is_verified ? 'success' : 'warning' }}">
                                                {{ $doctor->is_verified ? 'Verified' : 'Not Verified' }}
                                            </span>
                                        </div>
                                    </div>

                                </div>
                                <div class="col-lg-6">

                                    <!-- Average Rating -->
                                    <div class="row align-items-center mb-3">
                                        <div class="col-lg-4">
                                            <label class="form-label mb-0">Average Rating</label>
                                        </div>
                                        <div class="col-lg-8">
                                            <span class="text-warning">
                                                <i class="ti ti-star-filled"></i> {{ $doctor->rating_avg ?? 'N/A' }}
                                            </span>
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

<section class="card mt-4" aria-labelledby="license-verification-heading">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h5 id="license-verification-heading" class="fw-bold mb-0">License Verification</h5>
        @if(auth()->user()->role === 'admin')
            <div class="d-flex gap-2">
                @if($doctor->license_state === 'MA')
                    <button type="button" id="profile-verify-license" class="btn btn-outline-primary" data-url="{{ route('admin.doctors.license.verify', $doctor) }}">
                        <i class="ti ti-refresh me-1"></i>Verify License
                    </button>
                @endif
                @if($doctor->license_state === 'UK')
                    <button type="button" id="profile-verify-uk-registration" class="btn btn-outline-success" data-url="{{ route('admin.doctors.uk.verify', $doctor) }}">
                        <i class="ti ti-shield-check me-1"></i>Verify UK Registration
                    </button>
                @endif
            </div>
        @endif
    </div>
    <div class="card-body">
        @php($officialLicense = $doctor->license_verification_data ?? [])
        @php($ukOfficialData = $doctor->uk_verification_data ?? [])
        <div class="row g-3">
            <div class="col-md-6"><span class="text-muted d-block">License state and type</span><strong>{{ $doctor->license_state ?? 'Not provided' }}{{ $doctor->license_type ? ' / ' . $doctor->license_type : '' }}</strong></div>
            <div class="col-md-6"><span class="text-muted d-block">License number</span><strong>{{ $doctor->license_number ?? 'Not provided' }}</strong></div>
            @if($doctor->license_state === 'MA')
            <div class="col-md-6"><span class="text-muted d-block">Submitted latest issue date</span><strong>{{ $doctor->license_issue_date?->format('M j, Y') ?? 'Not provided' }}</strong></div>
            <div class="col-md-6"><span class="text-muted d-block">Submitted expiration date</span><strong>{{ $doctor->license_expiration_date?->format('M j, Y') ?? 'Not provided' }}</strong></div>
            <div class="col-md-6"><span class="text-muted d-block">Official status</span><strong>{{ $officialLicense['status'] ?? $doctor->official_license_status ?? 'Not returned' }}</strong></div>
            <div class="col-md-6"><span class="text-muted d-block">Official license holder</span><strong>{{ $officialLicense['full_name'] ?? 'Not returned' }}</strong></div>
            <div class="col-md-6"><span class="text-muted d-block">Official latest issue / expiration</span><strong>{{ $officialLicense['issue_date'] ?? $doctor->official_license_issue_date?->format('Y-m-d') ?? 'Not returned' }} / {{ $officialLicense['expiration_date'] ?? $doctor->official_license_expiration_date?->format('Y-m-d') ?? 'Not returned' }}</strong></div>
            <div class="col-md-6"><span class="text-muted d-block">Verification status</span><span id="profile-license-status" class="badge bg-{{ $doctor->license_verification_status === 'verified' ? 'success' : (in_array($doctor->license_verification_status, ['failed', 'expired']) ? 'danger' : 'warning') }} text-capitalize">{{ str_replace('_', ' ', $doctor->license_verification_status ?? 'pending') }}</span></div>
            <div class="col-md-6"><span class="text-muted d-block">Last verified</span><strong id="profile-license-checked-at">{{ $doctor->license_verified_at?->format('M j, Y g:i A') ?? 'Never' }}</strong></div>
            <div class="col-12"><div id="profile-license-result" class="alert alert-secondary mb-0" role="status" aria-live="polite">{{ $doctor->license_verification_message ?? 'No verification attempt recorded.' }}</div></div>
            @elseif($doctor->license_state === 'UK')
            <div class="col-md-6"><span class="text-muted d-block">UK verification status</span><span id="profile-uk-status" class="badge bg-{{ $doctor->uk_verification_status === 'verified' ? 'success' : ($doctor->uk_verification_status === 'failed' ? 'danger' : 'warning') }} text-capitalize">{{ str_replace('_', ' ', $doctor->uk_verification_status ?? 'pending') }}</span></div>
            <div class="col-md-6"><span class="text-muted d-block">UK GMC number</span><strong>{{ $doctor->uk_gmc_number ?? $ukOfficialData['gmc_number'] ?? 'Not returned' }}</strong></div>
            <div class="col-md-6"><span class="text-muted d-block">UK matched doctor</span><strong>{{ $doctor->uk_verified_name ?? $ukOfficialData['full_name'] ?? 'Not returned' }}</strong></div>
            <div class="col-md-6"><span class="text-muted d-block">UK registration status</span><strong>{{ $doctor->uk_registration_status ?? $ukOfficialData['registration_status'] ?? 'Not returned' }}</strong></div>
            <div class="col-md-6"><span class="text-muted d-block">UK licence status</span><strong>{{ $doctor->uk_licence_status ?? $ukOfficialData['licence_status'] ?? 'Not returned' }}</strong></div>
            <div class="col-md-6"><span class="text-muted d-block">UK last verified</span><strong id="profile-uk-checked-at">{{ $doctor->uk_verified_at?->format('M j, Y g:i A') ?? 'Never' }}</strong></div>
            @if($doctor->uk_verification_status !== 'verified')
            <div class="col-md-12">
                <span class="text-muted d-block">UK profile / register search</span>
                @if($doctor->uk_profile_url ?? $ukOfficialData['profile_url'] ?? null)
                    <a href="{{ $doctor->uk_profile_url ?? $ukOfficialData['profile_url'] }}" target="_blank" rel="noopener">{{ $doctor->uk_profile_url ?? $ukOfficialData['profile_url'] }}</a>
                @elseif($doctor->license_state === 'UK')
                    <a href="https://www.gmc-uk.org/registrants?text={{ urlencode($doctor->user->name) }}&amp;allProfessions=true" target="_blank" rel="noopener">Search the GMC register for {{ $doctor->user->name }}</a>
                @else
                    <strong>Not returned</strong>
                @endif
            </div>
            @endif
            <div class="col-12"><div id="profile-uk-result" class="alert alert-{{ $doctor->uk_verification_status === 'verified' ? 'success' : ($doctor->uk_verification_status === 'failed' ? 'danger' : 'secondary') }} mb-0" role="status" aria-live="polite">{{ $doctor->uk_verification_message ?? 'No UK verification attempt recorded.' }}</div></div>
            @endif
        </div>
    </div>
</section>

{{-- Doctor Reviews Section --}}
@include('components.doctor-reviews', ['doctorId' => $doctor->id])

@endsection

@push('scripts')
<script>
document.getElementById('profile-verify-license')?.addEventListener('click', async function () {
    const button = this;
    const resultBox = document.getElementById('profile-license-result');
    button.disabled = true;
    try {
        const response = await fetch(button.dataset.url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        const result = await response.json();
        resultBox.className = `alert alert-${result.status === 'verified' ? 'success' : (['failed', 'expired'].includes(result.status) ? 'danger' : 'warning')} mb-0`;
        resultBox.textContent = result.message || 'The license verification request was completed.';
        const status = document.getElementById('profile-license-status');
        status.textContent = result.status.replaceAll('_', ' ');
        status.className = `badge bg-${result.status === 'verified' ? 'success' : (['failed', 'expired'].includes(result.status) ? 'danger' : 'warning')} text-capitalize`;
        document.getElementById('profile-license-checked-at').textContent = result.checked_at ? new Date(result.checked_at).toLocaleString() : 'Not verified';
    } catch (error) {
        resultBox.className = 'alert alert-warning mb-0';
        resultBox.textContent = 'Unable to Verify: the verification service could not be reached.';
    } finally {
        button.disabled = false;
    }
});

document.getElementById('profile-verify-uk-registration')?.addEventListener('click', async function () {
    const button = this;
    const resultBox = document.getElementById('profile-uk-result');
    button.disabled = true;
    try {
        const response = await fetch(button.dataset.url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        const result = await response.json();
        const isVerified = result.status === 'verified';
        const isFailed = result.status === 'failed';
        resultBox.className = `alert alert-${isVerified ? 'success' : (isFailed ? 'danger' : 'warning')} mb-0`;
        resultBox.textContent = result.message || 'The GMC verification request was completed.';

        const status = document.getElementById('profile-uk-status');
        status.textContent = result.status.replaceAll('_', ' ');
        status.className = `badge bg-${isVerified ? 'success' : (isFailed ? 'danger' : 'warning')} text-capitalize`;

        if (result.checked_at && isVerified) {
            document.getElementById('profile-uk-checked-at').textContent = new Date(result.checked_at).toLocaleString();
        }
    } catch (error) {
        resultBox.className = 'alert alert-warning mb-0';
        resultBox.textContent = 'Unable to reach the GMC verification service. Please retry later or use the manual register search.';
    } finally {
        button.disabled = false;
    }
});
</script>

@endpush
