@extends('layouts.layout')

@section('title', 'Patients')

@php
    $userRole = auth()->user()->role;
    $patientColspan = $userRole === 'doctor' ? 9 : 8;
@endphp

@section('content')
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <span><i class="ti ti-user-heart"></i> {{ ucfirst($listScope ?? 'Patient Management') }}</span>
        </div>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <div class="input-group">
                <span class="input-group-text"><i class="ti ti-search"></i></span>
                <input type="text" id="filter-search" class="form-control" placeholder="Search by name or email..." value="{{ request('search') }}">
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
                        <th>Email</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Blood Group</th>
                        <th>Verified</th>
                        @if($userRole === 'doctor')
                            <th>Appointment History</th>
                        @endif
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="patients-tbody">
                    @forelse($patients as $patient)
                        <tr>
                            <td>{{ $patient->id }}</td>
                            <td>{{ $patient->user->name }}</td>
                            <td>{{ $patient->user->email }}</td>
                            <td>{{ $patient->age }}</td>
                            <td>{{ ucfirst($patient->gender) }}</td>
                            <td><span class="badge bg-info">{{ $patient->blood_group }}</span></td>
                            <td>
                                @if($patient->is_payment_method_verified)
                                    <span class="badge bg-success"><i class="ti ti-check"></i> Yes</span>
                                @else
                                    <span class="badge bg-warning">No</span>
                                @endif
                            </td>
                            @if($userRole === 'doctor')
                                <td>{{ $patient->appointment_history_count ?? 0 }}</td>
                            @endif
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="{{ route('patients.show', $patient->id) }}" class="btn btn-info"><i class="ti ti-eye"></i></a>
                                    @if($userRole === 'admin')
                                        <form action="{{ route('patients.destroy', $patient->id) }}" method="POST" style="display:inline;" class="delete-form">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-danger" data-patient-id="{{ $patient->id }}"><i class="ti ti-trash"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $patientColspan }}" class="text-center text-muted py-4">No patients found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div id="patients-pagination">
            {{ $patients->links() }}
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const FETCH_URL = "{{ route('patients.index') }}";
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const USER_ROLE = "{{ $userRole }}";
    const COLSPAN = {{ $patientColspan }};

    const searchInput = document.getElementById('filter-search');
    const resetBtn = document.getElementById('filter-reset');
    const tbody = document.getElementById('patients-tbody');
    const pagination = document.getElementById('patients-pagination');
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
            console.error('Patient fetch error:', error);
        } finally {
            loading.classList.add('d-none');
            tbody.style.opacity = '1';
        }
    }

    function renderRows(rows) {
        if (!rows || rows.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="text-center text-muted py-4">No patients found</td></tr>`;
            return;
        }

        tbody.innerHTML = rows.map(patient => {
            const verified = patient.is_payment_method_verified
                ? '<span class="badge bg-success"><i class="ti ti-check"></i> Yes</span>'
                : '<span class="badge bg-warning">No</span>';
            const history = USER_ROLE === 'doctor' ? `<td>${patient.appointment_history_count}</td>` : '';

            let actions = `<a href="${patient.show_url}" class="btn btn-info"><i class="ti ti-eye"></i></a>`;
            if (USER_ROLE === 'admin' && patient.delete_url) {
                actions += `<form action="${patient.delete_url}" method="POST" style="display:inline;" class="delete-form">
                    <input type="hidden" name="_token" value="${CSRF_TOKEN}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="btn btn-danger" data-patient-id="${patient.id}"><i class="ti ti-trash"></i></button>
                </form>`;
            }

            return `<tr>
                <td>${patient.id}</td>
                <td>${esc(patient.name)}</td>
                <td>${esc(patient.email)}</td>
                <td>${esc(patient.age)}</td>
                <td>${esc(patient.gender)}</td>
                <td><span class="badge bg-info">${esc(patient.blood_group)}</span></td>
                <td>${verified}</td>
                ${history}
                <td><div class="btn-group btn-group-sm" role="group">${actions}</div></td>
            </tr>`;
        }).join('');

        bindDeleteForms();
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
