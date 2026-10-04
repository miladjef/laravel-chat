<?php

namespace App\Support;

final class DisplayNameNormalizer
{
    private const CHARACTER_MAP = [
        'ي' => 'ی',
        'ى' => 'ی',
        'ئ' => 'ی',
        'ك' => 'ک',
        'ة' => 'ه',
        'ۀ' => 'ه',
        'ؤ' => 'و',
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
    ];

    public static function normalize(string $value): string
    {
        $value = trim($value);

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $value = $normalized;
            }
        }

        $value = strtr($value, self::CHARACTER_MAP);
        $value = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{FEFF}]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_strtolower(trim($value), 'UTF-8');
    }
}
