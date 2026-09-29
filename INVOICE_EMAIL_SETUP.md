# Invoice Email Implementation - Complete Setup Guide

## 📋 Overview

This document provides complete instructions for implementing automatic invoice email sending in your Laravel telemedicine platform. The system is simple, beginner-friendly, and follows clean Laravel best practices.

**Key Features:**
- ✅ Automatic invoice email after successful payment
- ✅ Email verification checks (only verified emails)
- ✅ Duplicate prevention (won't resend same invoice)
- ✅ PDF invoice attachment
- ✅ Professional clean email template
- ✅ Error handling (won't crash app)
- ✅ No queues needed (simple synchronous)

---

## 🔧 Setup Instructions

### 1. Install DOMPDF

```bash
composer require barryvdh/laravel-dompdf
```

✅ Already completed in the project

### 2. Run Database Migration

```bash
php artisan migrate
```

**What it does:**
- Adds `email_sent` column (boolean, tracks if email was sent)
- Adds `emailed_at` column (timestamp, when email was sent)

**Migration Location:**
- `database/migrations/2026_05_10_000001_add_email_tracking_to_invoices_table.php`

### 3. Configure Mail Settings

Update your `.env` file with Gmail SMTP configuration:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password-16-chars
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="Healthcare Platform"
```

### 4. Get Gmail App Password

**Why not use regular password?**
- Gmail requires app-specific passwords for security
- Regular password won't work with SMTP

**Steps:**
1. Go to https://myaccount.google.com
2. Enable 2-Factor Authentication (if not already enabled)
3. Click "Security" in left sidebar
4. Find "App passwords" (appears after 2FA is enabled)
5. Select "Mail" and "Windows Computer"
6. Google generates a 16-character password
7. Copy it (ignore spaces)
8. Paste into MAIL_PASSWORD in .env

---

## 📧 Email Verification Rule

**ONLY send invoice if:** `$user->email_verified_at IS NOT NULL`

**This means:**

| User Type | Email Verified? | Invoice Email? | Notes |
|-----------|-----------------|----------------|-------|
| Google OAuth | ✅ YES | ✅ Send | Auto-verified on login |
| Facebook OAuth | ✅ YES | ✅ Send | Auto-verified on login |
| Email signup | ❌ NO | ❌ Skip | User must verify first |
| Email signup | ✅ YES | ✅ Send | After email verification |

**Important:**
- System won't crash if email unverified
- Invoice still created, just not emailed
- Message logged: "Email not verified, invoice email not sent"
- When user verifies email later, they can request invoice resend

---

## 🔄 Payment Flow (What Happens)

1. Patient clicks "Pay Now" for approved appointment
2. Patient enters Stripe card details
3. Payment processes in Stripe
4. Frontend calls `/appointments/payment/confirm`
5. Backend verifies payment succeeded
6. ✅ Appointment marked as "paid"
7. ✅ Payment record updated
8. ✅ Invoice created automatically
9. **NEW** ✅ Check: Is patient's email verified?
10. **NEW** ✅ If YES: Generate PDF invoice
11. **NEW** ✅ If YES: Send invoice email
12. **NEW** ✅ If YES: Mark `email_sent = true`
13. ✅ Return success response
14. Frontend shows: "Payment successful. Invoice has been sent to your email."

---

## 📁 Files Created & Modified

### Created Files

#### 1. **app/Mail/InvoiceMail.php**
- Mailable class that handles email composition
- Generates PDF invoice
- Attaches PDF to email

#### 2. **app/Services/InvoiceEmailService.php**
- Service class with invoice email logic
- Email verification check
- Duplicate prevention
- Error handling

#### 3. **resources/views/emails/invoice.blade.php**
- Professional HTML email template
- Displays invoice details
- Shows appointment info
- Professional footer

#### 4. **resources/views/invoices/pdf.blade.php**
- Professional PDF invoice template
- Clean formatting
- Appointment details
- Payment summary
- Professional branding

#### 5. **database/migrations/2026_05_10_000001_add_email_tracking_to_invoices_table.php**
- Adds email tracking columns
- Safe migration with hasColumn checks

### Modified Files

#### 1. **app/Models/Invoice.php**
```php
// Added to fillable
'email_sent',
'emailed_at',

// Added to casts
'email_sent' => 'boolean',
'emailed_at' => 'datetime',
```

#### 2. **app/Http/Controllers/AppointmentController.php**
```php
// Added import
use App\Services\InvoiceEmailService;

// Modified confirmPayment() method
// Now calls: InvoiceEmailService::sendInvoiceEmail($invoice)
```

---

## 🧪 Testing

### Test 1: Manual Email Send

```bash
php artisan tinker
```

```php
$invoice = \App\Models\Invoice::first();
\App\Services\InvoiceEmailService::sendInvoiceEmail($invoice);
```

**Expected output:**
- Log entry in `storage/logs/laravel.log`
- Email in your inbox (if verified)

### Test 2: Check Email Status

```php
$invoice = \App\Models\Invoice::first();
echo $invoice->email_sent; // true or false
echo $invoice->emailed_at; // timestamp or null
```

### Test 3: Full Payment Flow

1. Book an appointment as patient
2. Doctor approves appointment
3. Go to appointment details
4. Click "Pay Now"
5. Enter test Stripe card: `4242 4242 4242 4242`
6. Expiry: any future date
7. CVC: any 3 digits
8. Click "Pay"
9. Check your email for invoice

### Test 4: Check Logs

```bash
tail -f storage/logs/laravel.log
```

You should see:
```
[timestamp] local.INFO: Invoice INV-202605-00001: Successfully sent to patient@example.com
```

---

## 🔐 Safety & Error Handling

### What Happens If...

| Scenario | What Happens | Error? |
|----------|--------------|--------|
| Email not verified | Invoice email skipped | ❌ No - logged as info |
| Email already sent | Duplicate prevented | ❌ No - skipped safely |
| Mail server down | Error logged | ❌ No - app continues |
| PDF generation fails | Email sent without PDF | ❌ No - logged as warning |
| Invalid email address | Skipped safely | ❌ No - logged as warning |

**Key:** The system never crashes - it logs and continues.

---

## 📊 Database Schema

### invoices table

```sql
ALTER TABLE invoices ADD COLUMN email_sent BOOLEAN DEFAULT FALSE;
ALTER TABLE invoices ADD COLUMN emailed_at TIMESTAMP NULL;
```

**Query to check email status:**

```sql
SELECT 
    invoice_number, 
    total_amount, 
    status, 
    email_sent, 
    emailed_at,
    created_at
FROM invoices
ORDER BY created_at DESC;
```

---

## 🎯 Key Implementation Details

### InvoiceEmailService.php

This service class handles all email logic:

```php
// Send invoice email (with all checks)
InvoiceEmailService::sendInvoiceEmail($invoice);

// Check if can email invoice
InvoiceEmailService::canEmailInvoice($invoice);
```

**What sendInvoiceEmail() does:**

1. ✅ Gets patient user
2. ✅ Checks email_verified_at IS NOT NULL
3. ✅ Prevents duplicate sends (checks email_sent flag)
4. ✅ Generates PDF invoice
5. ✅ Sends email via Mail facade
6. ✅ Updates email_sent = true
7. ✅ Updates emailed_at = now()
8. ✅ Logs success/error
9. ✅ Returns true/false

### InvoiceMail.php

This Mailable class:

1. ✅ Receives Invoice model
2. ✅ Loads related data (appointment, patient, doctor)
3. ✅ Generates PDF in storage/app/invoices/
4. ✅ Attaches PDF to email
5. ✅ Sets email subject to invoice number
6. ✅ Uses invoice.blade.php template
7. ✅ Handles PDF generation errors gracefully

---

## 📧 Email Template Customization

### Location
`resources/views/emails/invoice.blade.php`

### Variables Available
- `$invoice` - Invoice model
- `$appointment` - Appointment model
- `$patient` - Patient model
- `$doctor` - Doctor model

### Customization Examples

**Change from name:**
```php
// In config/mail.php or .env
MAIL_FROM_NAME="Your Clinic Name"
```

**Add company logo:**
```blade
@component('mail::message')
    @slot('header')
        ![Logo]({{ asset('images/logo.png') }})
    @endslot
    ...
@endcomponent
```

---

## 🔍 Monitoring & Debugging

### Check Logs

```bash
grep "Invoice" storage/logs/laravel.log
```

### View Recent Emails

```php
$invoices = \App\Models\Invoice::whereNotNull('emailed_at')
    ->latest('emailed_at')
    ->limit(10)
    ->get();
```

### Debug Mail Configuration

```bash
php artisan tinker
```

```php
$config = config('mail');
dd($config);
```

### Test Mail Connection

```php
Mail::raw('Test', function ($m) {
    $m->to('test@example.com')->subject('Test');
});
```

---

## ⚠️ Troubleshooting

### Issue: "SMTP connection failed"

**Solution:**
- Check MAIL_HOST=smtp.gmail.com (not mail.gmail.com)
- Check MAIL_PORT=587 (not 465 or 25)
- Check MAIL_ENCRYPTION=tls (not ssl)

### Issue: "Authentication failed"

**Solution:**
- Verify Gmail app password (16 characters)
- Check 2FA enabled in Gmail
- Regenerate app password if old

### Issue: "Emails not sending"

**Solution:**
1. Check logs: `tail storage/logs/laravel.log`
2. Verify email_verified_at: `SELECT email_verified_at FROM users WHERE id=?`
3. Test manually: `php artisan tinker` then `InvoiceEmailService::sendInvoiceEmail($invoice)`

### Issue: "PDF not attaching"

**Solution:**
- Create directory: `mkdir -p storage/app/invoices`
- Fix permissions: `chmod 755 storage/app/invoices`
- Check dompdf: `php artisan tinker` then `\PDF::loadView('invoices.pdf')`

---

## 🚀 Performance Tips

### Current Performance
- Email generation: 1-2 seconds (includes PDF)
- Synchronous (no queueing)
- Suitable for low-volume clinics

### For High Volume (Future)

Use Laravel queues:

```php
// In InvoiceEmailService
InvoiceEmailService::dispatch($invoice)->delay(now()->addSeconds(5));
```

---

## 📚 References

### Related Files
- [Invoice Model](app/Models/Invoice.php)
- [Appointment Controller](app/Http/Controllers/AppointmentController.php)
- [Invoice Controller](app/Http/Controllers/InvoiceController.php)
- [User Model](app/Models/User.php)

### Laravel Documentation
- [Mail](https://laravel.com/docs/mail)
- [Mailables](https://laravel.com/docs/mail#mailable-objects)
- [DOMPDF](https://github.com/barryvdh/laravel-dompdf)

---

## ✅ Verification Checklist

After setup, verify:

- [ ] `.env` updated with Gmail credentials
- [ ] Migration run: `php artisan migrate`
- [ ] dompdf installed: `composer show barryvdh/laravel-dompdf`
- [ ] Files created: `InvoiceMail.php`, `InvoiceEmailService.php`
- [ ] Templates created: `invoice.blade.php`, `pdf.blade.php`
- [ ] Controller updated: `AppointmentController.php`
- [ ] Model updated: `Invoice.php`
- [ ] Test payment works
- [ ] Invoice email received (if verified)
- [ ] PDF attached to email
- [ ] Logs show success message

---

## 🎓 Learning Resources

### Understanding the Flow

1. **Payment Success** → `AppointmentController::confirmPayment()`
2. **Invoice Creation** → `InvoiceController::createFromPayment()`
3. **Email Sending** → `InvoiceEmailService::sendInvoiceEmail()`
4. **Email Composition** → `InvoiceMail` Mailable
5. **Template Rendering** → `emails/invoice.blade.php`
6. **PDF Generation** → `invoices/pdf.blade.php`

### Best Practices Used

- ✅ Separation of concerns (Service class)
- ✅ Error handling (try/catch, logging)
- ✅ Idempotent operations (duplicate prevention)
- ✅ Clean code (readable, documented)
- ✅ Database migrations (safe, reversible)
- ✅ Model relationships (Invoice → Patient → User)

---

**Created:** May 10, 2026  
**System:** Laravel 11 Telemedicine Platform  
**Status:** Production Ready
