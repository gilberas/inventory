<?php

namespace App\Notifications;

use App\Models\PurchaseRequisition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequisitionStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PurchaseRequisition $requisition,
        public readonly string $newStatus,
        public readonly ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $statusLabel = ucfirst(str_replace('_', ' ', $this->newStatus));
        $url         = url("/requisitions/{$this->requisition->id}");

        $mail = (new MailMessage)
            ->subject("Requisition #{$this->requisition->id} — {$statusLabel}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your purchase requisition #{$this->requisition->id} has been **{$statusLabel}**.");

        if ($this->reason) {
            $mail->line("Reason: {$this->reason}");
        }

        $mail->action("View Requisition", $url)
             ->line('You can review the details and take any necessary action via the link above.');

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'requisition_status_changed',
            'requisition_id' => $this->requisition->id,
            'status'         => $this->newStatus,
            'reason'         => $this->reason,
            'message'        => "Your purchase requisition #{$this->requisition->id} has been {$this->newStatus}.",
            'action_url'     => "/requisitions/{$this->requisition->id}",
        ];
    }
}
