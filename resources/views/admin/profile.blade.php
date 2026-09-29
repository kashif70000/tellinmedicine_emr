@extends('layouts.layout')

@section('title', 'Profile Admin')

@section('content')

<div class="content" id="profilePage">

                <!-- Page Header -->
                <div class="mb-3 border-bottom pb-3">
                    <h4 class="fw-bold mb-0">Settings</h4>
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
                                    <form action="{{ route('profile.update', $admin->user->uuid) }}" method="POST" enctype="multipart/form-data">
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

                                            </div>
                                            <div class="col-lg-6">

                                                <!-- Name Field -->
                                                <div class="row align-items-center mb-3">
                                                    <div class="col-lg-4">
                                                        <label class="form-label mb-0">Name<span class="text-danger ms-1">*</span></label>
                                                    </div>
                                                    <div class="col-lg-8">
                                                        <input type="text" class="form-control" value="{{ $admin->user->name }}" disabled>
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
                                                        <input type="email" class="form-control" value="{{ $admin->user->email }}" disabled>
                                                    </div>
                                                </div>

                                            </div>
                                            <div class="col-lg-6">

                                                <!-- Role Field -->
                                                <div class="row align-items-center mb-3">
                                                    <div class="col-lg-4">
                                                        <label class="form-label mb-0">Role</label>
                                                    </div>
                                                    <div class="col-lg-8">
                                                        <span class="badge bg-primary">{{ ucfirst($admin->user->role) }}</span>
                                                    </div>
                                                </div>

                                            </div>
                                            <div class="col-lg-6">

                                                <!-- Status Field -->
                                                <div class="row align-items-center mb-3">
                                                    <div class="col-lg-4">
                                                        <label class="form-label mb-0">Status</label>
                                                    </div>
                                                    <div class="col-lg-8">
                                                        <span class="badge bg-{{ $admin->user->is_active ? 'success' : 'danger' }}">
                                                            {{ $admin->user->is_active ? 'Active' : 'Inactive' }}
                                                        </span>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>

                                        <!-- Permissions Information -->
                                        <div class="row border-bottom mb-3">
                                            <div class="mb-3">
                                                <h5 class="fw-bold mb-0">Permissions</h5>
                                            </div>
                                            <div class="col-lg-12">

                                                <!-- Permissions Field -->
                                                <div class="row align-items-center mb-3">
                                                    <div class="col-lg-2">
                                                        <label class="form-label mb-0">Assigned Permissions</label>
                                                    </div>
                                                    <div class="col-lg-10">
                                                        @if(is_array($admin->permissions) && count($admin->permissions) > 0)
                                                            <div class="d-flex flex-wrap gap-2">
                                                                @foreach($admin->permissions as $permission)
                                                                    <span class="badge bg-info">{{ $permission }}</span>
                                                                @endforeach
                                                            </div>
                                                        @else
                                                            <span class="text-muted">No permissions assigned</span>
                                                        @endif
                                                    </div>
                                                </div>

                                            </div>
                                        </div>

                                        {{-- <div class="d-flex align-items-center justify-content-end">
                                            <a href="{{ route('admins.index') }}" class="btn btn-light me-3">Cancel</a>
                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                        </div> --}}
                                    </form>
                                </div>
                            </div>
                        </div>

                    </div><!-- end card body -->
                </div><!-- end card -->
                                
            </div>

@endsection

@push('scripts')

@endpush
