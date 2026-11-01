<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class LanguageController extends Controller
{
    /* Row counts the translate screen offers. Anything else falls back to the
       configured default, so ?limit= can't be used to render the whole file. */
    private const PER_PAGE_OPTIONS = [25, 50, 100, 200];

    /* sourceLabel() runs a regex per key and is asked for the same keys over
       and over — once per row on the translate screen, and once per language
       on the index. Memoised per request, not per language: the key set is
       shared, so the second language reads the first one's work. */
    private array $sourceLabels = [];

    public function index()
    {
        $setting = BusinessSetting::where('key', 'system_language')->first();

        if (!$setting) {
            Helpers::insert_business_settings_key('system_language', '[{"id":1,"direction":"ltr","code":"en","status":1,"default":true}]');
            $setting = BusinessSetting::where('key', 'system_language')->first();
        }

        $configured = json_decode($setting?->value, true) ?? [];
        $languages = [];

        foreach ($configured as $index => $data) {
            $code = (string) ($data['code'] ?? '');
            /* LANGUAGE_NAMES stores "English name - native spelling"
               ("Arabic - العربية"). Split so the card can print the native
               name underneath; codes with no entry fall back to the code. */
            [$name, $native] = array_pad(explode(' - ', Helpers::get_language_name($code), 2), 2, null);

            $languages[] = [
                'index' => $index,
                'code' => $code,
                'name' => $name,
                'native' => $native,
                'direction' => ($data['direction'] ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr',
                'status' => (int) ($data['status'] ?? 0) === 1,
                'default' => (bool) ($data['default'] ?? false),
                'progress' => $this->languageProgress($code),
            ];
        }

        $default = null;
        foreach ($languages as $language) {
            if ($language['default']) {
                $default = $language;
                break;
            }
        }

        $summary = [
            'total' => count($languages),
            'active' => count(array_filter($languages, fn ($language) => $language['status'])),
            'inactive' => count(array_filter($languages, fn ($language) => !$language['status'])),
            'default' => $default,
        ];

        return view('admin-views.business-settings.language.index', compact('languages', 'summary'));
    }

    /*
     * Completion figures for a locale, so the list can show how much of each
     * language is actually written rather than just that it exists.
     *
     * Costs one include() and one pass over ~11k keys per language (~10ms).
     * Returns null when the file is missing, which the view renders as an
     * explicit "file missing" state instead of a misleading 0%.
     */
    private function languageProgress(string $code): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{2,10}$/', $code)) {
            return null;
        }

        $path = base_path('resources/lang/' . $code . '/messages.php');
        if (!file_exists($path)) {
            return null;
        }

        $data = include($path);
        if (!is_array($data)) {
            return null;
        }

        $total = 0;
        $translated = 0;

        foreach ($data as $key => $value) {
            if (is_null($value)) {
                continue;
            }
            $total++;
            if ($this->isTranslatedValue($code, $key, $value)) {
                $translated++;
            }
        }

        // ~850KB per language file; released before the next one is read.
        unset($data);

        return [
            'total' => $total,
            'translated' => $translated,
            'pending' => $total - $translated,
            'percentage' => $total > 0 ? round(($translated / $total) * 100, 1) : 0,
        ];
    }

    public function store(Request $request)
    {
        $language = BusinessSetting::where('key', 'system_language')->first();
        $existingLanguages = json_decode($language?->value, true) ?? [];

        foreach ($existingLanguages as $data) {
            if ($data['code'] === $request->code) {
                Toastr::error('Language already exists!');
                return back();
            }
        }
        $lang_array = [];
        $codes = [];
        foreach (json_decode($language?->value, true) as $key => $data) {
            if ($data['code'] != $request['code']) {
                if (!array_key_exists('default', $data)) {
                    $default = array('default' => ($data['code'] == 'en') ? true : false);
                    $data = array_merge($data, $default);
                }
                array_push($lang_array, $data);
                array_push($codes, $data['code']);
            }
        }
        array_push($codes, $request['code']);

        if (!file_exists(base_path('resources/lang/' . $request['code']))) {
            mkdir(base_path('resources/lang/' . $request['code']), 0777, true);
        }

        $lang_file = fopen(base_path('resources/lang/' . $request['code'] . '/' . 'messages.php'), "w") or die("Unable to open file!");
        $read = file_get_contents(base_path('resources/lang/en/messages.php'));
        fwrite($lang_file, $read);

        $lang_array[] = [
            'id' => $request['code'].count(json_decode($language['value'], true)) + 1,
            'code' => $request['code'],
            'direction' => $request['direction'],
            'status' => 0,
            'default' => false,
        ];
        Helpers::businessUpdateOrInsert(['key' => 'system_language'], [
            'value' => $lang_array
        ]);

        Helpers::businessUpdateOrInsert(['key' => 'language'], [
            'value' => json_encode($codes),
        ]);

        Toastr::success('Language Added!');
        return back();
    }

    public function update_status(Request $request)
    {
        $language = BusinessSetting::where('key', 'system_language')->first();
        $lang_array = [];
        foreach (json_decode($language?->value, true) as $key => $data) {

            if ($data['code'] == $request['code']) {
                if( array_key_exists('default', $data) && $data['default'] == true ){
                    return response()->json(['error' => 403]);
                }
                $lang = [
                    'id' => $data['id'],
                    'direction' => $data['direction'] ?? 'ltr',
                    'code' => $data['code'],
                    'status' => $data['status'] == 1 ? 0 : 1,
                    'default' => (array_key_exists('default', $data) ? $data['default'] : (($data['code'] == 'en') ? true : false)),
                ];
                $lang_array[] = $lang;
            } else {
                $lang = [
                    'id' => $data['id'],
                    'direction' => $data['direction'] ?? 'ltr',
                    'code' => $data['code'],
                    'status' => $data['status'],
                    'default' => (array_key_exists('default', $data) ? $data['default'] : (($data['code'] == 'en') ? true : false)),
                ];
                $lang_array[] = $lang;
            }
        }
        $businessSetting = Helpers::businessUpdateOrInsert(['key' => 'system_language'], [
            'value' => $lang_array
        ]);
        return $businessSetting;
    }

    public function update_default_status(Request $request)
    {
        $language = BusinessSetting::where('key', 'system_language')->first();


        $lang_array = [];
        foreach (json_decode($language?->value, true) as $key => $data) {
            if ($data['code'] == $request['code']) {

               if($data['default'] == true){
                Toastr::warning(translate('messages.You can not change the default status of this language'));
                return back();
               }

                $lang = [
                    'id' => $data['id'],
                    'direction' => $data['direction'] ?? 'ltr',
                    'code' => $data['code'],
                    'status' => 1,
                    'default' => true,
                ];
                $lang_array[] = $lang;
            } else {
                $lang = [
                    'id' => $data['id'],
                    'direction' => $data['direction'] ?? 'ltr',
                    'code' => $data['code'],
                    'status' => $data['status'],
                    'default' => false,
                ];
                $lang_array[] = $lang;
            }
        }

        Helpers::businessUpdateOrInsert(['key' => 'system_language'], [
            'value' => $lang_array
        ]);

        $direction = Helpers::get_business_settings('site_direction', false) ?? 'ltr';
        $language = BusinessSetting::where('key', 'system_language')->first();
        foreach (json_decode($language?->value, true) as $key => $data) {
            if ($data['code'] == $request['code']) {
                $direction = isset($data['direction']) ? $data['direction'] : 'ltr';
            }
        }
        session()->forget('language_settings');
        Helpers::language_load();
        session()->put('local', $request['code']);
        session()->put('site_direction', $direction);
        Toastr::success('Default Language Changed!');
        return back();
    }

    public function update(Request $request)
    {
        $language = BusinessSetting::where('key', 'system_language')->first();
        $lang_array = [];
        foreach (json_decode($language?->value, true) as $key => $data) {
            if ($data['code'] == $request['old_code']) {
                $lang = [
                    'id' => $data['id'],
                    'direction' => $request['direction'] ?? 'ltr',
                    'code' => $request['code'],
                    'status' => $data['status'],
                    'default' => (array_key_exists('default', $data) ? $data['default'] : (($data['code'] == 'en') ? true : false)),
                ];
                $lang_array[] = $lang;
            } else {
                $lang = [
                    'id' => $data['id'],
                    'direction' => $data['direction'] ?? 'ltr',
                    'code' => $data['code'],
                    'status' => $data['status'],
                    'default' => (array_key_exists('default', $data) ? $data['default'] : (($data['code'] == 'en') ? true : false)),
                ];
                $lang_array[] = $lang;
            }
        }

        Helpers::businessUpdateOrInsert(['key' => 'system_language'], [
            'value' => $lang_array
        ]);

        if($request->code != $request->old_code){
            $dir = base_path('resources/lang/' . $request['old_code']);
            if (File::isDirectory($dir)) {
                rename($dir, base_path('resources/lang/' . $request['code']));
            }

            $codes = [];
            foreach ($lang_array as $key => $data) {
                array_push($codes, $data['code']);
            }
            Helpers::businessUpdateOrInsert(['key' => 'language'], [
                'value' => json_encode($codes),
            ]);
        }

    Toastr::success('Language updated!');
    return back();
    }

    public function convertArrayToCollection($lang, $items, $perPage = null, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        $options = [
        "path" => route('admin.business-settings.language.translate',[$lang]),
        "pageName" => "page"
        ];
        return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }

    public function translate(Request $request, $lang)
    {
        // Same guard the write endpoints use: {lang} is concatenated into a
        // filesystem path, so an unconfigured code must never reach include().
        $path = $this->languageFilePath($lang);
        if (!$path) {
            Toastr::error(translate('messages.Invalid language selected'));

            return redirect()->route('admin.business-settings.language.index');
        }

        $searchTerm = trim((string) $request->query('search', ''));
        $status = in_array($request->query('status'), ['translated', 'pending'], true)
            ? $request->query('status')
            : 'all';

        $default_limit = (int) config('default_pagination', 25) ?: 25;
        $limit = (int) $request->query('limit', $default_limit);
        if (!in_array($limit, self::PER_PAGE_OPTIONS, true)) {
            $limit = $default_limit;
        }

        $full_data = include($path);
        // Only nulls are dropped. Blank values are exactly the rows that still
        // need work, so they stay listed instead of being invisible.
        $full_data = array_filter($full_data, fn($value) => !is_null($value));

        $translated_count = 0;
        foreach ($full_data as $key => $value) {
            if ($this->isTranslatedValue($lang, $key, $value)) {
                $translated_count++;
            }
        }

        $total = count($full_data);
        $stats = [
            'total' => $total,
            'translated' => $translated_count,
            'pending' => $total - $translated_count,
            'percentage' => $total > 0 ? round(($translated_count / $total) * 100, 1) : 0,
        ];

        if ($status !== 'all') {
            $wanted = $status === 'translated';
            $full_data = array_filter($full_data, function ($value, $key) use ($lang, $wanted) {
                return $this->isTranslatedValue($lang, $key, $value) === $wanted;
            }, ARRAY_FILTER_USE_BOTH);
        }

        if ($searchTerm !== '') {
            $full_data = array_filter($full_data, function ($value, $key) use ($searchTerm) {
                return (stripos((string) $value, $searchTerm) !== false)
                    || (stripos($key, $searchTerm) !== false)
                    || (stripos($this->sourceLabel($key), $searchTerm) !== false);
            }, ARRAY_FILTER_USE_BOTH);
        }

        ksort($full_data);
        $result_count = count($full_data);
        $direction = $this->languageDirection($lang);
        $full_data = $this->convertArrayToCollection($lang, $full_data, $limit);

        // Resolve the per-row presentation once, so the view never has to
        // re-derive what "translated" means.
        $full_data->through(fn ($value, $key) => [
            'source' => $this->sourceLabel($key),
            'value' => (string) $value,
            'translated' => $this->isTranslatedValue($lang, $key, $value),
        ]);

        return view('admin-views.business-settings.language.translate', compact(
            'lang', 'full_data', 'stats', 'status', 'searchTerm', 'limit', 'direction', 'result_count'
        ));
    }

    /*
     * A row counts as translated when it has a value that is no longer the
     * English source phrase. English itself is the source, so any non-empty
     * value there is complete.
     */
    private function isTranslatedValue($lang, $key, $value): bool
    {
        $value = trim((string) $value);

        if ($value === '') {
            return false;
        }

        if ($lang === 'en') {
            return true;
        }

        return strcasecmp($value, trim($this->sourceLabel($key))) !== 0;
    }

    /*
     * The English phrase a key stands for.
     *
     * Deliberately does NOT run the key through remove_invalid_charcaters():
     * that strips ' " ; < >, so a key like
     *   'Click Plus icon -> select App IDs -> click on Continue'
     * rendered as '... - select App IDs - ...' beside the untouched key, and
     * the row read as two near-identical duplicates. Only the underscore
     * convention is unwound here, so the column shows the real source text.
     */
    private function sourceLabel($key): string
    {
        $key = (string) $key;

        return $this->sourceLabels[$key]
            ??= ucfirst(trim(preg_replace('/\s+/u', ' ', preg_replace_callback(
                '/:[a-zA-Z_][a-zA-Z0-9_]*|_/',
                fn ($match) => $match[0] === '_' ? ' ' : $match[0],
                $key
            ))));
    }

    private function keyPlaceholders(string $key): array
    {
        return preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $key, $matches)
            ? array_values(array_unique($matches[0]))
            : [];
    }

    private function missingPlaceholders(string $key, string $value): array
    {
        return array_values(array_filter(
            $this->keyPlaceholders($key),
            fn ($token) => !str_contains($value, $token)
        ));
    }

    private function placeholderRefusal(array $missing): string
    {
        return translate('messages.Each placeholder is replaced with a value when the page renders.') . ' ' . translate('messages.Keep these placeholders exactly as written') . ': ' . implode(', ', $missing);
    }

    private function languageDirection($lang): string
    {
        $configured = json_decode(BusinessSetting::where('key', 'system_language')->first()?->value, true) ?? [];

        foreach ($configured as $data) {
            if (($data['code'] ?? null) === $lang) {
                return ($data['direction'] ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr';
            }
        }

        return 'ltr';
    }

    /*
     * Resolves the messages.php path for a locale, but only for locales that
     * are actually configured. {lang} is a raw route segment concatenated
     * into a filesystem path, so it must never be trusted as-is.
     */
    private function languageFilePath($lang): ?string
    {
        if (!is_string($lang) || !preg_match('/^[A-Za-z0-9_-]{2,10}$/', $lang)) {
            return null;
        }

        $configured = json_decode(BusinessSetting::where('key', 'system_language')->first()?->value, true) ?? [];
        $codes = array_column($configured, 'code');
        $codes[] = (string) config('app.locale');
        if (!in_array($lang, $codes, true)) {
            return null;
        }

        $path = base_path('resources/lang/' . $lang . '/messages.php');

        return file_exists($path) ? $path : null;
    }

    public function translate_key_remove(Request $request, $lang)
    {
        $path = $this->languageFilePath($lang);
        if (!$path) {
            return response()->json(['message' => 'Invalid language'], 422);
        }

        $full_data = include($path);
        $key = $this->requestedKey($request);

        if (!array_key_exists($key, $full_data)) {
            return response()->json(['message' => 'Unknown translation key'], 422);
        }

        unset($full_data[$key]);
        $str = "<?php return " . var_export($full_data, true) . ";";
        file_put_contents($path, $str, LOCK_EX);

        return response()->json(['message' => 'removed']);
    }

    /*
     * Reads the posted key from the body only, and never as an array:
     * "key[]=x" made the old (string) cast raise an Array to string
     * conversion error instead of the 422 the caller expects.
     */
    private function requestedKey(Request $request): string
    {
        $key = $request->post('key');

        return is_scalar($key) ? (string) $key : '';
    }

    public function translate_submit(Request $request, $lang)
    {
        $path = $this->languageFilePath($lang);
        if (!$path) {
            return response()->json(['message' => 'Invalid language'], 422);
        }

        $full_data = include($path);
        $key = $this->requestedKey($request);

        // Edit-only. This endpoint saves a translation for a key that is
        // already in the file; letting it create keys means any POST body
        // becomes a permanent row in the admin Language table.
        if (!array_key_exists($key, $full_data)) {
            return response()->json(['message' => 'Unknown translation key'], 422);
        }

        $value = $request->post('value');
        $value = is_scalar($value) ? (string) $value : '';

        $missing = $this->missingPlaceholders($key, $value);
        if ($missing) {
            return response()->json(['message' => $this->placeholderRefusal($missing)], 422);
        }

        $full_data[$key] = $value;
        $str = "<?php return " . var_export($full_data, true) . ";";
        file_put_contents($path, $str, LOCK_EX);

        return response()->json([
            'message' => 'updated',
            'translated' => $this->isTranslatedValue($lang, $key, $full_data[$key]),
        ]);
    }

    /*
     * Saves every edited row in one write. The per-key endpoint re-serialises
     * the whole ~800KB file per request, so saving a page of 25 rows one by one
     * meant 25 full rewrites. Payload arrives as JSON: keys are free-form
     * English phrases and some contain characters form encoding mangles.
     */
    public function translate_bulk_submit(Request $request, $lang): \Illuminate\Http\JsonResponse
    {
        $path = $this->languageFilePath($lang);
        if (!$path) {
            return response()->json(['message' => 'Invalid language'], 422);
        }

        $translations = $request->json('translations');
        if (!is_array($translations) || count($translations) === 0) {
            return response()->json(['message' => translate('messages.Nothing to update')], 422);
        }

        $full_data = include($path);
        $updated = [];
        $skipped = 0;
        $placeholder_errors = [];

        foreach ($translations as $key => $value) {
            $key = (string) $key;

            // Edit-only, same rule as translate_submit().
            if (!array_key_exists($key, $full_data) || (!is_scalar($value) && !is_null($value))) {
                $skipped++;
                continue;
            }

            $value = (string) $value;
            $missing = $this->missingPlaceholders($key, $value);

            if ($missing) {
                $placeholder_errors[$key] = $missing;
                $skipped++;
                continue;
            }

            $full_data[$key] = $value;
            $updated[$key] = $this->isTranslatedValue($lang, $key, $full_data[$key]);
        }

        if (count($updated) > 0) {
            $str = "<?php return " . var_export($full_data, true) . ";";
            file_put_contents($path, $str, LOCK_EX);
        }

        return response()->json([
            'message' => 'updated',
            'updated' => count($updated),
            'skipped' => $skipped,
            'states' => $updated,
            'placeholder_errors' => $placeholder_errors,
            'placeholder_message' => $placeholder_errors
                ? $this->placeholderRefusal(array_values(array_unique(array_merge(...array_values($placeholder_errors)))))
                : null,
        ]);
    }

    public function auto_translate(Request $request, $lang): \Illuminate\Http\JsonResponse
    {
        $path = $this->languageFilePath($lang);
        if (!$path) {
            return response()->json(['message' => 'Invalid language'], 422);
        }

        $full_data = include($path);
        $key = $this->requestedKey($request);

        // Same edit-only rule as translate_submit().
        if (!array_key_exists($key, $full_data)) {
            return response()->json(['message' => 'Unknown translation key'], 422);
        }

        // Same English text the Source column shows, so the row translates
        // what the admin is looking at.
        $source = $this->sourceLabel($key);
        $translated = Helpers::auto_translator($source, 'en', $lang);

        // auto_translator() hands the source back when the call failed, so an
        // unchanged string is a failed lookup — not a translation. Writing it
        // would replace the row with English and report it as done.
        if ($lang !== 'en' && strcasecmp(trim($translated), trim($source)) === 0) {
            return response()->json([
                'message' => $this->translationFailureMessage(),
            ], 503);
        }

        $full_data[$key] = $translated;
        $str = "<?php return " . var_export($full_data, true) . ";";
        file_put_contents($path, $str, LOCK_EX);

        return response()->json([
            'translated_data' => $translated,
            'translated' => $this->isTranslatedValue($lang, $key, $translated),
        ]);
    }
    /**
     * Why the lookup failed, in words the admin can act on.
     *
     * "Please try again" is the wrong advice for a rate limit — retrying immediately is what
     * caused it — so a throttled service says to wait instead. Anything else keeps the original
     * wording, because retrying really is the right move for a blip.
     */
    private function translationFailureMessage(): string
    {
        return Helpers::lastTranslationFailure() === 'rate_limited'
            ? translate('messages.Translation service is rate limiting this server, please wait a few minutes and try again')
            : translate('messages.Translation service did not respond, please try again');
    }

    public function auto_translate_all(Request $request, $lang): \Illuminate\Http\JsonResponse
    {
        try {
            // {lang} reaches the filesystem here too, including the
            // new-messages.php scratch file this endpoint writes.
            $path = $this->languageFilePath($lang);
            if (!$path) {
                return response()->json(['message' => 'Invalid language', 'data' => 'error'], 422);
            }

            $translating_count = max((int) $request->query('translating_count'), 1);

            if($lang === 'en'){
                return response()->json([
                    'message' => translate('All data is translated') , 'data' => 'success'
                ]);
            }

            $data_filtered = [];
            $data_filtered_2 = [];
            $new_messages_path = dirname($path) . '/new-messages.php';
            $count=0;
            $start_time = now();
            $items_processed = 20;
            if(!file_exists($new_messages_path)){
                $str = "<?php return " . var_export($data_filtered, true) . ";";
                file_put_contents($new_messages_path, $str, LOCK_EX);
            }

            $translated_data = include($new_messages_path);
            $full_data = include($path);

            // The scratch queue outlives the file it was built from. Keys
            // pruned from messages.php since it was written must be dropped
            // here, or the array_replace() below reintroduces every one of
            // them as a brand-new row.
            $translated_data = array_intersect_key($translated_data, $full_data);
            $translated_data_count= count($translated_data);

            if($translated_data_count > 0){
                foreach ($translated_data as $key_1 => $data_1) {
                    if($count > $items_processed){
                        break;
                    }
                    $source = $this->sourceLabel($key_1);
                    if (strlen($source) > 0) {
                        $translated = Helpers::auto_translator($source, 'en', $lang);

                        // Unchanged means the lookup failed. Leave the row as
                        // it was rather than stamping English over it, or the
                        // admin can never tell a failure from a translation.
                        if (strcasecmp(trim($translated), trim($source)) !== 0) {
                            $data_filtered_2[$key_1] = $translated;
                        }
                    }
                    unset($translated_data[$key_1]);
                    $count++;
                }

                // A pass where every row came back unchanged is nearly always
                // the service refusing us, not 20 rows that happen to translate
                // to themselves. Probe once with a sentence that cannot
                // plausibly be identical in the target language; if that fails
                // too, stop *before* the queue is written back, so an outage
                // does not chew through thousands of rows and report success.
                if ($count > 0 && count($data_filtered_2) === 0) {
                    $probe = 'The delivery man has accepted your order.';

                    if (strcasecmp(trim(Helpers::auto_translator($probe, 'en', $lang)), $probe) === 0) {
                        return response()->json([
                            'message' => $this->translationFailureMessage(),
                            'data' => 'error',
                        ]);
                    }
                }

                $str = "<?php return " . var_export($translated_data, true) . ";";
                file_put_contents($new_messages_path, $str, LOCK_EX);
                // Edit-only, like every other write on this screen: translating
                // a row may change its value but never adds a row.
                $merged_data = array_replace($full_data, array_intersect_key($data_filtered_2, $full_data));

                $str = "<?php\n\nreturn " . var_export($merged_data, true) . ";\n";
                file_put_contents($path, $str, LOCK_EX);
                $renmaining_translated_data_count= count($translated_data);

                // The caller echoes back the total it was handed, which can be
                // stale: reloading mid-batch starts it at 0, and a queue built
                // before keys were pruned reports more rows than are left. Never
                // let the denominator fall below the queue this pass started
                // with, or the bar reads over 100% / a "done" above "total".
                $translating_count = max($translating_count, $translated_data_count);

                $done = max($translating_count - $renmaining_translated_data_count, 0);
                $percentage = $translating_count > 0 ? ($done / $translating_count) * 100 : 100;
                $percentage = min(max($percentage, 1), 100);

                $end_time =now();
                $time_taken = $start_time->diffInSeconds($end_time);
                $rate_per_second = $time_taken > 0 ? $items_processed / $time_taken : 0.01;
                $total_time_needed = (int) ($renmaining_translated_data_count > 0 ? $renmaining_translated_data_count / $rate_per_second : 1);

                // % is an integer operator; $total_time_needed was a float, so
                // PHP 8.1+ raised a deprecation on every poll.
                $hours = intdiv($total_time_needed, 3600);
                $minutes = 2 + intdiv($total_time_needed % 3600, 60);
                $seconds = $total_time_needed % 60;


                return response()->json([
                    'message' =>  translate('translating') , 'data' => 'translating', 'total' => $translating_count, 'percentage'=> round($percentage,1), 'hours' => $hours, 'minutes' => $minutes, 'seconds' => $seconds,
                    'remaining' => $renmaining_translated_data_count,
                    'done' => $done,
                    'status' =>  $renmaining_translated_data_count > 0 ? 'pending' : 'done'
                ]);

            } else{

                    foreach ($full_data as $key => $data) {
                        // A blank row is the most untranslated row there is,
                        // but '+' never matches '' so it used to be skipped.
                        if (trim((string) $data) === '' || preg_match('/^[\x20-\x7E\x{2019}]+$/u', $data)) {
                            $data_filtered[$key] = $data;
                        }
                    }
                    // Serialize once. Writing inside the loop re-wrote the
                    // whole ~800KB file on every one of ~14k iterations.
                    $str = "<?php return " . var_export($data_filtered, true) . ";";
                    file_put_contents($new_messages_path, $str, LOCK_EX);

                    // Nothing left to queue. Reporting 'data_prepared' here
                    // sent the caller straight back to this same branch, which
                    // rebuilt an empty queue and answered 'data_prepared'
                    // again - the batch never terminated once a language was
                    // fully translated.
                    if (count($data_filtered) === 0) {
                        return response()->json([
                            'message' => translate('messages.All data is translated'), 'data' => 'success'
                        ]);
                    }

                    return response()->json([
                        'message' =>  translate('Data prepared') , 'data' => 'data_prepared' , 'total' => count($data_filtered)
                    ]);

            }
            return response()->json([
                'message' => translate('All data is translated') , 'data' => 'success'
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => $exception->getMessage() , 'data' => 'error'
            ]);
        }
    }

    public function delete($lang)
    {
        $language = BusinessSetting::where('key', 'system_language')->first();

        foreach (json_decode($language?->value, true) ?? [] as $data) {
            if (($data['code'] ?? null) == $lang && !empty($data['default'])) {
                Toastr::error(translate('messages.Default language cannot be deleted'));

                return back();
            }
        }

        $del_default = false;
        foreach (json_decode($language?->value, true) as $key => $data) {
            if ($data['code'] == $lang && array_key_exists('default', $data) && $data['default'] == true) {
                $del_default = true;
            }
        }

        $lang_array = [];
        foreach (json_decode($language?->value, true) as $key => $data) {
            if ($data['code'] != $lang) {
                $lang_data = [
                    'id' => $data['id'],
                    'direction' => $data['direction'] ?? 'ltr',
                    'code' => $data['code'],
                    'status' => ($del_default == true && $data['code'] == 'en') ? 1 : $data['status'],
                    'default' => ($del_default == true && $data['code'] == 'en') ? true : (array_key_exists('default', $data) ? $data['default'] : (($data['code'] == 'en') ? true : false)),
                ];
                array_push($lang_array, $lang_data);
            }
        }

        BusinessSetting::where('key', 'system_language')->update([
            'value' => $lang_array
        ]);
        Helpers::clearBusinessSettingsCache();

        $dir = base_path('resources/lang/' . $lang);
        if (File::isDirectory($dir)) {
            $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
            rmdir($dir);
        }


        $languages = array();
        $language = BusinessSetting::where('key', 'language')->first();
        foreach (json_decode($language?->value, true) as $key => $data) {
            if ($data != $lang) {
                array_push($languages, $data);
            }
        }
        if (in_array('en', $languages)) {
            unset($languages[array_search('en', $languages)]);
        }
        array_unshift($languages, 'en');

        Helpers::businessUpdateOrInsert(['key' => 'language'], [
            'value' => json_encode($languages),
        ]);

        Toastr::success('Removed Successfully!');
        return back();
    }

    public function lang($local)
    {
        $direction = Helpers::get_business_settings('site_direction', false) ?? 'ltr';
        $language = BusinessSetting::where('key', 'system_language')->first();
        foreach (json_decode($language?->value, true) as $key => $data) {
            if ($data['code'] == $local) {
                $direction = isset($data['direction']) ? $data['direction'] : 'ltr';
            }
        }
        session()->forget('language_settings');
        Helpers::language_load();
        session()->put('local', $local);
        session()->put('site_direction', $direction);
        return redirect()->back();
    }
}
