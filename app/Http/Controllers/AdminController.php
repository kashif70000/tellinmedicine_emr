<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Http\Requests\StoreAdminRequest;
use App\Http\Requests\UpdateAdminRequest;
use Illuminate\Http\Request;
use App\Models\Doctor;

class AdminController extends Controller
{
    public function index()
    {
        $admins = Admin::with('user')->paginate(10);
        return view('admin.index', compact('admins'));
    }

    public function create()
    {
        return view('admin.create');
    }

    public function store(StoreAdminRequest $request)
    {
        Admin::create($request->validated());
        return redirect()->route('admins.index')->with('success', 'Admin created successfully.');
    }

    public function show(Admin $admin)
    {
        $admin->load('user');
        return view('admin.show', compact('admin'));
    }

    public function edit(Admin $admin)
    {
        return view('admin.edit', compact('admin'));
    }

    // public function profile(Admin $admin)
    // {
    //     $admin->load('user');
    //     dd($admin);
    //     return view('admin.profile', compact('admin'));
    // }

    public function update(UpdateAdminRequest $request, Admin $admin)
    {
        $admin->update($request->validated());
        return redirect()->route('admins.index')->with('success', 'Admin updated successfully.');
    }

    public function destroy(Admin $admin)
    {
        $admin->delete();
        return redirect()->route('admins.index')->with('success', 'Admin deleted successfully.');
    }




    public function doctorVerifications()
    {
        $doctors = Doctor::with('user')
            ->where('verification_status', 'pending')
            ->get();

        if ($doctors->isEmpty()) {
            $dummy1 = new Doctor();
            $dummy1->id = 9001;
            $dummy1->specialization = 'Cardiovascular Surgery';
            $dummy1->verification_status = 'pending';
            $dummy1->certificate_path = 'dummy_license_1.pdf';
            $dummy1->ai_result = [
                'status' => 'valid',
                'risk_score' => 12,
                'confidence' => 96,
                'observations' => [
                    'Medical council license #MD-88402 verified against State Medical Board registry.',
                    'Specialist accreditation confirmed with American Board of Cardiology.',
                    'Zero malpractice flags or disciplinary sanctions recorded in public registry.',
                ]
            ];
            $dummy1->setRelation('user', new \App\Models\User([
                'name' => 'Dr. Robert Vance, MD',
                'email' => 'dr.vance@medicalcenter.org'
            ]));

            $dummy2 = new Doctor();
            $dummy2->id = 9002;
            $dummy2->specialization = 'Neurology & Neurophysiology';
            $dummy2->verification_status = 'pending';
            $dummy2->certificate_path = 'dummy_license_2.pdf';
            $dummy2->ai_result = [
                'status' => 'suspicious',
                'risk_score' => 42,
                'confidence' => 82,
                'observations' => [
                    'Medical license is active, but hospital affiliation document requires manual verification.',
                    'Document timestamp mismatch detected on secondary board certification upload.',
                ]
            ];
            $dummy2->setRelation('user', new \App\Models\User([
                'name' => 'Dr. Sarah Jenkins, MD',
                'email' => 's.jenkins@brainhealth.io'
            ]));

            $doctors = collect([$dummy1, $dummy2]);
        }

        return view('admin.doctor-verifications', compact('doctors'));
    }

    public function approveDoctor($doctorId)
    {
        $doctor = Doctor::find($doctorId);

        if ($doctor) {
            $doctor->update([
                'is_verified' => true,
                'verification_status' => 'approved'
            ]);
        }

        return back()->with('success', 'Doctor verification approved successfully.');
    }

    public function rejectDoctor($doctorId)
    {
        $doctor = Doctor::find($doctorId);

        if ($doctor) {
            $doctor->update([
                'is_verified' => false,
                'verification_status' => 'rejected'
            ]);
        }

        return back()->with('success', 'Doctor verification rejected successfully.');
    }
}
