<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $listScope = 'patients';
        $search = trim((string) $request->query('search'));

        if ($user?->role === 'doctor') {
            $doctorId = $user->doctor?->id;

            $patients = Patient::query()
                ->select('patients.*')
                ->distinct()
                ->join('appointments', 'appointments.patient_id', '=', 'patients.id')
                ->where('appointments.doctor_id', $doctorId)
                ->when($search !== '', function ($query) use ($search) {
                    $query->join('users', 'users.id', '=', 'patients.user_id')
                        ->where(function ($userQuery) use ($search) {
                            $userQuery->where('users.name', 'like', '%' . $search . '%')
                                ->orWhere('users.email', 'like', '%' . $search . '%');
                        });
                })
                ->with([
                    'user',
                    'appointments' => function ($query) use ($doctorId) {
                        $query->where('doctor_id', $doctorId)
                            ->with('doctor.user')
                            ->latest('appointment_date')
                            ->latest('appointment_time');
                    },
                ])
                ->withCount([
                    'appointments as appointment_history_count' => function ($query) use ($doctorId) {
                        $query->where('doctor_id', $doctorId);
                    },
                ])
                ->paginate(10)
                ->withQueryString();

            $listScope = 'my patients';
        } elseif ($user?->role === 'patient') {
            $patients = Patient::with('user')
                ->whereKey($user->patient?->id)
                ->when($search !== '', function ($query) use ($search) {
                    $query->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
                })
                ->paginate(10)
                ->withQueryString();

            $listScope = 'your profile';
        } else {
            $patients = Patient::with('user')
                ->when($search !== '', function ($query) use ($search) {
                    $query->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
                })
                ->paginate(10)
                ->withQueryString();
        }

        if ($request->expectsJson()) {
            $userRole = $user?->role;

            return response()->json([
                'rows' => $patients->map(function ($patient) use ($userRole) {
                    return [
                        'id' => $patient->id,
                        'name' => $patient->user->name,
                        'email' => $patient->user->email,
                        'age' => $patient->age,
                        'gender' => ucfirst($patient->gender),
                        'blood_group' => $patient->blood_group,
                        'is_payment_method_verified' => (bool) $patient->is_payment_method_verified,
                        'appointment_history_count' => $patient->appointment_history_count ?? 0,
                        'show_url' => route('patients.show', $patient->id),
                        'delete_url' => $userRole === 'admin'
                            ? route('patients.destroy', $patient->id)
                            : null,
                    ];
                }),
                'links' => (string) $patients->links(),
            ]);
        }

        return view('patient.index', compact('patients', 'listScope'));
    }

    public function create()
    {
        return view('patient.create');
    }

    public function store(Request $request)
    {
        if ($request->has('skip')) {
            return redirect()->route('login');

             
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|confirmed|min:8',
            'phone' => 'required|string|max:50',
            'age' => 'required|integer|min:0|max:150',
            'gender' => 'required|in:male,female,other',
            'blood_group' => 'required|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'address' => 'nullable|string|max:255',
            'is_verified' => 'boolean',
            'is_payment_method_verified' => 'boolean',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'patient',
            'is_active' => true,
        ]);

        event(new Registered($user));

       $patient = Patient::create([
            'user_id' => $user->id,
            'age' => $validated['age'],
            'gender' => $validated['gender'],
            'blood_group' => $validated['blood_group'],
            'is_payment_method_verified' => $validated['is_payment_method_verified'] ?? false,
            'phone' => $validated['phone'],
            'dob' => $validated['age'],
            'address' => $validated['address'] ?? null,
            'is_verified' => $validated['is_verified'] ?? false,
            ]);
            session([
                'pending_patient_id' => $patient->id
            ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Registration successful. A verification email has been sent.',
            ]);
        }

        return redirect()->route('login')->with(
            'success',
            'Registration successful. Please check your email for a verification link, then log in.'
        );
    }

    public function show(Patient $patient)
    {
        $user = Auth::user();

        if ($user?->role === 'doctor') {
            $doctorId = $user->doctor?->id;

            $patient->load([
                'user',
                'appointments' => function ($query) use ($doctorId) {
                    $query->when($doctorId, function ($q) use ($doctorId) {
                        $q->where('doctor_id', $doctorId);
                    })
                    ->with('doctor.user')
                    ->latest('appointment_date')
                    ->latest('appointment_time');
                },
            ]);
        } elseif ($user?->role === 'patient') {
            abort_unless($patient->id === $user->patient?->id, 403);
            $patient->load('user');
        } else {
            $patient->load('user');
        }

        return view('patient.show', compact('patient'));
    }

    public function edit(Patient $patient)
    {
        return view('patient.edit', compact('patient'));
    }

   

    public function destroy(Patient $patient)
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        $patient->delete();
        return redirect()->route('patients.index')->with('success', 'Patient deleted successfully.');
    }

    public function paymentPage()
{
    return view('patient.payment-verification');
}


public function markVerified()
{
    $patient = Auth::user()->patient;

    $patient->update([
        'is_payment_method_verified' => 1
    ]);

    return response()->json(['success' => true]);
}




public function createIntent(Request $request)
{
    Stripe::setApiKey(config('services.stripe.secret'));

    $intent = PaymentIntent::create([
        'amount' => 100,
        'currency' => 'usd',
        'capture_method' => 'manual',
        'metadata' => [
            'user_id' => Auth::id(),
            'source' => $request->source // dashboard
        ]
    ]);

    return response()->json([
        'clientSecret' => $intent->client_secret
    ]);
}


public function registerIntent(Request $request)
{
    Stripe::setApiKey(config('services.stripe.secret'));

    $intent = PaymentIntent::create([
        'amount' => 100,
        'currency' => 'usd',
        'capture_method' => 'manual',
        'metadata' => [
            'source' => 'register'
        ]
    ]);

    return response()->json([
        'clientSecret' => $intent->client_secret
    ]);
}


public function markVerifiedAfterRegister(Request $request)
{
    try {
        $patientId = session('pending_patient_id');

        if (!$patientId) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired or invalid registration flow'
            ], 400);
        }

        $patient = Patient::find($patientId);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'message' => 'Patient not found'
            ], 404);
        }

        // Optional: verify request is really from frontend payment success
        if (!$request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request'
            ], 403);
        }

        $patient->update([
            'is_payment_method_verified' => 1,
            'is_verified' => 1
        ]);

        session()->forget('pending_patient_id');

        return response()->json([
            'success' => true,
            'message' => 'Registration payment verified successfully'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
}
}
