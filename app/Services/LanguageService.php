<?php

namespace App\Services;

class LanguageService
{
    /**
     * Get all supported languages configuration.
     *
     * @return array<string, array{code: string, name: string, native: string, flag: string, dir: string}>
     */
    public static function supported(): array
    {
        return config('languages.supported', []);
    }

    /**
     * Get array of all supported ISO language codes.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(static::supported());
    }

    /**
     * Determine if a given locale code is supported.
     */
    public static function isValid(?string $code): bool
    {
        if (empty($code)) {
            return false;
        }

        return in_array(strtolower(trim($code)), static::codes(), true);
    }

    /**
     * Get the default language code.
     */
    public static function default(): string
    {
        return config('languages.default', 'en');
    }

    /**
     * Get metadata for a specific language code.
     *
     * @return array{code: string, name: string, native: string, flag: string, dir: string}|null
     */
    public static function get(string $code): ?array
    {
        return static::supported()[$code] ?? null;
    }

    /**
     * Get emoji flag for a given locale code.
     */
    public static function getFlag(string $code): string
    {
        return static::get($code)['flag'] ?? '🌐';
    }

    /**
     * Get native script label for a given locale code.
     */
    public static function getNativeName(string $code): string
    {
        return static::get($code)['native'] ?? strtoupper($code);
    }

    /**
     * Get English name for a given locale code.
     */
    public static function getName(string $code): string
    {
        return static::get($code)['name'] ?? strtoupper($code);
    }

    /**
     * Get text direction for a given locale code ('ltr' or 'rtl').
     */
    public static function getDirection(string $code): string
    {
        return static::get($code)['dir'] ?? 'ltr';
    }
}
