@extends('layouts.layout')

@section('title', 'Edit Admin')

@section('content')
<div class="card">
    <div class="card-header">
        <i class="ti ti-pencil"></i> Edit Admin
    </div>
    <div class="card-body">
        <form action="{{ route('admins.update', $admin->id) }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-3">
                <label for="user_id" class="form-label">User ID *</label>
                <input type="number" class="form-control @error('user_id') is-invalid @enderror" id="user_id" name="user_id" value="{{ old('user_id', $admin->user_id) }}" required>
                @error('user_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="permissions" class="form-label">Permissions (JSON)</label>
                <textarea class="form-control @error('permissions') is-invalid @enderror" id="permissions" name="permissions" rows="4">{{ old('permissions', json_encode($admin->permissions)) }}</textarea>
                @error('permissions') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="ti ti-device-storage"></i> Update</button>
                <a href="{{ route('admins.index') }}" class="btn btn-secondary"><i class="ti ti-x"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
