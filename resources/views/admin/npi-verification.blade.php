@extends('layouts.layout')

@section('title', 'Npi Verification')

@section('styles')
<style>
#result {
    width: 100%;
    max-width: 95%;
    height: auto;
    display: block;
    margin: 1.5rem 0 0;
    border: 0;
    box-shadow: none;
    border-radius: 0;
}

#result .card {
    width: 100%;
}

#result table {
    width: 100%;
}

#result:empty {
    display: none;
}
</style>
@endsection

@section('content')

<div class="container">

    <div class="card">

        <div class="card-header">
            <h4>NPI Verification</h4>
        </div>

        <div class="card-body">

            <form id="npiVerificationForm">
                  @csrf
                <div class="mb-3">
                    <label for="npi" class="form-label">
                        NPI Number
                    </label>
                    <input type="text" id="npi" name="npi" class="form-control" maxlength="10" placeholder="Enter 10-digit NPI" required>
                    <div class="text-danger mt-1" id="npiError"></div>
                </div>

                <button type="submit" class="btn btn-primary" id="verifyButton"> Verify NPI
                </button>

            </form>

            <div id="loading" class="mt-3" style="display:none;">
                Verifying NPI...
            </div>

        </div>

    </div>

    <div id="result" class="mt-4"></div>

</div>

@endsection
@push('scripts')

<script>

document
    .getElementById('npiVerificationForm')
    .addEventListener('submit', async function (event) {

        event.preventDefault();

        const npi = document
            .getElementById('npi')
            .value
            .trim();

        const button = document.getElementById('verifyButton');
        const loading = document.getElementById('loading');
        const result = document.getElementById('result');
        const error = document.getElementById('npiError');

        error.innerHTML = '';
        result.innerHTML = '';

        if (!/^\d{10}$/.test(npi)) {
            error.innerHTML =
                'Please enter a valid 10-digit NPI.';

            return;
        }

        button.disabled = true;
        loading.style.display = 'block';

        try {

            const response = await fetch(
                '{{ route("admin.npi.verify") }}',
                {
                    method: 'POST',

                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN':
                            '{{ csrf_token() }}'
                    },

                    body: JSON.stringify({
                        npi: npi
                    })
                }
            );

            const data = await response.json();

            if (!data.success) {

                result.innerHTML = `
                    <div class="alert alert-danger">
                        <strong>NPI Not Found</strong><br>
                        ${data.message}
                    </div>
                `;
                return;
            }

            displayProvider(data.data);

        } catch (error) {

            result.innerHTML = `
                <div class="alert alert-danger">
                    Unable to verify NPI.
                    Please try again.
                </div>
            `;

        } finally {

            button.disabled = false;
            loading.style.display = 'none';
        }
    });


function displayProvider(provider)
{
    const basic = provider.basic ?? {};

    let name = '';

    if (provider.enumeration_type === 'NPI-1') {

        name = [
            basic.first_name,
            basic.middle_name,
            basic.last_name
        ]
        .filter(Boolean)
        .join(' ');

    } else {

        name = basic.organization_name ?? '';
    }


    const taxonomy = provider.taxonomies?.[0] ?? {};

    const address =
        provider.addresses?.find(
            address => address.address_purpose === 'LOCATION'
        )
        ?? provider.addresses?.[0]
        ?? {};


   document.getElementById('result').innerHTML = `

    <div class="alert alert-success">
        <strong>✓ NPI Found</strong>
    </div>

    <div class="card col-md-12">

        <div class="card-header">
            <strong>Provider Information</strong>
        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-bordered table-hover mb-0">

                    <tbody>

                        <tr>
                            <th style="width: 30%;">NPI</th>
                            <td>${provider.number ?? ''}</td>
                        </tr>

                        <tr>
                            <th>Provider Name</th>
                            <td>${name}</td>
                        </tr>

                        <tr>
                            <th>Credential</th>
                            <td>${basic.credential ?? ''}</td>
                        </tr>

                        <tr>
                            <th>Provider Type</th>
                            <td>${provider.enumeration_type ?? ''}</td>
                        </tr>

                        <tr>
                            <th>Taxonomy</th>
                            <td>${taxonomy.desc ?? ''}</td>
                        </tr>

                        <tr>
                            <th>Taxonomy Code</th>
                            <td>${taxonomy.code ?? ''}</td>
                        </tr>

                        <tr>
                            <th>Address</th>
                            <td>
                                ${address.address_1 ?? ''}
                                ${address.address_2 ?? ''}
                            </td>
                        </tr>

                        <tr>
                            <th>City</th>
                            <td>${address.city ?? ''}</td>
                        </tr>

                        <tr>
                            <th>State</th>
                            <td>${address.state ?? ''}</td>
                        </tr>

                        <tr>
                            <th>ZIP</th>
                            <td>${address.postal_code ?? ''}</td>
                        </tr>

                        <tr>
                            <th>Phone</th>
                            <td>${address.telephone_number ?? ''}</td>
                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </div>
`;
}

</script>

@endpush