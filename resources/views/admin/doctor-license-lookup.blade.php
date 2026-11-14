@extends('layouts.layout')

@section('title', 'DoctorLicenseLookup Verification')

@section('content')
<div class="container-fluid">
    <div class="page-header mb-4">
        <div>
            <h3 class="page-title">DoctorLicenseLookup Verification</h3>
            <p class="text-muted mb-0">Validate a physician license against the DoctorLicenseLookup state-based verification flow.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="ti ti-shield-check me-2 text-primary"></i>Lookup physician license</h5>
        </div>
        <div class="card-body">
            <form id="doctor-license-lookup-form" novalidate>
                @csrf
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="lookup-country" class="form-label">Country <span class="text-danger">*</span></label>
                        <select id="lookup-country" name="country" class="form-select" required>
                            <option value="">Select country</option>
                            <option value="US" selected>United States</option>
                        </select>
                        <div class="invalid-feedback" data-error-for="country"></div>
                    </div>
                    <div class="col-md-3" id="lookup-state-group">
                        <label for="lookup-state" class="form-label">State <span class="text-danger">*</span></label>
                        <select id="lookup-state" name="state" class="form-select" required>
                            <option value="">Select a state</option>
                            @foreach($states as $stateCode => $state)
                                <option value="{{ $stateCode }}">{{ $state['name'] }} ({{ $stateCode }})</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" data-error-for="state"></div>
                    </div>
                    <div class="col-md-3">
                        <label for="lookup-license-number" class="form-label">Physician License Number <span class="text-danger">*</span></label>
                        <input id="lookup-license-number" name="license_number" type="text" class="form-control" placeholder="Enter license number" maxlength="255" required>
                        <div class="invalid-feedback" data-error-for="license_number"></div>
                    </div>
                    <div class="col-md-3 d-grid">
                        <button id="lookup-verify-button" type="submit" class="btn btn-primary">
                            <span id="lookup-spinner" class="spinner-border spinner-border-sm me-2 d-none" aria-hidden="true"></span>
                            <i class="ti ti-search me-1"></i>
                            Verify
                        </button>
                    </div>
                </div>
            </form>

            <div class="mt-4">
                <div id="lookup-loading" class="text-muted d-none" role="status">Querying DoctorLicenseLookup...</div>
                <div id="lookup-result" class="mt-3" aria-live="polite"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('doctor-license-lookup-form');
    const country = document.getElementById('lookup-country');
    const state = document.getElementById('lookup-state');
    const stateGroup = document.getElementById('lookup-state-group');
    const button = document.getElementById('lookup-verify-button');
    const spinner = document.getElementById('lookup-spinner');
    const loading = document.getElementById('lookup-loading');
    const result = document.getElementById('lookup-result');
    const route = @json(route('admin.doctor-license-lookup.verify'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function clearResult() {
        result.replaceChildren();
    }

    function setFieldErrors(errors) {
        Object.entries(errors).forEach(([field, messages]) => {
            const input = form.querySelector('[name="' + field + '"]');
            const errorNode = form.querySelector('[data-error-for="' + field + '"]');
            if (input) {
                input.classList.add('is-invalid');
            }
            if (errorNode) {
                errorNode.textContent = Array.isArray(messages) ? messages[0] : messages;
            }
        });
    }

    function updateStateField() {
        const isUnitedStates = country.value === 'US';
        stateGroup.classList.toggle('d-none', !isUnitedStates);
        state.required = isUnitedStates;
        state.disabled = !isUnitedStates;
        if (!isUnitedStates) {
            state.value = '';
        }
        clearResult();
    }

    country.addEventListener('change', updateStateField);
    form.querySelectorAll('input, select').forEach((input) => {
        input.addEventListener('input', clearResult);
        input.addEventListener('change', clearResult);
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearResult();
        const formData = new FormData(form);
        const countryValue = formData.get('country');
        const stateValue = formData.get('state');
        const licenseValue = formData.get('license_number');

        if (!countryValue || !licenseValue || (countryValue === 'US' && !stateValue)) {
            const message = countryValue === 'US' && !stateValue
                ? 'Please select a valid US state before verifying.'
                : 'Please enter a country and physician license number.';
            result.innerHTML = '<div class="alert alert-warning">' + message + '</div>';
            return;
        }

        button.disabled = true;
        spinner.classList.remove('d-none');
        loading.classList.remove('d-none');

        try {
            const response = await fetch(route, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: formData,
                credentials: 'same-origin',
            });

            const payload = await response.json();
            if (!response.ok) {
                throw new Error(payload.message || 'The verification request failed.');
            }

            const isVerified = payload.status === 'verified';

            const alert = document.createElement('div');
            alert.className = 'alert ' + (isVerified ? 'alert-success' : 'alert-danger');

            const heading = document.createElement('h6');
            heading.className = 'alert-heading mb-2';
            heading.textContent = isVerified
                ? 'Verification succeeded'
                : payload.status === 'not_found'
                    ? 'Record not found'
                    : 'License not verified';

            const message = document.createElement('p');
            message.className = 'mb-0';
            message.textContent = isVerified
                ? (payload.message || 'The license was verified.')
                : (payload.status === 'not_found'
                    ? 'License record not found. Please check the license number and selected state.'
                    : (payload.message || 'License could not be verified. Please check the license number and selected state.'));
            alert.append(heading, message);
            result.appendChild(alert);

            if (isVerified) {
                renderRecordCards(payload.official_data, String(licenseValue).trim());
            }
        } catch (error) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger';
            alert.textContent = error.message || 'An unexpected verification error occurred.';
            result.replaceChildren(alert);
        } finally {
            button.disabled = false;
            spinner.classList.add('d-none');
            loading.classList.add('d-none');
        }
    });

    function renderRecordCards(data, verifiedLicenseNumber) {
        const records = Array.isArray(data)
            ? data.filter((record) => record && typeof record === 'object' && !Array.isArray(record))
            : data && typeof data === 'object'
                ? [data]
                : [];

        if (records.length === 0 && verifiedLicenseNumber) {
            records.push({});
        }

        if (records.length === 0) {
            return;
        }

        const grid = document.createElement('div');
        grid.className = 'row g-3 mt-1';

        records.forEach((record) => {
            const column = document.createElement('div');
            column.className = records.length === 1 ? 'col-12' : 'col-12 col-xl-6';

            const card = document.createElement('div');
            card.className = 'card h-100 border';

            const header = document.createElement('div');
            header.className = 'card-header d-flex justify-content-between align-items-center gap-2';

            const name = record.full_name || record.name || record.practitioner_name
                || record.doctor_name || record.physician_name || 'Physician record';
            const title = document.createElement('h6');
            title.className = 'mb-0';
            title.textContent = formatValue(name);
            header.appendChild(title);

            const status = record.lic_status || record.license_status || record.status;
            if (status !== undefined && status !== null && status !== '') {
                const badge = document.createElement('span');
                badge.className = 'badge bg-success';
                badge.textContent = formatValue(status);
                header.appendChild(badge);
            }

            const body = document.createElement('div');
            body.className = 'card-body';
            const details = document.createElement('div');
            details.className = 'row g-3';

            if (verifiedLicenseNumber) {
                appendDetail(details, 'License Number', verifiedLicenseNumber);
            }

            Object.entries(record).forEach(([key, value]) => {
                if (value === null || value === ''
                    || ['full_name', 'name', 'practitioner_name', 'doctor_name', 'physician_name'].includes(key)
                    || (verifiedLicenseNumber && isLicenseNumberField(key))) {
                    return;
                }

                appendDetail(details, formatLabel(key), formatValue(value));
            });

            body.appendChild(details);
            card.append(header, body);
            column.appendChild(card);
            grid.appendChild(column);
        });

        result.appendChild(grid);
    }

    function appendDetail(container, labelText, valueText) {
        const detail = document.createElement('div');
        detail.className = 'col-sm-6';

        const label = document.createElement('span');
        label.className = 'd-block text-muted small mb-1';
        label.textContent = labelText;

        const content = document.createElement('span');
        content.className = 'fw-medium';
        content.textContent = valueText;

        detail.append(label, content);
        container.appendChild(detail);
    }

    function isLicenseNumberField(key) {
        return [
            'license_number',
            'license_no',
            'lic_no',
            'licenseno',
            'licensenumber',
            'doctor_license_number',
            'physician_license_number',
        ].includes(key.toLowerCase());
    }

    function formatLabel(key) {
        const labels = {
            lic_no: 'License Number',
            lic_status: 'License Status',
            lic_status_slug: 'Status',
            lic_type: 'License Type',
            state_name: 'State',
            full_name: 'Physician Name',
        };

        if (labels[key]) {
            return labels[key];
        }

        return key
            .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
            .replace(/[_-]+/g, ' ')
            .replace(/\b\w/g, (character) => character.toUpperCase());
    }

    function formatValue(value) {
        if (value === null || value === undefined || value === '') {
            return '—';
        }

        if (Array.isArray(value)) {
            return value.map(formatValue).join(', ');
        }

        if (typeof value === 'object') {
            return Object.entries(value)
                .map(([key, nestedValue]) => formatLabel(key) + ': ' + formatValue(nestedValue))
                .join('; ');
        }

        if (typeof value === 'boolean') {
            return value ? 'Yes' : 'No';
        }

        return String(value);
    }

    updateStateField();
})();
</script>
@endpush
