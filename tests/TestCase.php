<?php

namespace Tests;

use App\Services\System\DistanceService;
use App\Services\System\MeasurementUnitService;
use App\Services\Zone\EtaConfigurationService;
use App\Services\Zone\SurgePriceService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Per-request memos are static, and every write path clears its own — but a test rolls
        // its rows back with DatabaseTransactions, which is not a write. Without this a
        // configuration created by one test is still memoised for the next, which then sees a
        // setup that no longer exists in the database.
        app(EtaConfigurationService::class)->forgetActive();
        app(SurgePriceService::class)->flushMemo();
        app(DistanceService::class)->forgetUnit();
        app(MeasurementUnitService::class)->forgetUnits();

        $this->useConfiguredHost();
    }

    /**
     * Raise the memory limit to at least $limit, never lower it.
     *
     * The sweeps each ask for a ceiling of their own, and PHP throws when ini_set() is handed a
     * limit BELOW what the process is already using -- which is what happens to the ones asking
     * for 512M once an earlier sweep in the same run has grown the process past that. Asking for
     * a floor is what every caller meant.
     */
    protected function ensureMemoryLimit(string $limit): void
    {
        $wanted = $this->bytesOf($limit);
        $current = $this->bytesOf((string) ini_get('memory_limit'));

        // -1 is unlimited; nothing to raise.
        if ($current === -1) {
            return;
        }

        ini_set('memory_limit', (string) max($wanted, $current, memory_get_usage(true)));
    }

    /** "512M" / "1G" / "-1" as bytes. */
    private function bytesOf(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * Point the test client at APP_HOST_DOMAIN.
     *
     * The panel's routes are host-constrained whenever that variable is set, so a request built
     * against the default http://localhost matches nothing and every page assertion fails with a
     * 404 that looks like a missing route. Read from config rather than hardcoded, so the suite
     * follows whatever host the machine it runs on is configured for, and is a no-op on an
     * install that leaves the variable empty (routes then match any host).
     */
    private function useConfiguredHost(): void
    {
        $host = config('app.host_domain');

        if (! $host) {
            return;
        }

        $this->baseUrl = 'http://'.$host;
        url()->forceRootUrl($this->baseUrl);
    }
}
