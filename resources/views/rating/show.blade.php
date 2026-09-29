@extends('layouts.layout')

@section('title', 'View Rating')

@section('content')
<div class="card">
    <div class="card-header">
        <i class="ti ti-eye"></i> Rating Details
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>ID:</strong> {{ $rating->id }}</p>
                <p><strong>Doctor:</strong> {{ $rating->doctor->user->name }}</p>
                <p><strong>Specialization:</strong> {{ $rating->doctor->specialization }}</p>
                <p><strong>Patient:</strong> {{ $rating->patient->user->name }}</p>
            </div>
            <div class="col-md-6">
                <p>
                    <strong>Rating:</strong>
                    <span class="badge bg-soft-warning border border-warning text-dark fw-bold px-3 py-2 fs-14">
                        @for($i = 1; $i <= 5; $i++)
                            @if($i <= $rating->rating)
                                <i class="ti ti-star-filled text-warning me-1"></i>
                            @else
                                <i class="ti ti-star text-muted me-1"></i>
                            @endif
                        @endfor
                        <span>{{ number_format($rating->rating, 1) }} / 5.0</span>
                    </span>
                </p>
                <p><strong>Date:</strong> {{ $rating->created_at->format('M d, Y H:i') }}</p>
            </div>
        </div>

        @if($rating->review)
            <hr>
            <h6 class="fw-bold text-dark"><i class="ti ti-message-2 text-primary me-1"></i>Patient Review</h6>
            <div class="p-3 bg-light rounded-3 border-start border-primary border-4 text-dark fs-14">
                "{{ $rating->review }}"
            </div>
        @endif

        <hr>

        <div class="d-flex gap-2">
            {{-- <a href="{{ route('ratings.edit', $rating->id) }}" class="btn btn-primary"><i class="ti ti-pencil"></i> Edit</a> --}}
            <a href="{{ route('ratings.index') }}" class="btn btn-primary"><i class="ti ti-arrow-left"></i> Back</a>
        </div>
    </div>
</div>
@endsection
