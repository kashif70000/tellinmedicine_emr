<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin;
use App\Models\Doctor;
use App\Models\Patient;
use App\Http\Controllers\AgoraCallController;
use App\Http\Controllers\AgoraTranslationController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\DoctorScheduleController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\AI\DoctorVerificationAIController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\PatientPreferenceController;
use App\Http\Controllers\Admin\DirectoryScraperController;


// Authentication Routes
Auth::routes();

Route::get('/email/verify', [VerificationController::class, 'show'])
    ->name('verification.notice');
Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
    ->name('verification.verify');
Route::post('/email/resend', [VerificationController::class, 'resend'])
    ->name('verification.resend');

Route::get('/register/patient', function () {
    return view('auth.patient-register');
})->name('register.patient');

Route::post('/register/patient', [PatientController::class, 'store'])->name('register.patient.store');

Route::get('/register/doctor', function () {
    return view('auth.doctor-register');
})->name('register.doctor');

Route::post('/register/doctor', [DoctorController::class, 'store'])->name('register.doctor.store');


Route::get('/auth/google', [SocialController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [SocialController::class, 'handleGoogleCallback']);


Route::get('/auth/facebook', [SocialController::class, 'redirectToFacebook']);
Route::get('/auth/facebook/callback', [SocialController::class, 'handleFacebookCallback']);

Route::get('/privacy-policy', function () {
    return view('privacy-policy');
});

Route::get('/data-deletion', function () {
return response("To delete your account, email support@pdms.developers.ink. We will remove your data within 7 days.");
});

Route::get('/terms', function () {
    return view('terms');
});


// Protected Routes - Require Authentication
Route::middleware(['auth'])->group(function () {
        // Change Password
        Route::get('/password/change', [ChangePasswordController::class, 'showChangeForm'])->name('password.change.form');
        Route::post('/password/change', [ChangePasswordController::class, 'change'])->name('password.change');
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::redirect('/', '/dashboard');

    Route::get('/doctor/dashboard', function () {
        return view('doctor.dashboard');
    });
    Route::get('/admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

    // Profile Routes
    Route::get('/profile/admin/{admin}', function (Admin $admin) {
        return redirect()->route('profile.show', $admin->user->ensureUuid());
    })->name('admin.profile');

    Route::get('/profile/doctor/{doctor}', function (Doctor $doctor) {
        return redirect()->route('profile.show', $doctor->user->ensureUuid());
    })->name('doctor.profile');

    Route::get('/profile/patient/{patient}', function (Patient $patient) {
        return redirect()->route('profile.show', $patient->user->ensureUuid());
    })->name('patient.profile');

    Route::get('/profile/{uuid}', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/{uuid}/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile/{uuid}', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/patient/payment-verification', [PatientController::class, 'paymentPage'])
    ->name('patient.payment.page')
    ->middleware(['auth']);

    // Patient Preferred Pharmacy & Laboratory routes
    Route::get('/patient/pharmacies/search', [PatientPreferenceController::class, 'searchPharmacies'])->name('patient.pharmacies.search');
    Route::post('/patient/preferred-pharmacy', [PatientPreferenceController::class, 'savePreferredPharmacy'])->name('patient.preferred-pharmacy.store');
    Route::delete('/patient/preferred-pharmacy', [PatientPreferenceController::class, 'removePreferredPharmacy'])->name('patient.preferred-pharmacy.destroy');

    Route::get('/patient/laboratories/search', [PatientPreferenceController::class, 'searchLaboratories'])->name('patient.laboratories.search');
    Route::post('/patient/preferred-laboratory', [PatientPreferenceController::class, 'savePreferredLaboratory'])->name('patient.preferred-laboratory.store');
    Route::delete('/patient/preferred-laboratory', [PatientPreferenceController::class, 'removePreferredLaboratory'])->name('patient.preferred-laboratory.destroy');
    
    Route::post('/mark-verified', [PatientController::class, 'markVerified'])->middleware('auth');

    Route::get('/ai/doctor/{id}/analyze', [
    DoctorVerificationAIController::class,
    'analyzeCertificate'
     ])->name('ai.doctor.analyze')->middleware('auth');
    

    Route::resource('admins', AdminController::class);
    Route::resource('doctors', DoctorController::class);
    Route::resource('patients', PatientController::class);
    Route::resource('appointments', AppointmentController::class);

    // Prescriptions – immutable records (view-only after creation)
    Route::get('prescriptions',          [PrescriptionController::class, 'index'])->name('prescriptions.index');
    Route::get('prescriptions/{prescription}', [PrescriptionController::class, 'show'])->name('prescriptions.show');

    // Invoices – immutable records (view-only after auto-creation)
    Route::get('invoices',               [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{invoice}',     [InvoiceController::class, 'show'])->name('invoices.show');

    // ── Panel-scoped aliases (redirect to same controllers) ──────────────────
    Route::get('patient/prescriptions',           [PrescriptionController::class, 'index'])->name('patient.prescriptions.index');
    Route::get('patient/prescriptions/{id}',      [PrescriptionController::class, 'show'])->name('patient.prescriptions.show');
    Route::get('patient/invoices',                [InvoiceController::class, 'index'])->name('patient.invoices.index');
    Route::get('patient/invoices/{id}',           [InvoiceController::class, 'show'])->name('patient.invoices.show');

    Route::get('admin/prescriptions',             [PrescriptionController::class, 'index'])->name('admin.prescriptions.index');
    Route::get('admin/prescriptions/{id}',        [PrescriptionController::class, 'show'])->name('admin.prescriptions.show');
    Route::get('admin/invoices',                  [InvoiceController::class, 'index'])->name('admin.invoices.index');
    Route::get('admin/invoices/{id}',             [InvoiceController::class, 'show'])->name('admin.invoices.show');

    Route::get('doctor/prescriptions',            [PrescriptionController::class, 'index'])->name('doctor.prescriptions.index');
    Route::get('doctor/prescriptions/{id}',       [PrescriptionController::class, 'show'])->name('doctor.prescriptions.show');
    Route::get('doctor/invoices',                 [InvoiceController::class, 'index'])->name('doctor.invoices.index');
    Route::get('doctor/invoices/{id}',            [InvoiceController::class, 'show'])->name('doctor.invoices.show');

    Route::resource('payments', PaymentController::class);
    Route::resource('ratings', RatingController::class);

    });
    
Route::post('/stripe/create-intent', [PatientController::class, 'createIntent'])
    ->middleware('throttle:10,1');
Route::post('/stripe/register-intent', [PatientController::class, 'registerIntent'])
    ->middleware('throttle:10,1');    
Route::post('/register-mark-verified', [PatientController::class, 'markVerifiedAfterRegister'])
    ->middleware('throttle:10,1');

//admin doctor verification routes
Route::get('/admin/doctor-verifications', [AdminController::class, 'doctorVerifications'])
    ->name('doctor-verifications');

Route::post('/admin/doctor-verifications/{doctor}/approve', [AdminController::class, 'approveDoctor'])
    ->name('doctor.approve');

Route::post('/admin/doctor-verifications/{doctor}/reject', [AdminController::class, 'rejectDoctor'])
    ->name('doctor.reject');

// Admin Site Settings (Logo & Favicon)
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/settings/site', [SettingController::class, 'index'])->name('admin.settings.site');
    Route::post('/admin/settings/site', [SettingController::class, 'update'])->name('admin.settings.site.update');

    // Admin Directory Scraper Routes
    Route::get('/admin/directory-scraper', [DirectoryScraperController::class, 'index'])->name('admin.directory-scraper.index');
    Route::post('/admin/directory-scraper/scrape', [DirectoryScraperController::class, 'scrape'])->name('admin.directory-scraper.scrape');
    Route::get('/admin/directory-scraper/data', [DirectoryScraperController::class, 'data'])->name('admin.directory-scraper.data');
    Route::get('/admin/directory-scraper/location-suggest', [DirectoryScraperController::class, 'locationSuggest'])->name('admin.directory-scraper.location-suggest');
});

Route::post('/doctor/update-verification', [DoctorController::class, 'updateVerification'])
    ->middleware('auth')
    ->name('doctor.updateVerification');

Route::middleware(['auth'])->group(function () {
    Route::get('/doctor/patients/search', [DoctorController::class, 'searchPatients'])->name('doctor.patients.search');
    Route::get('/doctor/appointments', [AppointmentController::class, 'doctorAppointments'])->name('doctor.appointments');
    Route::get('/doctor/my-patients', [DoctorController::class, 'myPatients'])->name('doctor.my-patients');
    Route::get('/doctor/schedules', [DoctorScheduleController::class, 'index'])->name('doctor.schedules.index');
    Route::post('/doctor/schedule', [DoctorScheduleController::class, 'store'])->name('doctor.schedule.store');
    Route::put('/doctor/schedules/{schedule}', [DoctorScheduleController::class, 'update'])->name('doctor.schedules.update');
    Route::delete('/doctor/schedules/{schedule}', [DoctorScheduleController::class, 'destroy'])->name('doctor.schedules.destroy');
    Route::get('/doctor/{doctor}/schedule', [DoctorScheduleController::class, 'getScheduleByDoctor'])->name('doctor.schedule.show');
    Route::get('/admin/appointments', [AppointmentController::class, 'adminAppointments'])->name('admin.appointments');
    Route::post('/appointments/book', [AppointmentController::class, 'store'])->name('appointments.book');
    Route::post('/doctor/appointments/{appointment}/approve', [AppointmentController::class, 'approve'])->name('doctor.appointments.approve');
    Route::post('/doctor/appointments/{appointment}/reject', [AppointmentController::class, 'reject'])->name('doctor.appointments.reject');

    // Doctor Patient Clinical Workspace Routes
    Route::prefix('doctor/patients/{patient}')->name('doctor.clinical.')->group(function () {
        Route::get('/clinical/preferred-destinations', [\App\Http\Controllers\DoctorClinicalWorkspaceController::class, 'getPreferredDestinations'])->name('preferred-destinations');
        Route::get('/medicines/search', [\App\Http\Controllers\DoctorClinicalWorkspaceController::class, 'searchMedicines'])->name('medicines.search');
        Route::get('/pharmacies/search', [\App\Http\Controllers\DoctorClinicalWorkspaceController::class, 'searchPharmacies'])->name('pharmacies.search');
        Route::post('/prescriptions', [\App\Http\Controllers\DoctorClinicalWorkspaceController::class, 'storePrescription'])->name('prescriptions.store');
        Route::get('/prescriptions', [\App\Http\Controllers\DoctorClinicalWorkspaceController::class, 'listPrescriptions'])->name('prescriptions.index');
        Route::get('/prescriptions/{prescription}', [\App\Http\Controllers\DoctorClinicalWorkspaceController::class, 'showPrescription'])->name('prescriptions.show');
        Route::get('/prescriptions/{prescription}/pdf', [\App\Http\Controllers\DoctorClinicalWorkspaceController::class, 'prescriptionPdf'])->name('prescriptions.pdf');

        Route::get('/lab-tests/search', [\App\Http\Controllers\DoctorClinicalWorkspaceController::class, 'searchLabTests'])->name('lab-tests.search');
        Route::get('/laboratories/search', [\App\Http\Controllers\DoctorClinicalWorkspaceController::class, 'searchLaboratories'])->name('laboratories.search');
        Route::post('/lab-orders', [\App\Http\Controllers\DoctorClinicalWorkspaceController::class, 'storeLabOrder'])->name('lab-orders.store');
        Route::get('/lab-orders', [\App\Http\Controllers\DoctorClinicalWorkspaceController::class, 'listLabOrders'])->name('lab-orders.index');
        Route::get('/lab-orders/{labOrder}', [\App\Http\Controllers\DoctorClinicalWorkspaceController::class, 'showLabOrder'])->name('lab-orders.show');
        Route::get('/lab-orders/{labOrder}/pdf', [\App\Http\Controllers\DoctorClinicalWorkspaceController::class, 'labOrderPdf'])->name('lab-orders.pdf');
    });
});

// Payment Routes
Route::middleware(['auth'])->group(function () {
    Route::post('/appointments/{appointment}/payment-intent',
        [AppointmentController::class, 'createPaymentIntent']
    )->name('appointments.payment.intent');

    Route::post('/appointments/payment/confirm',
        [AppointmentController::class, 'confirmPayment']
    )->name('appointments.payment.confirm');

    Route::post('/appointments/{appointment}/refund',
        [AppointmentController::class, 'refundPayment']
    )->name('appointments.payment.refund');
});

// -----------------------------------------------------------------------
// Video Call & Live Prescription Routes
// -----------------------------------------------------------------------
Route::middleware(['auth'])->group(function () {

    // Doctor starts call
    Route::post('/doctor/appointments/{id}/start-call', [AgoraCallController::class, 'startCall'])
        ->name('doctor.appointments.start-call');

    // Both doctor and patient join (renders video-call view)
    Route::get('/appointments/{id}/join-call', [AgoraCallController::class, 'joinCall'])
        ->name('appointments.call');

    // Doctor ends call
    Route::post('/appointments/{id}/end-call', [AgoraCallController::class, 'endCall'])
        ->name('appointments.end-call');

    // Call status polling (JSON) — used by the video call page to detect auto-end
    Route::get('/appointments/{id}/call-status', [AgoraCallController::class, 'callStatus'])
        ->name('appointments.call-status');

    // Patient active call check for header banner notification
    Route::get('/patient/active-call-check', [AgoraCallController::class, 'activeCallCheck'])
        ->name('patient.active-call-check');

    // ── Live Subtitles / Real-Time Translation Routes ─────────────
    Route::post('/appointments/{id}/translation/start', [AgoraTranslationController::class, 'start'])
        ->name('appointments.translation.start');

    Route::post('/appointments/{id}/translation/stop', [AgoraTranslationController::class, 'stop'])
        ->name('appointments.translation.stop');

    Route::post('/appointments/{id}/translation/language', [AgoraTranslationController::class, 'updateLanguage'])
        ->name('appointments.translation.language');

    Route::get('/appointments/{id}/translation/status', [AgoraTranslationController::class, 'status'])
        ->name('appointments.translation.status');

    // Live prescription – doctor writes (POST), both read (GET)
    Route::post('/appointments/{id}/prescription', [PrescriptionController::class, 'liveStore'])
        ->name('appointments.prescription.store');

    Route::get('/appointments/{id}/prescription', [PrescriptionController::class, 'liveShow'])
        ->name('appointments.prescription.show');

    // Medicine Search & Quick-Create Routes
    Route::get('/medicines/search', [MedicineController::class, 'search'])
        ->name('medicines.search');
    Route::post('/medicines/quick-create', [MedicineController::class, 'quickCreate'])
        ->name('medicines.quick-create');

    // ── Rating Routes ──────────────────────────────────────────────
    Route::post('/appointments/{id}/rate', [RatingController::class, 'store'])
        ->name('appointments.rate');

    Route::get('/appointments/{id}/rating', [RatingController::class, 'show'])
        ->name('appointments.rating.show');

    Route::get('/doctors/{id}/reviews', [RatingController::class, 'doctorReviews'])
        ->name('doctors.reviews');

    // ── Dev-only debug routes (disabled automatically in production) ──
    Route::get('/agora/debug-token', [AgoraCallController::class, 'debugToken'])
        ->name('agora.debug-token');
});
