<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class TranslationService
{
    /**
     * Translate text into Russian (or specified target language).
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
                'q' => $text,
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
                    $result = [
                        'success' => true,
                        'original_text' => $text,
                        'translated_text' => html_entity_decode(trim($translated), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
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
                'q' => $text,
                'langpair' => $langPair,
                'de' => 'contact@sundaycity.local',
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                $translated = $responseData['responseData']['translatedText'] ?? null;

                if ($translated && is_string($translated) && ! str_contains($translated, 'MYMEMORY WARNING') && trim($translated) !== '') {
                    $result = [
                        'success' => true,
                        'original_text' => $text,
                        'translated_text' => html_entity_decode(trim($translated), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
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
}

