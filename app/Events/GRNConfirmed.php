<?php

namespace App\Events;

use App\Models\GoodsReceivedNote;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GRNConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly GoodsReceivedNote $grn) {}
}
