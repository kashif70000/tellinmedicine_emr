<?php

namespace App\Http\Controllers;

use App\Models\Rating;
use App\Models\Appointment;
use App\Http\Requests\StoreRatingRequest;
use App\Http\Requests\UpdateRatingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RatingController extends Controller
{
    public function index()
    {
        $ratings = Rating::with('doctor.user', 'patient.user', 'appointment')->paginate(10);
        return view('rating.index', compact('ratings'));
    }

    public function create()
    {
        return view('rating.create');
    }

    /**
     * AJAX Store — Submit rating after appointment completion
     * POST /appointments/{id}/rate
     */
    public function store(Request $request, $appointmentId)
    {
        $user = Auth::user();

        $appointment = Appointment::with(['doctor', 'patient'])->findOrFail($appointmentId);
        $currentPatientId = (int) optional($user->patient)->id;
        $ownsAppointment = (int) $appointment->patient_id === $currentPatientId;
        $isAuthorizedPatient = $user->role === 'patient' && $currentPatientId > 0 && $ownsAppointment;

        if (! $isAuthorizedPatient || $appointment->status !== 'completed') {
            return response()->json([
                'error' => 'Unauthorized.'
            ], 403);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|between:1,5',
            'review' => 'nullable|string|max:2000',
        ]);

        $existingRating = Rating::where('appointment_id', (int) $appointment->id)->first();
        if ($existingRating) {
            return response()->json([
                'error' => 'Review already submitted.'
            ], 422);
        }

        try {
            $rating = Rating::create([
                'appointment_id' => (int) $appointment->id,
                'doctor_id' => (int) $appointment->doctor_id,
                'patient_id' => $currentPatientId,
                'rating' => (int) $validated['rating'],
                'review' => $validated['review'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Review submitted successfully.',
                'rating' => $rating,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to save rating: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Show — Fetch rating for an appointment (check if exists)
     * GET /appointments/{id}/rating
     */
    public function show(Request $request, $id)
    {
        if ($request->routeIs('ratings.show')) {
            $rating = Rating::with(['doctor.user', 'patient.user', 'appointment'])->findOrFail((int) $id);
            return view('rating.show', compact('rating'));
        }

        $user = Auth::user();
        $appointment = Appointment::with(['doctor.user', 'patient.user'])
            ->findOrFail((int) $id);

        $currentUserRole = (string) $user->role;
        $currentPatientId = (int) optional($user->patient)->id;
        $appointmentPatientId = (int) $appointment->patient_id;
        $isDoctor = $currentUserRole === 'doctor'
            && (int) optional($user->doctor)->id === (int) $appointment->doctor_id;
        $isPatient = $currentUserRole === 'patient'
            && $appointmentPatientId === $currentPatientId;
        $isAdmin = $user->role === 'admin';

        if (!$isDoctor && !$isPatient && !$isAdmin) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $rating = Rating::where('appointment_id', (int) $appointment->id)
            ->with('doctor.user', 'patient.user')
            ->first();

        $hasRating = $rating !== null;
        $canReview = $currentUserRole === 'patient'
            && $appointmentPatientId === $currentPatientId
            && (string) $appointment->status === 'completed'
            && ! $hasRating;

        return response()->json([
            'rating' => $rating,
            'has_rating' => $hasRating,
            'appointment_status' => (string) $appointment->status,
            'appointment_patient_id' => $appointmentPatientId,
            'current_patient_id' => $currentPatientId,
            'current_user_role' => $currentUserRole,
            'can_review' => $canReview,
        ], 200);
    }

    /**
     * Doctor Reviews — Get all reviews for a specific doctor
     * GET /doctors/{id}/reviews
     */
    public function doctorReviews(Request $request, $doctorId)
    {
        $limit = $request->query('limit', 10);
        $page = $request->query('page', 1);

        $reviews = Rating::where('doctor_id', (int) $doctorId)
            ->with('patient.user', 'appointment')
            ->orderByDesc('created_at')
            ->paginate($limit, ['*'], 'page', $page);

        // Calculate average rating
        $averageRating = Rating::where('doctor_id', (int) $doctorId)
            ->avg('rating');

        return response()->json([
            'average_rating' => $averageRating ? round($averageRating, 1) : 0,
            'total_reviews' => Rating::where('doctor_id', (int) $doctorId)->count(),
            'reviews' => $reviews->items(),
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'total_pages' => $reviews->lastPage(),
                'total' => $reviews->total(),
                'per_page' => $reviews->perPage(),
            ],
        ], 200);
    }

    public function edit(Rating $rating)
    {
        return view('rating.edit', compact('rating'));
    }

    public function update(UpdateRatingRequest $request, Rating $rating)
    {
        $rating->update($request->validated());
        return redirect()->route('ratings.index')->with('success', 'Rating updated successfully.');
    }

    public function destroy(Rating $rating)
    {
        $rating->delete();
        return redirect()->route('ratings.index')->with('success', 'Rating deleted successfully.');
    }
}
