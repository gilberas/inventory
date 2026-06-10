<?php

namespace App\Notifications;

use App\Models\PurchaseRequisition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequisitionSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly PurchaseRequisition $requisition) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url("/requisitions/{$this->requisition->id}");

        return (new MailMessage)
            ->subject("Action Required — Requisition #{$this->requisition->id} Awaits Approval")
            ->greeting("Hello {$notifiable->name},")
            ->line("A new purchase requisition (#**{$this->requisition->id}**) has been submitted and requires your approval.")
            ->action('Review & Approve', $url)
            ->line('Please review the items and approve, request revision, or reject as appropriate.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'            => 'requisition_submitted',
            'requisition_id'  => $this->requisition->id,
            'requested_by'    => $this->requisition->requested_by,
            'message'         => "Purchase requisition #{$this->requisition->id} requires your approval.",
            'action_url'      => "/requisitions/{$this->requisition->id}",
        ];
    }
}
