<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class MidtransService
{
    private ?string $serverKey = null;

    private ?string $paymentLinkUrl = null;

    public function setServerKey(string $key): static
    {
        $this->serverKey = $key;

        return $this;
    }

    public function setPaymentLinkUrl(string $url): static
    {
        $this->paymentLinkUrl = $url;

        return $this;
    }

    private function resolveServerKey(): string
    {
        return $this->serverKey ?? config('midtrans.server_key');
    }

    private function resolvePaymentLinkUrl(): string
    {
        return $this->paymentLinkUrl ?? config('midtrans.payment_link_url');
    }

    public function createPaymentLink(array $params): array
    {
        $payload = $this->buildPaymentLinkPayload($params);

        $response = Http::withBasicAuth($this->resolveServerKey(), '')
            ->timeout(30)
            ->post($this->resolvePaymentLinkUrl(), $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                'Midtrans Payment Link creation failed: '.$response->body(),
                $response->status()
            );
        }

        return $response->json();
    }

    public function buildPaymentLinkPayload(array $params): array
    {
        $payload = [
            'transaction_details' => [
                'order_id' => $params['order_id'],
                'gross_amount' => $params['gross_amount'],
            ],
        ];

        if (isset($params['usage_limit'])) {
            $payload['usage_limit'] = $params['usage_limit'];
        }

        if (isset($params['expiry'])) {
            $payload['expiry'] = $params['expiry'];
        }

        if (isset($params['item_details'])) {
            $payload['item_details'] = $params['item_details'];
        }

        if (isset($params['customer_details'])) {
            $payload['customer_details'] = $params['customer_details'];
        }

        if (isset($params['enabled_payments'])) {
            $payload['enabled_payments'] = $params['enabled_payments'];
        }

        if (isset($params['callbacks'])) {
            $payload['callbacks'] = $params['callbacks'];
        }

        return $payload;
    }

    public function verifySignature(string $orderId, string $statusCode, string $grossAmount, string $signature): bool
    {
        $computed = hash('sha512', $orderId.$statusCode.$grossAmount.$this->resolveServerKey());

        return hash_equals($computed, $signature);
    }

    public function generateOrderId(): string
    {
        return 'PL-'.strtoupper(bin2hex(random_bytes(12)));
    }
}
