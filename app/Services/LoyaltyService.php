<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    private const DEFAULT_EARN_RATE   = 1;   // 1 point per 1 000 TZS
    private const DEFAULT_REDEEM_RATE = 1;   // 1 point = 1 TZS

    public function earn(Sale $sale): void
    {
        if (!$sale->customer_id) {
            return;
        }

        $customer = Customer::find($sale->customer_id);
        if (!$customer) {
            return;
        }

        $earnRate = (float) data_get($customer->tenant?->config, 'loyalty_earn_rate', self::DEFAULT_EARN_RATE);
        $points   = (int) floor((float) $sale->grand_total / 1000 * $earnRate);

        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($customer, $sale, $points) {
            // Idempotency guard: one earn per sale, handles duplicate event firing
            if ($sale->id && LoyaltyTransaction::where('sale_id', $sale->id)
                    ->where('type', LoyaltyTransaction::TYPE_EARN)
                    ->exists()) {
                return;
            }

            $customer->increment('loyalty_points', $points);
            $newBalance = $customer->loyalty_points;

            LoyaltyTransaction::create([
                'tenant_id'    => $customer->tenant_id,
                'customer_id'  => $customer->id,
                'sale_id'      => $sale->id,
                'type'         => LoyaltyTransaction::TYPE_EARN,
                'points'       => $points,
                'balance_after' => $newBalance,
            ]);
        });
    }

    /**
     * Redeem points and return the TZS discount amount.
     *
     * @throws \DomainException when customer has fewer points than requested
     */
    public function redeem(Customer $customer, int $pointsToRedeem, ?int $saleId = null): float
    {
        if ($customer->loyalty_points < $pointsToRedeem) {
            throw new \DomainException(
                "Insufficient loyalty points. Available: {$customer->loyalty_points}, requested: {$pointsToRedeem}."
            );
        }

        $redeemRate     = (float) data_get($customer->tenant?->config, 'loyalty_redeem_rate', self::DEFAULT_REDEEM_RATE);
        $discountAmount = $pointsToRedeem * $redeemRate;

        DB::transaction(function () use ($customer, $saleId, $pointsToRedeem) {
            $customer->decrement('loyalty_points', $pointsToRedeem);
            $newBalance = $customer->loyalty_points;

            LoyaltyTransaction::create([
                'tenant_id'    => $customer->tenant_id,
                'customer_id'  => $customer->id,
                'sale_id'      => $saleId,
                'type'         => LoyaltyTransaction::TYPE_REDEEM,
                'points'       => $pointsToRedeem,
                'balance_after' => $newBalance,
            ]);
        });

        return $discountAmount;
    }
}
