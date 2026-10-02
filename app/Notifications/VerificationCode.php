<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerificationCode extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $code,
        public string $purpose = 'verification',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = 'Your Inea Scents verification code';
        if (app()->environment('local')) {
            $subject = '[LOCAL] '.$subject;
        }

        return (new MailMessage)
            ->subject($subject)
            ->view('mail.verification-code', [
                'code' => $this->code,
                'purpose' => $this->purpose,
            ]);
    }
}
