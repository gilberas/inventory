<?php

namespace App\Services;

class PaymentGatewayFactory
{
    /**
     * Return the gateway service for the given mobile-money method.
     * Airtel/Tigo/Halo use the same STK-push pattern as M-Pesa (MVP stub).
     */
    public function for(string $method): MpesaService
    {
        // MVP: all mobile-money providers share the same stub implementation.
        // In production each would have its own service class.
        return app(MpesaService::class);
    }
}
