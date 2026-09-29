<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    // ─── INDEX ───────────────────────────────────────────────

    public function index()
    {
        $user = Auth::user();

        $this->authorize('viewAny', Invoice::class);

        if ($user->role === 'admin') {

            $invoices = Invoice::with([
                'patient.user',
                'appointment.doctor.user',
                'payment',
            ])->latest()->paginate(15);

        } elseif ($user->role === 'doctor') {

            $doctorId = optional($user->doctor)->id;

            if (!$doctorId) {
                abort(403, 'Doctor profile missing.');
            }

            $invoices = Invoice::with([
                'patient.user',
                'appointment',
                'payment',
            ])
            ->whereHas('appointment', function ($q) use ($doctorId) {
                $q->where('doctor_id', $doctorId);
            })
            ->latest()
            ->paginate(15);

        } else {

            $patientId = optional($user->patient)->id;

            if (!$patientId) {
                abort(403, 'Patient profile missing.');
            }

            $invoices = Invoice::with([
                'appointment.doctor.user',
                'payment',
            ])
            ->where('patient_id', $patientId)
            ->latest()
            ->paginate(15);
        }

        return view('invoice.index', compact('invoices'));
    }

    // ─── SHOW ───────────────────────────────────────────────

    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $invoice->load(
            'patient.user',
            'appointment.doctor.user',
            'appointment.patient.user',
            'payment'
        );

        return view('invoice.show', compact('invoice'));
    }

    // ─── CREATE FROM PAYMENT (UNCHANGED BUT SAFE) ───────────

    public static function createFromPayment(Payment $payment): Invoice
    {
        $appointment = $payment->appointment()->with('patient')->firstOrFail();

        $existing = Invoice::where('payment_id', $payment->id)->first();
        if ($existing) {
            return $existing;
        }

        return Invoice::create([
            'payment_id'     => $payment->id,
            'appointment_id' => $appointment->id,
            'patient_id'     => $appointment->patient_id,
            'invoice_number' => Invoice::generateNumber(),
            'total_amount'   => $payment->amount,
            'issued_date'    => now()->toDateString(),
            'status'         => 'paid',
        ]);
    }
}