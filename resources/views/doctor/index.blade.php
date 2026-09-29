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
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="doctors-tbody">
                    @forelse($doctors as $doctor)
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
                            <td colspan="8" class="text-center text-muted py-4">No doctors found</td>
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
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No doctors found</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(doctor => {
            const status = doctor.is_verified
                ? '<span class="badge bg-success"><i class="ti ti-check"></i> Verified</span>'
                : '<span class="badge bg-warning">Pending</span>';

            let actions = `<a href="${doctor.show_url}" class="btn btn-info"><i class="ti ti-eye"></i></a>`;

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
                <td>${status}</td>
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
