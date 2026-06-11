<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Services\AiService;

final class AiController extends AppController
{
    private const SYSTEM_PROMPT = 'Jesteś pomocnym asystentem kulinarnym aplikacji MealPlanner. '
        . 'Pomagasz użytkownikom planować posiłki i diety, znajdować przepisy, '
        . 'rozumieć wartości odżywcze, tworzyć listy zakupów i odpowiadać na pytania kulinarne. '
        . 'Odpowiadaj po polsku, zwięźle i przyjaźnie. Jeśli pytanie nie dotyczy gotowania, '
        . 'jedzenia lub planowania posiłków, grzecznie przekieruj rozmowę na tematy kulinarne.';

    private const MAX_HISTORY = 20;

    public function chat(): Response
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        if (!$this->isPost()) {
            return $this->jsonError('Metoda niedozwolona.', 405);
        }

        $messages = $this->request->input('messages', []);

        if (empty($messages) || !is_array($messages)) {
            return $this->jsonError('Pole messages jest wymagane.', 400);
        }

        foreach ($messages as $msg) {
            if (!isset($msg['role'], $msg['content']) || !in_array($msg['role'], ['user', 'assistant'], true)) {
                return $this->jsonError('Nieprawidłowa struktura wiadomości.', 400);
            }
            if (strlen((string) $msg['content']) > 4000) {
                return $this->jsonError('Wiadomość jest zbyt długa.', 400);
            }
        }

        $history = array_slice($messages, -self::MAX_HISTORY);

        $payload = array_merge(
            [['role' => 'system', 'content' => self::SYSTEM_PROMPT]],
            $history
        );

        try {
            $reply = (new AiService())->chat($payload);
            return Response::json(['reply' => $reply]);
        } catch (\RuntimeException) {
            return $this->jsonError('Asystent AI jest chwilowo niedostępny. Sprawdź czy Ollama jest uruchomiona.', 503);
        }
    }
}
