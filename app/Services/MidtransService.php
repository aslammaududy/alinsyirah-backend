<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class MidtransService
{
    private ?string $serverKey = null;

    public function setServerKey(string $key): static
    {
        $this->serverKey = $key;

        return $this;
    }

    private function resolveServerKey(): string
    {
        return $this->serverKey ?? config('midtrans.server_key');
    }

    private function resolveCoreApiBaseUrl(): string
    {
        return config('midtrans.core_api_base_url');
    }

    public function createCharge(array $params): array
    {
        $payload = [
            'payment_type' => $params['payment_type'],
            'transaction_details' => $params['transaction_details'],
            'item_details' => $params['item_details'],
            'customer_details' => $params['customer_details'],
        ];

        if (isset($params['bank_transfer'])) {
            $payload['bank_transfer'] = $params['bank_transfer'];
        }

        if (isset($params['bsi_va'])) {
            $payload['bsi_va'] = $params['bsi_va'];
        }

        $response = Http::withBasicAuth($this->resolveServerKey(), '')
            ->timeout(30)
            ->post("{$this->resolveCoreApiBaseUrl()}/v2/charge", $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                'Midtrans Core API charge failed: '.$response->body(),
                $response->status()
            );
        }

        return $response->json();
    }

    public function verifySignature(string $orderId, string $statusCode, string $grossAmount, string $signature): bool
    {
        $computed = hash('sha512', $orderId.$statusCode.$grossAmount.$this->resolveServerKey());

        return hash_equals($computed, $signature);
    }

    public function generateOrderId(): string
    {
        return 'ORD-'.strtoupper(bin2hex(random_bytes(12)));
    }
}
