@extends('layouts.layout')

@section('title', 'View Admin')

@section('content')
<div class="card">
    <div class="card-header">
        <i class="ti ti-eye"></i> Admin Details
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>ID:</strong> {{ $admin->id }}</p>
                <p><strong>User ID:</strong> {{ $admin->user_id }}</p>
                <p><strong>User:</strong> {{ $admin->user->name ?? 'N/A' }}</p>
            </div>
            <div class="col-md-6">
                <p><strong>Email:</strong> {{ $admin->user->email ?? 'N/A' }}</p>
                <p><strong>Permissions:</strong> {{ is_array($admin->permissions) ? count($admin->permissions) : 0 }} permissions</p>
            </div>
        </div>

        <hr>

        <div class="d-flex gap-2">
            <a href="{{ route('admins.edit', $admin->id) }}" class="btn btn-primary"><i class="ti ti-pencil"></i> Edit</a>
            <a href="{{ route('admins.index') }}" class="btn btn-secondary"><i class="ti ti-arrow-left"></i> Back</a>
        </div>
    </div>
</div>
@endsection
