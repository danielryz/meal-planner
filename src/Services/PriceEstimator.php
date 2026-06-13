<?php

declare(strict_types=1);

namespace App\Services;

final class PriceEstimator
{
    private const PRICE_TABLE = [
        'ziemniak' => ['unit' => 'kg', 'cents' => 350],
        'pomidor' => ['unit' => 'kg', 'cents' => 900],
        'cebula' => ['unit' => 'kg', 'cents' => 450],
        'marchew' => ['unit' => 'kg', 'cents' => 400],
        'cukinia' => ['unit' => 'piece', 'cents' => 450],
        'brokul' => ['unit' => 'piece', 'cents' => 650],
        'ogorek' => ['unit' => 'piece', 'cents' => 350],
        'salata' => ['unit' => 'piece', 'cents' => 500],
        'banan' => ['unit' => 'kg', 'cents' => 700],
        'jablko' => ['unit' => 'kg', 'cents' => 650],
        'makaron' => ['unit' => 'kg', 'cents' => 1000],
        'ryz' => ['unit' => 'kg', 'cents' => 900],
        'maka' => ['unit' => 'kg', 'cents' => 450],
        'platki owsiane' => ['unit' => 'kg', 'cents' => 900],
        'mleko' => ['unit' => 'l', 'cents' => 420],
        'jogurt' => ['unit' => 'kg', 'cents' => 1200],
        'smietana' => ['unit' => 'l', 'cents' => 1600],
        'maslo' => ['unit' => 'kg', 'cents' => 3500],
        'ser feta' => ['unit' => 'kg', 'cents' => 4500],
        'mozzarella' => ['unit' => 'kg', 'cents' => 3600],
        'jajka' => ['unit' => 'piece', 'cents' => 120],
        'jajko' => ['unit' => 'piece', 'cents' => 120],
        'kurczak' => ['unit' => 'kg', 'cents' => 2200],
        'tunczyk' => ['unit' => 'piece', 'cents' => 800],
        'tofu' => ['unit' => 'kg', 'cents' => 3200],
        'soczewica' => ['unit' => 'kg', 'cents' => 1200],
        'ciecierzyca' => ['unit' => 'piece', 'cents' => 550],
        'czekolada' => ['unit' => 'kg', 'cents' => 5000],
        'cukier' => ['unit' => 'kg', 'cents' => 500],
        'oliwa' => ['unit' => 'l', 'cents' => 4500],
        'olej' => ['unit' => 'l', 'cents' => 1200],
        'sos sojowy' => ['unit' => 'l', 'cents' => 3000],
        'przyprawy' => ['unit' => 'piece', 'cents' => 400],
        'sol' => ['unit' => 'kg', 'cents' => 250],
    ];

    public function estimateCents(string $name, ?string $quantity = null): int
    {
        return $this->estimateKnownCents($name, $quantity) ?? 500;
    }

    public function estimateKnownCents(string $name, ?string $quantity = null): ?int
    {
        $entry = $this->matchProduct($name);

        if ($entry === null) {
            return null;
        }

        $amount = $this->parseQuantity($quantity ?? '', $entry['unit']);

        return max(50, (int) round($entry['cents'] * $amount));
    }

    public function parseMoneyToCents(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return max(0, (int) round(((float) $value) * 100));
        }

        $normalized = str_replace([' ', 'zl', 'zł', 'PLN'], '', mb_strtolower((string) $value));
        $normalized = str_replace(',', '.', $normalized);

        if (!is_numeric($normalized)) {
            return null;
        }

        return max(0, (int) round(((float) $normalized) * 100));
    }

    private function matchProduct(string $name): ?array
    {
        $normalized = $this->normalize($name);

        foreach (self::PRICE_TABLE as $needle => $entry) {
            if (str_contains($normalized, $needle)) {
                return $entry;
            }
        }

        return null;
    }

    private function parseQuantity(string $quantity, string $priceUnit): float
    {
        if (!preg_match('/(\d+(?:[.,]\d+)?)\s*([a-zA-ZąćęłńóśźżĄĆĘŁŃÓŚŹŻ.]*)/u', trim($quantity), $m)) {
            return 1.0;
        }

        $value = (float) str_replace(',', '.', $m[1]);
        $unit = $this->normalizeUnit($m[2] ?? '');

        if ($priceUnit === 'kg') {
            return match ($unit) {
                'g' => $value / 1000,
                'dag' => $value / 100,
                'kg' => $value,
                'lyzka' => ($value * 15) / 1000,
                'lyzeczka' => ($value * 5) / 1000,
                'szt' => $value * 0.2,
                '' => $this->looksLikePieces($quantity) ? $value * 0.2 : $value,
                default => $this->looksLikePieces($quantity) ? $value * 0.2 : $value,
            };
        }

        if ($priceUnit === 'l') {
            return match ($unit) {
                'ml' => $value / 1000,
                'l' => $value,
                'lyzka' => ($value * 15) / 1000,
                'lyzeczka' => ($value * 5) / 1000,
                default => $value,
            };
        }

        return max(1.0, $value);
    }

    private function normalizeUnit(string $unit): string
    {
        $unit = trim(mb_strtolower($unit), '. ');

        return match ($unit) {
            'gram', 'gramy', 'gramow', 'gramów' => 'g',
            'kilogram', 'kilogramy', 'kilogramow', 'kilogramów' => 'kg',
            'litr', 'litry', 'litrow', 'litrów' => 'l',
            'szt', 'sztuka', 'sztuki', 'sztuk' => 'szt',
            'lyzka', 'łyżka', 'lyzki', 'łyżki', 'lyzek', 'łyżek' => 'lyzka',
            'lyzeczka', 'łyżeczka', 'lyzeczki', 'łyżeczki', 'lyzeczek', 'łyżeczek' => 'lyzeczka',
            default => $unit,
        };
    }

    private function looksLikePieces(string $quantity): bool
    {
        return preg_match('/\b(szt|sztuka|sztuki|duze|duże|male|małe|puszka|opakowanie)\b/iu', $quantity) === 1;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = strtr($value, [
            'ą' => 'a',
            'ć' => 'c',
            'ę' => 'e',
            'ł' => 'l',
            'ń' => 'n',
            'ó' => 'o',
            'ś' => 's',
            'ź' => 'z',
            'ż' => 'z',
        ]);

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }
}
