<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Env;
use Paynow\Client;
use Paynow\Environment;
use Paynow\Notification;
use Paynow\Service\Payment;

final class PaynowService
{
    private Client $client;

    public function __construct()
    {
        $apiKey  = (string) Env::get('PAYNOW_API_KEY', '');
        $sigKey  = (string) Env::get('PAYNOW_SIGNATURE_KEY', '');
        $sandbox = Env::get('PAYNOW_SANDBOX', '1') === '1';

        $this->client = new Client(
            $apiKey,
            $sigKey,
            $sandbox ? Environment::SANDBOX : Environment::PRODUCTION,
            ''
        );
    }

    /**
     * Creates a payment and returns ['paymentId', 'redirectUrl', 'status'].
     */
    public function createPayment(
        string $externalId,
        int $amountGrosh,
        string $description,
        string $buyerEmail,
        string $continueUrl,
        string $notificationUrl
    ): array {
        $payment = new Payment($this->client);

        $result = $payment->authorize([
            'amount'          => $amountGrosh,
            'currency'        => 'PLN',
            'externalId'      => $externalId,
            'description'     => $description,
            'buyer'           => ['email' => $buyerEmail],
            'continueUrl'     => $continueUrl,
            'notificationUrl' => $notificationUrl,
        ], $externalId);

        return [
            'paymentId'   => $result->getPaymentId(),
            'redirectUrl' => $result->getRedirectUrl(),
            'status'      => $result->getStatus(),
        ];
    }

    public function verifyNotification(string $rawBody, string $headerSignature): bool
    {
        try {
            new Notification(
                (string) Env::get('PAYNOW_SIGNATURE_KEY', ''),
                $rawBody,
                ['Signature' => $headerSignature]
            );
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
