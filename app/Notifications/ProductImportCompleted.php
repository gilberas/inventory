<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductImportCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private int   $created,
        private int   $updated,
        private array $errors,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'product_import_completed',
            'created' => $this->created,
            'updated' => $this->updated,
            'failed'  => count($this->errors),
            'errors'  => array_slice($this->errors, 0, 10),
            'message' => "Import complete: {$this->created} created, {$this->updated} updated, " . count($this->errors) . ' failed.',
        ];
    }
}
