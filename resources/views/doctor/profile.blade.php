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

{{-- Doctor Reviews Section --}}
@include('components.doctor-reviews', ['doctorId' => $doctor->id])

@endsection

@push('scripts')

@endpush
