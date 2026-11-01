<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Prunes junk keys out of resources/lang/<locale>/messages.php.
 *
 * translate() auto-learns unknown keys by appending them to the language file,
 * so anything that reaches it — a price, a DB value, an empty variable — can
 * become a permanent row in the admin Language table. The runtime guard
 * isPersistableTranslationKey() blocks the shapes it can recognise; this
 * command cleans up what is already on disk, using that same guard so the two
 * can never disagree.
 *
 * Keys that look like runtime DATA but are not a recognisable junk SHAPE
 * (demo emails, mock currency labels, keyboard mash) are reported for review
 * rather than deleted, because that call is not safely automatable.
 */
class LanguageFileClean extends Command
{
    protected $signature = 'lang:clean
                            {--fix : Write the cleaned files instead of only reporting}
                            {--with-data : Also remove keys that look like runtime data (demo emails, mock currency labels, keyboard mash)}
                            {--merge-variants : Also fold near-duplicate keys that differ only by "_" vs " " or stripped punctuation}
                            {--locale= : Restrict to a single locale directory}';

    protected $description = 'Report (or with --fix, remove) junk keys in the language files';

    public function handle(): int
    {
        $files = $this->targetFiles();

        if (empty($files)) {
            $this->error('No messages.php files found under resources/lang.');

            return self::FAILURE;
        }

        $fix = (bool) $this->option('fix');
        $totalJunk = 0;
        $totalMerged = 0;
        $totalFolded = 0;
        $review = [];

        // Built once, from the source locale, so every locale collapses to the
        // same surviving key and the files stay aligned.
        $variantMap = [];
        if ($this->option('merge-variants')) {
            $source = $files['en/messages.php'] ?? reset($files);
            $sourceKeys = include $source;
            if (is_array($sourceKeys)) {
                $variantMap = $this->variantMap(array_keys($sourceKeys));
                $this->info('Folding ' . count($variantMap) . ' near-duplicate key(s) into their canonical form.');
            }
        }

        foreach ($files as $label => $path) {
            $original = include $path;
            if (! is_array($original)) {
                $this->warn("$label: not a PHP array, skipped.");

                continue;
            }

            $clean = [];
            $junk = [];
            $merged = 0;
            $folded = 0;
            $rescued = [];

            foreach ($original as $key => $value) {
                $raw = (string) $key;

                if (! isPersistableTranslationKey($raw)) {
                    // A junk shape that somebody nevertheless translated by
                    // hand — three Arabic validation messages are in this
                    // position. Deleting it throws that work away for nothing:
                    // translate() looks a key up before it ever consults the
                    // guard, so the translation still renders. Report and keep.
                    if ($this->looksTranslated($raw, $value)) {
                        $review[] = "$label  [translated, junk shape]  " . var_export($raw, true);
                        $clean[$raw] = $value;

                        continue;
                    }

                    $junk[] = $raw;

                    continue;
                }

                // A near-duplicate of another key. Drop it, but keep its value
                // as a candidate so a real translation is not thrown away.
                if (isset($variantMap[$raw])) {
                    $folded++;
                    if ($this->looksTranslated($raw, $value)) {
                        $rescued[$variantMap[$raw]][] = $value;
                    }

                    continue;
                }

                // translate() trims its input, so an untrimmed key is an
                // unreachable duplicate of the trimmed one.
                $trimmed = trim($raw);
                if ($trimmed !== $raw) {
                    $merged++;
                    if (array_key_exists($trimmed, $original) || isset($clean[$trimmed])) {
                        continue;
                    }
                    $clean[$trimmed] = is_string($value) ? trim($value) : $value;

                    continue;
                }

                if ($reason = $this->dataShapeReason($raw)) {
                    $review[] = "$label  [$reason]  " . var_export($raw, true);
                    if ($this->option('with-data')) {
                        $junk[] = $raw;

                        continue;
                    }
                }

                $clean[$raw] = $value;
            }

            // Carry a dropped variant's translation over when the surviving
            // key has none of its own.
            foreach ($rescued as $survivor => $candidates) {
                if (! array_key_exists($survivor, $clean)) {
                    $clean[$survivor] = $candidates[0];

                    continue;
                }
                if (! $this->looksTranslated($survivor, $clean[$survivor])) {
                    $clean[$survivor] = $candidates[0];
                }
            }

            $totalJunk += count($junk);
            $totalMerged += $merged;
            $totalFolded += $folded;

            $this->line(sprintf(
                '%-26s %6d keys  junk %-4d merged %-4d folded %-4d %s',
                $label,
                count($original),
                count($junk),
                $merged,
                $folded,
                $fix && (count($junk) || $merged || $folded) ? '-> cleaned' : ''
            ));

            foreach (array_slice($junk, 0, 15) as $one) {
                $this->line('    - ' . var_export($one, true));
            }
            if (count($junk) > 15) {
                $this->line('    ... and ' . (count($junk) - 15) . ' more');
            }

            if ($fix && (count($junk) || $merged || $folded)) {
                file_put_contents($path, "<?php return " . var_export($clean, true) . ";\n", LOCK_EX);
            }
        }

        if (! empty($review)) {
            $this->newLine();
            $this->warn('Looks like runtime data — review by hand, not removed automatically:');
            foreach (array_slice($review, 0, 40) as $one) {
                $this->line('    ' . $one);
            }
            if (count($review) > 40) {
                $this->line('    ... and ' . (count($review) - 40) . ' more');
            }
        }

        $this->newLine();
        $this->info($fix
            ? "Cleaned: removed {$totalJunk} junk key(s), merged {$totalMerged} untrimmed key(s), folded {$totalFolded} duplicate variant(s)."
            : "Found {$totalJunk} junk key(s), {$totalMerged} untrimmed key(s) and {$totalFolded} duplicate variant(s). Re-run with --fix to apply.");

        return self::SUCCESS;
    }

    /**
     * False when the value is just the auto-generated echo of the key, which
     * is what translate() writes for a key nobody has translated yet.
     */
    private function looksTranslated(string $key, $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        $auto = ucfirst(str_replace('_', ' ', $key));

        return $value !== $key && $value !== $auto && trim($value) !== trim($auto);
    }

    /**
     * Collapses a key to the text it actually renders as, so the historical
     * mangling can be undone: translate() turns "_" into " " for display, and
     * removeSpecialCharacters() rewrites ' " , ; < > ? to a space. One string
     * therefore ends up as 2-4 separate rows in the Language table.
     *
     * Case is deliberately NOT folded — "default" and "Default" are each used
     * at ~1,000 call sites and render different text, so folding them is a far
     * bigger change than de-duplicating punctuation twins.
     */
    /**
     * The text a key renders as once translate() turns "_" into " ".
     * Stricter than canonicalForm(): punctuation is significant here, so
     * "Delete" and "Delete?" are NOT the same rendered form.
     */
    private function renderedForm(string $key): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace('_', ' ', $key)));
    }

    private function canonicalForm(string $key): string
    {
        $s = str_replace('_', ' ', $key);
        $s = str_ireplace(["'", '"', ',', ';', '<', '>', '?'], ' ', $s);

        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    /**
     * Every key passed to translate() as a static literal anywhere in the
     * codebase. A variant that code still references must not be deleted —
     * it would simply be re-learned on the next page render.
     */
    private function referencedKeys(): array
    {
        static $keys = null;
        if ($keys !== null) {
            return $keys;
        }

        $keys = [];
        foreach (['resources/views', 'app', 'Modules', 'routes'] as $dir) {
            $root = base_path($dir);
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getPathname(), '.php')) {
                    continue;
                }
                // Only Composer's vendor/ is skipped. A plain "/vendor/" test
                // also matched resources/views/layouts/vendor and every
                // Modules/*/Resources/views/vendor tree, so the whole vendor
                // dashboard read as unreferenced and its keys were foldable.
                if (preg_match('#/(node_modules|storage)/#', $file->getPathname())
                    || preg_match('#/Modules/[^/]+/vendor/#', $file->getPathname())) {
                    continue;
                }

                $source = file_get_contents($file->getPathname());
                if ($source === false || ! str_contains($source, 'translate(')) {
                    continue;
                }

                if (preg_match_all('/\btranslate\s*\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s', $source, $m, PREG_SET_ORDER)) {
                    foreach ($m as $one) {
                        $key = stripcslashes($one[2]);
                        if (str_starts_with($key, 'messages.')) {
                            $key = substr($key, 9);
                        }
                        $keys[trim($key)] = true;
                    }
                }
            }
        }

        return $keys;
    }

    /*
     * Every quoted string literal in the codebase, not just the ones sitting
     * inside translate().
     *
     * referencedKeys() only sees literal translate() calls, so it cannot see
     * the two ways a key reaches translate() indirectly:
     *
     *   self::storeRow('Campaign Join Approval', $id, 'Get notification on …')
     *   translate($order->status)   // 'pending', 'cash_on_delivery', …
     *
     * Both look unreferenced, so --merge-variants was free to fold them into a
     * spaced spelling — and 53 DB enum values had exactly that shape, which
     * would have silently broken translate($row->status) for every one of
     * them. A key appearing anywhere as a literal is treated as live here.
     */
    private function literalStrings(): array
    {
        static $literals = null;
        if ($literals !== null) {
            return $literals;
        }

        $literals = [];
        foreach (['resources/views', 'app', 'Modules', 'routes', 'config', 'database'] as $dir) {
            $root = base_path($dir);
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getPathname(), '.php')) {
                    continue;
                }
                if (preg_match('#/(node_modules|storage)/#', $file->getPathname())
                    || preg_match('#/Modules/[^/]+/vendor/#', $file->getPathname())) {
                    continue;
                }

                $source = file_get_contents($file->getPathname());
                if ($source === false) {
                    continue;
                }

                if (preg_match_all('/([\'"])((?:\\\\.|(?!\1).){1,300})\1/s', $source, $m, PREG_SET_ORDER)) {
                    foreach ($m as $one) {
                        $literals[trim(stripcslashes($one[2]))] = true;
                    }
                }
            }
        }

        return $literals;
    }

    /**
     * Picks the variant to keep and the ones to drop, per duplicate group.
     * Groups where more than one variant is still referenced in code are left
     * alone — those need the call sites converged first.
     *
     * @return array<string,string> dropped key => surviving key
     */
    private function variantMap(array $keys): array
    {
        $referenced = $this->referencedKeys() + $this->literalStrings();
        $groups = [];

        foreach ($keys as $key) {
            $groups[$this->canonicalForm((string) $key)][] = (string) $key;
        }

        $map = [];

        foreach ($groups as $group) {
            if (count($group) < 2) {
                continue;
            }

            $inCode = array_values(array_filter($group, fn($k) => isset($referenced[$k])));

            if (count($inCode) > 1) {
                // Several spellings are still live in code, so there is no
                // single survivor. Orphaned variants can still be folded into
                // whichever live spelling they render identically to — same
                // text once "_" becomes " ".
                foreach ($group as $key) {
                    if (isset($referenced[$key])) {
                        continue;
                    }
                    foreach ($inCode as $live) {
                        if ($this->renderedForm($key) === $this->renderedForm($live)) {
                            $map[$key] = $live;
                            break;
                        }
                    }
                }

                continue;
            }

            if (count($inCode) === 1) {
                $keep = $inCode[0];
            } else {
                // No call site: keep the best-formed variant — real spaces
                // over underscores, then the longest (most punctuation intact).
                usort($group, function ($a, $b) {
                    $au = substr_count($a, '_');
                    $bu = substr_count($b, '_');
                    if ($au !== $bu) {
                        return $au <=> $bu;
                    }

                    return mb_strlen($b) <=> mb_strlen($a);
                });
                $keep = $group[0];
            }

            foreach ($group as $key) {
                if ($key !== $keep) {
                    $map[$key] = $keep;
                }
            }
        }

        return $map;
    }

    /** @return array<string,string> label => absolute path */
    private function targetFiles(): array
    {
        $only = $this->option('locale');
        $found = [];

        foreach (glob(base_path('resources/lang/*'), GLOB_ONLYDIR) as $dir) {
            $locale = basename($dir);
            if ($only && $locale !== $only) {
                continue;
            }

            foreach (['messages.php', 'new-messages.php'] as $file) {
                if (is_file("$dir/$file")) {
                    $found["$locale/$file"] = "$dir/$file";
                }
            }
        }

        return $found;
    }

    /**
     * Keys that smell like runtime data rather than UI copy. Reported only —
     * these overlap with legitimate help text (e.g. "Example: If packaging
     * costs $2 ...") so deleting them automatically is not safe.
     */
    private function dataShapeReason(string $key): ?string
    {
        // Numbers, ratios, dimensions and file extensions are data, not
        // translatable text ("1352 X 250 px", ".jpeg, .jpg ... 1MB", "S3").
        // Only flagged when NO code references the key: a referenced one needs
        // its call site restructured to interpolate the value, and deleting
        // the row would just have it re-learned on the next render.
        if (! isset($this->referencedKeys()[$key])
            && (preg_match('/\.(jpe?g|png|gif|webp|svg|pdf|docx?|xlsx?|csv|zip|mp4|mp3)\b/i', $key)
                || preg_match('/\d+\s*[xX×]\s*\d+/u', $key)
                || preg_match('/\d+\s*:\s*\d+/', $key)
                || preg_match('/\d/', $key))) {
            return 'data-number';
        }

        // Mojibake: UTF-8 that was decoded as Latin-1 and re-encoded, so
        // ‘ ’ “ ” ' became "â"/"Ã"/"Â" sequences ("Letâs", "âONâ").
        // Every key in this project is English source text, which never
        // legitimately contains these characters.
        if (preg_match('/[\x{00E2}\x{00C3}\x{00C2}]/u', $key)) {
            return 'mojibake';
        }

        // "Ex: john@example.com" is a placeholder hint shown in a form, not
        // leaked data — the "Ex:" prefix is real, translatable UI copy.
        // No \b here: "_" is a word character, so it would not match "Ex_:_...".
        $isExample = (bool) preg_match('/^\s*(ex|e\.g|example|for ex)[\s_:.]/i', $key);

        if (! $isExample && preg_match('/[\w.+-]+@[\w-]+\.[\w.]{2,}/', $key)) {
            return 'email';
        }

        // A short label dominated by a currency amount: "Order place ($ 1,109)".
        if (mb_strlen($key) <= 45 && preg_match('/[$€£¥₹]\s*[\d,]+(\.\d+)?/u', $key)) {
            return 'currency';
        }

        // Two or more long vowel-less tokens: "22cdsdcsdcv sdcsdc sdcvsdcv".
        $mash = 0;
        foreach (preg_split('/[\s_]+/', $key, -1, PREG_SPLIT_NO_EMPTY) as $token) {
            $token = preg_replace('/[^A-Za-z]/', '', $token);
            if (strlen($token) < 5 || preg_match('/[aeiou]/i', $token)) {
                continue;
            }
            // "xxxxx"/"nnnn" are deliberate placeholder masks, not mash.
            if (count(array_unique(str_split(strtolower($token)))) === 1) {
                continue;
            }
            $mash++;
        }

        return $mash >= 2 ? 'keyboard-mash' : null;
    }
}
