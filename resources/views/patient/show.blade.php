@extends('layouts.layout')

@section('title', 'View Patient')

@section('styles')
<style>
    /* Hide floating theme customizer settings button on patient profile page only */
    .sidebar-contact,
    .sidebar-themesettings,
    #theme-settings-offcanvas {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }

    /* Clinical Workspace Medication Styling */
    .rx-med-card {
        border: 1px solid #e2e8f0;
        background-color: #ffffff;
        transition: all 0.2s ease-in-out;
    }
    .rx-group-item {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        margin-bottom: 6px;
        overflow: hidden;
        background: #ffffff;
        transition: all 0.15s ease;
    }
    .rx-group-item:hover {
        border-color: #cbd5e1;
    }
    .rx-group-item:last-child {
        margin-bottom: 0;
    }
    .rx-group-heading {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        padding: 8px 12px;
        background: #f8fafc;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        user-select: none;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .rx-group-heading:hover {
        background: #f1f5f9;
        color: #0f172a;
    }
    .rx-group-item.is-open {
        border-color: #99f6e4;
        box-shadow: 0 1px 3px rgba(13, 148, 136, 0.08);
    }
    .rx-group-item.is-open .rx-group-heading {
        background: #f0fdfa;
        color: #0f766e;
        border-bottom: 1px solid #ccfbf1;
    }
    .rx-group-chevron {
        font-size: 11px;
        transition: transform 0.2s ease;
        color: #64748b;
    }
    .rx-group-item.is-open .rx-group-chevron {
        transform: rotate(90deg);
        color: #0d9488;
    }
    .rx-group-variants {
        display: none;
        padding: 6px 8px;
        background: #ffffff;
    }
    .rx-group-item.is-open .rx-group-variants {
        display: flex;
    }
    .rx-variant-btn {
        display: flex;
        align-items: center;
        width: 100%;
        margin-left: 0;
        padding: 6px 10px;
        font-size: 12.5px;
        color: #334155;
        background: transparent;
        border: 1px solid transparent;
        border-radius: 6px;
        text-align: left;
        cursor: pointer;
        transition: background 0.15s ease, border-color 0.15s ease;
    }
    .rx-variant-btn:hover {
        background: #f1f5f9;
        color: #0d9488;
        border-color: #cbd5e1;
    }
    .rx-variant-btn.selected {
        background: #f0fdfa;
        border-color: #0d9488;
        color: #0f766e;
        font-weight: 600;
    }
    .rx-variant-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        border: 2px solid #94a3b8;
        margin-right: 8px;
        flex-shrink: 0;
    }
    .rx-variant-btn.selected .rx-variant-dot {
        border-color: #0d9488;
        background-color: #0d9488;
        box-shadow: inset 0 0 0 2px #ffffff;
    }
</style>
@endsection

@section('content')
@php
    $isDoctor = auth()->check() && auth()->user()->role === 'doctor';
@endphp

<div class="row">
    <div class="{{ $isDoctor ? 'col-xl-11 col-lg-10' : 'col-12' }}">
        <div class="card shadow-sm border">
            <div class="card-header d-flex justify-content-between align-items-center bg-white py-3">
                <span class="fw-bold fs-15 text-dark"><i class="ti ti-eye me-1 text-primary"></i> Patient Details</span>
                @if($isDoctor)
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#prescriptionModal">
                            <i class="ti ti-pill me-1"></i> Write Prescription
                        </button>
                        <button type="button" class="btn btn-outline-info btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#labOrderModal">
                            <i class="ti ti-flask me-1"></i> Order Lab Tests
                        </button>
                    </div>
                @endif
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <img src="{{ $patient->user->profile_image_url ?? asset('assets/img/users/user-08.jpg') }}" alt="{{ $patient->user->name }}" class="rounded-circle" style="width: 64px; height: 64px; object-fit: cover; border: 2px solid #e2e8f0;">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">{{ $patient->user->name }}</h5>
                        <span class="text-muted fs-13">{{ $patient->user->email }}</span>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <p class="mb-1 text-muted fs-12">Patient ID</p>
                        <p class="fw-semibold mb-0">#{{ $patient->id }}</p>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1 text-muted fs-12">Phone Number</p>
                        <p class="fw-semibold mb-0">{{ $patient->phone ?: 'Not provided' }}</p>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1 text-muted fs-12">Payment Verification</p>
                        <p class="mb-0">
                            @if($patient->is_payment_method_verified)
                                <span class="badge bg-success"><i class="ti ti-check"></i> Verified</span>
                            @else
                                <span class="badge bg-warning">Pending</span>
                            @endif
                        </p>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1 text-muted fs-12">Age</p>
                        <p class="fw-semibold mb-0">{{ $patient->age ? $patient->age . ' years' : 'Not provided' }}</p>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1 text-muted fs-12">Gender</p>
                        <p class="fw-semibold mb-0">{{ $patient->gender ? ucfirst($patient->gender) : 'Not provided' }}</p>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1 text-muted fs-12">Blood Group</p>
                        <p class="fw-semibold mb-0">
                            @if($patient->blood_group)
                                <span class="badge bg-info">{{ $patient->blood_group }}</span>
                            @else
                                <span class="text-muted">Not specified</span>
                            @endif
                        </p>
                    </div>
                    @if($patient->dob)
                        <div class="col-md-4">
                            <p class="mb-1 text-muted fs-12">Date of Birth</p>
                            <p class="fw-semibold mb-0">{{ $patient->dob }}</p>
                        </div>
                    @endif
                    @if($patient->address)
                        <div class="col-md-8">
                            <p class="mb-1 text-muted fs-12">Address</p>
                            <p class="fw-semibold mb-0">{{ $patient->address }}</p>
                        </div>
                    @endif
                </div>

                <hr class="my-4">

                @if($isDoctor)
                    <h6 class="fw-semibold mb-3"><i class="ti ti-calendar me-1"></i> Appointment History</h6>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Fee</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($patient->appointments ?? collect() as $appointment)
                                    <tr>
                                        <td>{{ $appointment->appointment_date->format('M d, Y') }}</td>
                                        <td>{{ $appointment->formatted_time }}</td>
                                        <td><span class="badge bg-light text-dark border">{{ ucfirst($appointment->status) }}</span></td>
                                        <td>${{ number_format($appointment->fee_snapshot ?? $appointment->doctor->fees ?? 0, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-muted text-center py-3">No appointment history available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="d-flex gap-2">
                    <a href="{{ route('patients.index') }}" class="btn btn-outline-secondary btn-sm"><i class="ti ti-arrow-left"></i> Back to Patients</a>
                </div>
            </div>
        </div>
    </div>

    {{-- Doctor Clinical Action Sidebar (Tool Rail) --}}
    @if($isDoctor)
        <div class="col-xl-1 col-lg-2 mt-3 mt-lg-0">
            <div class="card shadow-sm border p-2 text-center sticky-top" style="top: 85px; z-index: 10;">
                <!-- <div class="fs-11 fw-bold text-uppercase text-muted mb-2 border-bottom pb-1">Actions</div> -->
                <div class="d-flex flex-column gap-2 align-items-center">
                    {{-- 1. Medication / Prescription --}}
                    <button type="button" 
                            class="btn btn-outline-primary btn-icon rounded-circle d-flex align-items-center justify-content-center clinical-tool-btn" 
                            style="width: 44px; height: 44px;" 
                            data-bs-toggle="modal" 
                            data-bs-target="#prescriptionModal" 
                            title="Write Prescription" 
                            aria-label="Write Medication Prescription">
                        <i class="ti ti-pill fs-18"></i>
                    </button>

                    {{-- 2. Lab Tests --}}
                    <button type="button" 
                            class="btn btn-outline-info btn-icon rounded-circle d-flex align-items-center justify-content-center clinical-tool-btn" 
                            style="width: 44px; height: 44px;" 
                            data-bs-toggle="modal" 
                            data-bs-target="#labOrderModal" 
                            title="Order Lab Tests" 
                            aria-label="Order Laboratory Tests">
                        <i class="ti ti-flask fs-18"></i>
                    </button>

                    <hr class="w-100 my-1">

                    {{-- 3. Prescription History --}}
                    <button type="button" 
                            class="btn btn-outline-secondary btn-icon rounded-circle d-flex align-items-center justify-content-center clinical-tool-btn" 
                            style="width: 44px; height: 44px;" 
                            data-bs-toggle="modal" 
                            data-bs-target="#prescriptionHistoryModal" 
                            onclick="loadPrescriptionHistory()" 
                            title="Prescription History" 
                            aria-label="View Patient Prescription History">
                        <i class="ti ti-clipboard-list fs-18"></i>
                    </button>

                    {{-- 4. Lab Order History --}}
                    <button type="button" 
                            class="btn btn-outline-secondary btn-icon rounded-circle d-flex align-items-center justify-content-center clinical-tool-btn" 
                            style="width: 44px; height: 44px;" 
                            data-bs-toggle="modal" 
                            data-bs-target="#labOrderHistoryModal" 
                            onclick="loadLabOrderHistory()" 
                            title="Lab Order History" 
                            aria-label="View Patient Lab Order History">
                        <i class="ti ti-report-medical fs-18"></i>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

@if($isDoctor)
{{-- ========================================================================= --}}
{{-- MODAL 1: WRITE PRESCRIPTION WORKSPACE --}}
{{-- ========================================================================= --}}
<div class="modal fade" id="prescriptionModal" tabindex="-1" aria-labelledby="prescriptionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="avatar avatar-sm rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center">
                        <i class="ti ti-pill fs-16"></i>
                    </span>
                    <div>
                        <h5 class="modal-header-title text-white mb-0" id="prescriptionModalLabel">Medication Prescription Workspace</h5>
                        <div class="fs-12 text-white-50">Prescribe medications for {{ $patient->user->name }}</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <form id="prescriptionForm" onsubmit="submitPrescription(event)">
                    {{-- Top Metadata Row: Diagnosis / Indication & Patient Info --}}
                    <div class="card border-0 shadow-sm p-3 mb-3 bg-white rounded-3">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-7">
                                <label class="form-label fw-semibold fs-13 text-dark mb-1">Diagnosis / Clinical Indication <span class="text-muted">(Optional)</span></label>
                                <input type="text" id="rxDiagnosis" class="form-control form-control-sm" placeholder="e.g. Essential Hypertension, Type 2 Diabetes Mellitus, Hyperlipidemia">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold fs-13 text-dark mb-1">Physician General Notes <span class="text-muted">(Optional)</span></label>
                                <input type="text" id="rxNotes" class="form-control form-control-sm" placeholder="General precautions, follow-up advice...">
                            </div>
                        </div>
                    </div>

                    {{-- Medication Item Builder Section --}}
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h6 class="fw-bold fs-14 text-dark mb-0"><i class="ti ti-prescription text-primary me-1"></i> Medication Orders <span class="text-danger">*</span></h6>
                                <span class="text-muted fs-12">Search drug families and select exact strength + dosage form variants</span>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 py-1 shadow-sm" onclick="addMedicationRow()">
                                <i class="ti ti-plus me-1"></i> Add Medication
                            </button>
                        </div>
                        <div id="medicationsContainer" class="d-flex flex-column gap-3">
                            {{-- Rows added dynamically via JS --}}
                        </div>
                    </div>

                    {{-- Destination Pharmacy Section --}}
                    <div class="card border-0 shadow-sm p-3 mb-3 bg-white rounded-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold fs-13 text-dark"><i class="ti ti-building-store me-1 text-primary"></i> Destination Pharmacy <span class="text-danger">*</span></span>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fs-12 text-primary fw-medium" id="rxChangePharmacyBtn" onclick="togglePharmacyOverride(true)" style="display: none;">
                                <i class="ti ti-exchange me-1"></i> Choose Different Pharmacy
                            </button>
                        </div>

                        {{-- Preselected Preferred Pharmacy Card --}}
                        <div id="rxPreferredPharmacyCard" style="display: none;" class="p-3 bg-white rounded-3 border border-success border-opacity-50 mb-2 shadow-sm">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle fs-11 mb-1">
                                        <i class="ti ti-check me-1"></i> Patient Preferred Pharmacy
                                    </span>
                                    <div class="fw-bold text-dark fs-14" id="rxPreferredPharmacyName"></div>
                                    <div class="text-muted fs-12" id="rxPreferredPharmacyAddress"></div>
                                    <div class="text-muted fs-12" id="rxPreferredPharmacyPhone"></div>
                                </div>
                                <span class="badge bg-primary fs-11 px-2 py-1">Selected</span>
                            </div>
                        </div>

                        {{-- Manual Pharmacy Search Selector --}}
                        <div id="rxPharmacySearchSection" style="display: none;">
                            <div class="position-relative">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light"><i class="ti ti-search text-muted"></i></span>
                                    <input type="text" id="rxPharmacySearchInput" class="form-control form-control-sm" placeholder="Search local pharmacies by name, city, street, or zip..." autocomplete="off">
                                </div>
                                <div id="rxPharmacySearchResults" class="dropdown-menu shadow-lg w-100 p-2" style="max-height: 220px; overflow-y: auto; display: none; z-index: 1055;"></div>
                            </div>
                            <input type="hidden" id="rxSelectedPharmacyId" value="">
                            <div id="rxSelectedPharmacyPreview" class="mt-2 p-2 bg-light rounded border" style="display: none;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold text-dark fs-13" id="rxSelectedPharmacyNamePreview"></div>
                                        <div class="text-muted fs-12" id="rxSelectedPharmacyAddressPreview"></div>
                                    </div>
                                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 fs-11" onclick="clearSelectedPharmacy()">Change</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="rxAlertContainer"></div>

                    <div class="d-flex justify-content-end gap-2 pt-2">
                        <button type="button" class="btn btn-light btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="rxSubmitBtn" class="btn btn-primary btn-sm px-4 shadow-sm">
                            <i class="ti ti-check me-1"></i> Save & Send Prescription
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ========================================================================= --}}
{{-- MODAL 2: ORDER LAB TESTS WORKSPACE --}}
{{-- ========================================================================= --}}
<div class="modal fade" id="labOrderModal" tabindex="-1" aria-labelledby="labOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-header-title text-white mb-0" id="labOrderModalLabel">
                    <i class="ti ti-flask me-1"></i> Order Laboratory Tests
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="labOrderForm" onsubmit="submitLabOrder(event)">
                    {{-- Test Catalog Search --}}
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-14 text-dark mb-1">Select Laboratory Tests <span class="text-danger">*</span></label>
                        <div class="position-relative">
                            <input type="text" id="labTestSearchInput" class="form-control form-control-sm" placeholder="Search tests by name, alias, category, or LOINC code (e.g. CBC, Lipid, TSH)..." autocomplete="off">
                            <div id="labTestSearchResults" class="dropdown-menu shadow w-100 p-1" style="max-height: 240px; overflow-y: auto; display: none;"></div>
                        </div>
                        <div class="form-text fs-12">Type at least 2 characters to search the curated test catalog.</div>
                    </div>

                    {{-- Selected Tests Container --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-13 text-muted mb-2">Selected Tests (<span id="selectedTestCount">0</span>)</label>
                        <div id="selectedTestsList" class="d-flex flex-column gap-2 p-2 bg-light rounded border min-h-60">
                            <div class="text-muted fs-12 text-center py-2" id="noTestsPlaceholder">No laboratory tests selected yet.</div>
                        </div>
                    </div>

                    {{-- Clinical Notes --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold fs-13">Clinical Indications / Notes for Laboratory <span class="text-muted">(Optional)</span></label>
                        <textarea id="labClinicalNotes" rows="2" class="form-control form-control-sm" placeholder="Fasting status, specific symptoms, clinical notes..."></textarea>
                    </div>

                    {{-- Destination Laboratory Selection --}}
                    <div class="card bg-light border p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold fs-13 text-dark"><i class="ti ti-building me-1 text-info"></i> Destination Laboratory <span class="text-danger">*</span></span>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fs-12" id="labChangeLabBtn" onclick="toggleLaboratoryOverride(true)" style="display: none;">
                                Choose Different Laboratory
                            </button>
                        </div>

                        {{-- Preselected Preferred Laboratory Card --}}
                        <div id="labPreferredLabCard" style="display: none;" class="p-2 bg-white rounded border border-info border-opacity-50 mb-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle fs-11 mb-1">
                                        <i class="ti ti-check me-1"></i> Patient Preferred Laboratory
                                    </span>
                                    <div class="fw-bold text-dark fs-13" id="labPreferredLabName"></div>
                                    <div class="text-muted fs-12" id="labPreferredLabAddress"></div>
                                    <div class="text-muted fs-12" id="labPreferredLabPhone"></div>
                                </div>
                                <span class="badge bg-info fs-11">Selected</span>
                            </div>
                        </div>

                        {{-- Manual Laboratory Search Selector --}}
                        <div id="labLaboratorySearchSection" style="display: none;">
                            <div class="position-relative">
                                <input type="text" id="labLaboratorySearchInput" class="form-control form-control-sm" placeholder="Search local laboratories by name, city, street, or zip..." autocomplete="off">
                                <div id="labLaboratorySearchResults" class="dropdown-menu shadow w-100 p-1" style="max-height: 220px; overflow-y: auto; display: none;"></div>
                            </div>
                            <input type="hidden" id="labSelectedLaboratoryId" value="">
                            <div id="labSelectedLaboratoryPreview" class="mt-2 p-2 bg-white rounded border" style="display: none;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold text-dark fs-13" id="labSelectedLaboratoryNamePreview"></div>
                                        <div class="text-muted fs-12" id="labSelectedLaboratoryAddressPreview"></div>
                                    </div>
                                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 fs-11" onclick="clearSelectedLaboratory()">Change</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="labAlertContainer"></div>

                    <div class="d-flex justify-content-end gap-2 pt-2">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="labSubmitBtn" class="btn btn-info text-white btn-sm px-4">
                            <i class="ti ti-check me-1"></i> Save & Send Lab Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ========================================================================= --}}
{{-- MODAL 3: PRESCRIPTION HISTORY --}}
{{-- ========================================================================= --}}
<div class="modal fade" id="prescriptionHistoryModal" tabindex="-1" aria-labelledby="prescriptionHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-header-title text-white mb-0" id="prescriptionHistoryModalLabel">
                    <i class="ti ti-clipboard-list me-1"></i> Patient Prescription History
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div id="rxHistoryLoading" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <span class="ms-2 fs-13 text-muted">Loading prescription records...</span>
                </div>
                <div id="rxHistoryTableContainer" style="display: none;" class="table-responsive">
                    <table class="table table-sm align-middle table-hover">
                        <thead class="table-light fs-12">
                            <tr>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Meds</th>
                                <th>Destination Pharmacy</th>
                                <th>Email</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="rxHistoryTableBody" class="fs-13"></tbody>
                    </table>
                </div>
                <div id="rxHistoryEmpty" class="text-center py-4 text-muted fs-13" style="display: none;">
                    <i class="ti ti-notes-off fs-24 d-block mb-1 opacity-50"></i>
                    No prescriptions written for this patient yet.
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ========================================================================= --}}
{{-- MODAL 4: LAB ORDER HISTORY --}}
{{-- ========================================================================= --}}
<div class="modal fade" id="labOrderHistoryModal" tabindex="-1" aria-labelledby="labOrderHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-header-title text-white mb-0" id="labOrderHistoryModalLabel">
                    <i class="ti ti-report-medical me-1"></i> Patient Lab Order History
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div id="labHistoryLoading" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-info" role="status"></div>
                    <span class="ms-2 fs-13 text-muted">Loading lab order records...</span>
                </div>
                <div id="labHistoryTableContainer" style="display: none;" class="table-responsive">
                    <table class="table table-sm align-middle table-hover">
                        <thead class="table-light fs-12">
                            <tr>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Tests</th>
                                <th>Destination Laboratory</th>
                                <th>Email</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="labHistoryTableBody" class="fs-13"></tbody>
                    </table>
                </div>
                <div id="labHistoryEmpty" class="text-center py-4 text-muted fs-13" style="display: none;">
                    <i class="ti ti-flask-off fs-24 d-block mb-1 opacity-50"></i>
                    No laboratory orders placed for this patient yet.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const patientKey = "{{ $patient->getRouteKey() }}";
    const routes = {
        preferredDestinations: "{{ route('doctor.clinical.preferred-destinations', $patient->getRouteKey()) }}",
        medicinesSearch: "{{ route('doctor.clinical.medicines.search', $patient->getRouteKey()) }}",
        pharmaciesSearch: "{{ route('doctor.clinical.pharmacies.search', $patient->getRouteKey()) }}",
        prescriptionsStore: "{{ route('doctor.clinical.prescriptions.store', $patient->getRouteKey()) }}",
        prescriptionsIndex: "{{ route('doctor.clinical.prescriptions.index', $patient->getRouteKey()) }}",
        labTestsSearch: "{{ route('doctor.clinical.lab-tests.search', $patient->getRouteKey()) }}",
        laboratoriesSearch: "{{ route('doctor.clinical.laboratories.search', $patient->getRouteKey()) }}",
        labOrdersStore: "{{ route('doctor.clinical.lab-orders.store', $patient->getRouteKey()) }}",
        labOrdersIndex: "{{ route('doctor.clinical.lab-orders.index', $patient->getRouteKey()) }}",
    };

    let preferredPharmacy = null;
    let preferredLaboratory = null;
    let selectedLabTests = new Map(); // id -> test object
    let rxRowCount = 0;

    // Fetch patient preferred destinations on load
    fetch(routes.preferredDestinations, { headers: { 'Accept': 'application/json' } })
        .then(res => res.json())
        .then(data => {
            preferredPharmacy = data.preferred_pharmacy;
            preferredLaboratory = data.preferred_laboratory;
            setupInitialDestinations();
        })
        .catch(err => console.warn('Preferred destinations error:', err));

    function setupInitialDestinations() {
        // Pharmacy setup
        if (preferredPharmacy) {
            document.getElementById('rxPreferredPharmacyCard').style.display = 'block';
            document.getElementById('rxPreferredPharmacyName').textContent = preferredPharmacy.name;
            document.getElementById('rxPreferredPharmacyAddress').textContent = [preferredPharmacy.street_address, preferredPharmacy.city, preferredPharmacy.state, preferredPharmacy.postal_code].filter(Boolean).join(', ');
            document.getElementById('rxPreferredPharmacyPhone').textContent = preferredPharmacy.phone ? 'Phone: ' + preferredPharmacy.phone : '';
            document.getElementById('rxSelectedPharmacyId').value = preferredPharmacy.id;
            document.getElementById('rxChangePharmacyBtn').style.display = 'inline-block';
            document.getElementById('rxPharmacySearchSection').style.display = 'none';
        } else {
            togglePharmacyOverride(true);
        }

        // Laboratory setup
        if (preferredLaboratory) {
            document.getElementById('labPreferredLabCard').style.display = 'block';
            document.getElementById('labPreferredLabName').textContent = preferredLaboratory.name;
            document.getElementById('labPreferredLabAddress').textContent = [preferredLaboratory.street_address, preferredLaboratory.city, preferredLaboratory.state, preferredLaboratory.postal_code].filter(Boolean).join(', ');
            document.getElementById('labPreferredLabPhone').textContent = preferredLaboratory.phone ? 'Phone: ' + preferredLaboratory.phone : '';
            document.getElementById('labSelectedLaboratoryId').value = preferredLaboratory.id;
            document.getElementById('labChangeLabBtn').style.display = 'inline-block';
            document.getElementById('labLaboratorySearchSection').style.display = 'none';
        } else {
            toggleLaboratoryOverride(true);
        }
    }

    window.togglePharmacyOverride = function(showSearch) {
        if (showSearch) {
            document.getElementById('rxPharmacySearchSection').style.display = 'block';
            if (preferredPharmacy) {
                document.getElementById('rxPreferredPharmacyCard').style.opacity = '0.5';
            }
        }
    };

    window.toggleLaboratoryOverride = function(showSearch) {
        if (showSearch) {
            document.getElementById('labLaboratorySearchSection').style.display = 'block';
            if (preferredLaboratory) {
                document.getElementById('labPreferredLabCard').style.opacity = '0.5';
            }
        }
    };

    window.clearSelectedPharmacy = function() {
        document.getElementById('rxSelectedPharmacyId').value = '';
        document.getElementById('rxSelectedPharmacyPreview').style.display = 'none';
        document.getElementById('rxPharmacySearchInput').value = '';
        document.getElementById('rxPharmacySearchInput').focus();
    };

    window.clearSelectedLaboratory = function() {
        document.getElementById('labSelectedLaboratoryId').value = '';
        document.getElementById('labSelectedLaboratoryPreview').style.display = 'none';
        document.getElementById('labLaboratorySearchInput').value = '';
        document.getElementById('labLaboratorySearchInput').focus();
    };

    // -------------------------------------------------------------
    // ESCAPE HELPER
    // -------------------------------------------------------------
    function escapeHtml(str) {
        if (!str && str !== 0) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // -------------------------------------------------------------
    // MEDICATION PRESCRIPTION WORKSPACE LOGIC
    // -------------------------------------------------------------
    window.addMedicationRow = function(initialData = null) {
        rxRowCount++;
        const rowId = `rx-med-row-${rxRowCount}`;
        const container = document.getElementById('medicationsContainer');
        const medIndex = document.querySelectorAll('.rx-medication-item').length + 1;

        const card = document.createElement('div');
        card.className = 'card border rounded-3 p-3 shadow-sm bg-white rx-medication-item';
        card.id = rowId;
        card.innerHTML = `
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-12 fw-semibold px-2 py-1 rx-med-badge">
                        <i class="ti ti-pill me-1"></i> Medication #${medIndex}
                    </span>
                    <span class="fs-12 text-success fw-medium rx-selected-status" style="display: none;">
                        <i class="ti ti-check-circle me-1"></i> Selected
                    </span>
                </div>
                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 fs-12 rx-remove-btn" onclick="removeMedicationRow('${rowId}')" title="Remove medication">
                    <i class="ti ti-trash me-1"></i> Remove
                </button>
            </div>

            {{-- 1. Search Box --}}
            <div class="rx-search-box-container mb-3 position-relative">
                <label class="form-label fs-12 fw-semibold text-dark mb-1">
                    Search Medication <span class="text-danger">*</span>
                </label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="ti ti-search text-muted"></i></span>
                    <input type="text" class="form-control form-control-sm rx-search-input" placeholder="Type drug name (e.g. amlo, metformin, lisinopril, amoxicillin)..." autocomplete="off">
                </div>
                <div class="dropdown-menu shadow-lg w-100 p-2 rx-grouped-dropdown" style="max-height: 280px; overflow-y: auto; display: none; z-index: 1055;"></div>
            </div>

            {{-- 2. Selected Drug Concept Banner (Shown when variant chosen) --}}
            <div class="rx-selected-banner p-3 rounded-3 mb-3 border border-primary border-opacity-25 bg-light" style="display: none;">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <div class="fs-11 fw-bold text-uppercase text-primary tracking-wide mb-1">
                            <i class="ti ti-check me-1"></i> Selected Drug Concept
                        </div>
                        <h6 class="fw-bold text-dark fs-15 mb-1 rx-selected-title"></h6>
                        <div class="fs-13 text-secondary fw-semibold rx-selected-variant-text"></div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <span class="badge bg-secondary-subtle text-dark border fs-11 rx-selected-route-badge"></span>
                            <span class="badge bg-light text-muted border fs-11 rx-selected-rxcui-badge"></span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm py-1 px-2 fs-12 rx-change-drug-btn">
                        <i class="ti ti-exchange me-1"></i> Change Drug
                    </button>
                </div>
            </div>

            {{-- Hidden State Fields --}}
            <input type="hidden" class="rx-field-medicine-id" value="">
            <input type="hidden" class="rx-field-rxcui" value="">
            <input type="hidden" class="rx-field-name" value="">
            <input type="hidden" class="rx-field-generic-name" value="">
            <input type="hidden" class="rx-field-brand-name" value="">
            <input type="hidden" class="rx-field-strength" value="">
            <input type="hidden" class="rx-field-dosage-form" value="">
            <input type="hidden" class="rx-field-route" value="">

            {{-- 3. Structured Prescribing Controls (Hidden until medicine is selected) --}}
            <div class="rx-order-form" style="display: none;">
                {{-- Checkbox options --}}
                <div class="d-flex align-items-center gap-4 mb-3 pb-2 border-bottom">
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input rx-field-substitutions" type="checkbox" id="sub-${rowId}" checked>
                        <label class="form-check-label fs-12 fw-medium text-dark" for="sub-${rowId}">Substitutions Allowed</label>
                    </div>
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input rx-field-record-only" type="checkbox" id="rec-${rowId}">
                        <label class="form-check-label fs-12 fw-medium text-muted" for="rec-${rowId}">Record Only</label>
                    </div>
                </div>

                {{-- Row 1: Frequency, Days/Duration, Quantity, Units, Refills --}}
                <div class="row g-2 mb-3">
                    <div class="col-md-4 col-sm-6">
                        <label class="form-label fs-12 fw-semibold text-dark mb-1">Frequency</label>
                        <select class="form-select form-select-sm rx-field-frequency">
                            <option value="Once daily" selected>Once daily</option>
                            <option value="Twice daily">Twice daily</option>
                            <option value="Three times daily">Three times daily</option>
                            <option value="Four times daily">Four times daily</option>
                            <option value="Every 4 hours">Every 4 hours</option>
                            <option value="Every 6 hours">Every 6 hours</option>
                            <option value="Every 8 hours">Every 8 hours</option>
                            <option value="Every 12 hours">Every 12 hours</option>
                            <option value="As needed (PRN)">As needed (PRN)</option>
                            <option value="At bedtime">At bedtime</option>
                            <option value="Every other day">Every other day</option>
                            <option value="Once weekly">Once weekly</option>
                            <option value="Custom">Custom</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-3 col-6">
                        <label class="form-label fs-12 fw-semibold text-dark mb-1">Days / Duration</label>
                        <input type="text" class="form-control form-control-sm rx-field-duration" value="30 days" placeholder="30 days">
                    </div>
                    <div class="col-md-2 col-sm-3 col-6">
                        <label class="form-label fs-12 fw-semibold text-dark mb-1">Quantity</label>
                        <input type="number" min="1" class="form-control form-control-sm rx-field-quantity" value="30" placeholder="30">
                    </div>
                    <div class="col-md-2 col-sm-6 col-6">
                        <label class="form-label fs-12 fw-semibold text-dark mb-1">Units</label>
                        <select class="form-select form-select-sm rx-field-unit">
                            <option value="Tablet" selected>Tablet</option>
                            <option value="Capsule">Capsule</option>
                            <option value="mL">mL</option>
                            <option value="Packet">Packet</option>
                            <option value="Patch">Patch</option>
                            <option value="Drop">Drop</option>
                            <option value="Vial">Vial</option>
                            <option value="Spray">Spray</option>
                            <option value="Gram">Gram</option>
                            <option value="Unit">Unit</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6 col-6">
                        <label class="form-label fs-12 fw-semibold text-dark mb-1">Refills</label>
                        <select class="form-select form-select-sm rx-field-refills">
                            <option value="0" selected>0</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                            <option value="6">6</option>
                            <option value="7">7</option>
                            <option value="8">8</option>
                            <option value="9">9</option>
                            <option value="10">10</option>
                            <option value="11">11</option>
                            <option value="12">12</option>
                        </select>
                    </div>
                </div>

                {{-- Row 2: Dose Timing & Intake Method --}}
                <div class="row g-2 mb-3">
                    <div class="col-md-7">
                        <label class="form-label fs-12 fw-semibold text-dark mb-1">Dose Timing</label>
                        <div class="d-flex gap-1 flex-wrap rx-timing-group">
                            <input type="checkbox" class="btn-check" id="t-m-${rowId}" value="Morning" checked autocomplete="off">
                            <label class="btn btn-outline-primary btn-sm py-1 px-2 fs-11" for="t-m-${rowId}">Morning</label>

                            <input type="checkbox" class="btn-check" id="t-a-${rowId}" value="Afternoon" autocomplete="off">
                            <label class="btn btn-outline-primary btn-sm py-1 px-2 fs-11" for="t-a-${rowId}">Afternoon</label>

                            <input type="checkbox" class="btn-check" id="t-e-${rowId}" value="Evening" autocomplete="off">
                            <label class="btn btn-outline-primary btn-sm py-1 px-2 fs-11" for="t-e-${rowId}">Evening</label>

                            <input type="checkbox" class="btn-check" id="t-n-${rowId}" value="Night" autocomplete="off">
                            <label class="btn btn-outline-primary btn-sm py-1 px-2 fs-11" for="t-n-${rowId}">Night</label>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fs-12 fw-semibold text-dark mb-1">Intake Method</label>
                        <select class="form-select form-select-sm rx-field-intake">
                            <option value="After Food" selected>After Food</option>
                            <option value="Before Food">Before Food</option>
                            <option value="With Food">With Food</option>
                            <option value="With Water">With Water</option>
                            <option value="Empty Stomach">Empty Stomach</option>
                            <option value="As Directed">As Directed</option>
                            <option value="Custom">Custom</option>
                        </select>
                    </div>
                </div>

                {{-- Row 3: Directions (Patient Sig) --}}
                <div class="mb-2">
                    <label class="form-label fs-12 fw-semibold text-dark mb-1">Directions <span class="text-muted">(Patient Sig)</span></label>
                    <input type="text" class="form-control form-control-sm rx-field-directions" placeholder="e.g. Take 1 tablet daily by mouth with food...">
                </div>

                {{-- Row 4: Pharmacy Instructions --}}
                <div>
                    <label class="form-label fs-12 fw-semibold text-muted mb-1">Pharmacy Instructions <span class="text-muted">(Optional)</span></label>
                    <input type="text" class="form-control form-control-sm rx-field-pharmacy-instructions" placeholder="e.g. Dispense 30-day supply...">
                </div>
            </div>
        `;

        container.appendChild(card);
        bindMedicationCardEvents(card);
        updateMedicationBadges();
    };

    window.removeMedicationRow = function(rowId) {
        const row = document.getElementById(rowId);
        if (row) row.remove();
        if (document.querySelectorAll('.rx-medication-item').length === 0) {
            addMedicationRow();
        } else {
            updateMedicationBadges();
        }
    };

    function updateMedicationBadges() {
        const items = document.querySelectorAll('.rx-medication-item');
        items.forEach((item, idx) => {
            const badge = item.querySelector('.rx-med-badge');
            if (badge) badge.innerHTML = `<i class="ti ti-pill me-1"></i> Medication #${idx + 1}`;
            const removeBtn = item.querySelector('.rx-remove-btn');
            if (removeBtn) {
                removeBtn.style.display = items.length > 1 ? 'inline-block' : 'none';
            }
        });
    }

    function autoDetectUnit(dosageForm) {
        if (!dosageForm) return 'Tablet';
        const df = dosageForm.toLowerCase();
        if (df.includes('tablet')) return 'Tablet';
        if (df.includes('capsule')) return 'Capsule';
        if (df.includes('solution') || df.includes('suspension') || df.includes('syrup') || df.includes('liquid') || df.includes('elixir')) return 'mL';
        if (df.includes('patch')) return 'Patch';
        if (df.includes('drop')) return 'Drop';
        if (df.includes('spray')) return 'Spray';
        if (df.includes('packet') || df.includes('powder')) return 'Packet';
        if (df.includes('cream') || df.includes('ointment') || df.includes('gel')) return 'Gram';
        if (df.includes('vial') || df.includes('injection')) return 'Vial';
        return 'Tablet';
    }

    function bindMedicationCardEvents(card) {
        const searchInput = card.querySelector('.rx-search-input');
        const dropdown = card.querySelector('.rx-grouped-dropdown');
        const searchContainer = card.querySelector('.rx-search-box-container');
        const selectedBanner = card.querySelector('.rx-selected-banner');
        const selectedStatus = card.querySelector('.rx-selected-status');
        const changeDrugBtn = card.querySelector('.rx-change-drug-btn');
        const orderForm = card.querySelector('.rx-order-form');

        let searchTimer = null;

        // Debounced search
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimer);
            const val = this.value.trim();
            if (val.length < 2) {
                dropdown.style.display = 'none';
                return;
            }

            searchTimer = setTimeout(() => {
                fetch(`${routes.medicinesSearch}?q=${encodeURIComponent(val)}`, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.results || !data.results.length) {
                            dropdown.innerHTML = `
                                <div class="p-3 text-muted fs-12 text-center">
                                    <i class="ti ti-search-off fs-18 d-block mb-1 opacity-50"></i>
                                    No medicines found for "${escapeHtml(val)}".
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 fs-11 rx-use-custom-btn">
                                            Use Custom: "${escapeHtml(val)}"
                                        </button>
                                    </div>
                                </div>
                            `;
                            dropdown.style.display = 'block';

                            const customBtn = dropdown.querySelector('.rx-use-custom-btn');
                            if (customBtn) {
                                customBtn.addEventListener('click', () => {
                                    selectMedicationVariant(card, {
                                        id: null,
                                        rxcui: '',
                                        name: val,
                                        generic_name: val,
                                        brand_name: null,
                                        strength: '',
                                        dosage_form: 'Custom',
                                        route: 'ORAL',
                                        display_name: val,
                                        group_name: val,
                                    });
                                });
                            }
                            return;
                        }

                        // Render grouped results
                        let html = '';
                        data.results.forEach((group, index) => {
                            const variantCount = (group.variants || []).length;
                            const isSingleResult = data.results.length === 1;
                            const openClass = isSingleResult ? ' is-open' : '';

                            html += `
                                <div class="rx-group-item${openClass}" data-group-index="${index}">
                                    <div class="rx-group-heading" role="button" tabindex="0" title="Click to view dosage options">
                                        <div class="d-flex align-items-center gap-2 overflow-hidden">
                                            <i class="ti ti-folder text-primary flex-shrink-0"></i>
                                            <span class="fw-bold text-truncate">${escapeHtml(group.group_name)}</span>
                                            ${group.generic_name && group.generic_name.toLowerCase() !== group.group_name.toLowerCase() ? `<span class="badge bg-light text-muted border fw-normal fs-11 text-truncate">${escapeHtml(group.generic_name)}</span>` : ''}
                                        </div>
                                        <div class="d-flex align-items-center gap-2 flex-shrink-0 ms-2">
                                            <span class="badge bg-secondary-subtle text-secondary fs-11">${variantCount} ${variantCount === 1 ? 'option' : 'options'}</span>
                                            <i class="ti ti-chevron-right rx-group-chevron"></i>
                                        </div>
                                    </div>
                                    <div class="rx-group-variants flex-column gap-1">
                            `;

                            (group.variants || []).forEach(variant => {
                                html += `
                                    <button type="button" class="rx-variant-btn" 
                                            data-id="${variant.id || ''}"
                                            data-rxcui="${escapeHtml(variant.rxcui || '')}"
                                            data-name="${escapeHtml(variant.name || '')}"
                                            data-generic="${escapeHtml(variant.generic_name || '')}"
                                            data-brand="${escapeHtml(variant.brand_name || '')}"
                                            data-strength="${escapeHtml(variant.strength || '')}"
                                            data-form="${escapeHtml(variant.dosage_form || '')}"
                                            data-route="${escapeHtml(variant.route || '')}"
                                            data-display="${escapeHtml(variant.display_name || '')}"
                                            data-group="${escapeHtml(group.group_name || '')}">
                                        <span class="rx-variant-dot"></span>
                                        <span class="flex-grow-1">${escapeHtml(variant.display_name || variant.name)}</span>
                                        ${variant.route ? `<span class="badge bg-light text-muted border fs-10 ms-2">${escapeHtml(variant.route)}</span>` : ''}
                                    </button>
                                `;
                            });

                            html += `
                                    </div>
                                </div>
                            `;
                        });

                        dropdown.innerHTML = html;
                        dropdown.style.display = 'block';
                    })
                    .catch(err => {
                        console.error('Medicine search error:', err);
                    });
            }, 300);
        });

        // Event delegation for group toggle and variant selection
        dropdown.addEventListener('click', function(e) {
            // Check if clicking medicine group heading
            const heading = e.target.closest('.rx-group-heading');
            if (heading) {
                e.preventDefault();
                e.stopPropagation();
                const groupItem = heading.closest('.rx-group-item');
                if (groupItem) {
                    const wasOpen = groupItem.classList.contains('is-open');
                    // Optional: close other open items for cleaner accordion
                    dropdown.querySelectorAll('.rx-group-item.is-open').forEach(item => {
                        if (item !== groupItem) item.classList.remove('is-open');
                    });
                    groupItem.classList.toggle('is-open', !wasOpen);
                }
                return;
            }

            // Check if clicking variant option button
            const btn = e.target.closest('.rx-variant-btn');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();

            selectMedicationVariant(card, {
                id: btn.dataset.id,
                rxcui: btn.dataset.rxcui,
                name: btn.dataset.name,
                generic_name: btn.dataset.generic,
                brand_name: btn.dataset.brand,
                strength: btn.dataset.strength,
                dosage_form: btn.dataset.form,
                route: btn.dataset.route,
                display_name: btn.dataset.display,
                group_name: btn.dataset.group,
            });
        });

        // Keyboard support for accessibility on group heading
        dropdown.addEventListener('keydown', function(e) {
            if ((e.key === 'Enter' || e.key === ' ') && e.target.classList.contains('rx-group-heading')) {
                e.preventDefault();
                e.target.click();
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!card.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });

        // Change Drug button action
        changeDrugBtn.addEventListener('click', function() {
            selectedBanner.style.display = 'none';
            selectedStatus.style.display = 'none';
            orderForm.style.display = 'none';
            searchContainer.style.display = 'block';
            searchInput.value = '';
            searchInput.focus();
        });
    }

    function selectMedicationVariant(card, med) {
        const searchInput = card.querySelector('.rx-search-input');
        const dropdown = card.querySelector('.rx-grouped-dropdown');
        const searchContainer = card.querySelector('.rx-search-box-container');
        const selectedBanner = card.querySelector('.rx-selected-banner');
        const selectedStatus = card.querySelector('.rx-selected-status');
        const orderForm = card.querySelector('.rx-order-form');

        const selectedTitle = card.querySelector('.rx-selected-title');
        const selectedVariantText = card.querySelector('.rx-selected-variant-text');
        const selectedRouteBadge = card.querySelector('.rx-selected-route-badge');
        const selectedRxcuiBadge = card.querySelector('.rx-selected-rxcui-badge');

        const fieldMedId = card.querySelector('.rx-field-medicine-id');
        const fieldRxcui = card.querySelector('.rx-field-rxcui');
        const fieldName = card.querySelector('.rx-field-name');
        const fieldGenericName = card.querySelector('.rx-field-generic-name');
        const fieldBrandName = card.querySelector('.rx-field-brand-name');
        const fieldStrength = card.querySelector('.rx-field-strength');
        const fieldDosageForm = card.querySelector('.rx-field-dosage-form');
        const fieldRoute = card.querySelector('.rx-field-route');
        const fieldUnit = card.querySelector('.rx-field-unit');
        const fieldDirections = card.querySelector('.rx-field-directions');

        // Set state values
        fieldMedId.value = med.id || '';
        fieldRxcui.value = med.rxcui || '';
        fieldName.value = med.name;
        fieldGenericName.value = med.generic_name || '';
        fieldBrandName.value = med.brand_name || '';
        fieldStrength.value = med.strength || '';
        fieldDosageForm.value = med.dosage_form || '';
        fieldRoute.value = med.route || '';

        // Auto detect unit
        if (fieldUnit) {
            fieldUnit.value = autoDetectUnit(med.dosage_form);
        }

        // Set default directions if empty
        if (fieldDirections && !fieldDirections.value) {
            const unitWord = (fieldUnit ? fieldUnit.value : 'tablet').toLowerCase();
            const routeStr = med.route ? `by ${med.route.toLowerCase()} route` : 'by mouth';
            fieldDirections.value = `Take 1 ${unitWord} daily ${routeStr} with food`;
        }

        // Update presentation
        selectedTitle.textContent = med.group_name || med.generic_name || med.name;
        selectedVariantText.textContent = med.display_name || [med.strength, med.dosage_form].filter(Boolean).join(' ') || med.name;
        selectedRouteBadge.textContent = med.route ? 'Route: ' + med.route : 'Route: Standard';
        selectedRxcuiBadge.textContent = med.rxcui ? 'RxCUI: ' + med.rxcui : 'Clinical Drug';

        dropdown.style.display = 'none';
        searchContainer.style.display = 'none';
        selectedBanner.style.display = 'block';
        selectedStatus.style.display = 'inline-block';
        orderForm.style.display = 'block';
    }

    // Initialize 1 row on page load
    addMedicationRow();

    // Destination Pharmacy search bindings
    const rxPharmaInput = document.getElementById('rxPharmacySearchInput');
    const rxPharmaResults = document.getElementById('rxPharmacySearchResults');
    let pharmaTimer = null;

    rxPharmaInput.addEventListener('input', function() {
        clearTimeout(pharmaTimer);
        const q = this.value.trim();
        if (q.length < 2) {
            rxPharmaResults.style.display = 'none';
            return;
        }
        pharmaTimer = setTimeout(() => {
            fetch(`${routes.pharmaciesSearch}?q=${encodeURIComponent(q)}`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    if (!data.results || !data.results.length) {
                        rxPharmaResults.innerHTML = '<div class="p-2 text-muted fs-12 text-center">No pharmacies found.</div>';
                        rxPharmaResults.style.display = 'block';
                        return;
                    }
                    let html = '';
                    data.results.forEach(p => {
                        const addr = [p.street_address, p.city, p.state, p.postal_code].filter(Boolean).join(', ');
                        html += `
                            <a href="javascript:void(0);" class="dropdown-item p-2 border-bottom rx-pharmacy-opt" data-id="${p.id}" data-name="${escapeHtml(p.display_name)}" data-addr="${escapeHtml(addr)}">
                                <div class="fw-semibold text-dark fs-13">${escapeHtml(p.display_name)}</div>
                                <div class="text-muted fs-12">${escapeHtml(addr)}</div>
                            </a>
                        `;
                    });
                    rxPharmaResults.innerHTML = html;
                    rxPharmaResults.style.display = 'block';

                    rxPharmaResults.querySelectorAll('.rx-pharmacy-opt').forEach(opt => {
                        opt.addEventListener('click', function(e) {
                            e.preventDefault();
                            document.getElementById('rxSelectedPharmacyId').value = this.dataset.id;
                            document.getElementById('rxSelectedPharmacyNamePreview').textContent = this.dataset.name;
                            document.getElementById('rxSelectedPharmacyAddressPreview').textContent = this.dataset.addr;
                            document.getElementById('rxSelectedPharmacyPreview').style.display = 'block';
                            rxPharmaResults.style.display = 'none';
                            rxPharmaInput.value = '';
                        });
                    });
                });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!document.getElementById('rxPharmacySearchSection').contains(e.target)) {
            rxPharmaResults.style.display = 'none';
        }
    });

    // Prescription Submit Handler
    window.submitPrescription = function(e) {
        e.preventDefault();
        const alertEl = document.getElementById('rxAlertContainer');
        const submitBtn = document.getElementById('rxSubmitBtn');
        alertEl.innerHTML = '';

        const medItems = [];
        document.querySelectorAll('.rx-medication-item').forEach(card => {
            const name = card.querySelector('.rx-field-name').value.trim() 
                || card.querySelector('.rx-search-input').value.trim();

            if (!name) return;

            const medId = card.querySelector('.rx-field-medicine-id').value;
            const rxcui = card.querySelector('.rx-field-rxcui').value;
            const genericName = card.querySelector('.rx-field-generic-name').value;
            const brandName = card.querySelector('.rx-field-brand-name').value;
            const strength = card.querySelector('.rx-field-strength').value;
            const dosageForm = card.querySelector('.rx-field-dosage-form').value;
            const route = card.querySelector('.rx-field-route').value;
            const frequency = card.querySelector('.rx-field-frequency').value;
            const duration = card.querySelector('.rx-field-duration').value.trim();
            const quantity = card.querySelector('.rx-field-quantity').value;
            const unit = card.querySelector('.rx-field-unit').value;
            const refills = card.querySelector('.rx-field-refills').value;
            const substitutionsAllowed = card.querySelector('.rx-field-substitutions').checked;
            const recordOnly = card.querySelector('.rx-field-record-only').checked;

            const timings = [];
            card.querySelectorAll('.rx-timing-group input:checked').forEach(c => timings.push(c.value));
            const intake = card.querySelector('.rx-field-intake').value;
            const directions = card.querySelector('.rx-field-directions').value.trim();
            const pharmacyInstructions = card.querySelector('.rx-field-pharmacy-instructions').value.trim();

            medItems.push({
                medicine_id: medId || null,
                rxcui: rxcui || null,
                name: name,
                generic_name: genericName || null,
                brand_name: brandName || null,
                strength: strength || null,
                dosage_form: dosageForm || null,
                route: route || null,
                frequency: frequency,
                dosage: strength || `${quantity} ${unit}`,
                timing: timings,
                intake: intake,
                duration: duration,
                quantity: quantity,
                unit: unit,
                refills: refills,
                substitutions_allowed: substitutionsAllowed,
                record_only: recordOnly,
                directions: directions,
                pharmacy_instructions: pharmacyInstructions,
                notes: directions,
            });
        });

        if (!medItems.length) {
            alertEl.innerHTML = '<div class="alert alert-danger py-2 fs-12">Please search and select at least one medication order.</div>';
            return;
        }

        const pharmacyId = document.getElementById('rxSelectedPharmacyId').value;
        if (!pharmacyId) {
            alertEl.innerHTML = '<div class="alert alert-danger py-2 fs-12">Please select a destination pharmacy before finalizing the prescription.</div>';
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Finalizing Prescription...';

        fetch(routes.prescriptionsStore, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({
                diagnosis: document.getElementById('rxDiagnosis').value.trim(),
                notes: document.getElementById('rxNotes').value.trim(),
                pharmacy_id: pharmacyId,
                medicines: medItems,
            })
        })
        .then(async res => {
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Failed to save prescription.');
            return data;
        })
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="ti ti-check me-1"></i> Save & Send Prescription';

            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('prescriptionModal'));
            if (modal) modal.hide();

            // Reset form
            document.getElementById('prescriptionForm').reset();
            document.getElementById('medicationsContainer').innerHTML = '';
            rxRowCount = 0;
            addMedicationRow();

            // SweetAlert notification
            if (window.Swal) {
                Swal.fire({
                    icon: 'success',
                    title: 'Prescription Finalized!',
                    html: `Reference: <strong>${data.prescription.reference_number}</strong><br>Destination: <strong>${data.prescription.pharmacy}</strong><br>Patient Email: <span class="badge bg-success-subtle text-success">${data.prescription.email_status}</span>`,
                    confirmButtonColor: '#0d9488',
                });
            } else {
                alert(`Prescription saved successfully (${data.prescription.reference_number}).`);
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="ti ti-check me-1"></i> Save & Send Prescription';
            alertEl.innerHTML = `<div class="alert alert-danger py-2 fs-12">${escapeHtml(err.message)}</div>`;
        });
    };

    // -------------------------------------------------------------
    // LAB ORDER LOGIC
    // -------------------------------------------------------------
    const labTestInput = document.getElementById('labTestSearchInput');
    const labTestResults = document.getElementById('labTestSearchResults');
    let labTestTimer = null;

    labTestInput.addEventListener('input', function() {
        clearTimeout(labTestTimer);
        const q = this.value.trim();
        if (q.length < 2) {
            labTestResults.style.display = 'none';
            return;
        }
        labTestTimer = setTimeout(() => {
            fetch(`${routes.labTestsSearch}?q=${encodeURIComponent(q)}`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    if (!data.results || !data.results.length) {
                        labTestResults.innerHTML = '<div class="p-2 text-muted fs-12 text-center">No catalog tests found matching your query.</div>';
                        labTestResults.style.display = 'block';
                        return;
                    }
                    let html = '';
                    data.results.forEach(t => {
                        const isSelected = selectedLabTests.has(t.id);
                        html += `
                            <a href="javascript:void(0);" class="dropdown-item p-2 border-bottom lab-test-opt ${isSelected ? 'disabled bg-light' : ''}" data-id="${t.id}" data-json='${JSON.stringify(t)}'>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="fw-semibold text-dark fs-13">${escapeHtml(t.name)}</span>
                                        ${t.short_name ? `<span class="badge bg-secondary-subtle text-secondary fs-11 ms-1">${escapeHtml(t.short_name)}</span>` : ''}
                                        <div class="text-muted fs-11">${escapeHtml(t.category || 'General')} &bull; Specimen: ${escapeHtml(t.specimen || 'Routine')}</div>
                                    </div>
                                    ${isSelected ? '<span class="badge bg-success fs-10">Added</span>' : '<span class="badge bg-light text-dark border fs-10">+ Add</span>'}
                                </div>
                            </a>
                        `;
                    });
                    labTestResults.innerHTML = html;
                    labTestResults.style.display = 'block';

                    labTestResults.querySelectorAll('.lab-test-opt:not(.disabled)').forEach(opt => {
                        opt.addEventListener('click', function(e) {
                            e.preventDefault();
                            const test = JSON.parse(this.dataset.json);
                            addLabTest(test);
                            labTestResults.style.display = 'none';
                            labTestInput.value = '';
                        });
                    });
                });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!document.getElementById('labTestSearchInput').contains(e.target)) {
            labTestResults.style.display = 'none';
        }
    });

    function addLabTest(test) {
        if (selectedLabTests.has(test.id)) return;
        selectedLabTests.set(test.id, test);
        renderSelectedLabTests();
    }

    window.removeLabTest = function(testId) {
        selectedLabTests.delete(Number(testId));
        renderSelectedLabTests();
    };

    function renderSelectedLabTests() {
        const listEl = document.getElementById('selectedTestsList');
        const countEl = document.getElementById('selectedTestCount');
        countEl.textContent = selectedLabTests.size;

        if (selectedLabTests.size === 0) {
            listEl.innerHTML = '<div class="text-muted fs-12 text-center py-2" id="noTestsPlaceholder">No laboratory tests selected yet.</div>';
            return;
        }

        let html = '';
        selectedLabTests.forEach(test => {
            html += `
                <div class="p-2 bg-white rounded border d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-bold text-dark fs-13">${escapeHtml(test.name)}</span>
                        ${test.short_name ? `<span class="badge bg-info-subtle text-info border border-info-subtle fs-11 ms-1">${escapeHtml(test.short_name)}</span>` : ''}
                        <div class="text-muted fs-11">Category: ${escapeHtml(test.category || 'General')} &bull; Specimen: ${escapeHtml(test.specimen || 'Routine')}</div>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 fs-11" onclick="removeLabTest(${test.id})">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
            `;
        });
        listEl.innerHTML = html;
    }

    // Laboratory search bindings
    const labLabInput = document.getElementById('labLaboratorySearchInput');
    const labLabResults = document.getElementById('labLaboratorySearchResults');
    let labTimer = null;

    labLabInput.addEventListener('input', function() {
        clearTimeout(labTimer);
        const q = this.value.trim();
        if (q.length < 2) {
            labLabResults.style.display = 'none';
            return;
        }
        labTimer = setTimeout(() => {
            fetch(`${routes.laboratoriesSearch}?q=${encodeURIComponent(q)}`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    if (!data.results || !data.results.length) {
                        labLabResults.innerHTML = '<div class="p-2 text-muted fs-12 text-center">No laboratories found.</div>';
                        labLabResults.style.display = 'block';
                        return;
                    }
                    let html = '';
                    data.results.forEach(l => {
                        const addr = [l.street_address, l.city, l.state, l.postal_code].filter(Boolean).join(', ');
                        html += `
                            <a href="javascript:void(0);" class="dropdown-item p-2 border-bottom lab-lab-opt" data-id="${l.id}" data-name="${escapeHtml(l.display_name)}" data-addr="${escapeHtml(addr)}">
                                <div class="fw-semibold text-dark fs-13">${escapeHtml(l.display_name)}</div>
                                <div class="text-muted fs-12">${escapeHtml(addr)}</div>
                            </a>
                        `;
                    });
                    labLabResults.innerHTML = html;
                    labLabResults.style.display = 'block';

                    labLabResults.querySelectorAll('.lab-lab-opt').forEach(opt => {
                        opt.addEventListener('click', function(e) {
                            e.preventDefault();
                            document.getElementById('labSelectedLaboratoryId').value = this.dataset.id;
                            document.getElementById('labSelectedLaboratoryNamePreview').textContent = this.dataset.name;
                            document.getElementById('labSelectedLaboratoryAddressPreview').textContent = this.dataset.addr;
                            document.getElementById('labSelectedLaboratoryPreview').style.display = 'block';
                            labLabResults.style.display = 'none';
                            labLabInput.value = '';
                        });
                    });
                });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!document.getElementById('labLaboratorySearchSection').contains(e.target)) {
            labLabResults.style.display = 'none';
        }
    });

    // Lab Order submit
    window.submitLabOrder = function(e) {
        e.preventDefault();
        const alertEl = document.getElementById('labAlertContainer');
        const submitBtn = document.getElementById('labSubmitBtn');
        alertEl.innerHTML = '';

        const testIds = Array.from(selectedLabTests.keys());
        if (!testIds.length) {
            alertEl.innerHTML = '<div class="alert alert-danger py-2 fs-12">Please select at least one laboratory test.</div>';
            return;
        }

        const laboratoryId = document.getElementById('labSelectedLaboratoryId').value;
        if (!laboratoryId) {
            alertEl.innerHTML = '<div class="alert alert-danger py-2 fs-12">Please select a destination laboratory before saving the lab order.</div>';
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

        fetch(routes.labOrdersStore, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({
                test_ids: testIds,
                laboratory_id: laboratoryId,
                clinical_notes: document.getElementById('labClinicalNotes').value.trim(),
            })
        })
        .then(async res => {
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Failed to save lab order.');
            return data;
        })
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="ti ti-check me-1"></i> Save & Send Lab Order';

            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('labOrderModal'));
            if (modal) modal.hide();

            // Reset form
            document.getElementById('labOrderForm').reset();
            selectedLabTests.clear();
            renderSelectedLabTests();

            if (window.Swal) {
                Swal.fire({
                    icon: 'success',
                    title: 'Lab Order Saved!',
                    html: `Reference: <strong>${data.lab_order.reference_number}</strong><br>Destination: ${data.lab_order.laboratory}<br>Tests ordered: ${data.lab_order.test_count}<br>Patient email: ${data.lab_order.email_status}`,
                    confirmButtonColor: '#0284c7',
                });
            } else {
                alert(`Lab Order saved successfully (${data.lab_order.reference_number}).`);
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="ti ti-check me-1"></i> Save & Send Lab Order';
            alertEl.innerHTML = `<div class="alert alert-danger py-2 fs-12">${escapeHtml(err.message)}</div>`;
        });
    };

    // -------------------------------------------------------------
    // HISTORY MODAL LOADERS
    // -------------------------------------------------------------
    window.loadPrescriptionHistory = function() {
        const loading = document.getElementById('rxHistoryLoading');
        const container = document.getElementById('rxHistoryTableContainer');
        const empty = document.getElementById('rxHistoryEmpty');
        const tbody = document.getElementById('rxHistoryTableBody');

        loading.style.display = 'block';
        container.style.display = 'none';
        empty.style.display = 'none';

        fetch(routes.prescriptionsIndex, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                loading.style.display = 'none';
                if (!data.prescriptions || !data.prescriptions.length) {
                    empty.style.display = 'block';
                    return;
                }
                let html = '';
                data.prescriptions.forEach(p => {
                    html += `
                        <tr>
                            <td>${escapeHtml(p.date)}</td>
                            <td><span class="badge bg-light text-dark border">${escapeHtml(p.reference_number)}</span></td>
                            <td><span class="badge bg-primary-subtle text-primary">${p.medication_count} meds</span></td>
                            <td>${escapeHtml(p.pharmacy_name)}</td>
                            <td><span class="badge ${p.email_status === 'Sent' ? 'bg-success' : 'bg-secondary'}">${escapeHtml(p.email_status)}</span></td>
                            <td class="text-end">
                                <a href="${escapeHtml(p.view_url)}" target="_blank" class="btn btn-outline-primary btn-sm py-0 px-2 fs-11">
                                    <i class="ti ti-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
                container.style.display = 'block';
            })
            .catch(err => {
                loading.style.display = 'none';
                empty.innerHTML = `<div class="text-danger fs-12">Failed to load prescriptions: ${escapeHtml(err.message)}</div>`;
                empty.style.display = 'block';
            });
    };

    window.loadLabOrderHistory = function() {
        const loading = document.getElementById('labHistoryLoading');
        const container = document.getElementById('labHistoryTableContainer');
        const empty = document.getElementById('labHistoryEmpty');
        const tbody = document.getElementById('labHistoryTableBody');

        loading.style.display = 'block';
        container.style.display = 'none';
        empty.style.display = 'none';

        fetch(routes.labOrdersIndex, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                loading.style.display = 'none';
                if (!data.lab_orders || !data.lab_orders.length) {
                    empty.style.display = 'block';
                    return;
                }
                let html = '';
                data.lab_orders.forEach(o => {
                    html += `
                        <tr>
                            <td>${escapeHtml(o.date)}</td>
                            <td><span class="badge bg-light text-dark border">${escapeHtml(o.reference_number)}</span></td>
                            <td><span class="badge bg-info-subtle text-info">${o.test_count} tests</span></td>
                            <td>${escapeHtml(o.laboratory_name)}</td>
                            <td><span class="badge ${o.email_status === 'Sent' ? 'bg-success' : 'bg-secondary'}">${escapeHtml(o.email_status)}</span></td>
                            <td class="text-end">
                                <a href="${escapeHtml(o.view_url)}" target="_blank" class="btn btn-outline-info btn-sm py-0 px-2 fs-11">
                                    <i class="ti ti-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
                container.style.display = 'block';
            })
            .catch(err => {
                loading.style.display = 'none';
                empty.innerHTML = `<div class="text-danger fs-12">Failed to load lab orders: ${escapeHtml(err.message)}</div>`;
                empty.style.display = 'block';
            });
    };

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
});
</script>
@endif
@endsection
