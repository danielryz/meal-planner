<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Env;
use App\Database\Database;
use App\Http\Response;
use App\Services\PaynowService;
use PDO;

final class PaymentController extends AppController
{
    public function create(): Response
    {
        if (!$this->isPost()) {
            return $this->jsonError('Method not allowed.', 405);
        }

        $amountGrosh = (int) $this->request->input('amountGrosh', 0);
        $description = trim((string) $this->request->input('description', ''));
        $email       = trim((string) $this->request->input('email', ''));

        if ($amountGrosh < 100) {
            return Response::json(['error' => 'Minimalna kwota to 1 zł.'], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Response::json(['error' => 'Podaj poprawny adres e-mail.'], 422);
        }

        if ($description === '') {
            $description = 'Wsparcie MealPlanner';
        }

        $externalId = bin2hex(random_bytes(16));
        $appUrl     = rtrim((string) Env::get('APP_URL', 'http://localhost:8080'), '/');

        $db         = new Database();
        $connection = $db->connection();

        $stmt = $connection->prepare(
            'INSERT INTO payments (user_id, external_id, amount_grosz, description, buyer_email, status)
            VALUES (:user_id, :external_id, :amount, :description, :email, :status)'
        );
        $userId = $this->sessions->isLoggedIn() ? $this->sessions->currentUser()->id() : null;
        $stmt->bindValue(':user_id',     $userId,      $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':external_id', $externalId);
        $stmt->bindValue(':amount',      $amountGrosh, PDO::PARAM_INT);
        $stmt->bindValue(':description', $description);
        $stmt->bindValue(':email',       $email);
        $stmt->bindValue(':status',      'NEW');
        $stmt->execute();

        try {
            $result = (new PaynowService())->createPayment(
                $externalId,
                $amountGrosh,
                $description,
                $email,
                $appUrl . '/support?payment=success',
                $appUrl . '/api/payments/notify'
            );
        } catch (\RuntimeException $e) {
            return Response::json(['error' => 'Bramka płatności jest chwilowo niedostępna. Spróbuj ponownie.'], 503);
        }

        $stmt2 = $connection->prepare(
            'UPDATE payments SET paynow_payment_id = :pid, status = :status WHERE external_id = :ext'
        );
        $stmt2->bindValue(':pid',    $result['paymentId']);
        $stmt2->bindValue(':status', $result['status']);
        $stmt2->bindValue(':ext',    $externalId);
        $stmt2->execute();

        return Response::json(['redirectUrl' => $result['redirectUrl']]);
    }

    public function notify(): Response
    {
        $rawBody  = (string) file_get_contents('php://input');
        $sigHeader = (string) ($_SERVER['HTTP_SIGNATURE'] ?? '');

        if (!(new PaynowService())->verifyNotification($rawBody, $sigHeader)) {
            return Response::json(['error' => 'Invalid signature.'], 401);
        }

        $data       = json_decode($rawBody, true) ?? [];
        $externalId = (string) ($data['externalId'] ?? '');
        $status     = (string) ($data['status'] ?? '');
        $paymentId  = (string) ($data['paymentId'] ?? '');

        if ($externalId === '' || $status === '') {
            return Response::json(['ok' => true]);
        }

        $db   = new Database();
        $stmt = $db->connection()->prepare(
            'UPDATE payments SET status = :status, paynow_payment_id = COALESCE(paynow_payment_id, :pid)
            WHERE external_id = :ext'
        );
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':pid',    $paymentId);
        $stmt->bindValue(':ext',    $externalId);
        $stmt->execute();

        return Response::json(['ok' => true]);
    }
}
