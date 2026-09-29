@extends('layouts.layout')

@section('title', 'Doctor Verification Requests')

@section('styles')
<style>
    .verification-shell {
        display: grid;
        gap: 1.5rem;
    }
    .verification-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 0;
    }
    .verification-subtitle {
        margin: .35rem 0 0;
        color: #6b7280;
    }
    .verification-card {
        border: 0;
        border-radius: 24px;
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
        overflow: hidden;
    }
    .verification-card .card-body {
        padding: 1.5rem;
    }
    .doctor-identity {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .doctor-avatar {
        width: 58px;
        height: 58px;
        border-radius: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.14), rgba(16, 185, 129, 0.18));
        color: #2563eb;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75);
    }
    .doctor-avatar i {
        font-size: 1.6rem;
    }
    .doctor-name {
        font-size: 1.1rem;
        font-weight: 700;
        color: #111827;
        margin-bottom: .2rem;
    }
    .doctor-email {
        margin-bottom: .45rem;
        color: #6b7280;
    }
    .status-chip {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .45rem .8rem;
        border-radius: 999px;
        background: rgba(245, 158, 11, 0.14);
        color: #b45309;
        font-weight: 600;
        text-transform: capitalize;
    }
    .status-chip i {
        font-size: .95rem;
    }
    .action-toolbar {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: .6rem;
    }
    .action-btn {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 0;
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12);
        transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
    }
    .action-btn i {
        font-size: 1.15rem;
    }
    .action-btn:hover {
        transform: scale(1.05);
        box-shadow: 0 18px 30px rgba(15, 23, 42, 0.16);
        filter: saturate(1.05);
    }
    .action-btn.btn-primary {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
    }
    .action-btn.btn-success {
        background: linear-gradient(135deg, #22c55e, #16a34a);
    }
    .action-btn.btn-danger {
        background: linear-gradient(135deg, #f87171, #dc2626);
    }
    .action-btn.btn-info {
        background: linear-gradient(135deg, #38bdf8, #0ea5e9);
        color: #fff;
    }
    .action-state {
        min-height: 44px;
        padding: .6rem .9rem;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        gap: .55rem;
        background: rgba(34, 197, 94, 0.14);
        color: #15803d;
        font-weight: 700;
        box-shadow: inset 0 0 0 1px rgba(34, 197, 94, 0.12);
    }
    .action-state i {
        font-size: 1rem;
    }
    .ai-panel {
        margin-top: 1.35rem;
        padding: 1.35rem;
        border-radius: 22px;
        background:
            radial-gradient(circle at top right, rgba(59, 130, 246, 0.12), transparent 34%),
            linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid rgba(148, 163, 184, 0.2);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
    }
    .ai-panel-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: 1rem;
    }
    .ai-title {
        display: flex;
        align-items: center;
        gap: .65rem;
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
    }
    .ai-title-badge {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #dbeafe, #dcfce7);
        color: #2563eb;
    }
    .ai-status-pill {
        padding: .45rem .85rem;
        border-radius: 999px;
        font-weight: 700;
        text-transform: capitalize;
        background: rgba(148, 163, 184, 0.18);
        color: #334155;
    }
    .ai-status-pill.status-valid {
        background: rgba(34, 197, 94, 0.16);
        color: #15803d;
    }
    .ai-status-pill.status-suspicious {
        background: rgba(245, 158, 11, 0.18);
        color: #b45309;
    }
    .ai-status-pill.status-fake {
        background: rgba(239, 68, 68, 0.16);
        color: #b91c1c;
    }
    .ai-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }
    .metric-card {
        padding: 1rem;
        border-radius: 18px;
        background: #fff;
        border: 1px solid rgba(226, 232, 240, 0.9);
        box-shadow: 0 14px 28px rgba(15, 23, 42, 0.05);
    }
    .metric-label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .75rem;
        color: #475569;
        font-weight: 600;
        font-size: .95rem;
    }
    .metric-label i {
        color: #2563eb;
        font-size: 1rem;
    }
    .metric-value {
        font-size: 1.45rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: .7rem;
    }
    .metric-progress {
        height: 10px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
    }
    .metric-progress > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(135deg, #4f46e5, #06b6d4);
    }
    .metric-progress.risk > span {
        background: linear-gradient(135deg, #f97316, #ef4444);
    }
    .ai-observations {
        padding: 1rem;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(226, 232, 240, 0.9);
    }
    .ai-observations-title {
        display: flex;
        align-items: center;
        gap: .55rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: .75rem;
    }
    .insight-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        gap: .75rem;
    }
    .insight-list li {
        display: flex;
        gap: .75rem;
        align-items: flex-start;
        padding: .8rem .9rem;
        border-radius: 14px;
        background: linear-gradient(180deg, #f8fafc, #ffffff);
        color: #475569;
    }
    .insight-list i {
        color: #2563eb;
        font-size: 1rem;
        margin-top: .1rem;
    }
    :root[data-bs-theme="dark"] .verification-title {
        color: #d9dcff;
    }
    :root[data-bs-theme="dark"] .verification-subtitle,
    :root[data-bs-theme="dark"] .doctor-email {
        color: #9facbf;
    }
    :root[data-bs-theme="dark"] .verification-card {
        background: #03041a;
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.35);
    }
    :root[data-bs-theme="dark"] .doctor-name {
        color: #e6eefc;
    }
    :root[data-bs-theme="dark"] .status-chip {
        background: rgba(245, 158, 11, 0.18);
        color: #fbbf24;
    }
    :root[data-bs-theme="dark"] .action-state {
        background: rgba(34, 197, 94, 0.16);
        color: #86efac;
        box-shadow: inset 0 0 0 1px rgba(34, 197, 94, 0.16);
    }
    :root[data-bs-theme="dark"] .ai-panel {
        background:
            radial-gradient(circle at top right, rgba(59, 130, 246, 0.16), transparent 34%),
            linear-gradient(180deg, rgba(10, 14, 35, 0.96) 0%, rgba(3, 4, 26, 0.98) 100%);
        border-color: rgba(159, 172, 191, 0.16);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
    }
    :root[data-bs-theme="dark"] .ai-title,
    :root[data-bs-theme="dark"] .ai-observations-title,
    :root[data-bs-theme="dark"] .metric-value {
        color: #e6eefc;
    }
    :root[data-bs-theme="dark"] .ai-title-badge {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.18), rgba(34, 197, 94, 0.16));
        color: #93c5fd;
    }
    :root[data-bs-theme="dark"] .ai-status-pill {
        background: rgba(148, 163, 184, 0.14);
        color: #cbd5e1;
    }
    :root[data-bs-theme="dark"] .ai-status-pill.status-valid {
        background: rgba(34, 197, 94, 0.18);
        color: #86efac;
    }
    :root[data-bs-theme="dark"] .ai-status-pill.status-suspicious {
        background: rgba(245, 158, 11, 0.18);
        color: #fbbf24;
    }
    :root[data-bs-theme="dark"] .ai-status-pill.status-fake {
        background: rgba(239, 68, 68, 0.18);
        color: #fca5a5;
    }
    :root[data-bs-theme="dark"] .metric-card,
    :root[data-bs-theme="dark"] .ai-observations {
        background: rgba(255, 255, 255, 0.03);
        border-color: rgba(159, 172, 191, 0.14);
        box-shadow: 0 14px 28px rgba(0, 0, 0, 0.2);
    }
    :root[data-bs-theme="dark"] .metric-label,
    :root[data-bs-theme="dark"] .insight-list li,
    :root[data-bs-theme="dark"] pre.mb-0 {
        color: #9facbf;
    }
    :root[data-bs-theme="dark"] .metric-progress {
        background: rgba(148, 163, 184, 0.16);
    }
    :root[data-bs-theme="dark"] .insight-list li {
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.03), rgba(255, 255, 255, 0.015));
    }
    :root[data-bs-theme="dark"] pre.mb-0 {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(159, 172, 191, 0.14);
        border-radius: 14px;
        padding: 1rem;
        white-space: pre-wrap;
    }
    @media (max-width: 767.98px) {
        .verification-card .card-body {
            padding: 1.1rem;
        }
        .doctor-identity {
            align-items: flex-start;
        }
        .action-toolbar {
            justify-content: flex-start;
            margin-top: 1rem;
        }
    }
</style>
@endsection

@section('content')
<div class="content verification-shell">
    <div>
        <h4 class="verification-title">Doctor Verification Requests</h4>
    </div>
    
    @foreach($doctors as $doctor)
    @php
        $ai = is_array($doctor->ai_result) ? $doctor->ai_result : json_decode($doctor->ai_result ?? '[]', true);
        $riskScore = max(0, min(100, (int) ($ai['risk_score'] ?? 0)));
        $confidenceScore = max(0, min(100, (int) ($ai['confidence'] ?? 0)));
        $status = strtolower($ai['status'] ?? 'n/a');
    @endphp

    <div class="card verification-card">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
                <div class="doctor-identity">
                    <div class="doctor-avatar">
                        <i class="ti ti-user-heart"></i>
                    </div>
                    <div>
                        <h5 class="doctor-name">{{ $doctor->user->name }}</h5>
                        <p class="doctor-email">{{ $doctor->user->email }}</p>
                        <span class="status-chip">
                            <i class="ti ti-clock-hour-4"></i>
                            {{ $doctor->verification_status }}
                        </span>
                    </div>
                </div>
                <div class="action-toolbar">

                    @if(!$doctor->ai_result)
                        <a href="{{ route('ai.doctor.analyze', $doctor->id) }}"
                           class="btn btn-info action-btn"
                           title="Run AI Analysis"
                           data-bs-toggle="tooltip"
                           data-bs-placement="top">
                            <i class="ti ti-sparkles"></i>
                        </a>
                    @else
                        <span class="action-state" title="AI Analysis Completed" data-bs-toggle="tooltip" data-bs-placement="top">
                            <i class="ti ti-circle-check"></i> AI Done
                        </span>
                    @endif
                    <a href="{{ asset('storage/' . $doctor->certificate_path) }}"
                       target="_blank"
                       class="btn btn-primary action-btn"
                       title="View Certificate"
                       data-bs-toggle="tooltip"
                       data-bs-placement="top">
                        <i class="ti ti-eye"></i>
                    </a>
                   <form method="POST" action="{{ route('doctor.approve', ['doctor' => $doctor->id]) }}" class="d-inline">
                        @csrf
                        <button class="btn btn-success action-btn" title="Approve" data-bs-toggle="tooltip" data-bs-placement="top">
                            <i class="ti ti-check"></i>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('doctor.reject', ['doctor' => $doctor->id]) }}" class="d-inline">
                        @csrf
                        <button class="btn btn-danger action-btn" title="Reject" data-bs-toggle="tooltip" data-bs-placement="top">
                            <i class="ti ti-x"></i>
                        </button>
                    </form>
                </div>
            </div>

            @if($doctor->ai_result)
                <div class="ai-panel">
                    <div class="ai-panel-header">
                        <div class="ai-title">
                            <span class="ai-title-badge">
                                <i class="ti ti-brain"></i>
                            </span>
                            AI Insights
                        </div>
                        <span class="ai-status-pill {{ in_array($status, ['valid', 'suspicious', 'fake']) ? 'status-' . $status : '' }}">
                            {{ $ai['status'] ?? 'N/A' }}
                        </span>
                    </div>

                    @if(is_array($ai))
                        <div class="ai-grid">
                            <div class="metric-card">
                                <div class="metric-label">
                                    <span><i class="ti ti-alert-triangle me-1"></i>Risk Score</span>
                                    <span>{{ $riskScore }}%</span>
                                </div>
                                <div class="metric-value">{{ $riskScore }}%</div>
                                <div class="metric-progress risk"><span style="width: {{ $riskScore }}%;"></span></div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-label">
                                    <span><i class="ti ti-badge-filled me-1"></i>Confidence</span>
                                    <span>{{ $confidenceScore }}%</span>
                                </div>
                                <div class="metric-value">{{ $confidenceScore }}%</div>
                                <div class="metric-progress"><span style="width: {{ $confidenceScore }}%;"></span></div>
                            </div>
                        </div>

                        <div class="ai-observations">
                            <div class="ai-observations-title">
                                <i class="ti ti-list-search"></i>
                                Key Observations
                            </div>
                            <ul class="insight-list">
                                @forelse($ai['observations'] ?? [] as $obs)
                                    <li>
                                        <i class="ti ti-circle-check-filled"></i>
                                        <span>{{ $obs }}</span>
                                    </li>
                                @empty
                                    <li>
                                        <i class="ti ti-info-circle"></i>
                                        <span>No additional observations were returned by the AI analysis.</span>
                                    </li>
                                @endforelse
                            </ul>
                        </div>
                    @else
                        <pre class="mb-0">{{ $doctor->ai_result }}</pre>
                    @endif
                </div>
            @endif
        </div>
    </div>
    
    @endforeach
</div>
@endsection
@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
        new bootstrap.Tooltip(element);
    });
</script>

    @if(session('error'))
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: "{{ session('error') }}",
        });
    </script>
    @endif
@endsection
