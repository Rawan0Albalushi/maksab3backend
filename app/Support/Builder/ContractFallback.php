<?php

namespace App\Support\Builder;

/**
 * Let core's Builder adapters load when the installed add-on is older than core.
 *
 * The 30 files in app/Builder/ ship with core, but each one declares
 * `implements Modules\Builder\Contracts\X`, and those interfaces ship with the Builder add-on. An
 * update package carries core only -- no Modules/ -- so between replacing core and the client
 * uploading a matching add-on there are adapters whose interface does not exist. `implements` is
 * resolved when the class is declared, not lazily like a parameter type, so loading one is a hard
 * PHP error: "Interface Modules\Builder\Contracts\HappyHourProvider not found".
 *
 * Every previous attempt guarded the CALLER -- don't load the adapter, or catch the failure around
 * the load. Both depend on core's own discovery loop being the only thing that ever touches these
 * classes, and on the error staying catchable. This guards the SYMBOL instead: a missing contract is
 * declared as an empty placeholder, so the adapter compiles no matter what loads it, how, or when.
 *
 * Registered from bootstrap/app.php, before any service provider runs.
 */
final class ContractFallback
{
    private const CONTRACT_NS = 'Modules\\Builder\\Contracts\\';

    /**
     * Contracts this shim stood in for, so a diagnostic can report what the add-on is missing.
     *
     * @var array<string, true>
     */
    private static array $shimmed = [];

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        // APPENDED, never prepended. Composer gets first refusal, so a contract the add-on does
        // ship always resolves to the real interface and this never runs for it. Only a genuine
        // miss -- every registered autoloader having failed -- reaches here.
        spl_autoload_register(static function (string $symbol): void {
            if (! str_starts_with($symbol, self::CONTRACT_NS)) {
                return;
            }

            // autoload: false. The symbol is being autoloaded right now; asking the autoloaders
            // again from inside one would recurse.
            if (interface_exists($symbol, false) || class_exists($symbol, false)) {
                return;
            }

            class_alias(AbsentContract::class, $symbol);

            self::$shimmed[$symbol] = true;

            // error_log(), not Log::. This runs inside an autoloader during bootstrap, where
            // resolving the log manager can itself fail -- and a throw here would replace a
            // recoverable gap with the fatal this class exists to prevent.
            error_log('Builder contract absent, stood in with a placeholder: '.$symbol
                .' (the installed Builder add-on is older than this release)');
        }, true, false);
    }

    /**
     * @return list<string>
     */
    public static function shimmed(): array
    {
        return array_keys(self::$shimmed);
    }

    public static function forget(): void
    {
        self::$shimmed = [];
    }
}
