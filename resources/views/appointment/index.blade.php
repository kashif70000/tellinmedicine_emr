@extends('layouts.layout')

@section('title', 'Appointments')

@php
    $userRole = auth()->user()->role;
@endphp

@section('content')
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="ti ti-calendar"></i> {{ ucfirst($listScope) }}</span>
            @if($userRole === 'patient')
                <a href="{{ route('appointments.create') }}" class="btn btn-sm btn-light"><i class="ti ti-plus"></i> Book Appointment</a>
            @endif
        </div>
    </div>
    <div class="card-body">

        {{-- ── Filter Bar ── --}}
        <div class="row g-2 mb-3" id="filter-bar">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="ti ti-search"></i></span>
                    <input type="text" id="filter-search" class="form-control"
                           placeholder="Search doctor or patient…"
                           value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-3">
                <input type="date" id="filter-date" class="form-control"
                       value="{{ request('appointment_date') }}">
            </div>
            <div class="col-md-3">
                <select id="filter-status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="pending"   {{ request('status') == 'pending'   ? 'selected' : '' }}>Pending</option>
                    <option value="approved"  {{ request('status') == 'approved'  ? 'selected' : '' }}>Approved</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                </select>
            </div>
            <div class="col-md-2">
                <button id="filter-reset" class="btn btn-outline-primary w-100">
                    <i class="ti ti-x"></i> Reset
                </button>
            </div>
        </div>

        {{-- ── Loading Spinner ── --}}
        <div id="table-loading" class="text-center py-3 d-none">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Loading…</span>
            </div>
            <span class="ms-2 text-muted">Loading…</span>
        </div>

        {{-- ── Table ── --}}
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Doctor</th>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Fee</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="appt-tbody">
                    @forelse($appointments as $appointment)
                        <tr>
                            <td>{{ $appointment->id }}</td>
                            <td>{{ $appointment->doctor->user->name }}</td>
                            <td>{{ $appointment->patient->user->name }}</td>
                            <td>{{ $appointment->appointment_date->format('M d, Y') }}</td>
                            <td>{{ $appointment->formatted_time }}</td>
                            <td>${{ number_format($appointment->fee_snapshot ?? $appointment->doctor->fees, 2) }}</td>
                            <td>
                                <span class="badge bg-{{ $appointment->status === 'approved' ? 'success' : ($appointment->status === 'cancelled' ? 'danger' : ($appointment->status === 'completed' ? 'secondary' : 'warning')) }}">
                                    {{ ucfirst($appointment->status) }}
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="{{ route('appointments.show', $appointment) }}" class="btn btn-info"><i class="ti ti-eye"></i></a>
                                    @if($userRole === 'admin')
                                        <a href="{{ route('appointments.edit', $appointment) }}" class="btn btn-warning"><i class="ti ti-pencil"></i></a>
                                    @endif
                                    @if(in_array($userRole, ['admin', 'doctor'], true) && $appointment->status === 'pending')
                                        <form action="{{ route('doctor.appointments.approve', $appointment) }}" method="POST" style="display:inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-success" title="Approve"><i class="ti ti-check"></i></button>
                                        </form>
                                        <form action="{{ route('doctor.appointments.reject', $appointment) }}" method="POST" style="display:inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-danger" title="Reject"><i class="ti ti-x"></i></button>
                                        </form>
                                    @endif
                                    @if($userRole === 'admin')
                                        <button type="button" class="btn btn-danger btn-delete"
                                                data-url="{{ route('appointments.destroy', $appointment) }}">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No appointments found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ── Pagination ── --}}
        <div id="appt-pagination">
            {{ $appointments->links() }}
        </div>

    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const FETCH_URL   = "{{ route('appointments.index') }}";
    const CSRF_TOKEN  = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const USER_ROLE   = "{{ $userRole }}";

    const searchInput = document.getElementById('filter-search');
    const dateInput   = document.getElementById('filter-date');
    const statusSel   = document.getElementById('filter-status');
    const resetBtn    = document.getElementById('filter-reset');
    const tbody       = document.getElementById('appt-tbody');
    const pagination  = document.getElementById('appt-pagination');
    const loading     = document.getElementById('table-loading');

    let debounceTimer = null;

    // ── Fetch rows via AJAX ──────────────────────────────────────────
    async function fetchRows(page = 1) {
        const params = new URLSearchParams({
            search:           searchInput.value.trim(),
            appointment_date: dateInput.value,
            status:           statusSel.value,
            page,
        });

        loading.classList.remove('d-none');
        tbody.style.opacity = '0.4';

        try {
            const res  = await fetch(`${FETCH_URL}?${params}`, {
                headers: {
                    'Accept':        'application/json',
                    'X-CSRF-TOKEN':  CSRF_TOKEN,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await res.json();
            renderRows(data.rows);
            pagination.innerHTML = data.links;
            bindPaginationLinks();
        } catch (e) {
            console.error('Appointment fetch error:', e);
        } finally {
            loading.classList.add('d-none');
            tbody.style.opacity = '1';
        }
    }

    // ── Render rows ──────────────────────────────────────────────────
    function renderRows(rows) {
        if (!rows || rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No appointments found</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(a => {
            const statusColor = { approved: 'success', cancelled: 'danger', completed: 'secondary', pending: 'warning' }[a.status] ?? 'secondary';

            let actions = `<a href="${a.show_url}" class="btn btn-sm btn-info"><i class="ti ti-eye"></i></a>`;

            if (USER_ROLE === 'admin' && a.edit_url) {
                actions += `<a href="${a.edit_url}" class="btn btn-sm btn-warning"><i class="ti ti-pencil"></i></a>`;
            }

            if (a.is_pending && a.approve_url) {
                actions += `
                    <button class="btn btn-sm btn-success btn-quick-action"
                            data-url="${a.approve_url}" data-confirm="Approve this appointment?">
                        <i class="ti ti-check"></i>
                    </button>
                    <button class="btn btn-sm btn-danger btn-quick-action"
                            data-url="${a.reject_url}" data-confirm="Reject this appointment?">
                        <i class="ti ti-x"></i>
                    </button>`;
            }

            if (USER_ROLE === 'admin' && a.delete_url) {
                actions += `<button class="btn btn-sm btn-danger btn-delete" data-url="${a.delete_url}"><i class="ti ti-trash"></i></button>`;
            }

            return `<tr>
                <td>${a.id}</td>
                <td>${esc(a.doctor)}</td>
                <td>${esc(a.patient)}</td>
                <td>${esc(a.date)}</td>
                <td>${esc(a.time)}</td>
                <td>${esc(a.fee)}</td>
                <td><span class="badge bg-${statusColor}">${cap(a.status)}</span></td>
                <td><div class="btn-group btn-group-sm">${actions}</div></td>
            </tr>`;
        }).join('');

        bindActionButtons();
    }

    // ── Bind pagination links inside the dynamic container ──────────
    function bindPaginationLinks() {
        pagination.querySelectorAll('a[href]').forEach(link => {
            link.addEventListener('click', e => {
                e.preventDefault();
                const url   = new URL(link.href);
                const page  = url.searchParams.get('page') ?? 1;
                fetchRows(page);
            });
        });
    }

    // ── Bind delete + quick-action buttons ──────────────────────────
    function bindActionButtons() {
        tbody.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', () => {
                const url = btn.dataset.url;
                Swal.fire({
                    title: 'Delete appointment?',
                    text: 'This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    confirmButtonColor: '#d33',
                }).then(r => {
                    if (!r.isConfirmed) return;
                    submitPost(url, { _method: 'DELETE' }).then(() => fetchRows());
                });
            });
        });

        tbody.querySelectorAll('.btn-quick-action').forEach(btn => {
            btn.addEventListener('click', () => {
                const url     = btn.dataset.url;
                const confirm = btn.dataset.confirm;
                Swal.fire({
                    title: confirm,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes',
                }).then(r => {
                    if (!r.isConfirmed) return;
                    submitPost(url, {}).then(() => fetchRows());
                });
            });
        });
    }

    // ── POST helper (CSRF-safe) ──────────────────────────────────────
    async function submitPost(url, extraFields) {
        const body = new URLSearchParams({ _token: CSRF_TOKEN, ...extraFields });
        await fetch(url, { method: 'POST', body, headers: { 'X-CSRF-TOKEN': CSRF_TOKEN } });
    }

    // ── Input listeners ─────────────────────────────────────────────
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchRows(), 350);
    });
    dateInput.addEventListener('change',   () => fetchRows());
    statusSel.addEventListener('change',   () => fetchRows());

    resetBtn.addEventListener('click', () => {
        searchInput.value = '';
        dateInput.value   = '';
        statusSel.value   = '';
        fetchRows();
    });

    // ── Static delete buttons (initial SSR rows) ────────────────────
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', () => {
            Swal.fire({
                title: 'Delete appointment?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                confirmButtonColor: '#d33',
            }).then(r => {
                if (!r.isConfirmed) return;
                submitPost(btn.dataset.url, { _method: 'DELETE' }).then(() => location.reload());
            });
        });
    });

    // Bind pagination on initial load (SSR)
    bindPaginationLinks();

    // ── Utils ────────────────────────────────────────────────────────
    function esc(s) { return (s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function cap(s) { return (s ?? '').charAt(0).toUpperCase() + s.slice(1); }
})();
</script>
@endsection

