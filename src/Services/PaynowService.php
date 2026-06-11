<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Env;

final class PaynowService
{
    private string $apiKey;
    private string $signatureKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey       = (string) Env::get('PAYNOW_API_KEY', '');
        $this->signatureKey = (string) Env::get('PAYNOW_SIGNATURE_KEY', '');
        $this->baseUrl      = Env::get('PAYNOW_SANDBOX', '1') === '1'
            ? 'https://api.sandbox.paynow.pl'
            : 'https://api.paynow.pl';
    }

    /**
     * Creates a payment in Paynow and returns ['paymentId', 'redirectUrl', 'status'].
     */
    public function createPayment(
        string $externalId,
        int $amountGrosh,
        string $description,
        string $buyerEmail,
        string $continueUrl,
        string $notificationUrl
    ): array {
        $body = json_encode([
            'amount'          => $amountGrosh,
            'currency'        => 'PLN',
            'externalId'      => $externalId,
            'description'     => $description,
            'buyer'           => ['email' => $buyerEmail],
            'continueUrl'     => $continueUrl,
            'notificationUrl' => $notificationUrl,
        ], JSON_THROW_ON_ERROR);

        $signature    = base64_encode(hash_hmac('sha256', $body, $this->signatureKey, true));
        $idempotency  = $externalId;

        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", [
                    'Content-Type: application/json',
                    'Api-Key: ' . $this->apiKey,
                    'Signature: ' . $signature,
                    'Idempotency-Key: ' . $idempotency,
                ]),
                'content'       => $body,
                'timeout'       => 15,
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($this->baseUrl . '/v3/payments', false, $ctx);

        $httpStatus = 0;
        foreach (array_reverse($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+ (\d{3})/', $h, $m)) {
                $httpStatus = (int) $m[1];
                break;
            }
        }

        if ($response === false || $httpStatus >= 500) {
            throw new \RuntimeException('Paynow API unreachable.');
        }

        $data = json_decode($response, true) ?? [];

        if ($httpStatus !== 201 || empty($data['paymentId'])) {
            $error = $data['errors'][0]['message'] ?? $data['message'] ?? "HTTP {$httpStatus}";
            throw new \RuntimeException('Paynow payment creation failed: ' . $error);
        }

        return [
            'paymentId'   => $data['paymentId'],
            'redirectUrl' => $data['redirectUrl'],
            'status'      => $data['status'] ?? 'NEW',
        ];
    }

    public function verifyNotification(string $rawBody, string $headerSignature): bool
    {
        $expected = base64_encode(hash_hmac('sha256', $rawBody, $this->signatureKey, true));
        return hash_equals($expected, $headerSignature);
    }


}
