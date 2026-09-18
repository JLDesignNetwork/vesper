<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class TranslationService
{
    /**
     * Translate text into specified target language, preserving URLs, code, and mentions.
     *
     * @return array{
     *     success: bool,
     *     original_text: string,
     *     translated_text: string,
     *     target_lang: string
     * }
     */
    public function translate(string $text, string $targetLang = 'ru'): array
    {
        $text = trim($text);

        if ($text === '') {
            return [
                'success' => true,
                'original_text' => '',
                'translated_text' => '',
                'target_lang' => $targetLang,
            ];
        }

        // Mask URLs, code blocks, and mentions with placeholder tokens
        $maskedData = $this->maskTokens($text);
        $textToTranslate = $maskedData['text'];
        $tokens = $maskedData['tokens'];

        // Detect if text contains Cyrillic
        $hasCyrillic = (bool) preg_match('/[\p{Cyrillic}]/u', $text);
        $sourceLang = $hasCyrillic ? 'ru' : 'en';
        $actualTarget = ($hasCyrillic && $targetLang === 'ru') ? 'en' : $targetLang;

        $cacheKey = 'trans_'.md5($text.'_'.$actualTarget);

        // Return from cache if previously successfully translated
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ! empty($cached['success'])) {
            return $cached;
        }

        // Provider 1: Google Translation API
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                'Accept' => '*/*',
            ])->timeout(5)->get('https://clients5.google.com/translate_a/t', [
                'client' => 'dict-chrome-ex',
                'sl' => 'auto',
                'tl' => $actualTarget,
                'q' => $textToTranslate,
            ]);

            if ($response->successful()) {
                $json = $response->json();
                $translated = null;

                if (is_array($json)) {
                    if (isset($json[0][0]) && is_string($json[0][0])) {
                        $translated = $json[0][0];
                    } elseif (isset($json[0]) && is_string($json[0])) {
                        $translated = $json[0];
                    }
                }

                if ($translated && trim($translated) !== '') {
                    $decoded = html_entity_decode(trim($translated), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $restored = $this->unmaskTokens($decoded, $tokens);

                    $result = [
                        'success' => true,
                        'original_text' => $text,
                        'translated_text' => $restored,
                        'target_lang' => $actualTarget,
                    ];

                    Cache::put($cacheKey, $result, now()->addDays(14));

                    return $result;
                }
            }
        } catch (Throwable) {
            // Proceed to secondary provider
        }

        // Provider 2: MyMemory API Fallback
        try {
            $langPair = "{$sourceLang}|{$actualTarget}";
            $response = Http::timeout(6)->get('https://api.mymemory.translated.net/get', [
                'q' => $textToTranslate,
                'langpair' => $langPair,
                'de' => 'contact@vesper.local',
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                $translated = $responseData['responseData']['translatedText'] ?? null;

                if ($translated && is_string($translated) && ! str_contains($translated, 'MYMEMORY WARNING') && trim($translated) !== '') {
                    $decoded = html_entity_decode(trim($translated), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $restored = $this->unmaskTokens($decoded, $tokens);

                    $result = [
                        'success' => true,
                        'original_text' => $text,
                        'translated_text' => $restored,
                        'target_lang' => $actualTarget,
                    ];

                    Cache::put($cacheKey, $result, now()->addDays(14));

                    return $result;
                }
            }
        } catch (Throwable) {
            // Fallback gracefully
        }

        // Do not cache failures so subsequent clicks can retry
        return [
            'success' => false,
            'original_text' => $text,
            'translated_text' => $text,
            'target_lang' => $actualTarget,
        ];
    }

    /**
     * Batch translate an array of message strings into a target language.
     *
     * @param  array<int|string, string>  $texts
     * @return array{
     *     success: bool,
     *     target_lang: string,
     *     translations: array<int|string, string>
     * }
     */
    public function translateBatch(array $texts, string $targetLang = 'en'): array
    {
        $translations = [];
        $allSuccessful = true;

        foreach ($texts as $key => $text) {
            $trimmed = trim((string) $text);
            if ($trimmed === '') {
                $translations[$key] = '';

                continue;
            }

            $res = $this->translate($trimmed, $targetLang);
            $translations[$key] = $res['translated_text'];
            if (! $res['success']) {
                $allSuccessful = false;
            }
        }

        return [
            'success' => $allSuccessful,
            'target_lang' => $targetLang,
            'translations' => $translations,
        ];
    }

    /**
     * Mask code blocks, URLs, and @mentions before passing text to translation engines.
     *
     * @return array{text: string, tokens: array<string, string>}
     */
    public function maskTokens(string $text): array
    {
        $tokens = [];
        $counter = 0;

        // 1. Code blocks and inline backtick snippets
        $text = (string) preg_replace_callback('/(`{1,3})([\s\S]*?)\1/u', function ($matches) use (&$tokens, &$counter) {
            $token = "VSPTOK_{$counter}_CODE";
            $tokens[$token] = $matches[0];
            $counter++;

            return $token;
        }, $text);

        // 2. URLs (http/https)
        $text = (string) preg_replace_callback('/https?:\/\/[^\s<>"\']+/iu', function ($matches) use (&$tokens, &$counter) {
            $token = "VSPTOK_{$counter}_URL";
            $tokens[$token] = $matches[0];
            $counter++;

            return $token;
        }, $text);

        // 3. User @mentions
        $text = (string) preg_replace_callback('/(?<=^|\s)@[a-zA-Z0-9_\-]+/u', function ($matches) use (&$tokens, &$counter) {
            $token = "VSPTOK_{$counter}_USR";
            $tokens[$token] = $matches[0];
            $counter++;

            return $token;
        }, $text);

        return [
            'text' => $text,
            'tokens' => $tokens,
        ];
    }

    /**
     * Restore original tokens into translated text.
     *
     * @param  array<string, string>  $tokens
     */
    public function unmaskTokens(string $text, array $tokens): string
    {
        if (empty($tokens)) {
            return $text;
        }

        foreach ($tokens as $token => $original) {
            if (str_contains($text, $token)) {
                $text = str_replace($token, $original, $text);
            } else {
                // Translation engines might lowercase or alter spaces around token underscores
                $escaped = preg_quote($token, '/');
                $pattern = '/\b'.str_replace('_', '[\s_]*', $escaped).'\b/iu';
                $text = (string) preg_replace($pattern, $original, $text);
            }
        }

        return $text;
    }
}
