<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $filename,
        private readonly string $reportType,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Your Report Is Ready')
            ->line("Your {$this->reportType} report ({$this->filename}) has been generated.")
            ->line('You can download it from the Reports section.');
    }
}
