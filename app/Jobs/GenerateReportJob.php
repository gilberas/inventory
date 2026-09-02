<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $reportType,
        private readonly array  $params,
        private readonly int    $userId,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $tenantId = $user->tenant_id;
        $filename = "{$this->reportType}-" . date('Ymd-His') . ".xlsx";
        $tmpFile  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

        $writer = new XlsxWriter();
        $writer->openToFile($tmpFile);
        $writer->addRow(Row::fromValues([ucwords(str_replace('-', ' ', $this->reportType))  . ' Report']));
        $writer->addRow(Row::fromValues(['Generated: ' . now()->format('d M Y H:i')]));
        $writer->addRow(Row::fromValues([]));

        $this->writeRows($writer, $tenantId);

        $writer->close();

        // Queue-only notification per Hard Rule §3
        Notification::route('mail', $user->email)->queue(
            new \App\Notifications\ReportReadyNotification($filename, $this->reportType)
        );

        @unlink($tmpFile);
    }

    private function writeRows(XlsxWriter $writer, int $tenantId): void
    {
        match ($this->reportType) {
            'product-performance' => $this->writeProductPerformance($writer, $tenantId),
            default               => $writer->addRow(Row::fromValues(['Report data not available'])),
        };
    }

    private function writeProductPerformance(XlsxWriter $writer, int $tenantId): void
    {
        $writer->addRow(Row::fromValues(['Product', 'SKU', 'Units Sold', 'Revenue', 'COGS', 'Margin%']));

        DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->whereNull('sales.deleted_at')
            ->selectRaw('products.name, products.sku, SUM(sale_items.qty) as units_sold, SUM(sale_items.subtotal) as revenue, SUM(sale_items.qty * sale_items.cost_price) as cogs')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('revenue')
            ->chunk(500, function ($chunk) use ($writer) {
                foreach ($chunk as $r) {
                    $margin = $r->revenue > 0 ? round(($r->revenue - $r->cogs) / $r->revenue * 100, 1) : 0;
                    $writer->addRow(Row::fromValues([$r->name, $r->sku, $r->units_sold, $r->revenue, $r->cogs, $margin . '%']));
                }
            });
    }
}
