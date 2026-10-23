<?php

namespace App\Http\Controllers\Auth;

use Laravel\Socialite\Facades\Socialite;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class SocialController extends Controller
{
    public function redirectToGoogle()
    {
        session(['role' => request('role', 'patient'),
        'type' => request('type')
        ]);

        return Socialite::driver('google')->redirect();
    }

   public function handleGoogleCallback()
{
    try {
        $googleUser = Socialite::driver('google')->stateless()->user();

        if (!$googleUser || !$googleUser->getEmail()) {
            abort(403, 'Google authentication failed.');
        }

        $role = session('role');
        $type = session('type'); 

        $user = User::where('email', $googleUser->getEmail())->first();

        if ($type === 'login') {
            if (!$user) {
                return redirect('/login')->withErrors([
                    'email' => 'No account found for chosen email. Please register first.'
                ]);
            }

            $doctor = $user->doctor;
            $licenseStatus = $doctor?->license_verification_status;
            $isUnavailableWithExistingAccess = $licenseStatus === 'unable_to_verify' && $user->is_active;
            if ($user->role === 'doctor' && $doctor?->license_state === 'MA' && $licenseStatus !== 'verified' && ! $isUnavailableWithExistingAccess) {
                return redirect('/login')->withErrors(['email' => 'Massachusetts physician licensure must be verified before doctor access is enabled.']);
            }

            Auth::login($user);

            $uuid = $user->ensureUuid();

            return redirect("/profile/{$uuid}");
        }

        if ($role === 'doctor') {
            return redirect('/register/doctor')->withErrors(['email' => 'Doctor accounts must use the license verification registration process.']);
        }

        if (!$user) {
            $user = User::create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'password' => bcrypt(str()->random(32)),
                'email_verified_at' => now(),
                'is_payment_method_verified' => false,
                'role' => $role,
            ]);
        } else {
            if (empty($user->role)) {
                $user->role = $role;
                $user->save();
            }
        }

        Auth::login($user);

        session()->forget(['role', 'type']);

        $uuid = $user->ensureUuid();

        return redirect("/profile/{$uuid}");

    } catch (\Exception $e) {
        dd($e->getMessage());
    }
}




// facebook functions

public function redirectToFacebook()
{
    session([
        'role' => request('role'),
        'type' => request('type')
    ]);

    return Socialite::driver('facebook')->redirect();
}


public function handleFacebookCallback()
{
    try {
        $fbUser = Socialite::driver('facebook')->stateless()->user();

        if (!$fbUser || !$fbUser->getEmail()) {
            abort(403, 'Facebook authentication failed.');
        }

        $role = session('role');
        $type = session('type');

        $user = User::where('email', $fbUser->getEmail())->first();

        if ($type === 'login') {
            if (!$user) {
                return redirect('/login')->withErrors([
                    'email' => 'No account found for chosen email. Please register first.'
                ]);
            }

            $doctor = $user->doctor;
            $licenseStatus = $doctor?->license_verification_status;
            $isUnavailableWithExistingAccess = $licenseStatus === 'unable_to_verify' && $user->is_active;
            if ($user->role === 'doctor' && $doctor?->license_state === 'MA' && $licenseStatus !== 'verified' && ! $isUnavailableWithExistingAccess) {
                return redirect('/login')->withErrors(['email' => 'Massachusetts physician licensure must be verified before doctor access is enabled.']);
            }

            Auth::login($user);

            $uuid = $user->ensureUuid();

            return redirect("/profile/{$uuid}");
        }

        if ($role === 'doctor') {
            return redirect('/register/doctor')->withErrors(['email' => 'Doctor accounts must use the license verification registration process.']);
        }

        if (!$user) {
            $user = User::create([
                'name' => $fbUser->getName(),
                'email' => $fbUser->getEmail(),
                'password' => bcrypt(str()->random(32)),
                'email_verified_at' => now(),
                'is_payment_method_verified' => false,
                'role' => $role,
            ]);
        } else {
            if (empty($user->role)) {
                $user->role = $role;
                $user->save();
            }
        }

        Auth::login($user);

        session()->forget(['role', 'type']);

        $uuid = $user->ensureUuid();

        return redirect("/profile/{$uuid}");

    } catch (\Exception $e) {
        dd($e->getMessage());
    }
}

}