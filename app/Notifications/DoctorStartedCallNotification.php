<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DoctorStartedCallNotification extends Notification
{
    use Queueable;

    public Appointment $appointment;

    /**
     * Create a new notification instance.
     */
    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $doctorName = optional(optional($this->appointment->doctor)->user)->name ?? 'Your Doctor';
        $siteName = Setting::getSiteName();

        return (new MailMessage)
            ->subject('Video Consultation Started - ' . $siteName)
            ->line("Dr. {$doctorName} has started your video consultation.")
            ->action('Join Video Consultation', route('appointments.call', ['id' => $this->appointment->id]))
            ->line('Thank you for using ' . $siteName . '!');
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $doctorName = optional(optional($this->appointment->doctor)->user)->name ?? 'Your Doctor';

        return [
            'type' => 'doctor_started_call',
            'appointment_id' => $this->appointment->id,
            'doctor_name' => $doctorName,
            'message' => "Dr. {$doctorName} has started your appointment.",
            'join_url' => route('appointments.call', ['id' => $this->appointment->id]),
            'started_at' => now()->toIso8601String(),
        ];
    }
}
