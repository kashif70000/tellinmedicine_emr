<?php

namespace App\Policies;

use App\Models\Prescription;
use App\Models\User;

class PrescriptionPolicy
{
    /**
     * Admins, doctors, and patients may list prescriptions (filtered by role in controller).
     */
    public function viewAny(User $user): bool
        {
            return in_array($user->role, ['admin', 'doctor', 'patient']);
        }

    /**
     * Admin can see all.
     * Doctor can see prescriptions they wrote.
     * Patient can see their own prescriptions.
     */
       public function view(User $user, Prescription $prescription): bool
        {
            if ($user->role === 'admin') {
                return true;
            }
        
            if ($user->role === 'doctor') {
                return $prescription->doctor_id == optional($user->doctor)->id;
            }
        
            if ($user->role === 'patient') {
                return $prescription->patient_id == optional($user->patient)->id;
            }
        
            return false;
        }

    // ── All mutating actions are permanently blocked ────────────────────────

    public function create(User $user): bool  { return false; }
    public function update(User $user, Prescription $prescription): bool { return false; }
    public function delete(User $user, Prescription $prescription): bool { return false; }
    public function restore(User $user, Prescription $prescription): bool { return false; }
    public function forceDelete(User $user, Prescription $prescription): bool { return false; }
}
