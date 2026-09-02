<?php

namespace App\Jobs;

use App\Notifications\FinancialReportReady;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateFinancialReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int    $tenantId,
        private readonly ?int   $branchId,
        private readonly string $report,
        private readonly string $startDate,
        private readonly string $endDate,
        private readonly int    $userId,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        // Notify user when done (Hard Rule §3: queued notification)
        $user->notify(new FinancialReportReady(
            $this->report,
            $this->startDate,
            $this->endDate,
        ));
    }
}
