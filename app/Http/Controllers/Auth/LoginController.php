<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    protected function authenticated(Request $request, $user)
    {
        $doctor = $user->doctor;
        $licenseStatus = $doctor?->license_verification_status;
        $isUnavailableWithExistingAccess = $licenseStatus === 'unable_to_verify' && $user->is_active;

        if ($user->role === 'doctor' && $doctor?->license_state === 'MA' && $licenseStatus !== 'verified' && ! $isUnavailableWithExistingAccess) {
            Auth::logout();

            return redirect()->route('login')->withErrors([
                'email' => 'Massachusetts physician licensure must be verified before doctor access is enabled.',
            ]);
        }
    }
}
