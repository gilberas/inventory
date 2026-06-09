<?php

namespace App\Services;

use App\Events\LowStockDetected;
use App\Events\OutOfStockDetected;
use App\Exceptions\InsufficientStockException;
use App\Models\ActivityLog;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    // ── Public API ────────────────────────────────────────────────────────────

    public function stockIn(
        int $productId,
        int $warehouseId,
        float $qty,
        float $unitCost,
        string $refType,
        int $refId,
        int $userId,
        ?string $notes = null
    ): void {
        DB::transaction(function () use ($productId, $warehouseId, $qty, $unitCost, $refType, $refId, $userId, $notes) {
            $inv = Inventory::firstOrCreate(
                ['product_id' => $productId, 'warehouse_id' => $warehouseId],
                ['quantity' => 0, 'valuation_method' => $this->defaultMethod(), 'unit_cost' => $unitCost]
            );

            $oldQty  = (float) $inv->quantity;
            $newQty  = $oldQty + $qty;

            // Weighted average recalculates on every stockIn; FIFO/LIFO track cost per movement layer
            $newCost = $inv->valuation_method === 'weighted_avg' && $newQty > 0
                ? (($oldQty * (float) $inv->unit_cost) + ($qty * $unitCost)) / $newQty
                : (float) $inv->unit_cost;

            $inv->update([
                'quantity'     => $newQty,
                'unit_cost'    => $newCost,
                'last_updated' => now(),
            ]);

            InventoryMovement::create([
                'product_id'     => $productId,
                'warehouse_id'   => $warehouseId,
                'type'           => 'stock_in',
                'qty'            => $qty,
                'balance_after'  => $newQty,
                'unit_cost'      => $unitCost,
                'reference_type' => $refType,
                'reference_id'   => $refId,
                'user_id'        => $userId,
                'notes'          => $notes,
                'created_at'     => now(),
            ]);
        });
    }

    public function stockOut(
        int $productId,
        int $warehouseId,
        float $qty,
        string $refType,
        int $refId,
        int $userId,
        ?string $notes = null
    ): void {
        DB::transaction(function () use ($productId, $warehouseId, $qty, $refType, $refId, $userId, $notes) {
            // Pessimistic lock prevents overselling under concurrent requests
            $inv = Inventory::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();

            $available = $inv ? (float) $inv->quantity : 0.0;

            if ($available < $qty) {
                throw new InsufficientStockException($productId, $warehouseId, $qty, $available);
            }

            $newQty = $available - $qty;

            $inv->update([
                'quantity'     => $newQty,
                'last_updated' => now(),
            ]);

            InventoryMovement::create([
                'product_id'     => $productId,
                'warehouse_id'   => $warehouseId,
                'type'           => 'stock_out',
                'qty'            => -$qty,           // negative = stock leaving
                'balance_after'  => $newQty,
                'unit_cost'      => null,
                'reference_type' => $refType,
                'reference_id'   => $refId,
                'user_id'        => $userId,
                'notes'          => $notes,
                'created_at'     => now(),
            ]);

            $this->fireStockAlerts($productId, $warehouseId, $newQty);
        });
    }

    public function adjust(
        int $productId,
        int $warehouseId,
        float $qtyChange,
        string $reason,
        int $userId
    ): void {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => ['Adjustment reason is required.']]);
        }

        DB::transaction(function () use ($productId, $warehouseId, $qtyChange, $reason, $userId) {
            $inv = Inventory::firstOrCreate(
                ['product_id' => $productId, 'warehouse_id' => $warehouseId],
                ['quantity' => 0, 'valuation_method' => $this->defaultMethod(), 'unit_cost' => 0]
            );

            $newQty = max(0.0, (float) $inv->quantity + $qtyChange);

            $inv->update([
                'quantity'     => $newQty,
                'last_updated' => now(),
            ]);

            InventoryMovement::create([
                'product_id'     => $productId,
                'warehouse_id'   => $warehouseId,
                'type'           => 'adjustment',
                'qty'            => $qtyChange,
                'balance_after'  => $newQty,
                'unit_cost'      => null,
                'reference_type' => null,
                'reference_id'   => null,
                'user_id'        => $userId,
                'notes'          => $reason,
                'created_at'     => now(),
            ]);

            ActivityLog::create([
                'user_id'    => $userId,
                'action'     => 'stock_adjustment',
                'model_type' => Inventory::class,
                'model_id'   => $inv->id,
                'new_values' => ['qty_change' => $qtyChange, 'reason' => $reason, 'new_qty' => $newQty],
                'ip_address' => request()?->ip(),
            ]);
        });
    }

    public function getBalance(int $productId, int $warehouseId): float
    {
        return (float) (Inventory::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->value('quantity') ?? 0.0);
    }

    public function recalculateValuation(int $productId, int $warehouseId): float
    {
        $inv = Inventory::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if (!$inv || (float) $inv->quantity <= 0) {
            return 0.0;
        }

        return match ($inv->valuation_method) {
            'fifo'  => $this->fifoValuation($productId, $warehouseId),
            'lifo'  => $this->lifoValuation($productId, $warehouseId),
            default => (float) $inv->quantity * (float) $inv->unit_cost,
        };
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function fifoValuation(int $productId, int $warehouseId): float
    {
        // Build cost layers oldest-first
        $layers = InventoryMovement::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('type', 'stock_in')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['qty', 'unit_cost'])
            ->map(fn ($m) => ['qty' => abs((float) $m->qty), 'cost' => (float) $m->unit_cost])
            ->all();

        $consumed = (float) InventoryMovement::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->whereIn('type', InventoryMovement::OUT_TYPES)
            ->sum(DB::raw('ABS(qty)'));

        // Consume from oldest layers (FIFO)
        foreach ($layers as &$layer) {
            if ($consumed <= 0) break;
            $take           = min($layer['qty'], $consumed);
            $layer['qty']  -= $take;
            $consumed      -= $take;
        }
        unset($layer);

        return (float) array_sum(array_map(fn ($l) => $l['qty'] * $l['cost'], $layers));
    }

    private function lifoValuation(int $productId, int $warehouseId): float
    {
        // Build cost layers newest-first
        $layers = InventoryMovement::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('type', 'stock_in')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['qty', 'unit_cost'])
            ->map(fn ($m) => ['qty' => abs((float) $m->qty), 'cost' => (float) $m->unit_cost])
            ->all();

        $consumed = (float) InventoryMovement::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->whereIn('type', InventoryMovement::OUT_TYPES)
            ->sum(DB::raw('ABS(qty)'));

        foreach ($layers as &$layer) {
            if ($consumed <= 0) break;
            $take           = min($layer['qty'], $consumed);
            $layer['qty']  -= $take;
            $consumed      -= $take;
        }
        unset($layer);

        return (float) array_sum(array_map(fn ($l) => $l['qty'] * $l['cost'], $layers));
    }

    private function fireStockAlerts(int $productId, int $warehouseId, float $newQty): void
    {
        $product   = Product::find($productId);
        $warehouse = Warehouse::find($warehouseId);

        if (!$product || !$warehouse) return;

        if ($newQty <= 0) {
            event(new OutOfStockDetected($product, $warehouse));
        } elseif ((float) $product->reorder_level > 0 && $newQty <= (float) $product->reorder_level) {
            event(new LowStockDetected($product, $warehouse, $newQty));
        }
    }

    private function defaultMethod(): string
    {
        return data_get(auth()->user()?->tenant?->config, 'valuation_method', 'weighted_avg');
    }
}
