<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HyperpayService
{
    protected string $base;
    protected ?string $entityId;
    protected ?string $authHeader;
    protected int $timeout;

    public function __construct()
    {
        $this->base = rtrim(config('hyperpay.base_url'), '/');
        $this->entityId = config('hyperpay.entity_id');
        $this->authHeader = config('hyperpay.authorization');
        $this->timeout = intval(config('hyperpay.timeout', 30));

        if (empty($this->authHeader)) {
            Log::warning('HyperpayService missing authorization configuration', [
                'authorization_set' => !empty($this->authHeader)
            ]);
        }
    }

    protected function headers(): array
    {
        $headers = [
            'Accept' => 'application/json',
        ];
        if (!empty($this->authHeader)) {
            $headers['Authorization'] = $this->authHeader;
        }
        return $headers;
    }

    /**
     * Prepare checkout (POST /v1/checkouts)
     * payload may include 'paymentBrand' => 'VISA'|'MASTER'|'MADA'
     */
    public function prepareCheckout(array $payload)
    {
        // pick entity id by brand if provided
        $brand = strtoupper($payload['paymentBrand'] ?? '');
        $entityId = $this->entityId;
        if ($brand === 'VISA' && config('hyperpay.visa_entity_id')) {
            $entityId = config('hyperpay.visa_entity_id');
        } elseif ($brand === 'MASTER' && config('hyperpay.master_entity_id')) {
            $entityId = config('hyperpay.master_entity_id');
        } elseif ($brand === 'MADA' && config('hyperpay.mada_entity_id')) {
            $entityId = config('hyperpay.mada_entity_id');
        }

        if (empty($entityId)) {
            throw new \RuntimeException('Hyperpay entity_id not configured for brand ' . ($brand ?: 'default'));
        }

        $payload['entityId'] = $entityId;

        Log::info('Hyperpay request payload', array_merge($payload, ['card.number' => isset($payload['card.number']) ? '***REDACTED***' : null]));

        $url = $this->base . '/v1/checkouts';
        $response = Http::withHeaders($this->headers())
                        ->timeout($this->timeout)
                        ->asForm()
                        ->post($url, $payload);

        Log::info('Hyperpay raw response', ['status' => $response->status(), 'body' => $response->body()]);

        return $response;
    }

    /**
     * 3D Secure Checkout (Hosted Payment Page)
     * Returns a redirect URL for customer authentication
     */
    public function create3DSCheckout(array $payload)
    {
        // Pick entity ID by brand if provided
        $brand = strtoupper($payload['paymentBrand'] ?? '');
        $entityId = $this->entityId;
        if ($brand === 'VISA' && config('hyperpay.visa_entity_id')) {
            $entityId = config('hyperpay.visa_entity_id');
        } elseif ($brand === 'MASTER' && config('hyperpay.master_entity_id')) {
            $entityId = config('hyperpay.master_entity_id');
        } elseif ($brand === 'MADA' && config('hyperpay.mada_entity_id')) {
            $entityId = config('hyperpay.mada_entity_id');
        }

        if (empty($entityId)) {
            throw new \RuntimeException('Hyperpay entity_id not configured for brand ' . ($brand ?: 'default'));
        }

        $payload['entityId'] = $entityId;

        Log::info('Hyperpay 3DS checkout request', [
            'amount' => $payload['amount'] ?? null,
            'currency' => $payload['currency'] ?? null,
            'brand' => $brand,
            'merchantTransactionId' => $payload['merchantTransactionId'] ?? null,
        ]);

        $url = $this->base . '/v1/checkouts';
        $response = Http::withHeaders($this->headers())
                        ->timeout($this->timeout)
                        ->asForm()
                        ->post($url, $payload);

        Log::info('Hyperpay 3DS response', ['status' => $response->status(), 'body' => $response->body()]);

        return $response;
    }

    /**
     * Direct payment (card details in payload)
     * Use with caution - requires PCI compliance
     */
    public function directPayment(array $payload)
    {
        // similar entityId selection as above
        $brand = strtoupper($payload['paymentBrand'] ?? '');
        $entityId = $this->entityId;
        if ($brand === 'VISA' && config('hyperpay.visa_entity_id')) {
            $entityId = config('hyperpay.visa_entity_id');
        } elseif ($brand === 'MASTER' && config('hyperpay.master_entity_id')) {
            $entityId = config('hyperpay.master_entity_id');
        } elseif ($brand === 'MADA' && config('hyperpay.mada_entity_id')) {
            $entityId = config('hyperpay.mada_entity_id');
        }

        if (empty($entityId)) {
            throw new \RuntimeException('Hyperpay entity_id not configured for brand ' . ($brand ?: 'default'));
        }

        $payload['entityId'] = $entityId;

        Log::info('Hyperpay direct payment payload', ['payload' => array_merge($payload, ['card.number' => isset($payload['card.number']) ? '***REDACTED***' : null])]);

        $url = $this->base . '/v1/payments';
        $response = Http::withHeaders($this->headers())
                        ->timeout($this->timeout)
                        ->asForm()
                        ->post($url, $payload);

        Log::info('Hyperpay direct response', ['status' => $response->status(), 'body' => $response->body()]);

        return $response;
    }

    /**
     * Get payment status (polling)
     */
    public function getPaymentStatus(string $checkoutId)
    {
        if (empty($checkoutId)) {
            throw new \RuntimeException('Checkout ID is required');
        }

        Log::info('Fetching payment status', ['checkoutId' => $checkoutId]);

        $url = $this->base . '/v1/checkouts/' . $checkoutId;
        $response = Http::withHeaders($this->headers())
                        ->timeout($this->timeout)
                        ->get($url);

        Log::info('Payment status response', ['status' => $response->status(), 'body' => $response->body()]);

        return $response;
    }
}
