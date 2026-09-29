<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class CustomVerifyEmail extends BaseVerifyEmail
{
    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);
        $siteName = Setting::getSiteName();
        $logoUrl = Setting::getLogo('normal');

        return (new MailMessage)
            ->subject('Verify Email Address - ' . $siteName)
            ->view('emails.verify-email', [
                'url' => $verificationUrl,
                'user' => $notifiable,
                'siteName' => $siteName,
                'logoUrl' => $logoUrl,
            ]);
    }
}
