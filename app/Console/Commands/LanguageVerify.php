<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Verifies a bulk key rewrite against the committed language files.
 *
 * Renaming a translation key is three edits that must land together: the key in
 * every locale file, the literal at every call site, and the ar/bn value that
 * has to travel with it. Miss one and nothing throws — the key simply renders
 * as itself in English, which looks fine on an English screen and is a silent
 * regression everywhere else. translation.md §9 requires this to report zero
 * before a rewrite is considered done.
 */
class LanguageVerify extends Command
{
    protected $signature = 'lang:verify
                            {--ref=HEAD : Git ref to compare the language files against}
                            {--map= : JSON file of {"old key":"new key"} for a precise translation check}';

    protected $description = 'Check a bulk key rewrite for regressions, lost translations and broken call sites';

    private const LOCALES = ['en', 'ar', 'bn'];

    public function handle(): int
    {
        $ref = (string) $this->option('ref');

        $before = [];
        foreach (self::LOCALES as $locale) {
            $old = $this->fileAtRef($ref, "resources/lang/$locale/messages.php");
            if ($old === null) {
                $this->error("Cannot read resources/lang/$locale/messages.php at $ref.");

                return self::FAILURE;
            }
            $before[$locale] = $old;
        }

        $after = [];
        foreach (self::LOCALES as $locale) {
            $after[$locale] = include base_path("resources/lang/$locale/messages.php");
        }

        $referenced = $this->referencedKeys();

        // Strings the admin panel renders in English on purpose (translation.md §8).
        // They are referenced and deliberately absent from the language files, so
        // they are not regressions — without this every entry on the list would be
        // reported as one.
        $excludedPath = base_path('resources/lang/excluded-keys.php');
        $excluded = file_exists($excludedPath) ? include $excludedPath : [];
        $excluded = is_array($excluded) ? array_flip($excluded) : [];

        // 1. A key a call site still asks for that used to exist and no longer does.
        //
        //    Two kinds of absence are deliberate and must not be reported: a key on
        //    the English-only list, and one the guard refuses outright (a brand, a
        //    hostname, a ratio — §5). Both render as themselves, so their row is
        //    dead weight and removing it is the correct outcome, not a regression.
        $regressions = [];
        foreach ($referenced as $key => $_) {
            if (isset($excluded[$key]) || ! isPersistableTranslationKey($key)) {
                continue;
            }
            if (! array_key_exists($key, $after['en']) && array_key_exists($key, $before['en'])) {
                $regressions[] = $key;
            }
        }

        // 2. A hand-written translation that no key carries any more.
        //
        //    With --map this is exact: every renamed key must land on a target that
        //    still has a real translation. Without it, the check falls back to asking
        //    whether the string survives anywhere — which over-reports, because a
        //    merge legitimately drops one of two translations for the same phrase.
        //    So the fallback is a warning and only the mapped check can fail a run.
        $map = $this->renameMap();
        $lost = [];
        $dropped = [];

        foreach (['ar', 'bn'] as $locale) {
            $surviving = array_flip(array_map(fn ($v) => trim((string) $v), $after[$locale]));
            $orphaned = 0;
            $mergeDrops = 0;

            foreach ($before[$locale] as $key => $value) {
                if (! $this->isRealTranslation($key, $value) || isset($excluded[$key])) {
                    continue;
                }

                if ($map !== null) {
                    $target = $map[$key] ?? $key;
                    if (! array_key_exists($target, $after[$locale])
                        || ! $this->isRealTranslation($target, $after[$locale][$target])) {
                        $orphaned++;
                    }

                    continue;
                }

                if (! isset($surviving[trim((string) $value)])) {
                    $mergeDrops++;
                }
            }

            $lost[$locale] = $orphaned;
            $dropped[$locale] = $mergeDrops;
        }

        // 3. Keys the source asks for that no locale file has. Pre-existing ones are
        //    normal (auto-collection fills them in); only the delta is a defect, so
        //    this is reported for context rather than failed on.
        $missingNow = 0;
        $missingBefore = 0;
        foreach ($referenced as $key => $_) {
            if (! array_key_exists($key, $after['en'])) {
                $missingNow++;
            }
            if (! array_key_exists($key, $before['en'])) {
                $missingBefore++;
            }
        }

        $this->line('');
        $this->line(sprintf('  %-46s %s', 'call-site keys lost by this change', $this->fmt(count($regressions))));

        if ($map !== null) {
            $this->line(sprintf('  %-46s %s', 'ar translations orphaned by a rename', $this->fmt($lost['ar'])));
            $this->line(sprintf('  %-46s %s', 'bn translations orphaned by a rename', $this->fmt($lost['bn'])));
        } else {
            $this->line(sprintf('  %-46s %d', 'ar translation strings no longer present', $dropped['ar']));
            $this->line(sprintf('  %-46s %d', 'bn translation strings no longer present', $dropped['bn']));
            $this->line('    (expected when merging duplicates — pass --map to check exactly)');
        }
        $this->line('');
        $this->line(sprintf('  %-46s %d -> %d', 'en keys', count($before['en']), count($after['en'])));
        $this->line(sprintf('  %-46s %d -> %d', 'referenced but absent (auto-collected)', $missingBefore, $missingNow));
        $this->line('');

        if ($regressions) {
            $this->warn('Keys a call site still asks for, now missing:');
            foreach (array_slice($regressions, 0, 20) as $key) {
                $this->line('    ' . $key);
            }
            if (count($regressions) > 20) {
                $this->line('    … and ' . (count($regressions) - 20) . ' more');
            }
        }

        $failed = $regressions || $lost['ar'] || $lost['bn'];

        if ($failed) {
            $this->error('FAIL — see translation.md §9.');

            return self::FAILURE;
        }

        $this->info('OK — no regressions, no translations lost.');

        return self::SUCCESS;
    }

    private function fmt(int $n): string
    {
        return $n === 0 ? '0' : "$n  <-- FIX";
    }

    /*
     * Merges the rename maps handed in via --map. Accepts one path or several
     * comma-separated, since a pass is usually applied as a sequence of maps.
     */
    private function renameMap(): ?array
    {
        $option = (string) $this->option('map');
        if ($option === '') {
            return null;
        }

        $map = [];
        foreach (explode(',', $option) as $path) {
            $path = trim($path);
            if ($path === '' || ! is_file($path)) {
                $this->warn("Map file not found, skipped: $path");

                continue;
            }
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $map += $decoded;
            }
        }

        // a -> b -> c collapses to a -> c, so a key renamed twice is checked
        // against where it actually ended up.
        foreach ($map as $from => $to) {
            $seen = [$from => true];
            while (isset($map[$to]) && ! isset($seen[$to])) {
                $seen[$to] = true;
                $to = $map[$to];
            }
            $map[$from] = $to;
        }

        return $map;
    }

    /*
     * A value counts as translated when it is not simply the English source
     * phrase the key already renders as. Mirrors the same test in
     * LanguageController and LanguageFileClean.
     */
    private function isRealTranslation($key, $value): bool
    {
        $value = trim((string) $value);
        if ($value === '') {
            return false;
        }

        $source = ucfirst(trim(preg_replace('/\s+/u', ' ', str_replace('_', ' ', (string) $key))));

        return strcasecmp($value, $source) !== 0;
    }

    /*
     * Every key a literal call site asks for, normalised the way translate()
     * normalises before lookup: typographic quotes folded to ASCII, a
     * "messages." prefix stripped, trimmed.
     */
    private function referencedKeys(): array
    {
        $keys = [];
        $pattern = '/(?:\btranslate|\b__|\btrans|@lang)\s*\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s';

        foreach (['resources/views', 'app', 'Modules', 'routes', 'config', 'database'] as $dir) {
            $root = base_path($dir);
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                $path = $file->getPathname();
                if (! $file->isFile() || ! str_ends_with($path, '.php')) {
                    continue;
                }
                // Composer's vendor only. A bare "/vendor/" test also matches
                // resources/views/layouts/vendor and Modules/*/Resources/views/vendor.
                if (preg_match('#/(node_modules|storage)/#', $path)
                    || preg_match('#/Modules/[^/]+/vendor/#', $path)) {
                    continue;
                }

                $source = file_get_contents($path);
                if ($source === false || ! preg_match_all($pattern, $source, $matches, PREG_SET_ORDER)) {
                    continue;
                }

                foreach ($matches as $match) {
                    $key = $match[1] === "'"
                        ? str_replace(["\\'", '\\\\'], ["'", '\\'], $match[2])
                        : stripcslashes($match[2]);

                    $key = strtr(trim($key), [
                        "\u{2018}" => "'", "\u{2019}" => "'",
                        "\u{201C}" => '"', "\u{201D}" => '"',
                    ]);

                    if (str_starts_with($key, 'messages.')) {
                        $key = trim(substr($key, 9));
                    }

                    if ($key !== '') {
                        $keys[$key] = true;
                    }
                }
            }
        }

        return $keys;
    }

    private function fileAtRef(string $ref, string $path): ?array
    {
        $escapedRef = escapeshellarg("$ref:$path");
        $output = shell_exec("cd " . escapeshellarg(base_path()) . " && git show $escapedRef 2>/dev/null");

        if (! is_string($output) || trim($output) === '') {
            return null;
        }

        $data = @eval('?>' . $output);

        return is_array($data) ? $data : null;
    }
}
