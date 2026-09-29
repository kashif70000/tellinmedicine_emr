# PDMS Project Features

## Overview
This project is a Laravel-based patient-doctor management system with support for authentication, appointment booking, payments, telehealth video calls, prescriptions, invoices, and doctor verification.

## Core Functional Areas

### Authentication & User Management
- Laravel built-in authentication (`Auth::routes()`)
- Email verification flow
- Patient and doctor registration pages
- Social login support via Google and Facebook
- Password change feature
- User profile view and edit
- Account deletion guidance via data deletion page

### User Roles
- Admin
- Doctor
- Patient

### Registration & Verification
- Separate registration flows for patients and doctors
- Doctor verification workflow with admin approval/rejection
- AI doctor certificate analysis endpoint

### Appointment Management
- Book, approve, reject appointments
- Doctor-specific appointment list and patient list
- Doctor schedules and schedule retrieval by doctor
- Admin appointments dashboard

### Payments & Stripe Integration
- Create Stripe payment intents
- Register payment intents for patient registration
- Appointment payment confirmation
- Refund appointments
- Payment verification page for patients

### Video Call & Telehealth
- Agora video call integration for doctor and patient
- Start, join, and end call flows
- Call status polling endpoint
- Live prescription creation during the appointment call

### Prescriptions & Invoices
- Immutable prescription records (view-only after creation)
- Immutable invoice records (view-only after creation)
- Patient/admin/doctor scoped aliases for prescriptions and invoices
- Invoice generation likely supported via PDF tools

### Ratings & Reviews
- Appointment-based rating submission
- Rating display per appointment
- Doctor review listing

### Administrative Features
- Admin dashboard
- Admin profile redirect
- Doctor verification management
- Admin-accessible prescriptions and invoices views

### Static Pages
- Privacy policy
- Terms page
- Data deletion instructions

## Data Models
The project includes the following core models:
- `Admin`
- `Doctor`
- `Patient`
- `Appointment`
- `DoctorSchedule`
- `Invoice`
- `Payment`
- `Prescription`
- `Rating`
- `User`

## Main Controllers
- `AdminController`
- `AgoraCallController`
- `AppointmentController`
- `DoctorController`
- `DoctorScheduleController`
- `InvoiceController`
- `PatientController`
- `PaymentController`
- `PrescriptionController`
- `ProfileController`
- `RatingController`
- `DashboardController`
- `ChangePasswordController`
- `App\Http\Controllers\Auth\SocialController`
- `App\Http\Controllers\Auth\VerificationController`
- `App\Http\Controllers\AI\DoctorVerificationAIController`

## Frontend & Asset Tooling
- Vite build system
- Tailwind CSS
- Bootstrap 5
- Sass
- Axios
- Laravel Vite plugin

## Key Composer Packages
- `laravel/framework` ^12.0
- `barryvdh/laravel-dompdf` ^3.1
- `doctrine/dbal` ^4.4
- `laravel/socialite` ^5.26
- `stripe/stripe-php` ^20.0
- `laravel/ui` ^4.6
- `laravel/tinker` ^2.10.1

## Dev Tooling
- `fakerphp/faker`
- `laravel/pail`
- `laravel/pint`
- `laravel/sail`
- `mockery/mockery`
- `nunomaduro/collision`
- `phpunit/phpunit`

## Routes / Features Summary
- `/dashboard`
- `/doctor/dashboard`
- `/admin/dashboard`
- `/register/patient`
- `/register/doctor`
- `/auth/google` and `/auth/facebook`
- `/password/change`
- `/profile/{uuid}` and edit/update endpoints
- `/patient/payment-verification`
- `/admin/doctor-verifications`
- `/appointments/book`
- `/doctor/appointments`, `/doctor/my-patients`
- `/doctor/schedule`
- `/appointments/{id}/start-call`, `/appointments/{id}/join-call`, `/appointments/{id}/end-call`
- `/appointments/{id}/prescription`
- `/appointments/{id}/rate`
- `/doctors/{id}/reviews`

## Notes
This file summarizes available features and major components from the current repository.
If you want, I can also create a separate `README` section or a `feature-checklist.md` for deployment and setup details.