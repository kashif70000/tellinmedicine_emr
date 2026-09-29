@extends('layouts.layout')

@section('title', 'Site & Branding Settings | PDMS')

@section('content')
<div class="content container-fluid">
    <!-- Page Header -->
    <div class="page-header mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h3 class="page-title fw-bold text-dark mb-1">
                    <i class="ti ti-palette me-2 text-primary"></i>System Branding & Settings
                </h3>
                <ul class="breadcrumb bg-transparent p-0 mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item text-muted">Admin</li>
                    <li class="breadcrumb-item active text-primary">Branding & Logo Settings</li>
                </ul>
            </div>
        </div>
    </div>
    <!-- /Page Header -->

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4" role="alert">
            <i class="ti ti-circle-check me-2 fs-18"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
            <i class="ti ti-alert-circle me-2 fs-18"></i> <strong>There were issues with your submission:</strong>
            <ul class="mb-0 mt-2 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form action="{{ route('admin.settings.site.update') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <!-- General Settings Card -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                <i class="ti ti-app-window fs-20 text-primary me-2"></i>
                <h5 class="card-title mb-0 fw-semibold">General Application Settings</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="site_name" class="form-label fw-medium text-dark">Application Name</label>
                            <input type="text" class="form-control shadow-none" id="site_name" name="site_name" value="{{ old('site_name', $settings['site_name']) }}" placeholder="e.g. PDMS - Patient Doctor Management System">
                            <small class="text-muted">Displayed in browser titles, email templates, and application headers.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logo & Favicon Assets Card -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                <i class="ti ti-photo fs-20 text-primary me-2"></i>
                <h5 class="card-title mb-0 fw-semibold">Dynamic Logos & Favicon</h5>
            </div>
            <div class="card-body">
                <p class="text-muted fs-14 mb-4">
                    Upload dynamic branding graphics to replace default logos across the topbar, sidebar, login/register screens, invoices, and browser favicon.
                </p>

                <div class="row g-4">
                    <!-- Main Light Logo -->
                    <div class="col-lg-6">
                        <div class="p-3 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <label class="form-label fw-bold text-dark mb-0">Main Light Logo</label>
                                    <span class="badge bg-soft-primary text-primary fs-12">Light Backgrounds</span>
                                </div>
                                <p class="text-muted fs-13 mb-3">Recommended dimensions: 200x50px. Max size: 2MB. SVG, PNG, WebP supported.</p>

                                <div class="text-center p-3 mb-3 border rounded bg-white shadow-xs position-relative">
                                    <img src="{{ \App\Models\Setting::getLogo('normal') }}" alt="Main Logo Preview" id="preview_site_logo" style="max-height: 55px; width: auto;" class="img-fluid">
                                </div>
                            </div>

                            <div>
                                <div class="mb-2">
                                    <input type="file" class="form-control" name="site_logo" accept="image/*" onchange="previewImage(this, 'preview_site_logo')">
                                </div>
                                @if(!empty($settings['site_logo']))
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remove_site_logo" value="1" id="remove_site_logo">
                                        <label class="form-check-label text-danger fs-13" for="remove_site_logo">
                                            Reset to default logo
                                        </label>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Dark Logo -->
                    <div class="col-lg-6">
                        <div class="p-3 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <label class="form-label fw-bold text-dark mb-0">Dark Header / Footer Logo</label>
                                    <span class="badge bg-dark text-white fs-12">Dark Backgrounds</span>
                                </div>
                                <p class="text-muted fs-13 mb-3">Logo used for dark sidebar themes or footers. Max size: 2MB.</p>

                                <div class="text-center p-3 mb-3 border rounded shadow-xs position-relative" style="background-color: #1a1f2c;">
                                    <img src="{{ \App\Models\Setting::getLogo('dark') }}" alt="Dark Logo Preview" id="preview_site_logo_dark" style="max-height: 55px; width: auto;" class="img-fluid">
                                </div>
                            </div>

                            <div>
                                <div class="mb-2">
                                    <input type="file" class="form-control" name="site_logo_dark" accept="image/*" onchange="previewImage(this, 'preview_site_logo_dark')">
                                </div>
                                @if(!empty($settings['site_logo_dark']))
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remove_site_logo_dark" value="1" id="remove_site_logo_dark">
                                        <label class="form-check-label text-danger fs-13" for="remove_site_logo_dark">
                                            Reset to default dark logo
                                        </label>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Small / Collapsed Sidebar Logo -->
                    <div class="col-lg-6">
                        <div class="p-3 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <label class="form-label fw-bold text-dark mb-0">Small / Collapsed Sidebar Icon Logo</label>
                                    <span class="badge bg-soft-info text-info fs-12">Icon Only</span>
                                </div>
                                <p class="text-muted fs-13 mb-3">Square icon shown when sidebar is collapsed. Max size: 2MB.</p>

                                <div class="text-center p-3 mb-3 border rounded bg-white shadow-xs position-relative">
                                    <img src="{{ \App\Models\Setting::getLogo('small') }}" alt="Small Logo Preview" id="preview_site_logo_small" style="max-height: 45px; width: auto;" class="img-fluid">
                                </div>
                            </div>

                            <div>
                                <div class="mb-2">
                                    <input type="file" class="form-control" name="site_logo_small" accept="image/*" onchange="previewImage(this, 'preview_site_logo_small')">
                                </div>
                                @if(!empty($settings['site_logo_small']))
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remove_site_logo_small" value="1" id="remove_site_logo_small">
                                        <label class="form-check-label text-danger fs-13" for="remove_site_logo_small">
                                            Reset to default small logo
                                        </label>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- System Favicon -->
                    <div class="col-lg-6">
                        <div class="p-3 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <label class="form-label fw-bold text-dark mb-0">Browser Favicon</label>
                                    <span class="badge bg-soft-warning text-warning fs-12">Browser Tab Icon</span>
                                </div>
                                <p class="text-muted fs-13 mb-3">Square icon (.png or .ico recommended, 32x32px or 64x64px). Max size: 2MB.</p>

                                <div class="text-center p-3 mb-3 border rounded bg-white shadow-xs position-relative">
                                    <img src="{{ \App\Models\Setting::getFavicon() }}" alt="Favicon Preview" id="preview_site_favicon" style="width: 32px; height: 32px; object-fit: contain;">
                                </div>
                            </div>

                            <div>
                                <div class="mb-2">
                                    <input type="file" class="form-control" name="site_favicon" accept="image/*,.ico" onchange="previewImage(this, 'preview_site_favicon')">
                                </div>
                                @if(!empty($settings['site_favicon']))
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remove_site_favicon" value="1" id="remove_site_favicon">
                                        <label class="form-check-label text-danger fs-13" for="remove_site_favicon">
                                            Reset to default favicon
                                        </label>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-white border-top py-3 text-end">
                <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold">
                    <i class="ti ti-device-floppy me-1"></i> Save Changes
                </button>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
    function previewImage(input, previewId) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(previewId).src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
@endpush
@endsection
