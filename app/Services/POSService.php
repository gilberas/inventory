<?php

namespace App\Services;

use App\Events\SaleCompleted;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;

class POSService
{
    public function __construct(
        private InventoryService $inventoryService,
        private MpesaService $mpesaService,
    ) {}

    /**
     * Atomically process a POS sale: items → inventory → payment → complete.
     * Hard Rule §2: everything in a single DB::transaction().
     *
     * @param  array<int, array{product_id:int, qty:float, unit_price:float, cost_price?:float, discount?:float}> $items
     * @param  array<int, array{method:string, amount:float, reference?:string}>  $splitPayments  (only used when $paymentMethod === 'split')
     */
    public function processSale(
        array   $items,
        string  $paymentMethod,
        int     $warehouseId,
        float   $discount       = 0.0,
        float   $tax            = 0.0,
        ?int    $customerId     = null,
        array   $splitPayments  = [],
        ?string $mpesaPhone     = null,
        ?float  $amountTendered = null,
        ?int    $posSessionId   = null,
    ): Sale {
        return DB::transaction(function () use (
            $items, $paymentMethod, $warehouseId,
            $discount, $tax, $customerId, $splitPayments, $mpesaPhone, $amountTendered, $posSessionId
        ) {
            $itemsTotal = collect($items)->sum(
                fn($i) => (float) $i['qty'] * (float) $i['unit_price'] - (float) ($i['discount'] ?? 0)
            );
            $grandTotal = max(0.0, $itemsTotal - $discount + $tax);

            // 0. Credit limit check — before any write
            if ($paymentMethod === 'credit' && $customerId) {
                $customer = Customer::lockForUpdate()->find($customerId);
                if ($customer && !$customer->canPurchaseOnCredit($grandTotal)) {
                    $available = max(0, (float) $customer->credit_limit - (float) $customer->balance);
                    throw new \DomainException(
                        "Credit limit exceeded. Available credit: {$available} TZS."
                    );
                }
            }

            // 1. Create Sale (pending)
            $sale = Sale::create([
                'cashier_id'     => auth()->id(),
                'customer_id'    => $customerId,
                'warehouse_id'   => $warehouseId,
                'pos_session_id' => $posSessionId,
                'total'          => $itemsTotal,
                'discount'       => $discount,
                'tax'            => $tax,
                'grand_total'    => $grandTotal,
                'payment_method' => $paymentMethod,
                'status'         => Sale::STATUS_PENDING,
            ]);

            // 2. Per item: SaleItem + stockOut (throws InsufficientStockException on shortage)
            foreach ($items as $item) {
                $sale->items()->create([
                    'product_id' => $item['product_id'],
                    'qty'        => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'cost_price' => $item['cost_price'] ?? 0,
                    'discount'   => $item['discount'] ?? 0,
                    'subtotal'   => (float) $item['qty'] * (float) $item['unit_price'] - (float) ($item['discount'] ?? 0),
                ]);

                $this->inventoryService->stockOut(
                    productId:  (int) $item['product_id'],
                    warehouseId: $warehouseId,
                    qty:        (float) $item['qty'],
                    refType:    'sale',
                    refId:      $sale->id,
                    userId:     auth()->id(),
                    notes:      "POS sale #{$sale->id}",
                );
            }

            // 3. Create Payment record(s) and determine final sale status
            $saleStatus = Sale::STATUS_COMPLETED;

            if ($paymentMethod === 'split') {
                foreach ($splitPayments as $p) {
                    $pStatus = ($p['method'] === 'mpesa') ? Payment::STATUS_PENDING : Payment::STATUS_COMPLETED;
                    if ($pStatus === Payment::STATUS_PENDING) {
                        $saleStatus = Sale::STATUS_PENDING;
                    }
                    $this->createPaymentRecord($sale->id, $p['method'], (float) $p['amount'], $pStatus, $p['reference'] ?? null);
                }
            } elseif (in_array($paymentMethod, ['mpesa', 'airtel', 'tigo', 'halo'])) {
                $gateway  = app(PaymentGatewayFactory::class)->for($paymentMethod);
                $response = $gateway->stkPush($mpesaPhone ?? '', $grandTotal);
                $this->createPaymentRecord($sale->id, $paymentMethod, $grandTotal, Payment::STATUS_PENDING, $response['CheckoutRequestID'] ?? null);
                $saleStatus = Sale::STATUS_PENDING;
            } else {
                $changeGiven = ($paymentMethod === 'cash' && $amountTendered !== null)
                    ? max(0.0, $amountTendered - $grandTotal)
                    : null;
                $notes = ($amountTendered !== null)
                    ? "Tendered: {$amountTendered}" . ($changeGiven !== null ? " | Change: {$changeGiven}" : '')
                    : null;
                $this->createPaymentRecord($sale->id, $paymentMethod, $grandTotal, Payment::STATUS_COMPLETED, null, $notes);
            }

            // 4. Mark sale completed / still pending
            $sale->update(['status' => $saleStatus]);

            // 5. If credit sale: customer owes the full amount (receivable)
            if ($paymentMethod === 'credit' && $customerId) {
                Customer::where('id', $customerId)->increment('balance', $grandTotal);
            }

            // 6. Audit log (Hard Rule §4)
            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'created',
                'model_type' => Sale::class,
                'model_id'   => $sale->id,
                'new_values' => [
                    'grand_total'    => $grandTotal,
                    'payment_method' => $paymentMethod,
                    'status'         => $saleStatus,
                ],
            ]);

            // 7. Fire SaleCompleted event
            if ($saleStatus === Sale::STATUS_COMPLETED) {
                event(new SaleCompleted($sale));
            }

            return $sale->load(['items', 'payments']);
        });
    }

    private function createPaymentRecord(int $saleId, string $method, float $amount, string $status, ?string $reference = null, ?string $notes = null): void
    {
        // Map POS method → legacy payment_method enum (CASH/MOBILE_MONEY/OTHER)
        $legacyMethod = match ($method) {
            'cash'   => 'CASH',
            'credit' => 'OTHER',
            default  => 'MOBILE_MONEY',
        };

        Payment::create([
            'tenant_id'      => auth()->user()?->tenant_id,
            'payable_type'   => Sale::class,
            'payable_id'     => $saleId,
            'amount'         => $amount,
            'method'         => $method,
            'reference'      => $reference,
            'notes'          => $notes,
            'status'         => $status,
            'payment_method' => $legacyMethod,
            'payment_date'   => now()->toDateString(),
            'created_by'     => auth()->id(),
        ]);
    }
}
