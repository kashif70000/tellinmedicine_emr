@extends('layouts.layout')

@section('title', 'Change Password')
@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Change Password</div>
                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif
                    <form method="POST" action="{{ route('password.change') }}">
                        @csrf
                        <div class="mb-3 row">
                            <label for="current_password" class="col-md-4 col-form-label text-md-end">Current Password</label>
                            <div class="col-md-6">
                                <div class="pass-group input-group position-relative border rounded">
                                    <span class="input-group-text bg-white border-0">
                                        <i class="ti ti-lock text-dark fs-14"></i>
                                    </span>
                                    <input id="current_password" type="password" class="form-control ps-0 border-0 @error('current_password') is-invalid @enderror" name="current_password" required autocomplete="current-password">
                                    <span class="input-group-text bg-white border-0" style="cursor:pointer" onclick="togglePassword('current_password', this)">
                                        <i class="ti ti-eye-off text-dark fs-14"></i>
                                    </span>
                                </div>
                                @error('current_password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="password" class="col-md-4 col-form-label text-md-end">New Password</label>
                            <div class="col-md-6">
                                <div class="pass-group input-group position-relative border rounded">
                                    <span class="input-group-text bg-white border-0">
                                        <i class="ti ti-lock text-dark fs-14"></i>
                                    </span>
                                    <input id="password" type="password" class="form-control ps-0 border-0 @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">
                                    <span class="input-group-text bg-white border-0" style="cursor:pointer" onclick="togglePassword('password', this)">
                                        <i class="ti ti-eye-off text-dark fs-14"></i>
                                    </span>
                                </div>
                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="password-confirm" class="col-md-4 col-form-label text-md-end">Confirm Password</label>
                            <div class="col-md-6">
                                <div class="pass-group input-group position-relative border rounded">
                                    <span class="input-group-text bg-white border-0">
                                        <i class="ti ti-lock text-dark fs-14"></i>
                                    </span>
                                    <input id="password-confirm" type="password" class="form-control ps-0 border-0" name="password_confirmation" required autocomplete="new-password">
                                    <span class="input-group-text bg-white border-0" style="cursor:pointer" onclick="togglePassword('password-confirm', this)">
                                        <i class="ti ti-eye-off text-dark fs-14"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="mb-0 row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    Change Password
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection


@push('scripts')
<script>
function togglePassword(fieldId, element) {

    let input = document.getElementById(fieldId);
    let icon = element.querySelector('i');

    if (input.type === "password") {

        input.type = "text";

        icon.classList.remove("ti-eye-off");
        icon.classList.add("ti-eye");

    } else {

        input.type = "password";

        icon.classList.remove("ti-eye");
        icon.classList.add("ti-eye-off");
    }
}
</script>
@endpush
