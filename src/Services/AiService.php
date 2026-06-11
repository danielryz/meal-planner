<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Env;

final class AiService
{
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $this->baseUrl = rtrim(Env::get('OLLAMA_URL', 'http://ollama:11434'), '/');
        $this->model   = Env::get('OLLAMA_MODEL', 'llama3.2');
    }

    /**
     * @param  array<array<string, mixed>> $messages
     * @param  array<mixed>                $tools    Ollama tool definitions (empty = disabled)
     * @return array{role: string, content: string, tool_calls?: array<mixed>}
     * @throws \RuntimeException when Ollama is unreachable or returns an error
     */
    public function sendMessage(array $messages, array $tools = []): array
    {
        $body = [
            'model'    => $this->model,
            'messages' => $messages,
            'stream'   => false,
            'options'  => [
                'temperature' => 0.2,
                'top_p'       => 0.8,
            ],
        ];

        if (!empty($tools)) {
            $body['tools'] = $tools;
        }

        $payload = json_encode($body, JSON_THROW_ON_ERROR);

        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content'       => $payload,
                'timeout'       => 600,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($this->baseUrl . '/api/chat', false, $context);

        if ($result === false) {
            throw new \RuntimeException('ollama_unreachable');
        }

        $httpCode = 200;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $m)) {
                $httpCode = (int) $m[1];
            }
        }

        if ($httpCode === 400 && !empty($tools)) {
            return $this->sendMessage($messages);
        }

        $data = json_decode($result, true);

        if (!is_array($data) || !isset($data['message'])) {
            throw new \RuntimeException('ollama_bad_response');
        }

        return $data['message'];
    }

    /**
     * @param  array<array{role: string, content: string}> $messages
     * @throws \RuntimeException
     */
    public function chat(array $messages): string
    {
        $message = $this->sendMessage($messages);
        return (string) ($message['content'] ?? '');
    }
}
