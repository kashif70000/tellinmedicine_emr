<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Admin;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show($uuid)
    {
        [$user, $profile] = $this->resolveProfile($uuid);

        return $this->renderProfileView($user, $profile);
    }

    public function edit($uuid)
    {
        [$user, $profile] = $this->resolveProfile($uuid);

        if (Auth::id() !== $user->id) {
            abort(403);
        }

        return $this->renderProfileView($user, $profile);
    }

    public function update(Request $request, $uuid)
    {
        [$user, $profile] = $this->resolveProfile($uuid);

        if ($request->user()?->id !== $user->id) {
            abort(403);
        }

        $profileImagePath = $this->storeProfileImage($request, $user);

        if ($user->role === 'admin') {
            return redirect()
                ->route('profile.show', $user->ensureUuid())
                ->with('success', $profileImagePath ? 'Profile image updated successfully.' : 'Admin profile loaded successfully.');
        }

        if ($user->role === 'doctor') {
            $validated = $request->validate([
                'phone' => 'required|string|max:50',
                'specialization' => 'required|string|max:255',
                'experience' => 'nullable|integer|min:0',
                'fees' => 'nullable|numeric|min:0',
                'clinic_name' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:255',
            ]);

            $profile->update($validated);
        }

        if ($user->role === 'patient') {
            $validated = $request->validate([
                'phone' => 'required|string|max:50',
                'age' => 'required|integer|min:0|max:150',
                'gender' => 'required|in:male,female,other',
                'blood_group' => 'required|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
                'dob' => 'nullable|date',
                'address' => 'nullable|string|max:255',
            ]);

            $profile->update($validated);
        }

        return redirect()
            ->route('profile.show', $user->ensureUuid())
            ->with('success', 'Profile updated successfully.');
    }

    protected function storeProfileImage(Request $request, User $user): ?string
    {
        $request->validate([
            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if (! $request->hasFile('profile_image')) {
            return null;
        }

        $path = $request->file('profile_image')->store('profile-images', 'public');

        if (! empty($user->profile_image) && Storage::disk('public')->exists($user->profile_image)) {
            Storage::disk('public')->delete($user->profile_image);
        }

        $user->forceFill([
            'profile_image' => $path,
        ])->save();

        return $path;
    }

   protected function resolveProfile(string $uuid): array
{
    $user = User::where('uuid', $uuid)->firstOrFail();

    $profile = match ($user->role) {
        'admin' => Admin::firstOrCreate(['user_id' => $user->id]),
        'doctor' => Doctor::firstOrCreate(['user_id' => $user->id]),
        'patient' => Patient::firstOrCreate(['user_id' => $user->id]),
        default => abort(404),
    };

    return [$user, $profile];
}

    protected function renderProfileView(User $user, $profile)
    {
        return match ($user->role) {
            'admin' => view('admin.profile', ['admin' => $profile]),
            'doctor' => view('doctor.profile', ['doctor' => $profile]),
            'patient' => view('patient.profile', ['patient' => $profile]),
            default => abort(404),
        };
    }




}
