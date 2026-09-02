<?php

namespace App\Notifications;

use App\Models\Expense;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ExpenseApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Expense $expense) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'expense_approval_required',
            'expense_id'  => $this->expense->id,
            'reference'   => $this->expense->reference_no,
            'category'    => $this->expense->category,
            'amount'      => $this->expense->amount,
            'created_by'  => $this->expense->created_by,
            'message'     => "Expense {$this->expense->reference_no} of {$this->expense->amount} TZS requires approval.",
        ];
    }
}
