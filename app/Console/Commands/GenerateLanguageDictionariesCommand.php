<?php

namespace App\Console\Commands;

use App\Services\LanguageService;
use Illuminate\Console\Command;

class GenerateLanguageDictionariesCommand extends Command
{
    protected $signature = 'lang:generate {locales?* : Specific locales to generate}';

    protected $description = 'Generate and synchronize JSON language dictionary files for all supported languages';

    public function handle(): int
    {
        $enPath = base_path('lang/en.json');
        if (! file_exists($enPath)) {
            $this->error('lang/en.json does not exist!');

            return 1;
        }

        $enData = json_decode(file_get_contents($enPath), true);
        $totalKeys = count($enData);
        $this->info("Source dictionary has {$totalKeys} keys.");

        $targetLocales = $this->argument('locales');
        if (empty($targetLocales)) {
            $targetLocales = array_diff(LanguageService::codes(), ['en']);
        }

        $keys = array_keys($enData);
        $chunks = array_chunk($keys, 50);

        foreach ($targetLocales as $loc) {
            $tl = $loc === 'zh' ? 'zh-CN' : $loc;
            $this->info("Processing [{$loc}]...");
            $dict = [];

            $filePath = base_path("lang/{$loc}.json");
            $existing = file_exists($filePath) ? json_decode(file_get_contents($filePath), true) : [];
            if (! is_array($existing)) {
                $existing = [];
            }

            foreach ($chunks as $chunk) {
                // Check if chunk already completely exists in existing dictionary
                $needsTranslation = false;
                foreach ($chunk as $k) {
                    if (! isset($existing[$k]) || trim((string) $existing[$k]) === '') {
                        $needsTranslation = true;
                        break;
                    }
                }

                if (! $needsTranslation) {
                    foreach ($chunk as $k) {
                        $dict[$k] = $existing[$k];
                    }

                    continue;
                }

                $url = 'https://clients5.google.com/translate_a/t?client=dict-chrome-ex&sl=en&tl='.$tl.'&q='.urlencode(implode("\n", $chunk));
                $res = @file_get_contents($url);
                if ($res) {
                    $json = json_decode($res, true);
                    $lines = explode("\n", $json[0] ?? '');
                    if (count($lines) === count($chunk)) {
                        foreach ($chunk as $i => $k) {
                            $dict[$k] = trim($lines[$i]);
                        }

                        continue;
                    }
                }

                // Fallback item by item for this chunk
                foreach ($chunk as $k) {
                    if (isset($existing[$k]) && trim((string) $existing[$k]) !== '') {
                        $dict[$k] = $existing[$k];

                        continue;
                    }
                    $singleUrl = 'https://clients5.google.com/translate_a/t?client=dict-chrome-ex&sl=en&tl='.$tl.'&q='.urlencode($k);
                    $singleRes = @file_get_contents($singleUrl);
                    $singleJson = $singleRes ? json_decode($singleRes, true) : null;
                    $dict[$k] = trim($singleJson[0] ?? $k);
                    usleep(20000);
                }
            }

            // Clean, sort, and save
            $final = [];
            foreach ($keys as $k) {
                $val = $dict[$k] ?? ($existing[$k] ?? $k);
                $final[$k] = mb_convert_encoding($val, 'UTF-8', 'UTF-8');
            }
            ksort($final);

            $jsonStr = json_encode($final, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            file_put_contents($filePath, $jsonStr."\n");
            $this->info("✓ [{$loc}] saved with ".count($final).' keys.');
        }

        $this->info('All language dictionaries synchronized successfully.');

        return 0;
    }
}
