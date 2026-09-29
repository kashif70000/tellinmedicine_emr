# Laravel Email System and Feature Flow Documentation

## Project Overview

This project is a Laravel-based Patient Doctor Management System / Healthcare Management System. It manages users, patients, doctors, admins, appointments, payments, invoices, prescriptions, ratings, video calls, doctor verification, and email-based communication such as invoice delivery and account verification.

The application follows Laravel's MVC architecture with additional service classes for business logic:

```text
Route
  -> Middleware
  -> Controller
  -> Form Request Validation
  -> Service / Domain Logic
  -> Model / Database
  -> Mail / View / Redirect / JSON Response
```

Important architectural layers used in this project:

- `routes/web.php` defines browser routes and maps URLs to controllers.
- Controllers in `app/Http/Controllers` receive requests and coordinate the feature flow.
- Form Requests in `app/Http/Requests` validate incoming user input.
- Services in `app/Services` keep reusable business logic outside controllers.
- Models in `app/Models` represent database tables and relationships.
- Policies in `app/Policies` handle authorization decisions for protected records.
- Mailables in `app/Mail` build and send email messages.
- Blade views in `resources/views` render web pages, email templates, and PDF templates.
- Configuration files in `config` read environment variables from `.env`.

The current invoice email implementation uses:

```text
Payment confirmation
  -> AppointmentController
  -> Invoice creation
  -> InvoiceEmailService
  -> InvoiceMail
  -> resources/views/emails/invoice.blade.php
  -> SMTP provider
```

## Email System Flow

Laravel email sending is usually built as a clear chain of responsibility. Each part has one job, which makes the system easier to test, debug, and maintain.

### 1. User Action Triggers Event

An email flow starts when a user performs an action in the application.

Examples:

- A patient completes a payment.
- A user registers a new account.
- A user requests a password reset.
- A user asks to resend an invoice.
- An admin approves or rejects a doctor.

Example invoice flow:

```text
Patient pays appointment invoice
  -> Payment is confirmed
  -> Invoice is created or updated
  -> Invoice email sending is triggered
```

### 2. Controller Receives Request

Routes send the request to the correct controller method.

Example route flow:

```text
POST /appointments/payment/confirm
  -> AppointmentController::confirmPayment()
```

The controller should not contain heavy business logic. A controller should:

- Receive the request.
- Validate or use a Form Request.
- Call services or models.
- Return a response.

Good controller style:

```php
public function confirmPayment(Request $request)
{
    // Validate payment data
    // Confirm payment
    // Create invoice
    // Call InvoiceEmailService
    // Return response
}
```

### 3. Validation Happens

Validation protects the application from invalid, incomplete, or unsafe input.

Validation can happen in:

- A controller using `$request->validate([...])`
- A Form Request class such as `StorePatientRequest`
- Custom validation rules

Example:

```php
$validated = $request->validate([
    'appointment_id' => ['required', 'exists:appointments,id'],
    'payment_intent_id' => ['required', 'string'],
]);
```

Benefits:

- Prevents invalid database records.
- Protects payment and email flows.
- Gives clear feedback to users.
- Keeps business logic clean.

### 4. Service Handles Logic

Services contain reusable business logic that should not live directly inside a controller.

In this project, invoice email logic lives in:

```text
app/Services/InvoiceEmailService.php
```

The service handles:

- Finding the patient and user for the invoice.
- Checking whether the user's email is verified.
- Preventing duplicate email sends.
- Sending the mailable.
- Updating `email_sent` and `emailed_at`.
- Logging success or failure.

Current invoice email service flow:

```text
InvoiceEmailService::sendInvoiceEmail($invoice)
  -> Check invoice has patient
  -> Check patient has user
  -> Check user email is verified
  -> Check invoice has not already been emailed
  -> Send InvoiceMail
  -> Mark invoice email_sent = true
  -> Save emailed_at timestamp
  -> Log result
```

### 5. Job Dispatching

Jobs are used when work should happen in the background instead of making the user wait.

Email sending is a strong candidate for queues because SMTP and PDF generation can be slow.

Current project note:

- The invoice email system currently sends email synchronously through `InvoiceEmailService`.
- `InvoiceMail` uses Laravel's `Queueable` trait, so it is ready to be used in queued mail or jobs later.
- The project queue configuration defaults to the `database` connection in `config/queue.php`.

Future queued example:

```php
SendInvoiceEmailJob::dispatch($invoice->id);
```

Or using queued mail:

```php
Mail::to($user->email)->queue(new InvoiceMail($invoice));
```

### 6. Mail Class Execution

Mail classes, also called Mailables, prepare the email message.

Current mailable:

```text
app/Mail/InvoiceMail.php
```

`InvoiceMail` is responsible for:

- Receiving the `Invoice` model.
- Loading related appointment, patient, and doctor data.
- Setting the recipient.
- Setting the subject.
- Rendering the email Blade view.
- Generating and attaching the PDF invoice.

Mailable flow:

```text
new InvoiceMail($invoice)
  -> Load invoice relationships
  -> Generate PDF invoice
  -> Build envelope
  -> Render email template
  -> Attach PDF
  -> Send through configured mailer
```

### 7. Queue Worker Processing

If email is queued, the request does not send the email immediately. Instead, Laravel stores the job and a queue worker processes it later.

Queued flow:

```text
Controller
  -> Dispatch job
  -> Store job in queue backend
  -> Return response to user
  -> Queue worker picks job
  -> Job executes mailable
  -> Email is sent
```

Run a queue worker:

```bash
php artisan queue:work
```

Run a worker for a specific queue:

```bash
php artisan queue:work --queue=emails
```

Run a worker with retry settings:

```bash
php artisan queue:work --tries=3 --timeout=90
```

### 8. SMTP, Mailtrap, or Gmail Sending

Laravel sends email through the mailer configured in `.env` and `config/mail.php`.

Common providers:

- `log`: writes email content to the log; useful for local development.
- `smtp`: sends real emails through Gmail, Mailtrap, SendGrid, Mailgun, or another SMTP server.
- `array`: stores mail in memory during tests.
- `failover`: tries one mailer and falls back to another.

Example provider flow:

```text
InvoiceMail
  -> Laravel Mail Manager
  -> SMTP transport
  -> Gmail / Mailtrap / Mail server
  -> Patient inbox
```

### 9. Success and Failure Handling

Email sending should not crash the main application flow. For example, a payment should not fail just because the mail server is temporarily unavailable.

Current invoice email handling:

- If the email is sent successfully, the invoice is updated:

```text
email_sent = true
emailed_at = current timestamp
```

- If email sending fails, the error is logged and the service returns `false`.
- If the patient email is not verified, email sending is skipped and logged.
- If the email was already sent, duplicate sending is prevented.

Recommended behavior:

```text
Business action succeeds
  -> Email attempted
  -> Email success: mark as sent
  -> Email failure: log error and allow retry
```

## Mail Configuration

Laravel mail configuration is stored in:

```text
config/mail.php
```

Environment-specific values are stored in:

```text
.env
```

Never commit real email usernames, passwords, API keys, or app passwords to Git.

### Important `.env` Mail Settings

#### MAIL_MAILER

Defines the mail transport Laravel should use.

Common values:

```env
MAIL_MAILER=log
MAIL_MAILER=smtp
MAIL_MAILER=array
MAIL_MAILER=failover
```

Use `log` locally when you do not want to send real email.

Use `smtp` when sending real email.

#### MAIL_HOST

The SMTP server host.

Examples:

```env
MAIL_HOST=smtp.mailtrap.io
MAIL_HOST=smtp.gmail.com
```

#### MAIL_PORT

The port used by the SMTP server.

Common ports:

```env
MAIL_PORT=2525
MAIL_PORT=587
MAIL_PORT=465
```

#### MAIL_USERNAME

The SMTP username.

For Mailtrap, this is provided by Mailtrap.

For Gmail, this is usually your Gmail address.

#### MAIL_PASSWORD

The SMTP password.

For Gmail, use a Gmail App Password, not the normal account password.

#### MAIL_ENCRYPTION

Defines the encryption protocol.

Common values:

```env
MAIL_ENCRYPTION=tls
MAIL_ENCRYPTION=ssl
MAIL_ENCRYPTION=null
```

Laravel 12 may also use `MAIL_SCHEME` depending on the mail configuration style, but `MAIL_ENCRYPTION` is still commonly used in many Laravel projects and SMTP examples.

#### MAIL_FROM_ADDRESS

The sender email address shown to recipients.

Example:

```env
MAIL_FROM_ADDRESS=no-reply@example.com
```

#### MAIL_FROM_NAME

The sender name shown to recipients.

Example:

```env
MAIL_FROM_NAME="Healthcare Platform"
```

### Example `.env` Configuration for Local Log Mail

Use this when developing locally and you do not want real emails to be sent.

```env
MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="no-reply@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Check emails in:

```text
storage/logs/laravel.log
```

### Example `.env` Configuration for Mailtrap

Use this for safe testing with a fake inbox.

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@example.com"
MAIL_FROM_NAME="Healthcare Platform"
```

### Example `.env` Configuration for Gmail SMTP

Use this for real email sending through Gmail.

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-16-character-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="Healthcare Platform"
```

Important Gmail notes:

- Enable 2-Factor Authentication on the Gmail account.
- Generate an App Password from the Google account security settings.
- Use the App Password in `MAIL_PASSWORD`.
- Do not use your normal Gmail login password.

After changing `.env`, clear cached configuration:

```bash
php artisan config:clear
php artisan cache:clear
```

## Laravel Components Used

### Requests

Form Request classes validate incoming data before it reaches business logic.

Project examples:

```text
app/Http/Requests/StorePatientRequest.php
app/Http/Requests/StoreAppointmentRequest.php
app/Http/Requests/StoreInvoiceRequest.php
app/Http/Requests/UpdateDoctorRequest.php
```

Responsibilities:

- Validate input.
- Authorize the request when needed.
- Keep controllers clean.
- Return user-friendly validation errors.

Example:

```php
public function rules(): array
{
    return [
        'email' => ['required', 'email'],
        'name' => ['required', 'string', 'max:255'],
    ];
}
```

### Controllers

Controllers receive web requests and coordinate application actions.

Project examples:

```text
AppointmentController
PatientController
DoctorController
InvoiceController
PaymentController
PrescriptionController
DashboardController
```

Responsibilities:

- Receive request data.
- Call validation.
- Use services and models.
- Return Blade views, redirects, or JSON responses.

### Services

Services hold business logic that may be reused across controllers.

Project example:

```text
app/Services/InvoiceEmailService.php
```

Responsibilities:

- Keep controllers thin.
- Centralize important business rules.
- Make logic easier to test.
- Make flows easier to reuse.

### Policies

Policies decide whether a user can perform an action on a model.

Project examples:

```text
app/Policies/InvoicePolicy.php
app/Policies/PrescriptionPolicy.php
```

Responsibilities:

- Protect sensitive patient and doctor data.
- Keep authorization logic separate from controllers.
- Prevent users from viewing records they do not own.

Example policy decision:

```text
Can this patient view this invoice?
Can this doctor view this prescription?
Can this admin access all records?
```

### Models

Models represent database tables and relationships.

Project examples:

```text
User
Patient
Doctor
Admin
Appointment
Payment
Invoice
Prescription
Rating
DoctorSchedule
```

Responsibilities:

- Read and write database records.
- Define relationships.
- Cast attributes.
- Protect mass assignment using `$fillable`.

Example relationships:

```text
Invoice belongs to Patient
Invoice belongs to Appointment
Appointment belongs to Doctor
Patient belongs to User
Doctor belongs to User
```

### Mailables

Mailables build email messages.

Project example:

```text
app/Mail/InvoiceMail.php
```

Responsibilities:

- Define recipients.
- Define subject.
- Render email view.
- Attach files.
- Pass data to the email template.

### Queues

Queues move slow work into the background.

Good queue candidates:

- Sending emails.
- Generating PDFs.
- Calling external APIs.
- Processing reports.
- Sending notifications.

Current project note:

- `config/queue.php` defaults to `database`.
- Invoice email is currently synchronous but can be moved to a queued job.

### Jobs

Jobs are classes that perform background tasks.

Example future job:

```text
app/Jobs/SendInvoiceEmailJob.php
```

Example command:

```bash
php artisan make:job SendInvoiceEmailJob
```

Example usage:

```php
SendInvoiceEmailJob::dispatch($invoice->id);
```

### Events

Events announce that something happened.

Examples:

```text
UserRegistered
PaymentCompleted
InvoiceCreated
DoctorApproved
```

Event flow:

```text
Payment completed
  -> Event fired
  -> Listener handles email
```

Example command:

```bash
php artisan make:event InvoiceCreated
```

### Listeners

Listeners respond to events.

Example:

```text
InvoiceCreated event
  -> SendInvoiceEmail listener
  -> Dispatch job or send mail
```

Example command:

```bash
php artisan make:listener SendInvoiceEmail --event=InvoiceCreated
```

### Notifications

Notifications are a Laravel feature for sending messages through multiple channels, such as mail, database, SMS, and Slack.

Good notification examples:

- Appointment approved.
- Appointment rejected.
- Payment received.
- Password reset.
- Email verification.

Example command:

```bash
php artisan make:notification AppointmentApprovedNotification
```

### Middleware

Middleware filters requests before they reach controllers.

Project examples:

```text
auth
throttle:10,1
```

Responsibilities:

- Require login.
- Rate limit sensitive endpoints.
- Protect CSRF requests.
- Apply request-level checks.

Example flow:

```text
Request
  -> auth middleware
  -> throttle middleware
  -> controller
```

### Config Files

Configuration files live in:

```text
config/
```

Important config files:

```text
config/mail.php
config/queue.php
config/auth.php
config/database.php
config/services.php
```

These files usually read values from `.env`:

```php
'default' => env('MAIL_MAILER', 'log'),
```

## Queue System Flow

Queues are used to delay slow work and process it in the background.

### Sync Queue

The `sync` queue runs jobs immediately during the same request.

```env
QUEUE_CONNECTION=sync
```

Flow:

```text
User request
  -> Job dispatched
  -> Job runs immediately
  -> Response waits for job
```

Use when:

- Developing locally.
- Debugging.
- The task is very fast.

Avoid for:

- Real production email sending.
- PDF generation.
- Slow external APIs.

### Database Queue

The `database` queue stores jobs in the database.

```env
QUEUE_CONNECTION=database
```

Flow:

```text
User request
  -> Job stored in jobs table
  -> Response returns quickly
  -> Queue worker reads jobs table
  -> Worker executes job
```

Create queue tables:

```bash
php artisan queue:table
php artisan queue:failed-table
php artisan migrate
```

Run worker:

```bash
php artisan queue:work
```

Use when:

- You want simple queue setup.
- Your app already uses a database.
- Email volume is moderate.

### Redis Queue

The `redis` queue stores jobs in Redis.

```env
QUEUE_CONNECTION=redis
```

Flow:

```text
User request
  -> Job pushed to Redis
  -> Worker pops job from Redis
  -> Job runs quickly
```

Use when:

- Queue volume is high.
- You need better performance.
- You run multiple workers.

### Queue Jobs

A job should perform one focused background task.

Example job responsibility:

```text
SendInvoiceEmailJob
  -> Find invoice
  -> Call InvoiceEmailService
  -> Log result
```

Example job:

```php
public function handle(): void
{
    $invoice = Invoice::findOrFail($this->invoiceId);

    InvoiceEmailService::sendInvoiceEmail($invoice);
}
```

### Failed Jobs

If a queued job fails too many times, Laravel stores it as a failed job.

View failed jobs:

```bash
php artisan queue:failed
```

Retry one failed job:

```bash
php artisan queue:retry {id}
```

Retry all failed jobs:

```bash
php artisan queue:retry all
```

Delete one failed job:

```bash
php artisan queue:forget {id}
```

Clear all failed jobs:

```bash
php artisan queue:flush
```

### Retrying Jobs

Set retry count from the command line:

```bash
php artisan queue:work --tries=3
```

Set retry timeout:

```bash
php artisan queue:work --timeout=90
```

Set backoff in a job:

```php
public int $tries = 3;

public function backoff(): array
{
    return [60, 300, 900];
}
```

This means:

```text
Attempt 1 fails
  -> Wait 60 seconds
Attempt 2 fails
  -> Wait 300 seconds
Attempt 3 fails
  -> Wait 900 seconds
```

### Queue Workers

A queue worker is a long-running process that executes jobs.

Development:

```bash
php artisan queue:work
```

Restart workers after deployment:

```bash
php artisan queue:restart
```

Production workers should be managed by Supervisor or another process manager so they restart automatically if they stop.

## File Structure

### app/Http/Controllers

Contains controller classes.

Examples:

```text
app/Http/Controllers/AppointmentController.php
app/Http/Controllers/PatientController.php
app/Http/Controllers/InvoiceController.php
app/Http/Controllers/PaymentController.php
```

Purpose:

- Receive HTTP requests.
- Coordinate validation and services.
- Return views, redirects, or JSON.

### app/Services

Contains business service classes.

Current example:

```text
app/Services/InvoiceEmailService.php
```

Purpose:

- Reusable business logic.
- Keep controllers smaller.
- Centralize email and invoice rules.

### app/Mail

Contains Laravel Mailable classes.

Current example:

```text
app/Mail/InvoiceMail.php
```

Purpose:

- Build email subject, recipients, body, and attachments.

### app/Jobs

Contains queue job classes.

This folder may be created when the first job is generated:

```bash
php artisan make:job SendInvoiceEmailJob
```

Purpose:

- Run slow work in the background.
- Retry failed tasks.
- Improve user response time.

### app/Models

Contains Eloquent models.

Examples:

```text
app/Models/User.php
app/Models/Patient.php
app/Models/Doctor.php
app/Models/Appointment.php
app/Models/Invoice.php
app/Models/Payment.php
```

Purpose:

- Represent database records.
- Define relationships.
- Define casts and fillable fields.

### config

Contains Laravel configuration files.

Examples:

```text
config/mail.php
config/queue.php
config/auth.php
config/database.php
config/services.php
```

Purpose:

- Centralize app configuration.
- Read environment values from `.env`.

### resources/views/emails

Contains email Blade templates.

Current example:

```text
resources/views/emails/invoice.blade.php
```

Purpose:

- Render HTML email content.
- Display dynamic invoice, patient, doctor, and appointment data.

### resources/views/invoices

Contains invoice PDF templates.

Current example:

```text
resources/views/invoices/pdf.blade.php
```

Purpose:

- Render invoice PDFs using DOMPDF.

### routes

Contains route definitions.

Main file:

```text
routes/web.php
```

Purpose:

- Define application URLs.
- Assign middleware.
- Map routes to controller methods.

## Authentication Flow

The project uses Laravel authentication routes through:

```php
Auth::routes();
```

It also includes custom registration pages for patients and doctors, email verification routes, social login routes, and password change routes.

### Login

Flow:

```text
User opens login page
  -> Enters email and password
  -> Laravel validates credentials
  -> Session is created
  -> User is redirected to dashboard
```

Main pieces:

```text
resources/views/auth/login.blade.php
routes/web.php
Laravel Auth controllers
DashboardController
```

### Registration

The project includes role-specific registration pages:

```text
/register/patient
/register/doctor
```

Flow:

```text
User opens registration form
  -> User submits details
  -> PatientController or DoctorController validates data
  -> User record is created
  -> Patient or Doctor profile is created
  -> Verification or next setup step happens
```

Patient route:

```text
POST /register/patient
  -> PatientController::store()
```

Doctor route:

```text
POST /register/doctor
  -> DoctorController::store()
```

### Password Reset

Laravel's auth scaffolding provides password reset routes and views.

Typical flow:

```text
User clicks forgot password
  -> Enters email
  -> Laravel sends password reset email
  -> User clicks reset link
  -> User enters new password
  -> Password is updated
```

Views:

```text
resources/views/auth/passwords/email.blade.php
resources/views/auth/passwords/reset.blade.php
```

### Email Verification

The project defines email verification routes:

```text
GET /email/verify
GET /email/verify/{id}/{hash}
POST /email/resend
```

Flow:

```text
User registers
  -> Verification email is sent
  -> User opens signed verification link
  -> VerificationController validates link
  -> users.email_verified_at is updated
```

Email verification matters for invoice emails because the current invoice email service only sends invoices to verified email addresses.

```text
email_verified_at is null
  -> Invoice email is skipped

email_verified_at has timestamp
  -> Invoice email can be sent
```

## Example Email Sending Flow

### Example: User Registers

```text
User registers
  -> Registration controller validates data
  -> User model is created
  -> Registered event fires
  -> Email verification notification is sent
  -> User receives verification email
  -> User clicks verification link
  -> email_verified_at is updated
```

### Example: Event, Listener, Job, Queue, Email

This is a recommended scalable pattern for future email features:

```text
User registers
  ↓
Registered event fires
  ↓
SendEmailVerificationNotification listener runs
  ↓
Email job is dispatched
  ↓
Job is stored in queue
  ↓
Queue worker processes job
  ↓
Mail class builds message
  ↓
SMTP provider sends email
  ↓
User receives email
```

### Example: Invoice Email in This Project

Current flow:

```text
Patient pays for appointment
  ↓
AppointmentController confirms payment
  ↓
Payment and appointment records are updated
  ↓
Invoice is created
  ↓
InvoiceEmailService::sendInvoiceEmail($invoice)
  ↓
Service checks patient user exists
  ↓
Service checks email_verified_at
  ↓
Service checks email_sent flag
  ↓
InvoiceMail generates email and PDF
  ↓
Laravel Mail sends through configured mailer
  ↓
Invoice record is marked as emailed
```

Recommended future queued flow:

```text
Patient pays for appointment
  ↓
Invoice is created
  ↓
InvoiceCreated event fires
  ↓
SendInvoiceEmail listener dispatches job
  ↓
SendInvoiceEmailJob is stored in jobs table
  ↓
Queue worker processes job
  ↓
InvoiceEmailService sends InvoiceMail
  ↓
Email sent through SMTP
```

## Error Handling

### Failed Jobs

When queued jobs fail, Laravel records them so developers can inspect and retry them.

Commands:

```bash
php artisan queue:failed
php artisan queue:retry {id}
php artisan queue:retry all
php artisan queue:forget {id}
php artisan queue:flush
```

Best practice:

```text
Temporary SMTP error
  -> Retry job
Permanent invalid email
  -> Log and mark as failed
```

### Logging

Laravel logs are stored in:

```text
storage/logs/laravel.log
```

Use logs for:

- Email sent successfully.
- Email skipped because user is unverified.
- SMTP failures.
- PDF generation failures.
- Missing model relationships.

Example:

```php
Log::info("Invoice {$invoice->invoice_number}: Successfully sent.");
Log::warning("Invoice {$invoice->invoice_number}: Patient not found.");
Log::error("Invoice {$invoice->invoice_number}: Failed to send email.");
```

### Try/Catch

Use `try/catch` around operations that can fail because of external services.

Examples:

- SMTP sending.
- PDF generation.
- Payment gateway API calls.
- Third-party integrations.

Example:

```php
try {
    Mail::send(new InvoiceMail($invoice));
} catch (\Throwable $e) {
    Log::error('Invoice email failed: ' . $e->getMessage());
}
```

### Retry Logic

Retry logic is useful for temporary failures.

Examples:

- Mail server temporarily unavailable.
- Network timeout.
- Third-party provider rate limit.

Queue retry command:

```bash
php artisan queue:work --tries=3 --backoff=60
```

Job-level retry:

```php
public int $tries = 3;
public int $timeout = 90;
```

## Security Practices

### Validation

Always validate incoming data before using it.

Use:

- Form Requests.
- Controller validation.
- Database constraints.

Example:

```php
'email' => ['required', 'email', 'max:255']
```

### CSRF Protection

Laravel protects web forms using CSRF tokens.

Blade form example:

```blade
<form method="POST" action="/example">
    @csrf
    <button type="submit">Submit</button>
</form>
```

Without a valid CSRF token, Laravel rejects the request.

### Sanitization

Protect output by escaping user-generated content in Blade.

Safe:

```blade
{{ $user->name }}
```

Avoid unless content is trusted:

```blade
{!! $user->name !!}
```

### Authorization

Use policies and middleware to protect sensitive data.

Examples:

```text
Only a patient should see their own invoice.
Only a doctor should see prescriptions for their appointments.
Only an admin should approve doctors.
```

Policy usage:

```php
$this->authorize('view', $invoice);
```

### Rate Limiting

Rate limiting protects sensitive endpoints from abuse.

Project examples:

```text
throttle:10,1
```

This limits requests to 10 attempts per minute.

Good endpoints for rate limiting:

- Login.
- Password reset.
- Payment intent creation.
- Email resend.
- Registration payment verification.

### Environment Security

Protect `.env` because it contains secrets.

Examples:

```text
APP_KEY
DB_PASSWORD
MAIL_PASSWORD
STRIPE_SECRET
GOOGLE_CLIENT_SECRET
FACEBOOK_CLIENT_SECRET
```

Rules:

- Do not commit `.env`.
- Use `.env.example` for safe sample values.
- Rotate leaked credentials immediately.
- Use strong app passwords and API keys.

## Performance Optimization

### Queues

Use queues for slow tasks.

Good queue candidates:

- Email sending.
- PDF generation.
- Payment receipt processing.
- AI certificate analysis.
- External API calls.

Benefit:

```text
Without queue:
User waits for email sending

With queue:
User gets response quickly, email sends in background
```

### Caching

Cache expensive or frequently used data.

Examples:

- Dashboard counts.
- Doctor lists.
- Static settings.
- Role permissions.

Commands:

```bash
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Eager Loading

Use eager loading to avoid N+1 database queries.

Less efficient:

```php
$invoices = Invoice::all();

foreach ($invoices as $invoice) {
    echo $invoice->patient->user->email;
}
```

Better:

```php
$invoices = Invoice::with(['patient.user', 'appointment.doctor'])->get();
```

### Async Email Sending

For better user experience, send emails asynchronously.

Recommended:

```text
Create invoice
  -> Dispatch email job
  -> Return success response
  -> Worker sends email
```

## Best Practices

### Thin Controllers

Controllers should coordinate work, not contain all business logic.

Good:

```text
Controller validates request
  -> Calls service
  -> Returns response
```

Avoid:

```text
Controller validates request
  -> Processes payment
  -> Creates invoice
  -> Generates PDF
  -> Sends email
  -> Logs everything
```

### Reusable Services

Move repeated business logic into services.

Examples:

```text
InvoiceEmailService
PaymentService
AppointmentService
DoctorVerificationService
```

Benefits:

- Easier testing.
- Cleaner controllers.
- Reusable logic.
- Better separation of concerns.

### Policies for Authorization

Use policies when access depends on the model and the current user.

Examples:

```text
InvoicePolicy
PrescriptionPolicy
```

Good authorization flow:

```text
Controller receives request
  -> Policy checks permission
  -> Controller continues only if allowed
```

### Request Validation

Use Form Requests for larger forms.

Examples:

```text
StorePatientRequest
StoreDoctorRequest
StoreAppointmentRequest
UpdateInvoiceRequest
```

Benefits:

- Keeps validation consistent.
- Makes controllers easier to read.
- Reuses validation rules.

### Proper Folder Structure

Keep files in predictable Laravel locations:

```text
Controllers -> app/Http/Controllers
Requests    -> app/Http/Requests
Services    -> app/Services
Models      -> app/Models
Mailables   -> app/Mail
Jobs        -> app/Jobs
Policies    -> app/Policies
Views       -> resources/views
Routes      -> routes
Config      -> config
```

### Idempotency

Email sending should prevent duplicate sends.

Current invoice email example:

```text
email_sent = false
  -> send email
  -> mark email_sent = true

email_sent = true
  -> skip duplicate email
```

This is important for:

- Payment callbacks.
- Browser refreshes.
- Retry jobs.
- Manual resend tools.

## Deployment Notes

### Queue Workers in Production

If using queues in production, a queue worker must always be running.

Start manually:

```bash
php artisan queue:work --tries=3 --timeout=90
```

Restart after deployment:

```bash
php artisan queue:restart
```

Without a running worker:

```text
Jobs are stored
  -> But never processed
  -> Emails are not sent
```

### Supervisor Setup

Supervisor keeps queue workers alive on Linux servers.

Example Supervisor config:

```ini
[program:pdms-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/pdms/artisan queue:work --sleep=3 --tries=3 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/pdms/storage/logs/worker.log
stopwaitsecs=3600
```

Reload Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start pdms-worker:*
```

### Environment Security

Production `.env` should be protected.

Checklist:

- `APP_ENV=production`
- `APP_DEBUG=false`
- Strong `APP_KEY`
- Real database credentials
- Real mail credentials
- Secure Stripe keys
- Correct `APP_URL`
- `.env` not publicly accessible
- `.env` not committed to Git

### Cache Clearing and Optimization

After changing `.env` or config files:

```bash
php artisan config:clear
php artisan cache:clear
```

For production optimization:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

After deployment:

```bash
php artisan migrate --force
php artisan queue:restart
```

## Useful Artisan Commands

### Queues

Create jobs table:

```bash
php artisan queue:table
```

Create failed jobs table:

```bash
php artisan queue:failed-table
```

Run migrations:

```bash
php artisan migrate
```

Start queue worker:

```bash
php artisan queue:work
```

Start queue worker with retries:

```bash
php artisan queue:work --tries=3 --timeout=90
```

Restart queue workers:

```bash
php artisan queue:restart
```

List failed jobs:

```bash
php artisan queue:failed
```

Retry failed jobs:

```bash
php artisan queue:retry all
```

Clear failed jobs:

```bash
php artisan queue:flush
```

### Mail

Create a mailable:

```bash
php artisan make:mail InvoiceMail
```

Create a notification:

```bash
php artisan make:notification AppointmentApprovedNotification
```

Test mail in Tinker:

```bash
php artisan tinker
```

Example Tinker mail test:

```php
Mail::raw('Test email from Laravel', function ($message) {
    $message->to('test@example.com')->subject('Laravel Mail Test');
});
```

Test current invoice email service:

```php
$invoice = App\Models\Invoice::latest()->first();
App\Services\InvoiceEmailService::sendInvoiceEmail($invoice);
```

### Cache

Clear application cache:

```bash
php artisan cache:clear
```

Clear config cache:

```bash
php artisan config:clear
```

Cache config:

```bash
php artisan config:cache
```

Clear route cache:

```bash
php artisan route:clear
```

Cache routes:

```bash
php artisan route:cache
```

Clear compiled views:

```bash
php artisan view:clear
```

Cache views:

```bash
php artisan view:cache
```

Clear all optimized files:

```bash
php artisan optimize:clear
```

### Migrations

Run migrations:

```bash
php artisan migrate
```

Run migrations in production:

```bash
php artisan migrate --force
```

Rollback last migration batch:

```bash
php artisan migrate:rollback
```

Check migration status:

```bash
php artisan migrate:status
```

Create migration:

```bash
php artisan make:migration add_email_tracking_to_invoices_table
```

### Seeders

Run all seeders:

```bash
php artisan db:seed
```

Run migrations and seeders together:

```bash
php artisan migrate --seed
```

Create seeder:

```bash
php artisan make:seeder UserSeeder
```

Run a specific seeder:

```bash
php artisan db:seed --class=UserSeeder
```

### Development and Debugging

Start local server:

```bash
php artisan serve
```

Open Tinker:

```bash
php artisan tinker
```

List routes:

```bash
php artisan route:list
```

Run tests:

```bash
php artisan test
```

Follow logs with Laravel Pail:

```bash
php artisan pail
```

## Conclusion

This Laravel project uses a clean MVC structure supported by Form Requests, Services, Policies, Models, Mailables, Blade templates, and configuration files. The email system is centered around Laravel's mail infrastructure and currently sends invoice emails through `InvoiceEmailService` and `InvoiceMail`.

The complete email flow can be summarized as:

```text
User action
  -> Route
  -> Middleware
  -> Controller
  -> Validation
  -> Service
  -> Mailable or queued job
  -> Email template
  -> SMTP provider
  -> Success or failure logging
```

For small traffic, synchronous email sending is simple and easy to understand. For production growth, the recommended next step is moving email sending and PDF generation into queued jobs so users receive fast responses while Laravel processes email in the background.

By following Laravel best practices such as thin controllers, request validation, reusable services, policies, queues, logging, and secure environment configuration, the project remains professional, maintainable, scalable, and beginner-friendly for future developers.
