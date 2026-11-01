<?php

use App\Support\Settings\BusinessRules;
use App\Services\Payment\WalletTransactionService;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Store;
use App\Models\AdminWallet;
use App\Models\DeliveryMan;
use App\Models\WalletPayment;
use App\CentralLogics\Helpers;
use App\Models\AccountTransaction;
use Illuminate\Support\Facades\DB;
use App\Mail\OrderVerificationMail;
use App\Models\Module;
use App\Models\SubscriptionBillingAndRefundHistory;
use Brian2694\Toastr\Facades\Toastr;
use Modules\Rental\Entities\Trips;
use App\Services\Order\OrderPaymentService;
use App\Support\Notification\SendNotification;
use Illuminate\Support\Facades\Log;
use App\Support\Addon\AddonCompatibility;

/*
 * True when the given user belongs to a storefront (tenant or sub-tenant
 * set) AND the Builder wallet-features master switch is off. Lets the
 * shared host pipeline (wallet/loyalty/referral/cashback credits, refund
 * flow, related notifications) skip side effects for storefront customers
 * without affecting host customers (who carry tenant_id = sub_tenant_id = 0).
 */
if (! function_exists('storefront_wallet_disabled_for_user')) {
    function storefront_wallet_disabled_for_user($userId): bool
    {
        if (\config('builder.wallet_features_enabled', true)) {
            return false;
        }
        if (! $userId) {
            return false;
        }
        $user = \App\Models\User::withoutGlobalScope(\App\Scopes\HostScope::class)
            ->select(['id', 'tenant_id', 'sub_tenant_id'])
            ->find($userId);
        if (! $user) {
            return false;
        }
        return ((int) $user->tenant_id) > 0 || ((int) $user->sub_tenant_id) > 0;
    }
}

if (! function_exists('getDisallowedExtensionsListArray')) {
    function getDisallowedExtensionsListArray(): array
    {
        return [
            'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'pht', 'phar',
            'exe', 'com', 'bat', 'cmd', 'msi', 'scr', 'cpl', 'jar', 'app',
            'sh', 'bash', 'bin', 'run', 'csh', 'ksh', 'ps1',
            'vbs', 'vbe', 'js', 'jse', 'wsf', 'wsh', 'hta',
            'dll', 'so', 'sys', 'html', 'htm', 'shtml', 'svg', 'htaccess',
        ];
    }
}

if (! function_exists('applyTranslationReplacements')) {
    function applyTranslationReplacements(string $line, array $replace): string
    {
        if (empty($replace)) {
            return $line;
        }

        // Longest placeholder first, so :store_name is not eaten by :store.
        uksort($replace, fn ($a, $b) => mb_strlen((string) $b) <=> mb_strlen((string) $a));

        $substitutions = [];
        foreach ($replace as $key => $value) {
            // Objects and arrays have no string form trans() would accept
            // either; leaving the token in place beats "Array" in the UI.
            if (! is_scalar($value) && ! is_null($value)) {
                continue;
            }

            $key = (string) $key;
            $value = (string) $value;

            $substitutions[':' . $key] = $value;
            $substitutions[':' . mb_convert_case($key, MB_CASE_TITLE, 'UTF-8')] = mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
            $substitutions[':' . mb_strtoupper($key, 'UTF-8')] = mb_strtoupper($value, 'UTF-8');
        }

        return strtr($line, $substitutions);
    }
}

/*
 * Guards what translate() is allowed to persist into
 * resources/lang/<locale>/messages.php.
 *
 * translate() auto-learns unknown keys by appending them to the language
 * file. Without a guard anything that reaches it — a JS fragment spliced
 * into a Blade string, a DB column value, an empty variable, a price —
 * becomes a permanent row in the admin Language table and can never be
 * meaningfully translated. Keys rejected here still render as text; they
 * are just never written to disk.
 */
if (! function_exists('isPersistableTranslationKey')) {
    function isPersistableTranslationKey($key, bool $applyPolicyLists = true): bool
    {
        if (! is_string($key)) {
            return false;
        }

        $key = trim($key);

        // Empty, or a paragraph/blob rather than a UI label. The longest
        // legitimate string in the shipped file is ~390 chars.
        if ($key === '' || mb_strlen($key) > 500) {
            return false;
        }

        // Copy the admin panel renders in English on purpose. This is a list of
        // strings rather than a shape rule because nothing in the wording tells
        // an admin label from storefront copy — what separates them is the
        // screen the key is reached from, which this function cannot see.
        // Checked here so a render can never write one back into the language
        // file after it has been pruned. See resources/lang/excluded-keys.php.
        if ($applyPolicyLists) {
            static $excluded = null;
            if ($excluded === null) {
                $path = base_path('resources/lang/excluded-keys.php');
                $list = file_exists($path) ? include $path : [];
                $excluded = is_array($list) ? array_flip($list) : [];
            }
            if (isset($excluded[$key])) {
                return false;
            }
        }

        // Must contain a letter in some script. Rejects prices, ids,
        // ratios and phone numbers: "$5,465", "#_48573", "1:1", "--".
        if (! preg_match('/\p{L}/u', $key)) {
            return false;
        }

        if (preg_match('/\d/', $key) || preg_match('/(?<![\w:]):[a-zA-Z_]\w*/', $key)) {
            return false;
        }

        if (preg_match('/^[\-=*_~#|•]|[\-=*_~#|•]$|--|==|\*\*|__/u', $key)) {
            return false;
        }

        // Template / script fragments that leaked out of a view.
        $codeMarkers = [
            '{{', '}}', '<?', '?>', '${', '@@',
            "' +", "+ '", '" +', '+ "',
            'function(', 'function (',
            'querySelector', 'innerHTML',
        ];
        foreach ($codeMarkers as $marker) {
            if (str_contains($key, $marker)) {
                return false;
            }
        }

        // "document." and "window." are JS member access only when something
        // follows the dot. Matched as bare substrings they also reject any
        // sentence that happens to end on the word — "…sends them a push
        // notification within the window." and "…attach the document." are
        // ordinary copy, and were being refused.
        if (preg_match('/\b(?:document|window)\.\w/', $key)) {
            return false;
        }

        // Markup: "<div ...", "</span>", "<br/>".
        if (preg_match('~</?\s*[a-z][a-z0-9]*(\s[^<>]*)?/?>~i', $key)) {
            return false;
        }

        // JS member access on an indexed value: "data[count].name".
        if (preg_match('/\w+\s*\[\s*\w+\s*\]\s*\./', $key)) {
            return false;
        }

        // A bare filename: "index.blade.php", "app.js", "demo.pdf". A file is
        // called what it is called in every language, and these arrive from
        // upload chips and code-sample callouts. A sentence that mentions one
        // keeps its other words ("Create an html file named index.blade.php")
        // and is unaffected, because only a lone token is matched here.
        if (preg_match('/^[\w-]+(\.[\w-]+)*\.(blade\.php|php|js|jsx|ts|tsx|vue|css|scss|less|json|xml|ya?ml|env|lock|md|sql|sh|bat|ini|conf|htaccess|txt|log|zip|pdf|csv|xlsx?|docx?)$/i', trim($key))) {
            return false;
        }

        // Month and weekday names, abbreviated or in full, reach translate()
        // from chart axes, date formatting and day pickers, where they are data
        // rather than copy. Carbon already produces them localised, so keep
        // them out of the language files entirely and render them from there.
        if (preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|Sun|Mon|Tue|Wed|Thu|Fri|Sat)$/i', $key)
            || preg_match('/^(January|February|March|April|May|June|July|August|September|October|November|December'
                . '|Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)$/i', trim($key))) {
            return false;
        }

        // Navigation paths through another company's dashboard:
        // "Google Analytics → Admin → Data streams → your web stream.",
        // "CodeCanyon → Downloads → License certificate & purchase code.".
        // Every segment is a menu label in someone else's product, so there is
        // no sentence to translate and a translated segment stops matching what
        // the admin is looking at on screen. Two things stay translatable:
        // prose that merely quotes a path, which keeps a clause around it
        // ("Found under Downloads → Licence certificate on your Envato
        // account."), and paths through THIS panel, which are written with ">"
        // and whose menu labels this file localises anyway
        // ("Customers > Customer List > View Details.").
        if (str_contains($key, '→') || preg_match('/\s-+>\s/', $key)) {
            $segments = preg_split('/\s*(?:→|-+>)\s*/u', rtrim($key, " .\u{00A0}"), -1, PREG_SPLIT_NO_EMPTY);

            $allShort = true;
            foreach ($segments as $segment) {
                // A segment long enough to be a clause means this is a
                // sentence with an arrow in it, not a path.
                if (str_word_count($segment) > 4) {
                    $allShort = false;
                    break;
                }
            }

            // Two arrows or more with nothing but short segments is a
            // click-path however it opens: "Click Plus icon -> select App IDs
            // -> click on Continue" names three buttons in Apple's console, and
            // the verbs between them are glue, not copy. A single arrow still
            // needs a noun-phrase opener to count as a path, which is what
            // keeps "Found under Downloads → Licence certificate on your Envato
            // account." translatable.
            $isPath = $allShort && count($segments) > 1 && (
                count($segments) >= 3
                || (preg_match('/^[A-Z]/', $segments[0])
                    && ! preg_match('/^(Found|Go|Open|Copy|Created|Click|Visit|Navigate|See|Head|Choose|Select|Enter)\b/i', $segments[0]))
            );

            if ($isPath) {
                return false;
            }
        }

        // Placeholder masks used as form hints: "xxxxx xxxxxx", "XXX-XXXX",
        // "nnnn nnnn". Every letter run is one character repeated, so there is
        // no word to translate — and a translator who "corrects" one turns the
        // hint into nonsense. Prose that merely contains a mask keeps at least
        // one real word ("Typically it comes in the format gtm-xxxxxx.") and is
        // unaffected.
        $letterRuns = preg_split('/[^A-Za-z]+/', $key, -1, PREG_SPLIT_NO_EMPTY);
        if (! empty($letterRuns)) {
            $allMask = true;
            foreach ($letterRuns as $run) {
                if (strlen($run) < 3 || count(array_unique(str_split(strtolower($run)))) !== 1) {
                    $allMask = false;
                    break;
                }
            }
            if ($allMask) {
                return false;
            }

            // A word with no vowel in it is not a word: "zsxscds", "ghmnh",
            // "Edit Category sdfds", "Turn ON Xcfvz payment method". These are
            // records somebody typed into the panel while testing, which then
            // reached translate() as a name. Runs of one repeated character are
            // masks rather than mash and are handled above, so the "gtm-xxxxxx"
            // in a real sentence is not caught here. An all-caps run is left
            // alone too — "HTTPS", "SMTP" and "CSV" have no vowels either.
            foreach ($letterRuns as $run) {
                if (strlen($run) >= 5
                    && preg_match('/[a-z]/', $run)
                    && ! preg_match('/[aeiouy]/i', $run)
                    && count(array_unique(str_split(strtolower($run)))) > 1) {
                    return false;
                }
            }
        }

        if (! preg_match('/\s/u', $key) && preg_match('/\d/', $key)) {
            $longestRun = 0;
            foreach ($letterRuns as $run) {
                $longestRun = max($longestRun, strlen($run));
            }
            if ($longestRun > 0 && $longestRun < 3) {
                return false;
            }
        }

        // A trailing language code — "Trip Started (EN)", "Meta Description
        // (EN)" — belongs to a row in a per-locale database table, not to a UI
        // label. The panel builds its own language-tagged labels by appending
        // the code to a translated stem, so a key that arrives with one already
        // attached came from the data.
        if (preg_match('/\((EN|AR|BN|ES|FR|DE|HI|PT|RU|ZH)\)\s*$/i', $key)) {
            return false;
        }

        // A literal escape sequence: "\s Free Trial". The backslash survived
        // from a regex or a JS template, so what reached translate() is not the
        // sentence the user is shown.
        if (preg_match('/\\\\[a-zA-Z]/', $key)) {
            return false;
        }

        // Validation output that has already been rendered:
        // "The email field is required.", "The selected zone id is invalid."
        // Helpers::error_processor() passes what the validator produced through
        // translate(), so every validated field name becomes a key of its own —
        // 193 of them had accumulated, of which exactly one was ever translated,
        // and the set grows with every new rule anybody writes. Laravel
        // localises these from resources/lang/<locale>/validation.php, where one
        // template covers every field; a key collected here has the field name
        // baked in and can never be reused. Rejecting it does not change what
        // the API returns: an already-translated message is still found by the
        // lookup above, and everything else renders the English it renders now.
        if ($applyPolicyLists
            && (preg_match('/^The .+ (field is required|field must be|must be|has already been taken|format is invalid)\b/i', $key)
                || preg_match('/^The selected .+ is invalid\.$/i', $key)
                || $key === 'This action is unauthorized.')) {
            return false;
        }

        if (preg_match('/^\d+[.)]\s+\S/', $key)) {
            return false;
        }

        // A key that is nothing but a protocol, format, platform or layout
        // token: "smtp", "json", "url", "LTR", "VIN", "CNG", "ios", "EN".
        // These read the same in every language, and machine translation
        // destroys them — the Arabic file had "LTR" as "لتر" (litre), "ios" as
        // "دائرة الرقابة الداخلية" (Internal Control Department) and "android"
        // as "ذكري المظهر" (masculine in appearance). Only a key that is the
        // bare token is rejected, so compound labels such as "API Key",
        // "Meta Title" and "Download URL for User App" still translate.
        if (preg_match('/^(smtp|imap|pop3|tls|ssl|https?|ftps?|sftp|ssh|tcp|udp|dns|cdn|cors|jwt|oauth|sdk|json|xml|yaml|sql|api|url|uri|ltr|rtl|vin|cng|ios|android|en|ar|bn)$/i', $key)) {
            return false;
        }

        if (preg_match('/^[A-Za-z]$/', $key)
            || preg_match('/^(km|mi|cm|mm|ft|yd|in|kg|kgs|lb|lbs|oz|gm|ml|sqft|sqm)$/i', $key)) {
            return false;
        }

        // An aspect-ratio or pixel-dimension hint: "Ratio 1:1", "image (1:1)",
        // "Icon must be 1:1.", "Image Size Min 615 x 350 px". A ratio reads the
        // same in every language and translating one damages it — the Arabic
        // file had "Ratio 1:1" stored as "نسبة (1: 1)", with a space pushed
        // inside the ratio. Only a hint built around the figure is caught: the
        // words allowed beside it are the ones that add nothing on their own, so
        // a real sentence that happens to mention a ratio ("Upload your company
        // logo in 1:1 format. This will show above the Main Title") is kept.
        if (preg_match('/\d+\s*[:x×]\s*\d+/iu', $key) && mb_strlen($key) <= 60) {
            $rest = preg_replace('/\d+\s*[:x×]\s*\d+/iu', '', $key);
            $rest = preg_replace('/\b(ratio|size|image|images|icon|logo|banner|thumbnail|photo|min|max|minimum|maximum|recommended|px|pixels?|mb|kb|and|or|the|a|an|is|are|be|must|should|of|in|to)\b/i', '', $rest);
            $rest = preg_replace('/\d+/', '', $rest);
            $rest = preg_replace('/[\s,.:;+\-\/()\[\]!?&*]+/u', '', $rest);
            if ($rest === '') {
                return false;
            }
        }

        // A field hint made only of numbers and technical tokens:
        // "587 for TLS, 465 for SSL.", "5+ Characters", "20 MB". Port numbers,
        // protocol names and byte units read the same in every language, and
        // the connectives around them say nothing on their own — the same
        // reasoning as the file-format rule above, and the same strip-and-see
        // shape. A sentence that merely mentions a number keeps real words
        // ("Use 0 to switch this off for vendors.") and stays translatable.
        if (preg_match('/\d/', $key) && mb_strlen($key) <= 60) {
            $technical = 'TLS|SSL|SMTP|IMAP|POP3?|HTTPS?|FTP|SFTP|TCP|UDP|SSH|DNS|IP'
                . '|MB|KB|GB|TB|PX|MS|characters?|chars?|digits?|bits?|bytes?|ports?';
            $rest = preg_replace('/\b(' . $technical . ')\b/i', '', $key);
            $rest = preg_replace('/\d+(\.\d+)?\+?/', '', $rest);
            // The quantifiers a limit is phrased with carry nothing once the
            // figure and its unit are gone: "At least 8 characters",
            // "Maximum 2 MB", "Minimum 5 px".
            $rest = preg_replace('/\b(for|or|and|the|a|an|to|in|on|of|is|are|use|uses|used|default|recommended|only|per|up'
                . '|at|least|more|than|less|minimum|maximum|min|max|must|be|should|required|long|length|allowed|between)\b/i', '', $rest);
            $rest = preg_replace('/[\s,.:;+\-\/()\[\]!?&*x×]+/u', '', $rest);
            if ($rest === '') {
                return false;
            }
        }

        // A key that is entirely a parenthetical — or entirely quoted — is a
        // fragment: the brackets/quotes belong in the template, not in the
        // translatable string. Catches "(Ratio 2:1)", "(size: 1:1)", "(AR)",
        // "(Edited)", "(Qty: 67).", "‘Include Tax Amount?’".
        if (preg_match('/^\(.*\)[.,;:!?]?$/su', $key)
            || preg_match('/^[‘“].*[’”][.,;:!?]?$/su', $key)) {
            return false;
        }

        // Every key in this project is an English source string, in all
        // locales. A key with letters but no Latin ones is leaked DB data
        // (module/unit names such as "طعام", "খাবার", or mojibake of them).
        if (! preg_match('/[A-Za-z]/', $key) && preg_match('/\p{L}/u', $key)) {
            return false;
        }

        // Order line items: "2. 3USB Head Phone x 1", "1.ABC_x_1".
        if (preg_match('/[\s_]x[\s_]*\d+$/i', $key)) {
            return false;
        }

        // Formatted dates/times: "23 Jul, 2023 3:34 am".
        if (preg_match('/\b\d{1,2}\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\b/i', $key)
            || preg_match('/\b\d{1,2}:\d{2}\s*(am|pm)\b/i', $key)) {
            return false;
        }

        // URLs, schemes, hosts and IPs. These reach translate() from the
        // placeholder="Ex: ..." attributes on configuration forms. There is
        // nothing to translate, and machine translation actively corrupts
        // them — "ws://178.128.117.0" came back as "وس://178.128.117.0".
        if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $key)
            || preg_match('~^\d{1,3}(\.\d{1,3}){3}(:\d+)?$~', $key)
            || preg_match('~^localhost(:\d+)?$~i', $key)
            || (preg_match('~^[\w-]+(\.[\w-]+)+$~', $key)
                && preg_match('~\.(com|net|org|io|co|dev|local|test|app|xyz|info|biz|cloud)$~i', $key))) {
            return false;
        }

        // The same host written as a brand, spaces and all: "6 Service.com",
        // "My Shop.io". The rule above only matches an unbroken token, so a
        // business name with a space in it reached the file as a key of its
        // own and was then machine-translated — a company trades under the
        // same name in every language, and every screen that shows it prints
        // it from the business_name setting rather than from here.
        //
        // Shaped tightly, because prose can also end on a domain: two to four
        // words, every one of them opening on a capital or a digit the way a
        // name does. That is what separates "6 Service.com" from "Powered by
        // 6amtech.co", whose lowercase "by" gives away the sentence.
        $words = preg_split('/\s+/u', $key, -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) >= 2 && count($words) <= 4
            && preg_match('~\.(?:com|net|org|io|co|dev|local|test|app|xyz|info|biz|cloud)$~i', $key)
            && preg_match('~^[\p{L}\d][\p{L}\d\s.&\'-]*$~u', $key)) {
            $allNameLike = true;
            foreach ($words as $word) {
                if (! preg_match('~^[\p{Lu}\d]~u', $word)) {
                    $allNameLike = false;
                    break;
                }
            }
            if ($allNameLike) {
                return false;
            }
        }

        // File-format and size hints: "jpeg, jpg, png, gif, webp",
        // "Video format : MP4", ".zip only", "pdf, doc, jpg. File size : max 2 MB".
        // Extension names and byte units are identical in every language, so a
        // translator can only make them worse: the Arabic file once rendered
        // "pdf, doc, jpg. File size : max 2 MB" as "قوات الدفاع الشعبي، وثيقة، JPG."
        // ("People's Defense Forces, document, JPG"). A single format word on its
        // own — "csv", "pdf" — is left alone; those are real menu labels.
        $formats   = 'jpe?g|png|gif|webp|svg|bmp|ico|pdf|docx?|xlsx?|csv|zip|rar|mp4|mp3|mov|avi|3gp';
        $qualifier = 'max|min|size|file|format|image|video|less|than|only|up|to';
        $tokenCount = preg_match_all('/\b(' . $formats . ')\b/i', $key);
        if ($tokenCount >= 2 || ($tokenCount === 1 && preg_match('/\b(' . $qualifier . ')\b/i', $key))) {
            // Strip the format tokens, the size/format vocabulary, any
            // "2MB"-style measurement and punctuation. Anything left is real
            // prose and the key stays translatable.
            $rest = preg_replace('/\b(' . $formats . ')\b/i', '', $key);
            $rest = preg_replace('/\d+\s*(mb|kb|gb|px)?/i', '', $rest);
            $rest = preg_replace('/\b(' . $qualifier . '|and|the|a|an)\b/i', '', $rest);
            $rest = preg_replace('/[\s,.:;\-\/()\[\]!?&]+/u', '', $rest);
            if ($rest === '') {
                return false;
            }
        }

        // Placeholder enumerations of proper nouns, used as form examples:
        // "Grocery, eCommerce, Pharmacy, etc.", "Service Tax, VAT, GST, Sales Tax, etc."
        // A list of brand/product names with no sentence around it.
        if (preg_match('/,\s*etc\.?\s*$/i', $key)
            && substr_count($key, ',') >= 2
            && ! preg_match('/\b(you|your|is|are|can|will|please|must|of|for|with)\b/i', $key)) {
            return false;
        }

        return true;
    }
}

if (! function_exists('translate')) {
    function translate($key, $replace = [])
    {
        static $lang_cache = [];
        static $lang_added = [];
        static $collect_keys = null;

        // translate($model?->column) is a common pattern here, so null and
        // non-string values arrive routinely. Normalise first: otherwise they
        // reach strpos()/array_key_exists() and raise PHP 8.1 deprecations.
        // Trimming also collapses " Save " and "Save" onto one entry.
        $key = is_scalar($key) ? trim((string) $key) : '';

        // Typographic quotes are the single largest source of accidental
        // duplicates: "can't" and "can’t" are the same sentence but two keys,
        // and which one you get depends on the editor that typed it. Fold them
        // onto ASCII here so both spellings resolve to one entry.
        $key = strtr($key, [
            "\u{2018}" => "'", "\u{2019}" => "'",
            "\u{201C}" => '"', "\u{201D}" => '"',
        ]);

        if ($key === '') {
            return '';
        }

        if(strpos($key, 'validation.') === 0 || strpos($key, 'passwords.') === 0 || strpos($key, 'pagination.') === 0 || strpos($key, 'order_texts.') === 0) {
            return trans($key, $replace);
        }

        // trim() again: keys are written as 'messages. Foo ' in a few views.
        $key = strpos($key, 'messages.') === 0 ? trim(substr($key, 9)) : $key;

        if ($key === '') {
            return '';
        }

        $local = app()->getLocale();

        // $lang_added memoises the untouched fallback string, never a
        // substituted one: it is keyed by $key alone, so caching the result of
        // one call's $replace would hand that call's values to every later
        // caller. Substitute on the way out instead.
        if (isset($lang_added[$local][$key])) {
            return applyTranslationReplacements($lang_added[$local][$key], $replace);
        }

        try {
            $path = base_path('resources/lang/' . $local . '/messages.php');

            if (!isset($lang_cache[$local])) {
                $loaded = file_exists($path) ? include($path) : [];
                $lang_cache[$local] = is_array($loaded) ? $loaded : [];
            }

            if (array_key_exists($key, $lang_cache[$local])) {
                $result = trans('messages.' . $key, $replace);
            } else {
                // The '_' => ' ' pass exists for snake_case keys ('add_on_activation'),
                // but a blind str_replace also splits :payment_method into
                // ":payment method", which no longer matches anything $replace
                // offers and gets persisted to the language file in that broken
                // form. Step over :placeholder tokens and convert the rest.
                // ':' followed by a digit is left alone so ratios like 9:16 and
                // times like 10:30 are not mistaken for tokens.
                // Punctuation is NOT stripped here. remove_invalid_charcaters()
                // turns ' " ; < > into spaces, which mangled the fallback and
                // anything persisted from it: "we're happy to help." came out as
                // "we re happy to help." and "admin's earnings" as "admin s
                // earnings". Nothing needed the sanitising — var_export() escapes
                // the value written to disk and Blade escapes it on the way out.
                // The fallback has to render the key verbatim, because a key that
                // is deliberately absent from the language file (see
                // resources/lang/excluded-keys.php) is displayed through this path.
                $processed_key = ucfirst(preg_replace_callback(
                    '/:[a-zA-Z_][a-zA-Z0-9_]*|_/',
                    fn ($match) => $match[0] === '_' ? ' ' : $match[0],
                    $key
                ));

                // Persisting a key is expensive out of all proportion to what it
                // does: var_export of the whole array (~1-2 ms and an 850 KB
                // string), an 850 KB LOCK_EX write, and — because the write bumps
                // the file mtime — eviction of the file from OPcache, so this and
                // every concurrent request re-parses it cold. That is a fine price
                // while authoring locally, where it saves hand-editing the file.
                // In production it is a tax on every key that is merely missing,
                // paid on every request forever. Collect in dev; in production
                // fall straight through to the English fallback, which is the same
                // string the write would have stored anyway.
                if ($collect_keys === null) {
                    $collect_keys = (bool) config('app.collect_translation_keys', false);
                }

                if (! $collect_keys || ! is_dir(dirname($path))) {
                    $lang_added[$local][$key] = $processed_key;
                    return applyTranslationReplacements($processed_key, $replace);
                }

                $lang_array = file_exists($path) ? include($path) : [];
                $lang_array = is_array($lang_array) ? $lang_array : [];

                if (!array_key_exists($key, $lang_array)) {
                    // Render it, but never let junk into the language file.
                    if (! isPersistableTranslationKey($key)) {
                        $lang_added[$local][$key] = $processed_key;
                        return applyTranslationReplacements($processed_key, $replace);
                    }

                    $lang_array[$key] = $processed_key;
                    $str = "<?php return " . var_export($lang_array, true) . ";";
                    // LOCK_EX: this runs from concurrent web requests, queue
                    // workers and scheduled commands. An unlocked write can
                    // interleave and truncate the whole language file.
                    file_put_contents($path, $str, LOCK_EX);
                    $lang_added[$local][$key] = $processed_key;
                    $result = applyTranslationReplacements($processed_key, $replace);
                } else {
                    $result = trans('messages.' . $key, $replace);
                }

                $lang_cache[$local] = $lang_array;
            }
        } catch (\Throwable $exception) {
            Log::error('helpers.translate_failed', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ]);
            $result = trans('messages.' . $key, $replace);
        }

        return $result;
    }
}

if (! function_exists('collect_cash_fail')) {
    function collect_cash_fail($data){
        return 0;
    }
}
if (! function_exists('collect_cash_success')) {
    function collect_cash_success($data){

        try {
            $account_transaction = new AccountTransaction();
            if($data->attribute === 'store_collect_cash_payments'){
                $store = Store::where('vendor_id', $data->attribute_id)->first();
                $store->status = 1;
                $store->save();
                $user_data = $store?->vendor;
                $current_balance = $user_data?->wallet?->collected_cash ?? 0;
                $account_transaction->from_type = 'store';
                $account_transaction->from_id = $store?->vendor?->id;
                $account_transaction->created_by = 'store';
            }
            elseif($data->attribute === 'deliveryman_collect_cash_payments'){
                $user_data = DeliveryMan::withoutGlobalScope('delivery_only')->findOrFail($data->attribute_id);
                $user_data->status = 1;
                $user_data->save();
                $current_balance = $user_data?->wallet?->collected_cash ?? 0;
                $account_transaction->from_type = 'deliveryman';
                $account_transaction->from_id = $user_data->id;
                $account_transaction->created_by = 'deliveryman';
            }
            elseif($data->attribute === 'rider_collect_cash_payments'){
                $user_data = DeliveryMan::withoutGlobalScope('delivery_only')->findOrFail($data->attribute_id);
                $user_data->status = 1;
                $user_data->save();
                $current_balance = $user_data?->wallet?->collected_cash ?? 0;
                $account_transaction->from_type = 'rider';
                $account_transaction->from_id = $user_data->id;
                $account_transaction->created_by = 'rider';
            }
            else{
                return 0;
            }
            $account_transaction->method = $data->payment_method;
            $account_transaction->ref = $data->attribute;
            $account_transaction->amount = $data->payment_amount;
            $account_transaction->current_balance = $current_balance;

            DB::beginTransaction();
            $account_transaction->save();
            $user_data?->wallet?->decrement('collected_cash', $account_transaction->amount);
            AdminWallet::where('admin_id', Admin::where('role_id', 1)->first()->id)->increment('digital_received',  $account_transaction->amount );

            DB::commit();


        } catch (\Exception $exception) {
            Log::error('helpers.collect_cash_success_failed', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ]);
            DB::rollBack();

        }


        try {
            if($data->attribute == 'deliveryman_collect_cash_payments' && SendNotification::canSendMail('cash_collect_mail_status_dm', 'deliveryman', 'deliveryman_collect_cash')){
                SendNotification::mail($user_data?->getRawOriginal('email'), new \App\Mail\CollectCashMail($account_transaction, $user_data));
            }
        } catch (\Exception $exception) {
            Log::error('helpers.collect_cash_success_failed', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ]);
        }
        return true;
    }
}



if (! function_exists('order_place')) {
    function order_place($data) {
        $order = Order::find($data->attribute_id);
        $order->order_status='confirmed';
        if($order->payment_method != 'partial_payment'){
            $order->payment_method=$data->payment_method;
        }
        $order->payment_status='paid';
        $order->confirmed=now();
        $order->save();



        if( $order?->store?->is_valid_subscription == 1 && $order?->store?->store_sub?->max_order != "unlimited" && $order?->store?->store_sub?->max_order > 0){
            $order?->store?->store_sub?->decrement('max_order' , 1);
        }


        app(OrderPaymentService::class)->markUnpaidOrderPaymentPaid(orderId: $order->id, paymentMethod: $data->payment_method);
        try {
            SendNotification::sendOrderNotifications($order);
            $address = json_decode($order->delivery_address, true);


            if(SendNotification::canSendMail('order_verification_mail_status_user', 'customer', 'customer_delivery_verification')){

                if ( BusinessRules::deliveryVerificationEnabled()  && $order->is_guest == 0) {
                    SendNotification::mail($order->customer?->getRawOriginal('email'), new OrderVerificationMail($order->otp,$order->customer->f_name));
                }

                if ($order->is_guest == 1   && isset($address['contact_person_email'])) {
                    SendNotification::mail($address['contact_person_email'], new OrderVerificationMail($order->otp,$order?->customer?->f_name));
                }
            }
        } catch (\Exception $e) {
            info($e);
        }

    }

}

if (! function_exists('trip_payment_success')) {
    function trip_payment_success($data) {
        $trip = Trips::find($data->attribute_id);
        if(!$trip || $trip->payment_status == 'paid'){
            return;
        }
        if($trip->payment_method != 'partial_payment'){
            $trip->payment_method=$data->payment_method;
        }elseif($trip->payment_method == 'partial_payment'){
            app(WalletTransactionService::class)->recordWalletTransaction($trip->user_id, $trip->partially_paid_amount, 'partial_payment', $trip->id);
        }
        $trip->transaction_reference=$data->transaction_ref;
        $trip->payment_status='paid';
        $trip->trip_status = $trip->trip_status == 'payment_failed' ? $trip->statusBeforePaymentFailure() : $trip->trip_status;
        $trip->save();

        if( $trip?->provider?->is_valid_subscription == 1 && $trip?->provider?->store_sub?->max_order != "unlimited" && $trip?->provider?->store_sub?->max_order > 0){
            $trip?->provider?->store_sub?->decrement('max_order' , 1);
        }

        if ($trip->trip_status == 'completed' && $trip->payment_status == 'paid' && !$trip->trip_transaction) {
            Helpers::createTransactionForTrip($trip, 'admin');
        }
        Helpers::sendTripPaymentNotificationCustomerMain($trip);
        app(OrderPaymentService::class)->markUnpaidTripPaymentPaid(tripId: $trip->id, paymentMethod: $data->payment_method);
    }

}



if (! function_exists('trip_payment_fail')) {
    function trip_payment_fail($data) {
        $trip = Trips::find($data->attribute_id);
        if(!$trip){
            return false;
        }
        $trip->trip_status='payment_failed';
        if($trip->payment_method != 'partial_payment'){
            $trip->payment_method=$data->payment_method;
        }
        $trip->payment_failed=now();
        $trip->save();
        return true;
    }
}



if (! function_exists('order_failed')) {
    function order_failed($data) {
        $order = Order::find($data->attribute_id);
        $order->order_status='failed';
        if($order->payment_method != 'partial_payment'){
            $order->payment_method=$data->payment_method;
        }
        $order->failed=now();
        $order->save();
    }
}

if (! function_exists('service_booking_success')) {
    function service_booking_success($data) {
        $booking = \Modules\Service\Entities\ServiceBooking::find($data->attribute_id);
        if (! $booking) {
            return;
        }
        $is_partial = $booking->payment_method === 'partial_payment';
        $booking->payment_method = $is_partial ? 'partial_payment' : $data->payment_method;
        $booking->transaction_reference = $data->transaction_id;
        $booking->payment_status = 'paid';
        $booking->markStatus('confirmed');
        $booking->save();

        if ($is_partial) {
            \Modules\Service\Entities\ServicePartialPayment::where('booking_id', $booking->id)
                ->where('payment_status', 'unpaid')
                ->update([
                    'payment_status' => 'paid',
                    'payment_method' => $data->payment_method,
                    'transaction_ref' => $data->transaction_id,
                ]);
        } else {
            app(\Modules\Service\Services\ServiceBookingService::class)->credit_admin_digital_received($data->payment_amount);
        }

        try {
            $provider = $booking->provider;
            if ($provider?->is_valid_subscription == 1 && $provider?->store_sub?->max_order != 'unlimited' && $provider?->store_sub?->max_order > 0) {
                $provider?->store_sub?->decrement('max_order', 1);
            }
            app(\Modules\Service\Services\ServiceBookingService::class)->sendNewBookingNotification($booking);
        } catch (\Throwable $exception) {
            Log::error('helpers.service_booking_success_failed', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ]);
        }
    }
}

if (! function_exists('service_booking_failed')) {
    function service_booking_failed($data) {
        $booking = \Modules\Service\Entities\ServiceBooking::find($data->attribute_id);
        if (! $booking) {
            return;
        }
        $booking->payment_method = $data->payment_method;
        $booking->payment_status = 'unpaid';
        $booking->markStatus('payment_failed');
        $booking->save();
    }
}

if (! function_exists('wallet_success')) {
    function wallet_success($data) {
        $order = WalletPayment::find($data->attribute_id);
        $order->payment_method=$data->payment_method;
        $order->payment_status='success';
        $order->save();
        $wallet_transaction = app(WalletTransactionService::class)->recordWalletTransaction($data->payer_id, $data->payment_amount, 'add_fund',$data->payment_method);
        if($wallet_transaction)
        {
            try{
                Helpers::add_fund_push_notification($data->payer_id);
                if(SendNotification::canSendMail('add_fund_mail_status_user', 'customer', 'customer_add_fund_to_wallet')) {
                    SendNotification::mail($wallet_transaction->user?->getRawOriginal('email'), new \App\Mail\AddFundToWallet($wallet_transaction));
                }
            }catch(\Exception $ex)
            {
                Log::error('helpers.wallet_success_failed', [
                    'error' => $ex->getMessage(),
                    'file' => $ex->getFile().':'.$ex->getLine(),
                ]);
            }
        }
    }
}

if (! function_exists('wallet_success')) {
    function wallet_failed($data) {
        $order = WalletPayment::find($data->attribute_id);
        $order->payment_status='failed';
        $order->payment_method=$data->payment_method;
        $order->save();
    }
}

if (!function_exists('addon_published_status')) {
    function addon_published_status($module_name): int
    {
        try {
            $path = base_path("Modules/{$module_name}/Addon/info.php");

            if (!file_exists($path)) {
                return 0;
            }

            if (! AddonCompatibility::isCompatible(name: $module_name)) {
                return 0;
            }

            $full_data = include $path;

            return (isset($full_data['is_published']) && $full_data['is_published'] == 1) ? 1 : 0;

        } catch (\Throwable $exception) {
            Log::error('helpers.addon_published_status_failed', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ]);
            return 0;
        }
    }
}



if (!function_exists('config_settings')) {
    function config_settings($key, $settings_type)
    {
        try {
            $config = DB::table('addon_settings')->where('key_name', $key)
                ->where('settings_type', $settings_type)->first();
        } catch (Exception $exception) {
            return null;
        }
        return (isset($config)) ? $config : null;
    }


    if (! function_exists('sub_success')) {
        function sub_success($data){
            $type='renew';
            if($data->attribute == 'store_subscription_payment'){
                $type='new_plan';
                }
                elseif($data->attribute == 'store_subscription_new_join'){
                    $type='new_join';
                }

                $pending_bill= SubscriptionBillingAndRefundHistory::where(['store_id'=>$data->payer_id,
                'transaction_type'=>'pending_bill', 'is_success' =>0])?->sum('amount')?? 0;
                Helpers::subscription_plan_chosen(store_id:$data->payer_id,package_id:$data->attribute_id,payment_method:$data->payment_method,discount:0,pending_bill:$pending_bill,reference:$data->attribute,type: $type);
                if($type !== 'new_join'){
                    Toastr::success(  $type == 'renew' ?  translate('Subscription package renewed successfully.'): translate('Subscription package shifted successfully.')  );
                }

            return true;
        }
    }

    if (! function_exists('sub_fail')) {
        function sub_fail($data){
            return true;
        }
    }
    if (! function_exists('getEnvMode')) {
         function getEnvMode()
            {
                $configKey='envAppMode_conf';
                if (Config::has($configKey)) {
                    $data = Config::get($configKey);
                } else {
                    $data = env('APP_MODE')??config('app.app_mode');
                    Config::set($configKey, $data);
                }
                return $data;
            }
    }


    if (! function_exists('sanitize_client_string')) {
        /**
         * A string a client posted, with JavaScript's stringified emptiness treated as absent.
         *
         * A client that interpolates an unset variable sends the literal text `undefined` (or
         * `null`), and PHP has no reason to doubt it: `$request->house ?? ''` only catches a real
         * null, and `$request->contact_person_name ? … : …` reads a six-letter string as present.
         * That is how order addresses came to hold {"house":"undefined","road":"undefined"} and
         * how the admin order screen came to print it back at staff as if it were the customer's
         * address.
         *
         * Same reasoning as SanitizeBearerToken, which strips the same artefacts out of an
         * Authorization header — but a shorter list. That middleware also drops `nil`, `none` and
         * `false`, which is safe for a token and is not safe here: this runs over names and
         * addresses, where a customer may legitimately have typed one of them.
         */
        function sanitize_client_string(mixed $value, string $default = ''): string
        {
            $value = trim((string) ($value ?? ''));

            return in_array(strtolower($value), ['', 'null', 'undefined', '(null)'], true)
                ? $default
                : $value;
        }
    }

    if (! function_exists('isStaticOtpMode')) {
        /**
         * Whether this install hands out a FIXED verification code instead of a random one.
         *
         * True for `test` and `demo` alike. Neither has a phone or inbox anyone can read a code
         * out of -- a demo install's numbers belong to nobody and its SMS gateway is not wired to
         * a real account -- so a random six digits locks every visitor out of every flow that
         * asks for one: sign-up verification, login OTP, forgot-password. A `live` install never
         * takes this branch, and that is the only thing standing between a real customer's
         * account and a code anybody can guess.
         */
        function isStaticOtpMode(): bool
        {
            return in_array(getEnvMode(), ['test', 'demo'], true);
        }
    }

    if (! function_exists('generateOtpCode')) {
        /**
         * A six-digit verification code — the fixed one on a test or demo install, random on a
         * live one.
         *
         * One function so the rule lives in one place: it was previously spelled out at each of
         * the eight sites that issue a code, three of which had no env check at all and two of
         * which recognised `test` but not `demo`. Returns a STRING: a code is compared and stored
         * as text, and `rand()`'s int drops a leading zero the moment anything formats it.
         */
        function generateOtpCode(): string
        {
            return isStaticOtpMode() ? STATIC_OTP_CODE : (string) rand(100000, 999999);
        }
    }

    if (! function_exists('getModule')) {
         function getModule($value)
            {
                return is_numeric($value)
                ? Module::where('id', $value)->first()
                : Module::where('slug', $value)->first();
            }
    }

    if (! function_exists('getModuleId')) {
         function getModuleId($value)
            {
                return getModule($value)?->id;
            }
    }
}

if (! function_exists('pro_customer_subscription_success')) {
    function pro_customer_subscription_success($data)
    {
        $reference = 'pr_' . $data->id;
        if (\App\Models\ProCustomerTransaction::where('transaction_reference', $reference)->exists()) {
            return true;
        }

        $additional = is_array($data->additional_data) ? $data->additional_data : json_decode($data->additional_data ?? '[]', true);
        $planId = $additional['plan_id'] ?? null;
        $mode = $additional['mode'] ?? 'start';

        $user = \App\Models\User::find($data->payer_id);
        $plan = \App\Models\ProCustomerSubscriptionPlan::find($planId);
        if (!$user || !$plan) {
            return false;
        }

        $applier = new class { use \App\Traits\Payment\ProCustomerSubscriptionTrait; };
        $applier->applyProCustomerPlan($user, $plan, [
            'payment_method' => $data->payment_method,
            'payment_status' => 'success',
            'transaction_reference' => $reference,
            'paid_at' => now(),
        ], $mode);

        return true;
    }
}

if (! function_exists('pro_customer_subscription_failed')) {
    function pro_customer_subscription_failed($data)
    {
        return true;
    }
}

if (! function_exists('payment_method_label')) {
    /**
     * Display name for a stored payment_method value.
     *
     * Gateway names are brands, so they render verbatim in every locale and must not
     * reach translate(). Routing them through it did two bad things: it mistranslated
     * them (ar rendered 'paymob_accept' as "Paymob قبول" and 'ssl_commerz_payment' as
     * "SSL Commerz الدفع"), and because the argument is always a variable it wrote one
     * key per gateway into the language files — the growth §8 warns about.
     *
     * Only the platform's own methods are copy, so only those are translated, and from
     * literal keys so each one stays greppable.
     */
    function payment_method_label($method)
    {
        $method = is_scalar($method) ? trim((string) $method) : '';

        if ($method === '') {
            return '';
        }

        static $brands = null;

        if ($brands === null) {
            $brands = array_column(GATEWAYS_PAYMENT_METHODS, 'value', 'key');
        }

        // Orders and sessions store some gateways with a _payment suffix
        // ('ssl_commerz_payment'), the addon_settings rows without it.
        $key = preg_replace('/_payment$/', '', $method);

        if (isset($brands[$method])) {
            return $brands[$method];
        }

        if (isset($brands[$key])) {
            return $brands[$key];
        }

        $platform = [
            'cash_on_delivery' => translate('Cash on delivery'),
            'cash_after_service' => translate('Cash after service'),
            'cash' => translate('Cash'),
            'cash_payment' => translate('Cash payment'),
            'cash_collection' => translate('Cash collection'),
            'wallet' => translate('Wallet'),
            'digital_payment' => translate('Digital payment'),
            'offline_payment' => translate('Offline payment'),
            'partial_payment' => translate('Partial payment'),
            'pay_now' => translate('Pay now'),
            'free_trial' => translate('Free trial'),
        ];

        if (isset($platform[$method])) {
            return $platform[$method];
        }

        // An admin-defined offline method, or a gateway added since this list was
        // written: show it as stored rather than minting a translation key for it.
        return ucfirst(str_replace('_', ' ', $method));
    }
}

if (! function_exists('identity_type_label')) {
    function identity_type_label($type)
    {
        $type = is_scalar($type) ? trim((string) $type) : '';

        $labels = [
            'passport' => translate('Passport'),
            'driving_license' => translate('Driving license'),
            'trade_license' => translate('Trade license'),
            'nid' => 'NID',
        ];

        return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }
}
