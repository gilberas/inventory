<?php

namespace App\Notifications;

use App\Models\SupplierInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class InvoiceDiscrepancy extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly SupplierInvoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'            => 'invoice_discrepancy',
            'invoice_id'      => $this->invoice->id,
            'invoice_number'  => $this->invoice->invoice_number,
            'invoice_amount'  => $this->invoice->amount,
            'message'         => "Invoice #{$this->invoice->invoice_number} has a discrepancy of more than 1% against the GRN total.",
        ];
    }
}
