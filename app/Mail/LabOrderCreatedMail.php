<?php

namespace App\Mail;

use App\Models\LabOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LabOrderCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public LabOrder $labOrder;
    public ?string $pdfPath;

    /**
     * Create a new message instance.
     */
    public function __construct(LabOrder $labOrder)
    {
        $this->labOrder = $labOrder;
        $this->pdfPath = $this->generateLabOrderPDF();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $patientUser = $this->labOrder->patient?->user;
        $doctorUser = $this->labOrder->doctor?->user;
        $doctorName = $doctorUser ? 'Dr. ' . $doctorUser->name : 'Your Doctor';

        $recipientEmail = $patientUser?->email;
        $recipientName = $patientUser?->name;

        return new Envelope(
            to: $recipientEmail ? [new Address($recipientEmail, $recipientName)] : [],
            subject: 'New Laboratory Test Order from ' . $doctorName . ' - ' . ($this->labOrder->reference_number ?: 'Lab Order'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.lab_order_created',
            with: [
                'labOrder' => $this->labOrder,
                'patient' => $this->labOrder->patient,
                'doctor' => $this->labOrder->doctor,
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
                ->as('Lab-Order-' . ($this->labOrder->reference_number ?: $this->labOrder->id) . '.pdf')
                ->withMime('application/pdf'),
        ];
    }

    /**
     * Generate PDF lab order requisition and return the file path.
     */
    private function generateLabOrderPDF(): ?string
    {
        try {
            $this->labOrder->loadMissing(['items', 'patient.user', 'doctor.user', 'laboratory']);

            $pdf = Pdf::loadView('lab_orders.report', [
                'labOrder' => $this->labOrder,
                'patient' => $this->labOrder->patient,
                'doctor' => $this->labOrder->doctor,
            ]);

            $filename = 'lab-order-' . ($this->labOrder->reference_number ?: $this->labOrder->id) . '.pdf';
            $path = storage_path('app/lab_orders/' . $filename);

            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            $pdf->save($path);

            return $path;
        } catch (\Throwable $e) {
            Log::error('Lab Order PDF generation failed: ' . $e->getMessage());
            return null;
        }
    }
}
