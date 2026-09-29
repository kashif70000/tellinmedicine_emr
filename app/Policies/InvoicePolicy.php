<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Admins and patients may list invoices (filtered by role in controller).
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'doctor', 'patient']);
    }

    /**
     * Admin can see all.
     * Patient can only see their own invoices.
     * Doctor has read-only access to invoices linked to their appointments.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'patient') {
            return $invoice->patient_id == optional($user->patient)->id;
        }

        if ($user->role === 'doctor') {
            return optional($invoice->appointment)->doctor_id == optional($user->doctor)->id;
        }

        return false;
    }

    // ── All mutating actions are permanently blocked ────────────────────────

    public function create(User $user): bool  { return false; }
    public function update(User $user, Invoice $invoice): bool { return false; }
    public function delete(User $user, Invoice $invoice): bool { return false; }
    public function restore(User $user, Invoice $invoice): bool { return false; }
    public function forceDelete(User $user, Invoice $invoice): bool { return false; }
}
