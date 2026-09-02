<?php

namespace App\Services;

class MpesaService
{
    /**
     * Initiate an M-Pesa STK push to the customer's phone.
     * Returns the Daraja API response array.
     */
    public function stkPush(string $phone, float $amount): array
    {
        // MVP stub — replace with real Daraja API call in production
        return [
            'CheckoutRequestID' => 'ws_CO_' . now()->format('YmdHis') . rand(1000, 9999),
            'ResponseCode'      => '0',
            'CustomerMessage'   => 'Success. Request accepted for processing',
        ];
    }

    /**
     * Query the status of an STK push by CheckoutRequestID.
     */
    public function queryStatus(string $checkoutRequestId): string
    {
        // MVP stub
        return 'pending';
    }
}
