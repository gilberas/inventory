<?php

namespace App\Console\Commands;

use App\Models\ReportSchedule;
use App\Notifications\ReportReadyNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('reports:send-scheduled')]
#[Description('Dispatch due scheduled report notifications — runs daily via scheduler')]
class SendScheduledReportsCommand extends Command
{
    public function handle(): int
    {
        $due = ReportSchedule::withoutGlobalScopes()
            ->where('is_active', true)
            ->get()
            ->filter(fn (ReportSchedule $s) => $s->isDue());

        $this->info("Found {$due->count()} due scheduled report(s).");

        foreach ($due as $schedule) {
            // Queue-only per Hard Rule §3
            Notification::route('mail', $schedule->email)->queue(
                new ReportReadyNotification(
                    "{$schedule->report_type}-scheduled.xlsx",
                    $schedule->report_type
                )
            );

            $schedule->update(['last_sent_at' => now()]);
            $this->line("  Queued: {$schedule->report_type} → {$schedule->email}");
        }

        return Command::SUCCESS;
    }
}
