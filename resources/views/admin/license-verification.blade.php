@extends('layouts.layout')

@section('title', 'License Verification')

@section('content')
<div class="container-fluid">
    <div class="page-header mb-4">
        <div>
            <h3 class="page-title">License Verification</h3>
            <p class="text-muted mb-0">Independently verify a physician license using the existing state licensing sources.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="ti ti-shield-check me-2 text-success"></i>License lookup</h5>
        </div>
        <div class="card-body">
            <form id="licenseVerificationForm" novalidate>
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="license-country" class="form-label">Country <span class="text-danger">*</span></label>
                        <select id="license-country" name="country" class="form-select" required>
                            <option value="">Select a country</option>
                            <option value="US">United States</option>
                        </select>
                        <div class="invalid-feedback" data-error-for="country"></div>
                    </div>
                    <div class="col-md-4 d-none" id="license-state-group">
                        <label for="license-state" class="form-label">State <span class="text-danger">*</span></label>
                        <select id="license-state" name="state" class="form-select">
                            <option value="">Select a state</option>
                            @foreach($states as $stateCode => $stateName)
                                <option value="{{ $stateCode }}">{{ $stateName }} ({{ $stateCode }})</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" data-error-for="state"></div>
                    </div>
                    <div class="col-md-4">
                        <label for="license-number" class="form-label">License Number <span class="text-danger">*</span></label>
                        <input id="license-number" name="license_number" type="text" class="form-control" maxlength="255" autocomplete="off" required>
                        <div class="invalid-feedback" data-error-for="license_number"></div>
                    </div>
                    <div class="col-12 d-none" id="massachusetts-license-fields">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="license-type" class="form-label">License Type <span class="text-danger">*</span></label>
                                <select id="license-type" name="license_type" class="form-select">
                                    <option value="">Select physician license type</option>
                                    <option value="Full Physician">Full Physician</option>
                                    <option value="Limited Physician">Limited Physician</option>
                                    <option value="Temporary Physician">Temporary Physician</option>
                                </select>
                                <div class="invalid-feedback" data-error-for="license_type"></div>
                            </div>
                            <div class="col-md-4">
                                <label for="license-issue-date" class="form-label">Latest License Issue Date <span class="text-danger">*</span></label>
                                <input id="license-issue-date" name="license_issue_date" type="date" class="form-control">
                                <div class="invalid-feedback" data-error-for="license_issue_date"></div>
                            </div>
                            <div class="col-md-4">
                                <label for="license-expiration-date" class="form-label">License Expiration Date <span class="text-danger">*</span></label>
                                <input id="license-expiration-date" name="license_expiration_date" type="date" class="form-control">
                                <div class="invalid-feedback" data-error-for="license_expiration_date"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 d-flex align-items-center gap-3 mt-4">
                        <button id="verify-license-button" type="submit" class="btn btn-success">
                            <span id="verify-license-spinner" class="spinner-border spinner-border-sm me-2 d-none" aria-hidden="true"></span>
                            <i class="ti ti-shield-check me-1" id="verify-license-icon"></i>
                            Verify License
                        </button>
                        <span id="license-verification-loading" class="text-muted d-none" role="status">Checking the official licensing source...</span>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="license-verification-result" class="mt-4" aria-live="polite"></div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('licenseVerificationForm');
    const country = document.getElementById('license-country');
    const state = document.getElementById('license-state');
    const stateGroup = document.getElementById('license-state-group');
    const massachusettsFields = document.getElementById('massachusetts-license-fields');
    const button = document.getElementById('verify-license-button');
    const spinner = document.getElementById('verify-license-spinner');
    const icon = document.getElementById('verify-license-icon');
    const loading = document.getElementById('license-verification-loading');
    const result = document.getElementById('license-verification-result');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    function clearErrors() {
        form.querySelectorAll('.is-invalid').forEach((input) => input.classList.remove('is-invalid'));
        form.querySelectorAll('[data-error-for]').forEach((message) => {
            message.textContent = '';
        });
    }

    function clearResult() {
        result.replaceChildren();
    }

    function updateFields() {
        const isUnitedStates = country.value === 'US';
        const isMassachusetts = isUnitedStates && state.value === 'MA';

        stateGroup.classList.toggle('d-none', !isUnitedStates);
        state.required = isUnitedStates;
        if (!isUnitedStates) {
            state.value = '';
        }

        massachusettsFields.classList.toggle('d-none', !isMassachusetts);
        massachusettsFields.querySelectorAll('[name]').forEach((input) => {
            input.required = isMassachusetts;
            if (!isMassachusetts) {
                input.value = '';
            }
        });
        clearErrors();
        clearResult();
    }

    country.addEventListener('change', updateFields);
    state.addEventListener('change', updateFields);
    form.querySelectorAll('input, select').forEach((input) => {
        input.addEventListener('input', clearResult);
    });

    function setFieldErrors(errors) {
        Object.entries(errors || {}).forEach(([name, messages]) => {
            const input = form.elements.namedItem(name);
            const error = form.querySelector(`[data-error-for="${name}"]`);
            if (input) {
                input.classList.add('is-invalid');
            }
            if (error) {
                error.textContent = Array.isArray(messages) ? messages[0] : String(messages);
            }
        });
    }

    function formatLabel(value) {
        return String(value)
            .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
            .replace(/[_-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .replace(/\b\w/g, (letter) => letter.toUpperCase());
    }

    function isInternalField(key) {
        const normalized = String(key).toLowerCase().replace(/[^a-z0-9]/g, '');
        return [
            'found',
            'source',
            'error',
            'multiple',
            'rawstatus',
            'lastscraped',
            'apirecord'
        ].includes(normalized) || normalized.endsWith('metaid');
    }

    function appendDataRows(container, data, prefix = '', depth = 0) {
        if (!data || typeof data !== 'object' || depth > 5) {
            return;
        }

        Object.entries(data).forEach(([key, value]) => {
            if (value === null || value === undefined || value === '' || isInternalField(key)) {
                return;
            }
            if (/^(api[_-]?key|authorization|cookie|password|token)$/i.test(key)) {
                return;
            }

            const label = prefix ? `${prefix} / ${formatLabel(key)}` : formatLabel(key);
            if (typeof value === 'object') {
                if (Array.isArray(value) && value.every((item) => item === null || ['string', 'number', 'boolean'].includes(typeof item))) {
                    if (value.length) {
                        addDataRow(container, label, value.filter((item) => item !== null).join(', '));
                    }
                } else {
                    appendDataRows(container, value, label, depth + 1);
                }
                return;
            }

            addDataRow(container, label, typeof value === 'boolean' ? (value ? 'Yes' : 'No') : String(value));
        });
    }

    function addDataRow(container, label, value) {
        const column = document.createElement('div');
        column.className = 'col-md-6';
        const item = document.createElement('div');
        item.className = 'license-result-item';
        const heading = document.createElement('div');
        heading.className = 'small text-muted mb-1';
        heading.textContent = label;
        const content = document.createElement('div');
        content.className = 'fw-medium text-break';
        content.textContent = value;
        item.append(heading, content);
        column.append(item);
        container.append(column);
    }

    function displayResult(data) {
        const status = String(data.status || 'unable_to_verify').toLowerCase();
        const statusStyles = {
            verified: ['success', 'Verified'],
            failed: ['danger', 'Not Verified'],
            expired: ['danger', 'Expired'],
            manual_review: ['warning', 'Manual Review Required'],
            unable_to_verify: ['warning', 'Unable to Verify'],
            unavailable: ['warning', 'Source Unavailable']
        };
        const [style, label] = statusStyles[status] || ['secondary', formatLabel(status)];

        const card = document.createElement('div');
        card.className = 'card border-0 shadow-sm';
        const header = document.createElement('div');
        header.className = 'card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2';
        const title = document.createElement('h5');
        title.className = 'mb-0';
        title.textContent = 'Verification Result';
        const badge = document.createElement('span');
        badge.className = `badge bg-${style}`;
        badge.textContent = label;
        header.append(title, badge);

        const body = document.createElement('div');
        body.className = 'card-body';
        const message = document.createElement('p');
        message.className = `alert alert-${style} mb-4`;
        message.textContent = data.message || 'The verification source returned no message.';
        body.append(message);

        if (data.source) {
            const source = document.createElement('p');
            source.className = 'small text-muted mb-3';
            source.textContent = `Verification source: ${data.source}`;
            body.append(source);
        }

        const details = document.createElement('div');
        details.className = 'row g-3';
        appendDataRows(details, data.official_data);
        if (details.childElementCount) {
            body.append(details);
        } else {
            const empty = document.createElement('p');
            empty.className = 'text-muted mb-0';
            empty.textContent = 'No additional license details were returned.';
            body.append(empty);
        }

        card.append(header, body);
        result.replaceChildren(card);
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearErrors();
        clearResult();

        if (!form.reportValidity()) {
            return;
        }

        button.disabled = true;
        spinner.classList.remove('d-none');
        icon.classList.add('d-none');
        loading.classList.remove('d-none');

        try {
            const response = await fetch(@json(route('admin.license-verification.verify')), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify(Object.fromEntries(new FormData(form).entries()))
            });
            const data = await response.json();

            if (response.status === 422 && data.errors) {
                setFieldErrors(data.errors);
                return;
            }
            if (!response.ok) {
                throw new Error(data.message || 'The verification request could not be completed.');
            }

            displayResult(data);
        } catch (error) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger';
            alert.setAttribute('role', 'alert');
            alert.textContent = error.message || 'Unable to complete verification. Please try again.';
            result.replaceChildren(alert);
        } finally {
            button.disabled = false;
            spinner.classList.add('d-none');
            icon.classList.remove('d-none');
            loading.classList.add('d-none');
        }
    });

    updateFields();
})();
</script>
@endpush

@push('styles')
<style>
    .license-result-item {
        height: 100%;
        padding: .875rem 1rem;
        border: 1px solid var(--bs-border-color, #e9ecef);
        border-radius: .5rem;
        background: #fff;
    }
</style>
@endpush
