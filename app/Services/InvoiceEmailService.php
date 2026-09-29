<?php

namespace App\Services;

use App\Models\Invoice;
use App\Mail\InvoiceMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class InvoiceEmailService
{
    /**
     * Send invoice email to patient if email is verified.
     * Prevents duplicate sends and handles errors gracefully.
     *
     * @param Invoice $invoice
     * @return bool Returns true if email was sent or already sent, false if failed or skipped
     */
    public static function sendInvoiceEmail(Invoice $invoice): bool
    {
        try {
            // Get patient user to check email verification
            $patient = $invoice->patient;
            if (!$patient) {
                Log::warning("Invoice {$invoice->invoice_number}: Patient not found");
                return false;
            }

            $user = $patient->user;
            if (!$user) {
                Log::warning("Invoice {$invoice->invoice_number}: Patient user not found");
                return false;
            }

            // Check if email is verified
            if (!$user->email_verified_at) {
                Log::info(
                    "Invoice {$invoice->invoice_number}: Email not verified for {$user->email}. " .
                    "Invoice email not sent."
                );
                return false;
            }

            // Prevent duplicate email sends
            if ($invoice->email_sent) {
                Log::info("Invoice {$invoice->invoice_number}: Already emailed. Skipping duplicate send.");
                return true;
            }

            // Send the email
            Mail::send(new InvoiceMail($invoice));

            // Update invoice to mark email as sent
            $invoice->update([
                'email_sent' => true,
                'emailed_at' => now(),
            ]);

            Log::info(
                "Invoice {$invoice->invoice_number}: Successfully sent to {$user->email}"
            );

            return true;

        } catch (\Exception $e) {
            Log::error(
                "Invoice {$invoice->invoice_number}: Failed to send email. " .
                "Error: " . $e->getMessage()
            );

            return false;
        }
    }

    /**
     * Check if an invoice can be emailed (email verified and not already sent).
     *
     * @param Invoice $invoice
     * @return bool
     */
    public static function canEmailInvoice(Invoice $invoice): bool
    {
        $patient = $invoice->patient;
        if (!$patient) {
            return false;
        }

        $user = $patient->user;
        if (!$user || !$user->email_verified_at) {
            return false;
        }

        return !$invoice->email_sent;
    }
}
