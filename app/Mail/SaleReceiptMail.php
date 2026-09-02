<?php

namespace App\Mail;

use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SaleReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Sale $sale) {}

    public function build(): self
    {
        return $this->subject("Receipt #{$this->sale->receipt_no}")
                    ->view('sales.receipt-email');
    }
}
