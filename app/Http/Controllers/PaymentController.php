<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::with('appointment.patient.user', 'appointment.doctor.user')->paginate(10);
        return view('payment.index', compact('payments'));
    }

    public function create()
    {
        return view('payment.create');
    }

    public function store(StorePaymentRequest $request)
    {
        Payment::create($request->validated());
        return redirect()->route('payments.index')->with('success', 'Payment created successfully.');
    }

    public function show(Payment $payment)
    {
        $payment->load('appointment.patient.user', 'appointment.doctor.user');
        return view('payment.show', compact('payment'));
    }

    public function edit(Payment $payment)
    {
        return view('payment.edit', compact('payment'));
    }

    public function update(UpdatePaymentRequest $request, Payment $payment)
    {
        $payment->update($request->validated());
        return redirect()->route('payments.index')->with('success', 'Payment updated successfully.');
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();
        return redirect()->route('payments.index')->with('success', 'Payment deleted successfully.');
    }
}
