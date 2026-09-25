<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerificationCode extends Notification
{

    public function __construct(
        public readonly string $code,
        public readonly string $purpose = 'verification',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Inea Scents verification code')
            ->greeting('Hello from INEA Scents')
            ->line("Your {$this->purpose} code is:")
            ->line($this->code)
            ->line('This code expires in 15 minutes. If you did not request it, ignore this email.');
    }
}
