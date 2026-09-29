<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /**
     * Display the site settings form.
     */
    public function index()
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Unauthorized action. Only administrators can access site settings.');
        }

        $settings = [
            'site_name' => Setting::get('site_name', 'PDMS'),
            'site_logo' => Setting::get('site_logo'),
            'site_logo_dark' => Setting::get('site_logo_dark'),
            'site_logo_small' => Setting::get('site_logo_small'),
            'site_favicon' => Setting::get('site_favicon'),
        ];

        return view('admin.settings.site', compact('settings'));
    }

    /**
     * Update the site settings.
     */
    public function update(Request $request)
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Unauthorized action. Only administrators can update site settings.');
        }

        $request->validate([
            'site_name' => 'nullable|string|max:255',
            'site_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'site_logo_dark' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'site_logo_small' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'site_favicon' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,webp,ico,x-icon|max:2048',
        ]);

        if ($request->has('site_name')) {
            Setting::set('site_name', $request->input('site_name'));
        }

        $imageFields = ['site_logo', 'site_logo_dark', 'site_logo_small', 'site_favicon'];

        foreach ($imageFields as $field) {
            // Check if removal requested
            if ($request->has('remove_' . $field) && $request->input('remove_' . $field) == '1') {
                $oldPath = Setting::get($field);
                if (! empty($oldPath) && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
                Setting::set($field, null);
                continue;
            }

            if ($request->hasFile($field)) {
                $oldPath = Setting::get($field);
                if (! empty($oldPath) && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }

                $path = $request->file($field)->store('settings', 'public');
                Setting::set($field, $path);
            }
        }

        return redirect()
            ->route('admin.settings.site')
            ->with('success', 'Branding settings updated successfully.');
    }
}
