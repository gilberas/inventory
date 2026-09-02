<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FinancialReportReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $report,
        private readonly string $startDate,
        private readonly string $endDate,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'report'     => $this->report,
            'start_date' => $this->startDate,
            'end_date'   => $this->endDate,
            'message'    => "Your {$this->report} report for {$this->startDate} – {$this->endDate} is ready.",
        ];
    }
}
