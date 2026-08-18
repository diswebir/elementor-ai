<?php

declare(strict_types=1);

namespace AIEA\Support;

final class Temperature
{
    private const MINIMUM = 0.0;

    private const MAXIMUM = 2.0;

    public static function normalize(mixed $value, string $fallback = '0.2'): string
    {
        $normalized = self::toNumericString($value);
        if ($normalized === null || !is_numeric($normalized)) {
            return self::normalizeFallback($fallback);
        }

        $float = min(self::MAXIMUM, max(self::MINIMUM, (float) $normalized));
        $formatted = number_format($float, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }

    private static function normalizeFallback(string $fallback): string
    {
        $normalized = self::toNumericString($fallback);
        if ($normalized === null || !is_numeric($normalized)) {
            return '0.2';
        }

        $float = min(self::MAXIMUM, max(self::MINIMUM, (float) $normalized));
        $formatted = number_format($float, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }

    private static function toNumericString(mixed $value): ?string
    {
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = strtr($value, [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
            '٫' => '.',
            ',' => '.',
        ]);

        return preg_match('/^-?(?:\d+|\d*\.\d+)$/', $value) === 1 ? $value : null;
    }
}
