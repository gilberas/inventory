<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Notifications\ProductImportCompleted;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private int    $tenantId,
        private int    $userId,
        private string $storagePath,
    ) {}

    public function handle(): void
    {
        $fullPath = Storage::path($this->storagePath);

        if (!file_exists($fullPath)) {
            return;
        }

        ['created' => $created, 'updated' => $updated, 'errors' => $errors] =
            $this->processFile($fullPath);

        $user = User::find($this->userId);
        if ($user) {
            $user->notify(new ProductImportCompleted($created, $updated, $errors));
        }

        Storage::delete($this->storagePath);
    }

    public function processFile(string $path): array
    {
        $handle  = fopen($path, 'r');
        $headers = array_map('trim', fgetcsv($handle) ?: []);
        $created = 0;
        $updated = 0;
        $errors  = [];
        $row     = 1;

        // Temporarily authenticate as the user so TenantModel scoping works
        $user = User::find($this->userId);
        if ($user) {
            Auth::setUser($user);
        }

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            $record = array_combine($headers, array_pad($data, count($headers), ''));

            try {
                $result = $this->processRow($record, $row);
                if ($result === 'created') $created++;
                if ($result === 'updated') $updated++;
            } catch (\Throwable $e) {
                $errors[] = "Row {$row}: {$e->getMessage()}";
            }
        }

        fclose($handle);
        return compact('created', 'updated', 'errors');
    }

    private function processRow(array $record, int $rowNum): string
    {
        $name = trim($record['name'] ?? '');
        if ($name === '') {
            throw new \InvalidArgumentException('name is required');
        }

        $costPrice = filter_var(trim($record['cost_price'] ?? ''), FILTER_VALIDATE_FLOAT);
        if ($costPrice === false) {
            throw new \InvalidArgumentException('cost_price must be numeric');
        }

        $sellingPrice = filter_var(trim($record['selling_price'] ?? ''), FILTER_VALIDATE_FLOAT);
        if ($sellingPrice === false) {
            throw new \InvalidArgumentException('selling_price must be numeric');
        }

        $categoryId = null;
        if (!empty($record['category_name'])) {
            $cat = DB::table('product_categories')
                ->where('name', trim($record['category_name']))
                ->first();
            $categoryId = $cat?->id;
        }

        $brandId = null;
        if (!empty($record['brand_name'])) {
            $brand   = DB::table('brands')->where('name', trim($record['brand_name']))->first();
            $brandId = $brand?->id;
        }

        $sku = trim($record['sku'] ?? '');

        $payload = [
            'name'          => $name,
            'cost_price'    => $costPrice,
            'selling_price' => $sellingPrice,
            'category_id'   => $categoryId,
            'brand_id'      => $brandId,
            'barcode'       => trim($record['barcode'] ?? '') ?: null,
            'reorder_level' => (int) ($record['reorder_level'] ?? 0),
            'unit_of_measure' => trim($record['unit_of_measure'] ?? 'unit') ?: 'unit',
            'tax_rate'      => (float) ($record['tax_rate'] ?? 18),
            'status'        => trim($record['status'] ?? 'active'),
            'track_expiry'  => false,
            'track_batch'   => false,
        ];

        if ($sku !== '') {
            $existing = Product::withoutGlobalScopes()
                ->where('tenant_id', $this->tenantId)
                ->where('sku', $sku)
                ->first();

            if ($existing) {
                $existing->update($payload);
                return 'updated';
            }

            $payload['sku'] = $sku;
        }

        Product::create($payload);
        return 'created';
    }
}
