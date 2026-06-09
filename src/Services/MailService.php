<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Env;
use PHPMailer\PHPMailer\PHPMailer;

final class MailService
{
    public function sendActivationEmail(string $toEmail, string $toName, string $rawToken): void
    {
        $activationUrl = $this->appUrl() . '/activate?token=' . urlencode($rawToken);

        $body = $this->renderTemplate('activation', [
            'name'          => $toName,
            'activationUrl' => $activationUrl,
            'expiresHours'  => 48,
        ]);

        $this->send($toEmail, $toName, 'Potwierdź adres e-mail — MealPlanner', $body);
    }

    public function sendPasswordResetEmail(string $toEmail, string $toName, string $rawToken): void
    {
        $resetUrl = $this->appUrl() . '/reset-password?token=' . urlencode($rawToken);

        $body = $this->renderTemplate('password-reset', [
            'name'          => $toName,
            'resetUrl'      => $resetUrl,
            'expiresHours'  => 1,
        ]);

        $this->send($toEmail, $toName, 'Resetowanie hasła — MealPlanner', $body);
    }

    private function send(string $toEmail, string $toName, string $subject, string $htmlBody): void
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = (string) Env::get('MAIL_HOST', 'smtp.mailgun.org');
        $mail->SMTPAuth   = true;
        $mail->Username   = (string) Env::get('MAIL_USERNAME', '');
        $mail->Password   = (string) Env::get('MAIL_PASSWORD', '');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) Env::get('MAIL_PORT', '587');
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(
            (string) Env::get('MAIL_FROM', 'no-reply@mealplanner.pl'),
            (string) Env::get('MAIL_FROM_NAME', 'MealPlanner')
        );
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>'], "\n", $htmlBody));

        $mail->send();
    }

    private function renderTemplate(string $name, array $vars): string
    {
        $templatePath = dirname(__DIR__) . '/templates/emails/' . $name . '.html';

        if (!is_file($templatePath)) {
            throw new \RuntimeException("Email template not found: {$name}");
        }

        extract($vars, EXTR_SKIP);
        ob_start();
        include $templatePath;
        return (string) ob_get_clean();
    }

    private function appUrl(): string
    {
        return rtrim((string) Env::get('APP_URL', 'http://localhost:8080'), '/');
    }
}
