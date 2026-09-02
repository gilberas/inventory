<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Product $product,
        public readonly Warehouse $warehouse,
        public readonly float $currentQty,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $actionUrl = url('/requisitions/create?product_id=' . $this->product->id);

        return (new MailMessage)
            ->subject("Low Stock Alert: {$this->product->name} — Action Required")
            ->greeting('Low Stock Alert')
            ->line("**Product:** {$this->product->name} (SKU: {$this->product->sku})")
            ->line("**Current Stock:** {$this->currentQty} {$this->product->unit?->abbreviation}")
            ->line("**Reorder Level:** {$this->product->reorder_level}")
            ->line("**Branch:** {$this->warehouse->name}")
            ->action('Create Purchase Requisition →', $actionUrl)
            ->line('Please create a purchase requisition to restock this item.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'low_stock',
            'product_id'    => $this->product->id,
            'product_name'  => $this->product->name,
            'current_qty'   => $this->currentQty,
            'reorder_level' => $this->product->reorder_level,
            'branch_id'     => $this->warehouse->id,
            'branch_name'   => $this->warehouse->name,
            'action_url'    => '/requisitions/create?product_id=' . $this->product->id,
        ];
    }
}
