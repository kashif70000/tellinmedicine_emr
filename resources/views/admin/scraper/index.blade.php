@extends('layouts.layout')

@section('title', 'Directory Scraper - Labs & Pharmacies')

@section('styles')
<style>
    .scraper-hero {
        background: linear-gradient(135deg, #2E37A4 0%, #3538CD 50%, #4338CA 100%);
        color: #ffffff;
        border-radius: 16px;
        padding: 26px 28px;
        margin-bottom: 24px;
        box-shadow: 0 10px 25px -5px rgba(46, 55, 164, 0.28);
    }
    .stat-badge-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 20px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .stat-badge-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
    }
    .type-pill-select .btn-check:checked + .btn {
        background-color: #2E37A4 !important;
        border-color: #2E37A4 !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(46, 55, 164, 0.35);
    }

    .progress-bar-animated-custom {
        background: linear-gradient(45deg, #2E37A4, #3538CD, #6366F1, #2E37A4);
        background-size: 200% 200%;
        animation: gradientShift 2s ease infinite;
    }
    @keyframes gradientShift {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }
    .log-terminal {
        background: #0B0D1E;
        color: #93c5fd;
        font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
        font-size: 12px;
        border-radius: 10px;
        padding: 14px;
        min-height: 180px;
        max-height: 280px;
        overflow-y: auto;
        line-height: 1.6;
        border: 1px solid #1e293b;
    }
    .log-line-time {
        color: #64748b;
    }
    .log-line-success {
        color: #4ade80;
    }
    .log-line-warn {
        color: #fbbf24;
    }
    .log-line-error {
        color: #f87171;
    }
    .suggestion-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        max-height: 250px;
        overflow-y: auto;
        z-index: 1055;
        display: none;
    }
    .suggestion-item {
        padding: 8px 12px;
        cursor: pointer;
        font-size: 13px;
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.15s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .suggestion-item:last-child {
        border-bottom: none;
    }
    .suggestion-item:hover, .suggestion-item.active {
        background-color: #f0f4ff;
        color: #2E37A4;
    }
</style>
@endsection

@section('content')
<!-- Page Header -->
<div class="mb-3">
    <h3 class="mb-1 fw-bold text-dark">Directory Scraper</h3>
</div>

<!-- Hero Card -->
<div class="scraper-hero position-relative overflow-hidden">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <div class="d-flex align-items-center gap-3 mb-2">
                <div class="d-flex align-items-center justify-content-center rounded-3 shadow-sm" style="width: 48px; height: 48px; background: rgba(255, 255, 255, 0.18); border: 1px solid rgba(255, 255, 255, 0.35);">
                    <i class="ti ti-database fs-24 text-white"></i>
                </div>
                <h3 class="fw-bold mb-0 text-white">Healthcare Directory Importer</h3>
            </div>
            <p class="text-white text-opacity-90 fs-14 mb-0" style="max-width: 650px;">
                Search, extract, and automatically synchronize verified local pharmacies and diagnostic laboratories across any city and state worldwide with intelligent deduplication and data normalization.
            </p>
        </div>
        <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
            <span class="badge bg-white text-dark fs-12 px-3 py-2 shadow-sm rounded-pill">
                <i class="ti ti-world text-primary me-1"></i> Worldwide Coverage Active
            </span>
        </div>
    </div>
</div>

<!-- Stat Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="stat-badge-card d-flex align-items-center justify-content-between">
            <div>
                <span class="fs-12 fw-semibold text-muted text-uppercase tracking-wider">Total Stored Pharmacies</span>
                <h3 class="fw-bold text-dark mt-1 mb-0" id="statPharmacyCount">{{ number_format($pharmacyCount) }}</h3>
                <span class="fs-12 text-success"><i class="ti ti-check me-1"></i>Available for prescription fulfillment</span>
            </div>
            <div class="p-3 rounded-circle" style="background-color: #eef2ff; color: #2E37A4;">
                <i class="ti ti-pill fs-24"></i>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-badge-card d-flex align-items-center justify-content-between">
            <div>
                <span class="fs-12 fw-semibold text-muted text-uppercase tracking-wider">Total Stored Laboratories</span>
                <h3 class="fw-bold text-dark mt-1 mb-0" id="statLabCount">{{ number_format($labCount) }}</h3>
                <span class="fs-12 text-primary"><i class="ti ti-flask me-1"></i>Available for lab test orders</span>
            </div>
            <div class="p-3 rounded-circle" style="background-color: #f0f9ff; color: #0284c7;">
                <i class="ti ti-flask fs-24"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Main Scraper Form Card -->
    <div class="col-xl-5 col-lg-6">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white border-bottom py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="fw-bold fs-15 text-dark">
                        <i class="ti ti-adjustments-horizontal text-primary me-2"></i> Scraper Configuration
                    </span>
                    <span class="badge bg-light text-muted border fs-11">Live Directory & Maps</span>
                </div>
            </div>
            <div class="card-body p-4">
                <form id="scraperForm">
                    @csrf

                    {{-- 1. Target Directory Type --}}
                    <div class="mb-4">
                        <label class="form-label fs-13 fw-semibold text-dark mb-2">
                            1. What would you like to scrape? <span class="text-danger">*</span>
                        </label>
                        <div class="type-pill-select d-flex gap-2 flex-wrap">
                            <input type="radio" class="btn-check" name="scrape_type" id="type_pharmacies" value="pharmacies" checked>
                            <label class="btn btn-outline-secondary flex-fill py-2 text-center fs-13" for="type_pharmacies">
                                <i class="ti ti-pill me-1"></i> Pharmacies
                            </label>

                            <input type="radio" class="btn-check" name="scrape_type" id="type_laboratories" value="laboratories">
                            <label class="btn btn-outline-secondary flex-fill py-2 text-center fs-13" for="type_laboratories">
                                <i class="ti ti-flask me-1"></i> Laboratories
                            </label>
                        </div>
                    </div>

                    {{-- 2. Dynamic Worldwide Location Search with Autocomplete --}}
                    <div class="mb-3 position-relative">
                        <label class="form-label fs-13 fw-semibold text-dark mb-1">
                            2. Search Location (Worldwide Autocomplete) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="ti ti-map-pin text-primary"></i></span>
                            <input type="text" class="form-control" id="locationSearchInput" placeholder="Type any city/state worldwide (e.g. Colorado Springs, Loveland, London, Paris)..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="clearLocBtn" title="Clear"><i class="ti ti-x"></i></button>
                        </div>
                        <div class="suggestion-dropdown" id="suggestionDropdown"></div>
                        <span class="fs-11 text-muted">Type at least 2 characters for instant live suggestions anywhere in the world.</span>
                    </div>

                    {{-- 3. City & State (Auto-filled from selection or custom typed) --}}
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label fs-12 fw-semibold text-dark mb-1">
                                City <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control form-control-sm" name="city" id="cityInput" placeholder="e.g. Colorado Springs" required>
                        </div>
                        <div class="col-5">
                            <label class="form-label fs-12 fw-semibold text-dark mb-1">
                                State / Region <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control form-control-sm" name="state" id="stateInput" placeholder="e.g. CO" required>
                        </div>
                    </div>


                    {{-- 4. Limit Selector --}}
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fs-13 fw-semibold text-dark mb-0">
                                3. Fetch Limit
                            </label>
                            <span class="fs-12 fw-medium text-primary" id="limitLabel">20 listings</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <input type="number" min="0" max="500" class="form-control form-control-sm" name="limit" id="limitInput" value="20" placeholder="e.g. 20 (or 0 for Unlimited)">
                            <div class="btn-group btn-group-sm flex-shrink-0" id="limitBtnGroup">
                                <button type="button" class="btn btn-outline-secondary" onclick="setLimit(10, this)">10</button>
                                <button type="button" class="btn btn-outline-secondary active" onclick="setLimit(20, this)">20</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="setLimit(50, this)">50</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="setLimit(100, this)">100</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="setLimit(0, this)"><i class="ti ti-infinity me-1"></i>No Limit</button>
                            </div>
                        </div>
                        <span class="fs-11 text-muted">Select a preset or click <strong>No Limit</strong> to fetch all available listings.</span>
                    </div>

                    {{-- Submit CTA --}}
                    <button type="submit" class="btn btn-primary w-100 py-2 fs-14 fw-semibold rounded-3 shadow-sm d-flex align-items-center justify-content-center gap-2" id="startScrapeBtn" style="background-color: #2E37A4; border-color: #2E37A4;">
                        <i class="ti ti-rocket fs-18"></i>
                        <span id="btnText">Start Scraping & Importing</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Progress & Live Activity Window -->
    <div class="col-xl-7 col-lg-6">
        <div class="card border-0 shadow-sm rounded-3 h-100" id="statusCard">
            <div class="card-header bg-white border-bottom py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="fw-bold fs-15 text-dark">
                        <i class="ti ti-activity text-primary me-2"></i> Scraper Execution & Progress
                    </span>
                    <span class="badge bg-secondary-subtle text-secondary" id="engineStatusBadge">
                        <i class="ti ti-circle-filled fs-9 me-1"></i> Ready
                    </span>
                </div>
            </div>
            <div class="card-body p-4 d-flex flex-column gap-3">
                {{-- Progress Bar Section --}}
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fs-13 fw-semibold text-dark" id="progressStatusText">Waiting for parameters...</span>
                        <span class="fs-13 fw-bold text-primary" id="progressPercentage">0%</span>
                    </div>
                    <div class="progress rounded-pill mb-2" style="height: 12px; background-color: #f1f5f9;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated progress-bar-animated-custom" id="progressBar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>

                    <div class="alert alert-info border-0 rounded-3 py-2 px-3 mb-0 d-flex align-items-center gap-2" id="currentTaskNote" style="display: none !important; background-color: #eef2ff; color: #1e1b4b;">
                        <div class="spinner-border spinner-border-sm text-primary flex-shrink-0" role="status"></div>
                        <span class="fs-12 text-primary-emphasis" id="currentTaskNoteText">Scraper is initializing...</span>
                    </div>
                </div>

                {{-- Live Terminal Log Output --}}
                <div class="mt-0 mb-0 flex-grow-1">
                    <label class="form-label fs-12 fw-semibold text-muted text-uppercase mb-1">Live Activity Stream</label>
                    <div class="log-terminal" id="terminalLog">
                        <div class="log-line"><span class="log-line-time">[{{ now()->format('H:i:s') }}]</span> Scraper portal initialized. Ready to fetch listings worldwide.</div>
                    </div>
                </div>

                {{-- Quick Results Summary Card (Appears after completion) --}}
                <div id="resultsSummarySection" class="mt-1 p-3 rounded-3 border bg-light" style="display: none;">
                    <h6 class="fw-bold text-dark fs-14 mb-2 d-flex align-items-center gap-2">
                        <i class="ti ti-circle-check text-success"></i> Batch Execution Results
                    </h6>
                    <div class="row g-2 text-center mb-2">
                        <div class="col">
                            <div class="p-2 bg-white rounded border">
                                <span class="fs-11 text-muted d-block">Found</span>
                                <strong class="fs-14 text-dark" id="resFound">0</strong>
                            </div>
                        </div>
                        <div class="col">
                            <div class="p-2 bg-white rounded border">
                                <span class="fs-11 text-muted d-block">Inserted</span>
                                <strong class="fs-14 text-success" id="resInserted">0</strong>
                            </div>
                        </div>
                        <div class="col">
                            <div class="p-2 bg-white rounded border">
                                <span class="fs-11 text-muted d-block">Updated</span>
                                <strong class="fs-14 text-primary" id="resUpdated">0</strong>
                            </div>
                        </div>
                        <div class="col">
                            <div class="p-2 bg-white rounded border">
                                <span class="fs-11 text-muted d-block">Skipped</span>
                                <strong class="fs-14 text-secondary" id="resSkipped">0</strong>
                            </div>
                        </div>
                        <div class="col">
                            <div class="p-2 bg-white rounded border">
                                <span class="fs-11 text-muted d-block">Failed</span>
                                <strong class="fs-14 text-danger" id="resFailed">0</strong>
                            </div>
                        </div>
                    </div>
                    <div class="fs-12 text-muted" id="resStopReason"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scraped Records Results Preview Table -->
<div class="card border-0 shadow-sm rounded-3 mt-4" id="resultsTableCard" style="display: none;">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold fs-15 text-dark">
            <i class="ti ti-list-check text-primary me-2"></i> Processed Listings in this Batch
        </span>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-12 px-2" id="batchBadgeCount">0 items</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
            <table class="table table-hover align-middle mb-0 fs-13">
                <thead class="table-light sticky-top">
                    <tr>
                        <th class="ps-3" style="width: 120px;">Type</th>
                        <th>Business Name</th>
                        <th>Address / Location</th>
                        <th>Phone</th>
                        <th class="text-center" style="width: 120px;">Outcome</th>
                    </tr>
                </thead>
                <tbody id="resultsTableBody">
                    <!-- Populated dynamically -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Existing Directory Overview Section -->
<div class="card border-0 shadow-sm rounded-3 mt-4">
    <div class="card-header bg-white border-bottom py-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="fw-bold fs-15 text-dark">
                <i class="ti ti-database text-primary me-2"></i> Database Directory Records
            </span>
            <div class="d-flex align-items-center gap-2">
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary active" id="tabBtnPharmacies" onclick="switchDirectoryTab('pharmacies')">
                        <i class="ti ti-pill me-1"></i> Pharmacies
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="tabBtnLaboratories" onclick="switchDirectoryTab('laboratories')">
                        <i class="ti ti-flask me-1"></i> Laboratories
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body p-3">
        {{-- Filter Bar --}}
        <div class="row g-2 mb-3">
            <div class="col-md-8 col-sm-8">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="ti ti-search text-muted"></i></span>
                    <input type="text" class="form-control" id="dirSearchInput" placeholder="Search by name, city, state, or address...">
                </div>
            </div>
            <div class="col-md-4 text-end">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadDirectoryData(1)">
                    <i class="ti ti-refresh me-1"></i> Refresh Table
                </button>
            </div>
        </div>

        {{-- Table --}}
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-13">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Name</th>
                        <th>Street Address</th>
                        <th>City, State</th>
                        <th>Phone</th>
                        <th>Website</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody id="directoryTableBody">
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm text-primary me-1" role="status"></div> Loading records...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Pagination controls --}}
        <div class="d-flex justify-content-between align-items-center mt-3 px-1 flex-wrap gap-2">
            <span class="fs-12 text-muted" id="dirPaginationInfo">Showing 0 of 0</span>
            <div class="btn-group btn-group-sm" id="dirPaginationBtns">
                <!-- Dynamic -->
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const routes = {
        scrape: "{{ route('admin.directory-scraper.scrape') }}",
        data: "{{ route('admin.directory-scraper.data') }}",
        suggest: "{{ route('admin.directory-scraper.location-suggest') }}",
    };

    let activeDirectoryTab = 'pharmacies';
    let currentDirPage = 1;
    let progressTimer = null;
    let suggestTimer = null;

    // Worldwide Location Suggestion Autocomplete
    const locInput = document.getElementById('locationSearchInput');
    const suggestDropdown = document.getElementById('suggestionDropdown');
    const cityInput = document.getElementById('cityInput');
    const stateInput = document.getElementById('stateInput');
    const clearLocBtn = document.getElementById('clearLocBtn');

    locInput.addEventListener('input', function() {
        clearTimeout(suggestTimer);
        const val = this.value.trim();

        if (val.length < 2) {
            suggestDropdown.style.display = 'none';
            return;
        }

        suggestTimer = setTimeout(() => {
            fetch(`${routes.suggest}?q=${encodeURIComponent(val)}`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    const results = data.results || [];
                    if (results.length === 0) {
                        suggestDropdown.innerHTML = `<div class="p-2 text-muted fs-12 text-center">No location suggestions found for "${escapeHtml(val)}". You can type directly below.</div>`;
                        suggestDropdown.style.display = 'block';
                        return;
                    }

                    let html = '';
                    results.forEach(item => {
                        html += `
                            <div class="suggestion-item" data-city="${escapeHtml(item.city)}" data-state="${escapeHtml(item.state || '')}">
                                <i class="ti ti-map-pin text-primary fs-14"></i>
                                <span>${escapeHtml(item.text)}</span>
                            </div>
                        `;
                    });

                    suggestDropdown.innerHTML = html;
                    suggestDropdown.style.display = 'block';
                })
                .catch(err => {
                    console.error('Location suggest error:', err);
                });
        }, 250);
    });

    suggestDropdown.addEventListener('click', function(e) {
        const item = e.target.closest('.suggestion-item');
        if (!item) return;

        const city = item.dataset.city;
        const state = item.dataset.state;

        cityInput.value = city;
        stateInput.value = state;
        locInput.value = `${city}, ${state}`;
        suggestDropdown.style.display = 'none';
    });

    document.addEventListener('click', function(e) {
        if (!locInput.contains(e.target) && !suggestDropdown.contains(e.target)) {
            suggestDropdown.style.display = 'none';
        }
    });

    clearLocBtn.addEventListener('click', function() {
        locInput.value = '';
        cityInput.value = '';
        stateInput.value = '';
        suggestDropdown.style.display = 'none';
        locInput.focus();
    });


    window.setLimit = function(val, btnElement) {
        const input = document.getElementById('limitInput');
        const label = document.getElementById('limitLabel');
        input.value = (val > 0) ? val : 0;
        
        if (val > 0) {
            label.innerText = val + ' listings';
            label.className = 'fs-12 fw-medium text-primary';
        } else {
            label.innerText = 'No Limit (Fetch All)';
            label.className = 'fs-12 fw-bold text-success';
        }

        if (btnElement) {
            document.querySelectorAll('#limitBtnGroup button').forEach(b => b.classList.remove('active'));
            btnElement.classList.add('active');
        }
    };

    document.getElementById('limitInput').addEventListener('input', function() {
        const val = parseInt(this.value, 10);
        const label = document.getElementById('limitLabel');
        document.querySelectorAll('#limitBtnGroup button').forEach(b => b.classList.remove('active'));
        
        if (isNaN(val) || val <= 0) {
            label.innerText = 'No Limit (Fetch All)';
            label.className = 'fs-12 fw-bold text-success';
        } else {
            label.innerText = val + ' listings';
            label.className = 'fs-12 fw-medium text-primary';
        }
    });

    function appendLog(text, type = 'info') {
        const terminal = document.getElementById('terminalLog');
        const now = new Date();
        const timeStr = now.toTimeString().split(' ')[0];
        
        let colorClass = '';
        if (type === 'success') colorClass = 'log-line-success';
        else if (type === 'warn') colorClass = 'log-line-warn';
        else if (type === 'error') colorClass = 'log-line-error';

        const div = document.createElement('div');
        div.className = `log-line ${colorClass}`;
        div.innerHTML = `<span class="log-line-time">[${timeStr}]</span> ${escapeHtml(text)}`;
        terminal.appendChild(div);
        terminal.scrollTop = terminal.scrollHeight;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Form Submission for Scraping
    document.getElementById('scraperForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const form = this;
        const type = form.querySelector('input[name="scrape_type"]:checked').value;
        const state = stateInput.value.trim();
        const city = cityInput.value.trim();
        const limit = document.getElementById('limitInput').value;

        if (!city) {
            alert('Please enter or select a city.');
            cityInput.focus();
            return;
        }
        if (!state) {
            alert('Please enter or select a state/region.');
            stateInput.focus();
            return;
        }

        // Lock UI
        const btn = document.getElementById('startScrapeBtn');
        btn.disabled = true;
        document.getElementById('btnText').innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status"></span> Scraping in progress...`;

        // Update Progress UI
        const progressBar = document.getElementById('progressBar');
        const progressPercentage = document.getElementById('progressPercentage');
        const progressStatusText = document.getElementById('progressStatusText');
        const engineStatusBadge = document.getElementById('engineStatusBadge');
        const currentTaskNote = document.getElementById('currentTaskNote');
        const currentTaskNoteText = document.getElementById('currentTaskNoteText');

        engineStatusBadge.className = 'badge bg-warning text-dark';
        engineStatusBadge.innerHTML = `<i class="ti ti-loader fs-9 me-1"></i> Scraping Active`;
        currentTaskNote.style.setProperty('display', 'flex', 'important');
        currentTaskNoteText.innerText = `Connecting to directory engine for ${city}, ${state}...`;

        document.getElementById('resultsSummarySection').style.display = 'none';
        document.getElementById('resultsTableCard').style.display = 'none';

        appendLog(`Initiated scraper request for ${type.toUpperCase()} in ${city}, ${state} (Limit: ${limit || 'Unlimited'})`, 'info');

        // Animate initial progress milestones
        let currentProgress = 5;
        progressBar.style.width = '5%';
        progressPercentage.innerText = '5%';
        progressStatusText.innerText = 'Initializing directory session...';

        clearInterval(progressTimer);
        progressTimer = setInterval(() => {
            if (currentProgress < 85) {
                currentProgress += Math.floor(Math.random() * 8) + 2;
                if (currentProgress > 85) currentProgress = 85;
                progressBar.style.width = currentProgress + '%';
                progressPercentage.innerText = currentProgress + '%';

                if (currentProgress > 25 && currentProgress <= 55) {
                    progressStatusText.innerText = `Scanning listings for ${city}, ${state}...`;
                    currentTaskNoteText.innerText = `Extracting business records, coordinates, and contact details...`;
                } else if (currentProgress > 55) {
                    progressStatusText.innerText = `Normalizing data & evaluating deduplication rules...`;
                    currentTaskNoteText.innerText = `Verifying medical categories & matching existing database IDs...`;
                }
            }
        }, 1200);

        // Send AJAX request
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
            || form.querySelector('input[name="_token"]')?.value;

        fetch(routes.scrape, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                type: type,
                city: city,
                state: state,
                limit: limit ? parseInt(limit, 10) : null,
                dry_run: false,
            })
        })
        .then(async response => {
            clearInterval(progressTimer);
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Scraper failed to complete.');
            }
            return data;
        })
        .then(data => {
            progressBar.style.width = '100%';
            progressPercentage.innerText = '100%';
            progressStatusText.innerText = 'Scraping and import completed!';
            engineStatusBadge.className = 'badge bg-success';
            engineStatusBadge.innerHTML = `<i class="ti ti-check fs-9 me-1"></i> Completed`;
            currentTaskNote.style.setProperty('display', 'none', 'important');

            appendLog(`Finished! Found: ${data.summary.found}, Inserted: ${data.summary.inserted}, Updated: ${data.summary.updated}, Skipped: ${data.summary.skipped}, Failed: ${data.summary.failed}`, 'success');

            (data.notes || []).forEach(note => {
                appendLog(`Note: ${note}`, 'warn');
            });

            // Update Stats Summary
            document.getElementById('resFound').innerText = data.summary.found;
            document.getElementById('resInserted').innerText = data.summary.inserted;
            document.getElementById('resUpdated').innerText = data.summary.updated;
            document.getElementById('resSkipped').innerText = data.summary.skipped;
            document.getElementById('resFailed').innerText = data.summary.failed;

            let stopReasonHtml = '';
            if (data.notes && data.notes.length) {
                stopReasonHtml = data.notes.join(' • ');
            } else {
                stopReasonHtml = 'Batch processed successfully with active deduplication.';
            }
            document.getElementById('resStopReason').innerHTML = `<i class="ti ti-info-circle me-1"></i> ${escapeHtml(stopReasonHtml)}`;
            document.getElementById('resultsSummarySection').style.display = 'block';

            // Render table of scraped items
            renderScrapedBatchTable(data.items || []);

            // Update top counters if available
            if (data.current_totals) {
                document.getElementById('statPharmacyCount').innerText = Number(data.current_totals.pharmacies).toLocaleString();
                document.getElementById('statLabCount').innerText = Number(data.current_totals.laboratories).toLocaleString();
            }

            // Refresh Directory Database Table
            loadDirectoryData(1);
        })
        .catch(err => {
            clearInterval(progressTimer);
            progressBar.style.width = '100%';
            progressBar.className = 'progress-bar bg-danger';
            progressPercentage.innerText = 'Error';
            progressStatusText.innerText = 'Execution encountered an issue.';
            engineStatusBadge.className = 'badge bg-danger';
            engineStatusBadge.innerHTML = `<i class="ti ti-alert-triangle fs-9 me-1"></i> Failed`;
            currentTaskNote.style.setProperty('display', 'none', 'important');

            appendLog(`Error: ${err.message}`, 'error');
            alert(`Scraping Error: ${err.message}`);
        })
        .finally(() => {
            btn.disabled = false;
            document.getElementById('btnText').innerHTML = `Start Scraping & Importing`;
        });
    });

    function renderScrapedBatchTable(items) {
        const tableCard = document.getElementById('resultsTableCard');
        const tbody = document.getElementById('resultsTableBody');
        const countBadge = document.getElementById('batchBadgeCount');

        if (!items || items.length === 0) {
            tableCard.style.display = 'none';
            return;
        }

        countBadge.innerText = `${items.length} ${items.length === 1 ? 'item' : 'items'}`;
        let html = '';

        items.forEach(item => {
            let actionBadge = '';
            if (item.action === 'inserted') {
                actionBadge = `<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="ti ti-plus me-1"></i> Inserted</span>`;
            } else if (item.action === 'updated') {
                actionBadge = `<span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="ti ti-edit me-1"></i> Updated</span>`;
            } else if (item.action === 'skipped') {
                actionBadge = `<span class="badge bg-secondary-subtle text-secondary" title="${escapeHtml(item.reason || '')}"><i class="ti ti-minus me-1"></i> Skipped</span>`;
            } else {
                actionBadge = `<span class="badge bg-danger-subtle text-danger"><i class="ti ti-x me-1"></i> ${escapeHtml(item.action)}</span>`;
            }

            const typeBadge = item.type === 'Laboratory'
                ? `<span class="badge bg-info-subtle text-info border border-info-subtle"><i class="ti ti-flask me-1"></i> Lab</span>`
                : `<span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="ti ti-pill me-1"></i> Pharmacy</span>`;

            html += `
                <tr>
                    <td class="ps-3">${typeBadge}</td>
                    <td class="fw-semibold text-dark">${escapeHtml(item.name)}</td>
                    <td class="text-muted">${escapeHtml(item.address || 'N/A')}, ${escapeHtml(item.city)}, ${escapeHtml(item.state)}</td>
                    <td class="text-muted">${escapeHtml(item.phone || 'N/A')}</td>
                    <td class="text-center">${actionBadge}</td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        tableCard.style.display = 'block';
    }

    // Directory Browser Tab Switching & Pagination
    function switchDirectoryTab(tab) {
        activeDirectoryTab = tab;
        document.getElementById('tabBtnPharmacies').classList.toggle('active', tab === 'pharmacies');
        document.getElementById('tabBtnLaboratories').classList.toggle('active', tab === 'laboratories');
        loadDirectoryData(1);
    }

    function loadDirectoryData(page = 1) {
        currentDirPage = page;
        const q = document.getElementById('dirSearchInput').value.trim();
        const tbody = document.getElementById('directoryTableBody');

        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm text-primary me-1"></div> Loading...</td></tr>`;

        const url = `${routes.data}?type=${activeDirectoryTab}&q=${encodeURIComponent(q)}&page=${page}`;

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                if (!data.data || data.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted"><i class="ti ti-folder-off fs-20 d-block mb-1"></i> No ${activeDirectoryTab} records found matching filters.</td></tr>`;
                    document.getElementById('dirPaginationInfo').innerText = 'Showing 0 records';
                    document.getElementById('dirPaginationBtns').innerHTML = '';
                    return;
                }

                let html = '';
                data.data.forEach(row => {
                    const websiteLink = row.website 
                        ? `<a href="${escapeHtml(row.website)}" target="_blank" class="text-primary fs-12 text-truncate d-inline-block" style="max-width: 140px;"><i class="ti ti-external-link me-1"></i> Visit</a>`
                        : `<span class="text-muted fs-12">—</span>`;

                    html += `
                        <tr>
                            <td class="ps-3 fw-semibold text-dark">${escapeHtml(row.name)}</td>
                            <td class="text-muted">${escapeHtml(row.street_address || '—')}</td>
                            <td><span class="badge bg-light text-dark border">${escapeHtml(row.city || '')}, ${escapeHtml(row.state || '')}</span></td>
                            <td class="text-muted">${escapeHtml(row.phone || '—')}</td>
                            <td>${websiteLink}</td>
                            <td><span class="badge bg-secondary-subtle text-secondary fs-11">${escapeHtml(row.source || 'live_directory')}</span></td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;

                document.getElementById('dirPaginationInfo').innerText = `Showing ${data.from || 0} to ${data.to || 0} of ${data.total || 0} records`;

                // Render Pagination Buttons
                let pagHtml = '';
                if (data.prev_page_url) {
                    pagHtml += `<button type="button" class="btn btn-outline-secondary" onclick="loadDirectoryData(${data.current_page - 1})">&laquo; Prev</button>`;
                }
                pagHtml += `<button type="button" class="btn btn-secondary disabled">Page ${data.current_page} of ${data.last_page}</button>`;
                if (data.next_page_url) {
                    pagHtml += `<button type="button" class="btn btn-outline-secondary" onclick="loadDirectoryData(${data.current_page + 1})">Next &raquo;</button>`;
                }
                document.getElementById('dirPaginationBtns').innerHTML = pagHtml;
            })
            .catch(err => {
                console.error('Failed to load directory data:', err);
                tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-danger">Failed to load data.</td></tr>`;
            });
    }

    // Debounced search on directory table
    let searchDebounce = null;
    document.getElementById('dirSearchInput').addEventListener('input', function() {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => {
            loadDirectoryData(1);
        }, 300);
    });

    // Initial load
    document.addEventListener('DOMContentLoaded', function() {
        loadDirectoryData(1);
    });
</script>
@endsection
