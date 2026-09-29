<?php

namespace App\Mail;

use App\Models\Prescription;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PrescriptionCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Prescription $prescription;
    public ?string $pdfPath;

    /**
     * Create a new message instance.
     */
    public function __construct(Prescription $prescription)
    {
        $this->prescription = $prescription;
        $this->pdfPath = $this->generatePrescriptionPDF();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $patientUser = $this->prescription->patient?->user;
        $doctorUser = $this->prescription->doctor?->user;
        $doctorName = $doctorUser ? 'Dr. ' . $doctorUser->name : 'Your Doctor';

        $recipientEmail = $patientUser?->email;
        $recipientName = $patientUser?->name;

        return new Envelope(
            to: $recipientEmail ? [new Address($recipientEmail, $recipientName)] : [],
            subject: 'New Prescription from ' . $doctorName . ' - ' . ($this->prescription->reference_number ?: 'Prescription'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.prescription_created',
            with: [
                'prescription' => $this->prescription,
                'patient' => $this->prescription->patient,
                'doctor' => $this->prescription->doctor,
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
                ->as('Prescription-' . ($this->prescription->reference_number ?: $this->prescription->id) . '.pdf')
                ->withMime('application/pdf'),
        ];
    }

    /**
     * Generate PDF prescription and return the file path.
     */
    private function generatePrescriptionPDF(): ?string
    {
        try {
            $pdf = Pdf::loadView('prescription.show', [
                'prescription' => $this->prescription,
            ]);

            $filename = 'prescription-' . ($this->prescription->reference_number ?: $this->prescription->id) . '.pdf';
            $path = storage_path('app/prescriptions/' . $filename);

            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            $pdf->save($path);

            return $path;
        } catch (\Throwable $e) {
            Log::error('Prescription PDF generation failed: ' . $e->getMessage());
            return null;
        }
    }
}
