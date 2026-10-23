@extends('layouts.layout')

@section('title', 'Doctors')

@php
    $userRole = auth()->user()->role;
@endphp

@section('content')
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <span><i class="ti ti-stethoscope"></i> Doctor Management</span>
        </div>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <div class="input-group">
                <span class="input-group-text"><i class="ti ti-search"></i></span>
                <input type="text" id="filter-search" class="form-control" placeholder="Search by name, email, or specialization..." value="{{ request('search') }}">
                <button type="button" id="filter-reset" class="btn btn-outline-primary"><i class="ti ti-x"></i> Reset</button>
            </div>
        </div>

        <div id="table-loading" class="text-center py-3 d-none">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <span class="ms-2 text-muted">Loading...</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Specialization</th>
                        <th>Experience</th>
                        <th>Fees</th>
                        <th>Rating</th>
                        <th>License Verification</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="doctors-tbody">
                    @forelse($doctors as $doctor)
                        @php
                            $licenseVerificationStatus = match ($doctor->license_state) {
                                'MA' => $doctor->license_verification_status,
                                'UK' => $doctor->uk_verification_status ?? ($doctor->is_verified ? 'verified' : 'pending'),
                                default => 'not_applicable',
                            };
                        @endphp
                        <tr>
                            <td>{{ $doctor->id }}</td>
                            <td>{{ $doctor->user->name }}</td>
                            <td>{{ $doctor->specialization }}</td>
                            <td>{{ $doctor->experience }} yrs</td>
                            <td><strong>${{ number_format($doctor->fees, 2) }}</strong></td>
                            <td>
                                <span class="badge bg-info">{{ number_format($doctor->rating_avg, 1) }} / 5</span>
                            </td>
                            <td>
                                @if($licenseVerificationStatus !== 'not_applicable')
                                    <span class="badge bg-{{ $licenseVerificationStatus === 'verified' ? 'success' : (in_array($licenseVerificationStatus, ['expired', 'failed']) ? 'danger' : 'warning') }} text-capitalize">{{ str_replace('_', ' ', $licenseVerificationStatus) }}</span>
                                @else
                                    <span class="text-muted">Not applicable</span>
                                @endif
                            </td>
                            <td>
                                @if($doctor->is_verified)
                                    <span class="badge bg-success"><i class="ti ti-check"></i> Verified</span>
                                @else
                                    <span class="badge bg-warning">Pending</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="{{ route('doctors.show', $doctor->id) }}" class="btn btn-info"><i class="ti ti-eye"></i></a>
                                    @if(auth()->user()->role === 'patient')
                                        <a href="{{ route('doctors.show', $doctor->id) }}#appointment-booking-card" class="btn btn-primary" title="{{ $doctor->is_verified ? 'Book appointment' : 'Doctor is not verified yet' }}">
                                            <i class="ti ti-calendar-plus"></i>
                                        </a>
                                    @endif
                                    @if(auth()->user()->role === 'admin')
                                        <a href="{{ route('doctor.profile', $doctor) }}" class="btn btn-outline-info" title="Open doctor profile">
                                            <i class="ti ti-user-cog"></i>
                                        </a>
                                        @if($doctor->license_state === 'MA')
                                            <button type="button" class="btn btn-outline-primary verify-license-button" data-url="{{ route('admin.doctors.license.verify', $doctor) }}" title="Verify Massachusetts license"><i class="ti ti-refresh"></i></button>
                                        @elseif($doctor->license_state === 'UK')
                                            <button type="button" class="btn btn-outline-success verify-license-button" data-url="{{ route('admin.doctors.uk.verify', $doctor) }}" title="Verify GMC registration"><i class="ti ti-shield-check"></i></button>
                                        @else
                                            <button type="button" class="btn btn-outline-secondary" disabled title="License verification is not available for this jurisdiction"><i class="ti ti-shield-question"></i></button>
                                        @endif
                                        <form action="{{ route('doctors.destroy', $doctor->id) }}" method="POST" style="display:inline;" class="delete-form">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-danger" data-doctor-id="{{ $doctor->id }}"><i class="ti ti-trash"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No doctors found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div id="doctors-pagination">
            {{ $doctors->links() }}
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const FETCH_URL = "{{ route('doctors.index') }}";
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const USER_ROLE = "{{ $userRole }}";
    const LICENSE_VERIFY_URL = "{{ route('admin.doctors.license.verify', ['doctor' => '__DOCTOR__']) }}";

    const searchInput = document.getElementById('filter-search');
    const resetBtn = document.getElementById('filter-reset');
    const tbody = document.getElementById('doctors-tbody');
    const pagination = document.getElementById('doctors-pagination');
    const loading = document.getElementById('table-loading');

    let debounceTimer = null;

    async function fetchRows(page = 1) {
        const params = new URLSearchParams({
            search: searchInput.value.trim(),
            page,
        });

        loading.classList.remove('d-none');
        tbody.style.opacity = '0.4';

        try {
            const res = await fetch(`${FETCH_URL}?${params}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await res.json();
            renderRows(data.rows);
            pagination.innerHTML = data.links;
            bindPaginationLinks();
        } catch (error) {
            console.error('Doctor fetch error:', error);
        } finally {
            loading.classList.add('d-none');
            tbody.style.opacity = '1';
        }
    }

    function renderRows(rows) {
        if (!rows || rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No doctors found</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(doctor => {
            const status = doctor.is_verified
                ? '<span class="badge bg-success"><i class="ti ti-check"></i> Verified</span>'
                : '<span class="badge bg-warning">Pending</span>';

            let actions = `<a href="${doctor.show_url}" class="btn btn-info"><i class="ti ti-eye"></i></a>`;
            if (USER_ROLE === 'admin' && doctor.profile_url) {
                actions += `<a href="${doctor.profile_url}" class="btn btn-outline-info" title="Open doctor profile"><i class="ti ti-user-cog"></i></a>`;
            }

            const licenseStatus = doctor.license_verification_status === 'not_applicable'
                ? '<span class="text-muted">Not applicable</span>'
                : `<span class="badge bg-${doctor.license_verification_status === 'verified' ? 'success' : (['failed', 'expired'].includes(doctor.license_verification_status) ? 'danger' : 'warning')} text-capitalize">${esc(doctor.license_verification_status.replaceAll('_', ' '))}</span>`;

            if (USER_ROLE === 'admin') {
                if (doctor.license_verify_url) {
                    actions += `<button type="button" class="btn btn-outline-primary verify-license-button" data-url="${doctor.license_verify_url}" title="Verify Massachusetts license"><i class="ti ti-refresh"></i></button>`;
                } else if (doctor.uk_verify_url) {
                    actions += `<button type="button" class="btn btn-outline-success verify-license-button" data-url="${doctor.uk_verify_url}" title="Verify GMC registration"><i class="ti ti-shield-check"></i></button>`;
                } else {
                    actions += '<button type="button" class="btn btn-outline-secondary" disabled title="License verification is not available for this jurisdiction"><i class="ti ti-shield-question"></i></button>';
                }
            }

            if (USER_ROLE === 'patient' && doctor.booking_url) {
                actions += `<a href="${doctor.booking_url}" class="btn btn-primary" title="${doctor.is_verified ? 'Book appointment' : 'Doctor is not verified yet'}"><i class="ti ti-calendar-plus"></i></a>`;
            }

            if (USER_ROLE === 'admin' && doctor.delete_url) {
                actions += `<form action="${doctor.delete_url}" method="POST" style="display:inline;" class="delete-form">
                    <input type="hidden" name="_token" value="${CSRF_TOKEN}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="btn btn-danger" data-doctor-id="${doctor.id}"><i class="ti ti-trash"></i></button>
                </form>`;
            }

            return `<tr>
                <td>${doctor.id}</td>
                <td>${esc(doctor.name)}</td>
                <td>${esc(doctor.specialization)}</td>
                <td>${esc(doctor.experience)}</td>
                <td><strong>${esc(doctor.fees)}</strong></td>
                <td><span class="badge bg-info">${esc(doctor.rating)}</span></td>
                <td>${licenseStatus}</td>
                <td>${status}</td>
                <td><div class="btn-group btn-group-sm" role="group">${actions}</div></td>
            </tr>`;
        }).join('');

        bindDeleteForms();
        bindLicenseVerificationButtons();
    }

    function bindLicenseVerificationButtons() {
        document.querySelectorAll('.verify-license-button').forEach(button => {
            button.addEventListener('click', async () => {
                button.disabled = true;
                try {
                    const response = await fetch(button.dataset.url, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                    });
                    const result = await response.json();
                    const outcome = {
                        verified: { title: 'License Verified', icon: 'success' },
                        manual_review: { title: 'Manual Review Required', icon: 'warning' },
                        unavailable: { title: 'Verification Unavailable', icon: 'info' },
                        unable_to_verify: { title: 'Unable to Verify', icon: 'info' },
                        expired: { title: 'License Expired', icon: 'error' },
                        failed: { title: 'Verification Failed', icon: 'error' },
                    }[result.status] || { title: 'Verification Result', icon: 'info' };
                    await Swal.fire({
                        title: outcome.title,
                        text: result.message || 'The license verification request was completed.',
                        icon: outcome.icon,
                        confirmButtonText: 'Close',
                    });
                    fetchRows();
                } catch (error) {
                    await Swal.fire({
                        title: 'Unable to Verify',
                        text: 'The verification service could not be reached. Please retry later.',
                        icon: 'info',
                        confirmButtonText: 'Close',
                    });
                } finally {
                    button.disabled = false;
                }
            }, { once: true });
        });
    }

    function bindPaginationLinks() {
        pagination.querySelectorAll('a[href]').forEach(link => {
            link.addEventListener('click', event => {
                event.preventDefault();
                const url = new URL(link.href);
                fetchRows(url.searchParams.get('page') ?? 1);
            });
        });
    }

    function bindDeleteForms() {
        tbody.querySelectorAll('.delete-form').forEach(form => {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                const form = this;

                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    }

    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchRows(), 350);
    });

    resetBtn.addEventListener('click', () => {
        searchInput.value = '';
        fetchRows();
    });

    bindPaginationLinks();
    bindDeleteForms();
    bindLicenseVerificationButtons();

    function esc(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
</script>
@endsection
