<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly PurchaseOrder $purchaseOrder) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Purchase Order #{$this->purchaseOrder->reference_no}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'purchases.mail',
            with: ['po' => $this->purchaseOrder],
        );
    }
}
