<?php

namespace App\Mail;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invoice;
    public $appointment;
    public $patient;
    public $doctor;
    public $pdfPath;

    /**
     * Create a new message instance.
     */
    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
        $this->appointment = $invoice->appointment;
        $this->patient = $invoice->patient;
        $this->doctor = $this->appointment?->doctor;
        
        // Generate PDF path
        $this->pdfPath = $this->generateInvoicePDF();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $recipientEmail = optional(optional($this->patient)->user)->email;
        $recipientName = optional(optional($this->patient)->user)->name;

        return new Envelope(
            to: $recipientEmail ? [new Address($recipientEmail, $recipientName)] : [],
            subject: 'Invoice #' . $this->invoice->invoice_number . ' - Healthcare Platform',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
            with: [
                'invoice' => $this->invoice,
                'appointment' => $this->appointment,
                'patient' => $this->patient,
                'doctor' => $this->doctor,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        if (!$this->pdfPath || !file_exists($this->pdfPath)) {
            return [];
        }

        return [
            Attachment::fromPath($this->pdfPath)
                ->as('invoice-' . $this->invoice->invoice_number . '.pdf')
                ->withMime('application/pdf'),
        ];
    }

    /**
     * Generate PDF invoice and return the file path.
     */
    private function generateInvoicePDF(): string
    {
        try {
            $pdf = Pdf::loadView('invoices.pdf', [
                'invoice' => $this->invoice,
                'appointment' => $this->appointment,
                'patient' => $this->patient,
                'doctor' => $this->doctor,
            ]);

            $filename = 'invoice-' . $this->invoice->invoice_number . '.pdf';
            $path = storage_path('app/invoices/' . $filename);

            // Create invoices directory if it doesn't exist
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            $pdf->save($path);

            return $path;
        } catch (\Exception $e) {
            Log::error('PDF generation failed: ' . $e->getMessage());
            return '';
        }
    }
}
