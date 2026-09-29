# Hospital Management System

A complete **Laravel 11** Hospital Management System with modern UI, resource-based controllers, and a clean modular architecture.

## Features

✅ **Complete CRUD Operations** for all modules  
✅ **Eight Core Modules**: Admins, Doctors, Patients, Appointments, Prescriptions, Payments, Invoices, Ratings  
✅ **Database Relationships** with foreign key constraints  
✅ **Form Validation** using Laravel Form Requests  
✅ **Responsive Bootstrap 5 UI** with modern styling  
✅ **Pagination** on all index pages  
✅ **Search & Filtering** for efficient data management  
✅ **Seeders** with realistic sample data for all tables  
✅ **Resource Routes** for RESTful API structure  
✅ **Flash Messages** for user feedback  

---

## Installation

### 1. Clone the Repository
```bash
cd d:\laragon\www\project-name
```

### 2. Install Dependencies
```bash
composer install
npm install
```

### 3. Environment Setup
```bash
cp .env.example .env
php artisan key:generate
```

Configure your `.env` file with database credentials:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Run Migrations
```bash
php artisan migrate
```

### 5. Seed Database
```bash
php artisan db:seed
```

This populates the database with:
- **1 Admin** (admin@example.com)
- **5 Doctors** (with various specializations)
- **10 Patients** (with different details)
- **20 Appointments** (with various statuses)
- **15 Prescriptions** (with medicines JSON)
- **20 Payments** (with different methods)
- **10 Invoices** (for billing)
- **25 Ratings** (doctor reviews)

### 6. Start the Development Server
```bash
php artisan serve
```

Visit: **http://localhost:8000**

---

## Folder Structure

```
resources/views/
├── layouts/
│   └── app.blade.php          # Main layout with sidebar navigation
├── admin/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── edit.blade.php
│   └── show.blade.php
├── doctor/                    # Similar structure
├── patient/
├── appointment/
├── prescription/
├── payment/
├── invoice/
└── rating/

app/Http/
├── Controllers/
│   ├── AdminController.php
│   ├── DoctorController.php
│   ├── PatientController.php
│   ├── AppointmentController.php
│   ├── PrescriptionController.php
│   ├── PaymentController.php
│   ├── InvoiceController.php
│   └── RatingController.php
├── Requests/
│   ├── StoreAdminRequest.php
│   ├── UpdateAdminRequest.php
│   └── ... (similar for all modules)

app/Models/
├── User.php
├── Admin.php
├── Doctor.php
├── Patient.php
├── Appointment.php
├── Prescription.php
├── Payment.php
├── Invoice.php
└── Rating.php

database/
├── migrations/
│   ├── 2026_04_09_122909_add_role_and_is_active_to_users_table.php
│   ├── 2026_04_09_122921_create_admins_table.php
│   ├── ... (all other migrations)
└── seeders/
    ├── UserSeeder.php
    ├── AppointmentSeeder.php
    ├── PrescriptionSeeder.php
    ├── PaymentSeeder.php
    ├── InvoiceSeeder.php
    ├── RatingSeeder.php
    └── DatabaseSeeder.php
```

---

## Database Schema

### Users Table
- `id` - Primary Key
- `name` - User Name
- `email` - Unique Email
- `password` - Hashed Password
- `role` - Enum (admin, doctor, patient)
- `is_active` - Boolean
- `timestamps` - Created/Updated At

### Admins Table
- `id` - Primary Key
- `user_id` - Foreign Key (Users)
- `permissions` - JSON Array
- `timestamps`

### Doctors Table
- `id` - Primary Key
- `user_id` - Foreign Key (Users)
- `specialization` - String
- `experience` - Integer (Years)
- `fees` - Decimal
- `is_verified` - Boolean
- `rating_avg` - Float (Auto-calculated)
- `timestamps`

### Patients Table
- `id` - Primary Key
- `user_id` - Foreign Key (Users)
- `age` - Integer
- `gender` - Enum (male, female, other)
- `blood_group` - String
- `is_payment_method_verified` - Boolean
- `timestamps`

### Appointments Table
- `id` - Primary Key
- `patient_id` - Foreign Key (Patients)
- `doctor_id` - Foreign Key (Doctors)
- `date` - Date
- `time` - Time
- `status` - Enum (pending, completed, cancelled)
- `notes` - Text (Nullable)
- `timestamps`

### Prescriptions Table
- `id` - Primary Key
- `appointment_id` - Foreign Key (Appointments)
- `doctor_id` - Foreign Key (Doctors)
- `patient_id` - Foreign Key (Patients)
- `notes` - Text
- `medicines` - JSON Array
- `timestamps`

### Payments Table
- `id` - Primary Key
- `appointment_id` - Foreign Key (Appointments)
- `amount` - Decimal
- `status` - Enum (paid, unpaid, failed)
- `method` - Enum (cash, card, online)
- `transaction_id` - String (Nullable)
- `timestamps`

### Invoices Table
- `id` - Primary Key
- `patient_id` - Foreign Key (Patients)
- `total_amount` - Decimal
- `issued_date` - Date
- `status` - Enum (paid, pending)
- `timestamps`

### Ratings Table
- `id` - Primary Key
- `doctor_id` - Foreign Key (Doctors)
- `patient_id` - Foreign Key (Patients)
- `rating` - Integer (1-5)
- `review` - Text (Nullable)
- `timestamps`

---

## Routes

All routes follow RESTful conventions using Laravel Resource Routes:

```php
Route::resource('admins', AdminController::class);
Route::resource('doctors', DoctorController::class);
Route::resource('patients', PatientController::class);
Route::resource('appointments', AppointmentController::class);
Route::resource('prescriptions', PrescriptionController::class);
Route::resource('payments', PaymentController::class);
Route::resource('invoices', InvoiceController::class);
Route::resource('ratings', RatingController::class);
```

This automatically creates:
- `GET /module` - List all items
- `GET /module/{id}` - View specific item
- `GET /module/create` - Show create form
- `POST /module` - Store new item
- `GET /module/{id}/edit` - Show edit form
- `PUT /module/{id}` - Update item
- `DELETE /module/{id}` - Delete item

---

## Model Relationships

```
User
├── hasOne Admin
├── hasOne Doctor
└── hasOne Patient

Doctor
├── belongsTo User
├── hasMany Appointments
├── hasMany Ratings
└── hasMany Prescriptions

Patient
├── belongsTo User
├── hasMany Appointments
├── hasMany Ratings
├── hasMany Prescriptions
└── hasMany Invoices

Appointment
├── belongsTo Patient
├── belongsTo Doctor
├── hasOne Prescription
└── hasOne Payment

Prescription
├── belongsTo Appointment
├── belongsTo Doctor
└── belongsTo Patient

Payment
└── belongsTo Appointment

Invoice
└── belongsTo Patient

Rating
├── belongsTo Doctor
└── belongsTo Patient
```

---

## Key Features Explained

### 1. **Form Validation (Form Requests)**
Each module has dedicated Form Request classes that validate incoming data:

```php
// app/Http/Requests/StoreDoctorRequest.php
public function rules(): array
{
    return [
        'user_id' => 'required|exists:users,id|unique:doctors,user_id',
        'specialization' => 'required|string|max:255',
        'experience' => 'required|integer|min:0',
        'fees' => 'required|numeric|min:0',
        'is_verified' => 'boolean',
    ];
}
```

### 2. **Automatic Rating Calculation**
When a rating is saved/deleted, the doctor's average rating is automatically updated:

```php
// app/Models/Rating.php
protected static function booted()
{
    static::saved(function ($rating) {
        $rating->doctor->update(['rating_avg' => $rating->doctor->ratings()->avg('rating')]);
    });
}
```

### 3. **Search & Filtering**
Doctors can be filtered by specialization:

```php
// DoctorController.php
public function index(Request $request)
{
    $query = Doctor::with('user');
    
    if ($request->has('search') && $request->search) {
        $query->where('specialization', 'like', '%' . $request->search . '%');
    }
    
    $doctors = $query->paginate(10);
    return view('doctor.index', compact('doctors'));
}
```

### 4. **JSON Data Storage**
Prescriptions and Permissions are stored as JSON:

```php
// Stored as JSON array
$medicines = [
    ['name' => 'Aspirin', 'dosage' => '500mg', 'frequency' => '2x daily'],
    ['name' => 'Paracetamol', 'dosage' => '650mg', 'frequency' => '3x daily'],
];

$prescription->medicines = json_encode($medicines);
```

### 5. **Responsive UI**
- Bootstrap 5 for layout and components
- Font Awesome 6 for icons
- Custom styling with modern gradient sidebar
- Mobile-responsive design

---

## Sample Data Credentials

After seeding, you can access the system with:

**Admin Account:**
- Email: `admin@example.com`
- Password: `password`

**Doctor Accounts:**
- Email: `doctor1@example.com` to `doctor5@example.com`
- Password: `password`

**Patient Accounts:**
- Email: `patient1@example.com` to `patient10@example.com`
- Password: `password`

---

## Development Commands

```bash
# Create new migration
php artisan make:migration create_table_name

# Create new model
php artisan make:model ModelName

# Create new controller
php artisan make:controller ControllerName --resource

# Create new form request
php artisan make:request StoreModelRequest

# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Seed database
php artisan db:seed

# Clear application cache
php artisan cache:clear

# Run tests
php artisan test
```

---

## Best Practices Implemented

✅ **MVC Architecture** - Clean separation of concerns  
✅ **Form Requests** - Centralized validation  
✅ **Resource Controllers** - RESTful routing  
✅ **Model Relationships** - Eloquent ORM  
✅ **JSON Casting** - Type-safe data handling  
✅ **Pagination** - Efficient data loading  
✅ **Flash Messages** - User feedback  
✅ **Foreign Keys** - Database integrity  
✅ **Responsive UI** - Mobile-friendly design  
✅ **Seeders** - Easy data population  

---

## Troubleshooting

### Migration Errors
If you encounter migration issues:
```bash
php artisan migrate:refresh --seed
```

### Permission Errors
Ensure proper permissions on `storage/` and `bootstrap/cache/`:
```bash
chmod -R 775 storage bootstrap/cache
```

### Database Connection
Verify `.env` database credentials match your setup.

---

## Support

For issues or questions, refer to Laravel documentation:
- **Laravel Docs**: https://laravel.com/docs
- **Eloquent ORM**: https://laravel.com/docs/eloquent
- **Migrations**: https://laravel.com/docs/migrations

---

## License

This Hospital Management System is open-source and available for educational purposes.

---

**Happy Coding! 🚀**
