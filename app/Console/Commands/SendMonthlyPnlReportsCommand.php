<?php

namespace App\Console\Commands;

use App\Jobs\SendMonthlyPnlReportJob;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SendMonthlyPnlReportsCommand extends Command
{
    protected $signature = 'reports:send-monthly-pnl';

    protected $description = 'Dispatch monthly P&L report emails to all active tenant business owners';

    public function handle(): int
    {
        $tenants = Tenant::where('status', 'active')->get();

        foreach ($tenants as $tenant) {
            SendMonthlyPnlReportJob::dispatch($tenant->id);
            $this->line("Queued P&L report for tenant: {$tenant->name}");
        }

        $this->info("Dispatched {$tenants->count()} monthly P&L report jobs.");

        return self::SUCCESS;
    }
}
