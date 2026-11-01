<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class TranslationKeyHygieneTest extends TestCase
{
    private const LOCALE_DIR = 'resources/lang';

    private const SCAN_DIRS = ['resources/views', 'app', 'Modules', 'routes'];

    private const TRANS_PREFIXES = '(validation|passwords|pagination|order_texts)\.';

    public function test_language_files_contain_no_junk_keys(): void
    {
        $offenders = [];

        foreach ($this->locales() as $locale => $path) {
            $messages = include $path;
            $this->assertIsArray($messages, "$locale/messages.php must return an array");

            foreach ($messages as $key => $_) {
                if (! isPersistableTranslationKey((string) $key, applyPolicyLists: false)) {
                    $offenders[] = $locale . ': ' . var_export($key, true);
                }
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "%d junk key(s) found in language files. These can never be meaningfully translated:\n%s",
            count($offenders),
            implode("\n", array_slice($offenders, 0, 40))
        ));
    }

    public function test_language_files_contain_no_untrimmed_keys(): void
    {
        $offenders = [];

        foreach ($this->locales() as $locale => $path) {
            foreach (array_keys(include $path) as $key) {
                $key = (string) $key;
                if ($key !== trim($key)) {
                    $offenders[] = $locale . ': ' . var_export($key, true);
                }
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "%d key(s) have leading/trailing whitespace. translate() trims its input, "
            . "so these are unreachable duplicates:\n%s",
            count($offenders),
            implode("\n", array_slice($offenders, 0, 40))
        ));
    }

    public function test_placeholders_survive_in_every_locale(): void
    {
        $offenders = [];

        foreach ($this->locales() as $locale => $path) {
            foreach (include $path as $key => $value) {
                if (! preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', (string) $key, $matches)) {
                    continue;
                }

                foreach (array_unique($matches[0]) as $token) {
                    if (! str_contains((string) $value, $token)) {
                        $offenders[] = sprintf(
                            '%s: %s => %s  [lost %s]',
                            $locale,
                            var_export($key, true),
                            var_export($value, true),
                            $token
                        );
                    }
                }
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "%d translation(s) lost a :placeholder token. The value is substituted at render "
            . "time, so a translated or dropped token means the number/name never reaches the "
            . "screen:\n%s",
            count($offenders),
            implode("\n", array_slice($offenders, 0, 40))
        ));
    }

    public function test_language_files_contain_no_month_or_weekday_abbreviations(): void
    {
        $offenders = [];
        $pattern = '/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|Sun|Mon|Tue|Wed|Thu|Fri|Sat)$/';

        foreach ($this->locales() as $locale => $path) {
            foreach (array_keys(include $path) as $key) {
                if (preg_match($pattern, (string) $key)) {
                    $offenders[] = $locale . ': ' . var_export($key, true);
                }
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "%d month/weekday abbreviation(s) are stored as translations. These are date "
            . "data rather than copy, and translate() now refuses to persist them:\n%s",
            count($offenders),
            implode("\n", $offenders)
        ));
    }

    public function test_language_files_contain_no_typographic_quotes_in_keys(): void
    {
        $offenders = [];

        foreach ($this->locales() as $locale => $path) {
            foreach (array_keys(include $path) as $key) {
                $key = (string) $key;
                if (preg_match('/[\x{2018}\x{2019}\x{201C}\x{201D}]/u', $key)) {
                    $offenders[] = $locale . ': ' . var_export($key, true);
                }
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "%d key(s) contain a typographic quote. translate() folds these onto ASCII "
            . "before lookup, so such a key can never be resolved:\n%s",
            count($offenders),
            implode("\n", array_slice($offenders, 0, 40))
        ));
    }

    public function test_no_source_file_passes_a_junk_literal_to_translate(): void
    {
        $offenders = [];

        foreach ($this->sourceFiles() as $relative => $source) {
            foreach ($this->translateCalls($source) as [$offset, $firstArgument]) {
                if (! preg_match('/^\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*$/s', $firstArgument, $literal)) {
                    continue;
                }

                $key = stripcslashes($literal[2]);
                if (preg_match('/^' . self::TRANS_PREFIXES . '/', $key)) {
                    continue;
                }
                if (str_starts_with($key, 'messages.')) {
                    $key = substr($key, 9);
                }

                if (isPersistableTranslationKey($key, applyPolicyLists: false) && $key === trim($key)) {
                    continue;
                }

                $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                $offenders[] = sprintf('%s:%d  translate(%s)', $relative, $line, var_export($key, true));
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "%d translate() call site(s) pass a junk or untrimmed literal. Each one writes a "
            . "permanent row into messages.php.\n"
            . "Fix: a key carries no digit and no :token — print the value after a label, "
            . "outside the call; drop translate() around values with no letters; move "
            . "leading/trailing spaces outside the call.\n%s",
            count($offenders),
            implode("\n", array_slice($offenders, 0, 40))
        ));
    }

    public function test_no_translate_call_passes_replacements(): void
    {
        $offenders = [];

        foreach ($this->sourceFiles() as $relative => $source) {
            foreach ($this->translateCalls($source) as [$offset, $firstArgument, $argumentCount]) {
                if ($argumentCount < 2 || preg_match('/^\s*[\'"]' . self::TRANS_PREFIXES . '/', $firstArgument)) {
                    continue;
                }

                $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                $offenders[] = sprintf('%s:%d  translate(%s, …)', $relative, $line, trim(mb_substr($firstArgument, 0, 80)));
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "%d translate() call site(s) pass a replacement array. A key carries no :token, so "
            . "print the value after a label outside the call: translate('Requests') . ': ' . \$count.\n%s",
            count($offenders),
            implode("\n", array_slice($offenders, 0, 40))
        ));
    }

    private function translateCalls(string $source): \Generator
    {
        if (! preg_match_all('/(?<!->)(?<!::)(?<![\w$])(?<!function )translate\s*\(/', $source, $calls, PREG_OFFSET_CAPTURE)) {
            return;
        }

        $length = strlen($source);

        foreach ($calls[0] as [$match, $offset]) {
            $depth = 1;
            $quote = null;
            $argumentCount = 1;
            $firstArgument = '';

            for ($i = $offset + strlen($match); $i < $length && $depth > 0; $i++) {
                $char = $source[$i];

                if ($quote !== null) {
                    if ($char === '\\') {
                        if ($argumentCount === 1) {
                            $firstArgument .= $char . ($source[$i + 1] ?? '');
                        }
                        $i++;
                        continue;
                    }
                    if ($char === $quote) {
                        $quote = null;
                    }
                    if ($argumentCount === 1) {
                        $firstArgument .= $char;
                    }
                    continue;
                }

                if ($char === "'" || $char === '"') {
                    $quote = $char;
                } elseif (str_contains('([{', $char)) {
                    $depth++;
                } elseif (str_contains(')]}', $char)) {
                    $depth--;
                } elseif ($char === ',' && $depth === 1) {
                    $argumentCount++;
                    continue;
                }

                if ($argumentCount === 1 && $depth > 0) {
                    $firstArgument .= $char;
                }
            }

            yield [$offset, $firstArgument, $argumentCount];
        }
    }

    private function locales(): array
    {
        $found = [];

        foreach (glob(base_path(self::LOCALE_DIR . '/*'), GLOB_ONLYDIR) as $dir) {
            $path = $dir . '/messages.php';
            if (is_file($path)) {
                $found[basename($dir)] = $path;
            }
        }

        $this->assertNotEmpty($found, 'no locale messages.php files discovered');

        return $found;
    }

    private function sourceFiles(): \Generator
    {
        foreach (self::SCAN_DIRS as $dir) {
            $root = base_path($dir);
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || ! preg_match('/\.php$/', $file->getPathname())) {
                    continue;
                }
                if (preg_match('#/(node_modules|storage)/|/Modules/[^/]+/vendor/#', $file->getPathname())) {
                    continue;
                }

                $source = file_get_contents($file->getPathname());
                if ($source === false || ! str_contains($source, 'translate(')) {
                    continue;
                }

                yield substr($file->getPathname(), strlen(base_path()) + 1) => $source;
            }
        }
    }
}
