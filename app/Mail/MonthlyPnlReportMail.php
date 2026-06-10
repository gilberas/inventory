<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MonthlyPnlReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly User $owner,
        public readonly string $month,
        public readonly array $data,
        public readonly string $pdfContent,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "SmartStock — Monthly P&L Report: {$this->month} — {$this->tenant->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.monthly-pnl',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, "PnL-{$this->month}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
