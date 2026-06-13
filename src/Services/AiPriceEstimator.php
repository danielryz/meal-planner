<?php

declare(strict_types=1);

namespace App\Services;

final class AiPriceEstimator
{
    private const MIN_CENTS = 1;
    private const MAX_CENTS = 100000;

    public function estimateCents(string $name, ?string $quantity = null): ?int
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $quantityText = trim((string) $quantity);
        $quantityText = $quantityText !== '' ? $quantityText : 'standardowa ilosc dla jednej pozycji zakupowej';

        try {
            $reply = (new AiService(30))->chat(
                [
                    [
                        'role'    => 'system',
                        'content' => 'Jestes estymatorem cen zakupow spozywczych w Polsce. '
                            . 'Zwracasz zawsze poprawny JSON i nic poza JSON. '
                            . 'Schemat odpowiedzi: {"price_pln": number}. '
                            . 'price_pln to laczna cena za podana ilosc produktu w PLN, nie cena jednostkowa.',
                    ],
                    [
                        'role'    => 'user',
                        'content' => "Produkt: {$name}\nIlosc: {$quantityText}",
                    ],
                ],
                'json'
            );
        } catch (\RuntimeException) {
            return null;
        }

        return $this->parsePriceCents($reply);
    }

    private function parsePriceCents(string $reply): ?int
    {
        $decoded = json_decode(trim($reply), true);

        if (is_array($decoded)) {
            foreach (['price_pln', 'price', 'cena_pln', 'cena'] as $key) {
                if (array_key_exists($key, $decoded)) {
                    return $this->normalizePriceToCents($decoded[$key]);
                }
            }
        }

        $normalized = str_replace(',', '.', trim($reply));

        if (!preg_match('/\d+(?:\.\d+)?/', $normalized, $match)) {
            return null;
        }

        return $this->normalizePriceToCents($match[0]);
    }

    private function normalizePriceToCents(mixed $price): ?int
    {
        if (is_string($price)) {
            $price = str_replace([' ', ','], ['', '.'], $price);
        }

        if (!is_numeric($price)) {
            return null;
        }

        $cents = (int) round((float) $price * 100);

        if ($cents < self::MIN_CENTS || $cents > self::MAX_CENTS) {
            return null;
        }

        return $cents;
    }
}
