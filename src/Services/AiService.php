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
     * @param  array<array{role: string, content: string}> $messages
     * @throws \RuntimeException when Ollama is unreachable or returns an error
     */
    public function chat(array $messages): string
    {
        $payload = json_encode([
            'model'    => $this->model,
            'messages' => $messages,
            'stream'   => false,
        ], JSON_THROW_ON_ERROR);

        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content'       => $payload,
                'timeout'       => 60,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($this->baseUrl . '/api/chat', false, $context);

        if ($result === false) {
            throw new \RuntimeException('ollama_unreachable');
        }

        $data = json_decode($result, true);

        if (!is_array($data) || !isset($data['message']['content'])) {
            throw new \RuntimeException('ollama_bad_response');
        }

        return (string) $data['message']['content'];
    }
}
