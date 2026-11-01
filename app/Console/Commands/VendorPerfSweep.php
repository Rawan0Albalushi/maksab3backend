<?php

namespace App\Console\Commands;

use App\Models\Vendor;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

/**
 * Read-only performance sweep of the vendor panel.
 *
 * Dispatches every safe GET route in the vendor.* namespace as an authenticated
 * vendor, recording query count, exact-duplicate queries and repeated query
 * patterns (N+1 candidates) per route.
 *
 * Safety: every request runs inside a transaction that is always rolled back,
 * and state-mutating GET routes are explicitly skipped.
 */
class VendorPerfSweep extends Command
{
    protected $signature = 'vendor:perf-sweep
        {--store= : Store id to sweep as}
        {--out= : Write JSON results to this path}
        {--only= : Only sweep routes whose name contains this string}
        {--skip-exports : Skip export/report-download routes}
        {--nplus1=2 : Minimum repeats of one query pattern to flag as an N+1 candidate}
        {--dump= : Write each response body to this directory, for before/after diffing}
        {--module= : rental|service|reels — retype the store and seed fixture rows so the module vendor panel is reachable. Everything is rolled back.}
        {--rows=6 : Fixture rows per domain table when --module is used}
        {--trace= : Print app-code call frames for every duplicated query on routes whose name contains this string}';

    protected $description = 'Read-only query/performance baseline sweep of the vendor panel';

    /**
     * GET routes that mutate state. These are never dispatched.
     */
    private const MUTATING_GET_ROUTES = [
        // Writes on a GET: clears unread_message_count and marks messages seen.
        // Measure it directly with real ids instead (see §11 of the progress doc).
        'vendor.message.view',
        // Writes on a GET: sets orders.checked = 1 before building the file.
        'vendor.order.export',
        'vendor.lang',
        'vendor.site_direction',
        'vendor.store-category.status',
        'vendor.store-category.priority',
        'vendor.delivery-man.status',
        'vendor.delivery-man.earning',
        'vendor.item.status',
        'vendor.item.remove-image',
        'vendor.item.recommended',
        'vendor.banner.status_update',
        'vendor.banner.status',
        'vendor.campaign.remove-store',
        'vendor.campaign.add-store',
        'vendor.wallet-method.default',
        'vendor.coupon.status',
        'vendor.advertisement.status',
        'vendor.order.status',
        'vendor.order.remove-proof-image',
        'vendor.business-settings.remove-schedule',
        'vendor.business-settings.update-active-status',
        'vendor.business-settings.toggle-settings',
        'vendor.business-settings.website-builder-status',
        'vendor.business-settings.notification_status_change',
    ];

    private const EXPORT_ROUTES = [
        'vendor.reviewsExport',
        'vendor.category.export-categories',
        'vendor.category.export-sub-categories',
        'vendor.store-category.export',
        'vendor.employee.export-employee',
        'vendor.item.bulk-export-index',
        'vendor.wallet.export',
        'vendor.order.export',
        'vendor.report.store-earning-export',
        'vendor.report.expense-export',
        'vendor.report.disbursement-report-export',
        'vendor.report.vendorTaxExport',
        'vendor.subscriptionackage.subscriberTransactionExport',
    ];

    /**
     * --module value => the Modules\<X> namespace that owns those vendor routes.
     */
    private const MODULE_NAMESPACES = [
        'rental' => 'Rental',
        'service' => 'Service',
        'reels' => 'ReelsModule',
    ];

    private Store $store;

    private Vendor $vendor;

    /** Set while an (always rolled back) module fixture transaction is open. */
    private ?string $moduleFixture = null;

    /**
     * Fixture rows are inserted with explicit ids from this base.
     *
     * MySQL does not roll back AUTO_INCREMENT, so letting the fixture auto-number meant
     * every sweep seeded higher ids than the last — and those ids are rendered into the
     * HTML, which made before/after response diffing report differences that were pure
     * harness noise. Explicit ids make two runs byte-comparable.
     */
    private const FIXTURE_ID_BASE = 900000000;

    /** Eloquent models hydrated during the request currently being measured. */
    private int $modelCount = 0;

    /**
     * N+1 relations reported by beyondcode/laravel-query-detector for the current request.
     *
     * The package renders its warning through Debugbar, which is disabled in console — so
     * a sweep dispatching requests from the CLI never saw those warnings even though a
     * browser showed them on every page. Listening for its event closes that blind spot.
     */
    private array $detected = [];

    private array $paramCache = [];

    public function handle(): int
    {
        $storeId = $this->option('store');
        if (! $storeId) {
            $this->error('--store is required.');
            return self::FAILURE;
        }

        $store = Store::find($storeId);
        if (! $store) {
            $this->error("Store {$storeId} not found.");
            return self::FAILURE;
        }
        $this->store = $store;

        $vendor = Vendor::find($store->vendor_id);
        if (! $vendor) {
            $this->error("Vendor {$store->vendor_id} not found for store {$storeId}.");
            return self::FAILURE;
        }

        $this->vendor = $vendor;
        Auth::guard('vendor')->setUser($vendor);
        $this->keepVendorSessionAlive($vendor);

        // Registered once: re-registering per route would stack listeners and multiply the
        // count. measure() resets the counter instead. This is the same figure Debugbar
        // reports as "Models".
        Event::listen('eloquent.retrieved: *', function () {
            $this->modelCount++;
        });

        if (class_exists(\BeyondCode\QueryDetector\Events\QueryDetected::class)) {
            // getQueries(), not ->queries: the event's property is protected, so reading it
            // directly yielded null and the sweep silently reported no N+1s at all.
            Event::listen(\BeyondCode\QueryDetector\Events\QueryDetected::class, function ($event) {
                foreach ($event->getQueries() as $query) {
                    $key = ($query['model'] ?? '?').' => '.($query['relatedModel'] ?? '?');
                    $this->detected[$key] = max($this->detected[$key] ?? 0, (int) ($query['count'] ?? 0));
                }
            });
        }

        $this->info("Sweeping as vendor {$vendor->id} / store {$store->id} ({$store->name})");

        $moduleFixture = $this->option('module');
        if ($moduleFixture !== null) {
            if (! isset(self::MODULE_NAMESPACES[$moduleFixture])) {
                $this->error("--module must be one of: ".implode(', ', array_keys(self::MODULE_NAMESPACES)));

                return self::FAILURE;
            }
            $this->openModuleFixture($moduleFixture);
        }

        $this->newLine();

        try {
            $results = [];
            $skipped = [];

            foreach ($this->targetRoutes() as $route) {
                $name = $route->getName();

                if (in_array($name, self::MUTATING_GET_ROUTES, true)) {
                    $skipped[] = ['route' => $name, 'reason' => 'mutating GET route'];
                    continue;
                }

                if ($this->option('skip-exports') && in_array($name, self::EXPORT_ROUTES, true)) {
                    $skipped[] = ['route' => $name, 'reason' => 'export route (--skip-exports)'];
                    continue;
                }

                $uri = $this->buildUri($route);
                if ($uri === null) {
                    $skipped[] = ['route' => $name, 'reason' => 'could not resolve route parameters'];
                    continue;
                }

                $results[] = $this->measure($name, $uri);
            }

            $this->report($results, $skipped);
            $this->assertStillAuthenticated($results);

            if ($path = $this->option('out')) {
                file_put_contents($path, json_encode([
                    'store_id' => $this->store->id,
                    'vendor_id' => $vendor->id,
                    'module_fixture' => $moduleFixture,
                    'nplus1_threshold' => (int) $this->option('nplus1'),
                    'results' => $results,
                    'skipped' => $skipped,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $this->info("JSON written to {$path}");
            }
        } finally {
            $this->closeModuleFixture();
        }

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Routing\Route[]
     */
    private function targetRoutes(): array
    {
        $only = $this->option('only');
        $namespace = $this->moduleFixture
            ? 'Modules\\'.self::MODULE_NAMESPACES[$this->moduleFixture].'\\'
            : null;
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (! $name || ! str_starts_with($name, 'vendor.')) {
                continue;
            }
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            if ($only && ! str_contains($name, $only)) {
                continue;
            }
            // With --module, sweep only the routes that module owns. The rest of the
            // panel is measured by the ordinary sweep against an untouched store.
            if ($namespace && ! str_starts_with((string) ($route->getAction('controller') ?: ''), $namespace)) {
                continue;
            }
            $routes[$name] = $route;
        }

        ksort($routes);

        return array_values($routes);
    }

    /**
     * Make a module's vendor panel reachable.
     *
     * Rental and Service gate every vendor route on the store's module_type, and this
     * database has no store of either type and no rows in any of their domain tables —
     * so without a fixture every route 404s and nothing can be measured.
     *
     * The fixture is written inside an outer transaction that is never committed. The
     * per-request transactions in measure() nest as savepoints inside it, so a request's
     * own writes still roll back individually while the fixture survives the whole sweep.
     */
    private function openModuleFixture(string $module): void
    {
        DB::beginTransaction();
        $this->moduleFixture = $module;

        $rows = max(1, (int) $this->option('rows'));
        $storeId = (int) $this->store->id;

        match ($module) {
            'rental' => $this->seedRentalFixture($storeId, $rows),
            'service' => $this->seedServiceFixture($storeId, $rows),
            'reels' => $this->seedReelsFixture($storeId, $rows),
        };

        $this->warn("Module fixture '{$module}' seeded inside a transaction that will be rolled back.");
    }

    private function closeModuleFixture(): void
    {
        if ($this->moduleFixture === null) {
            return;
        }

        DB::rollBack();
        $this->moduleFixture = null;
        $this->info('Module fixture rolled back — database unchanged.');
    }

    /**
     * Point the sweep store at a module row of the given type, cloning the module it
     * already uses so the zone pivot and every other module-shaped lookup still resolves.
     */
    private function retypeStoreModule(int $storeId, string $moduleType): int
    {
        $current = DB::table('modules')->where('id', $this->store->module_id)->first();

        $row = $current ? (array) $current : ['module_name' => 'Sweep', 'status' => 1];
        unset($row['id']);
        $row['module_name'] = 'Sweep '.ucfirst($moduleType);
        $row['module_type'] = $moduleType;
        $row['slug'] = 'sweep-'.$moduleType;
        $row['status'] = 1;

        $moduleId = self::FIXTURE_ID_BASE;
        $row['id'] = $moduleId;
        DB::table('modules')->insert($row);

        // module_zone drives delivery/zone lookups on several module pages.
        foreach (DB::table('module_zone')->where('module_id', $this->store->module_id)->get() as $pivot) {
            $pivotRow = (array) $pivot;
            unset($pivotRow['id']);
            $pivotRow['module_id'] = $moduleId;
            DB::table('module_zone')->insert($pivotRow);
        }

        DB::table('stores')->where('id', $storeId)->update(['module_id' => $moduleId]);

        return $moduleId;
    }

    /**
     * Build a concrete URI, resolving every route parameter. Null if unresolvable.
     */
    private function buildUri($route): ?string
    {
        $uri = $route->uri();
        $name = $route->getName();

        preg_match_all('/\{(\w+)(\?)?\}/', $uri, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            [$placeholder, $param] = $match;
            $optional = isset($match[2]);

            $value = $this->resolveParam($name, $param);

            if ($value === null) {
                if ($optional) {
                    $uri = str_replace('/'.$placeholder, '', $uri);
                    continue;
                }
                return null;
            }

            $uri = str_replace($placeholder, (string) $value, $uri);
        }

        // Vendor routes may be constrained to a host domain (config app.host_domain);
        // a bare path would 404 against the default localhost host.
        $domain = $route->domain() ?: config('app.host_domain');
        $path = '/'.ltrim($uri, '/');

        return $domain ? 'http://'.$domain.$path : $path;
    }

    /**
     * Resolve one route parameter to a real id from this store's data.
     */
    private function resolveParam(string $routeName, string $param): int|string|null
    {
        $key = $routeName.':'.$param;
        if (array_key_exists($key, $this->paramCache)) {
            return $this->paramCache[$key];
        }

        return $this->paramCache[$key] = $this->lookupParam($routeName, $param);
    }

    private function lookupParam(string $routeName, string $param): int|string|null
    {
        $storeId = $this->store->id;

        // Enum-style params first.
        if ($param === 'status') {
            return str_contains($routeName, 'order.') ? 'all' : '1';
        }
        if ($param === 'locale') {
            return 'en';
        }
        if ($param === 'tab') {
            return null; // optional
        }
        if ($param === 'file_type') {
            return 'excel';
        }
        if ($param === 'type') {
            return str_contains($routeName, 'disbursement') ? 'excel' : 'all';
        }

        // Module fixture routes, resolved before the core rules: several of them use a
        // bare {id} that the core rules would otherwise send to the wrong table.
        if ($this->moduleFixture !== null) {
            $moduleId = match (true) {
                str_contains($routeName, '.driver.') => $this->firstId('vehicle_drivers', ['provider_id' => $storeId]),
                str_contains($routeName, '.vehicle.') => $this->firstId('vehicles', ['provider_id' => $storeId]),
                str_contains($routeName, '.vehicle_category.') => $this->firstId('vehicle_categories'),
                str_contains($routeName, '.vehicle_brand.') => $this->firstId('vehicle_brands'),
                str_contains($routeName, '.trip.') => $this->firstId('trips', ['provider_id' => $storeId]),
                $param === 'booking' || str_contains($routeName, '.booking.')
                    => $this->firstId('service_bookings', ['provider_id' => $storeId]),
                str_contains($routeName, '.serviceman') => $this->firstId('servicemen', ['provider_id' => $storeId]),
                str_contains($routeName, '.reels.') => $this->firstId('reels', ['store_id' => $storeId]),
                str_contains($routeName, 'vendor.service.') => $this->firstId('services', ['store_id' => $storeId]),
                default => null,
            };

            if ($moduleId !== null) {
                return $moduleId;
            }
        }

        // Model-ish params.
        return match (true) {
            $param === 'store' || $param === 'store_id' => $storeId,

            $param === 'order' || str_contains($routeName, 'order.')
                => $this->firstId('orders', ['store_id' => $storeId]),

            $param === 'banner' => $this->firstId('banners', ['store_id' => $storeId]),

            $param === 'advertisement' => $this->firstId('advertisements', ['store_id' => $storeId]),

            $param === 'campaign' => $this->firstId('campaigns'),

            $param === 'store_schedule' => $this->firstId('store_schedule', ['store_id' => $storeId]),

            $param === 'conversation_id' => $this->firstId('conversations'),

            $param === 'user_id' => $this->firstId('users'),

            str_contains($routeName, 'item.') => $this->firstId('items', ['store_id' => $storeId]),
            str_contains($routeName, 'coupon.') => $this->firstId('coupons', ['store_id' => $storeId]),
            str_contains($routeName, 'addon.') => $this->firstId('add_ons', ['store_id' => $storeId]),
            str_contains($routeName, 'employee.') => $this->firstId('vendor_employees', ['store_id' => $storeId]),
            str_contains($routeName, 'custom-role.') => $this->firstId('employee_roles', ['store_id' => $storeId]),
            str_contains($routeName, 'store-category.') => $this->firstId('store_categories', ['store_id' => $storeId]),
            str_contains($routeName, 'delivery-man.') => $this->firstId('delivery_men', ['store_id' => $storeId]),
            str_contains($routeName, 'subscriptionackage.') => $this->firstId('store_subscriptions', ['store_id' => $storeId]),

            default => null,
        };
    }

    private function seedRentalFixture(int $storeId, int $rows): void
    {
        $moduleId = $this->retypeStoreModule($storeId, 'rental');
        $zoneId = $this->store->zone_id;
        $now = now();

        $categoryId = self::FIXTURE_ID_BASE + 1;
        DB::table('vehicle_categories')->insert([
            'id' => $categoryId, 'name' => 'Sweep Category', 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $brandId = self::FIXTURE_ID_BASE + 2;
        DB::table('vehicle_brands')->insert([
            'id' => $brandId, 'name' => 'Sweep Brand', 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);

        for ($i = 1; $i <= $rows; $i++) {
            DB::table('vehicles')->insert([
                'id' => self::FIXTURE_ID_BASE + 100 + $i,
                'name' => "Sweep Vehicle {$i}",
                'description' => 'fixture',
                'zone_id' => $zoneId,
                'provider_id' => $storeId,
                'brand_id' => $brandId,
                'category_id' => $categoryId,
                'model' => '2024',
                'type' => 'car',
                'seating_capacity' => '4',
                'trip_hourly' => 1,
                'hourly_price' => 100,
                'status' => 1,
                'slug' => "sweep-vehicle-{$i}",
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('vehicle_drivers')->insert([
                'id' => self::FIXTURE_ID_BASE + 200 + $i,
                'provider_id' => $storeId,
                'first_name' => 'Sweep',
                'last_name' => "Driver {$i}",
                'email' => "sweep.driver.{$i}@example.test",
                'phone' => '+100000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $vehicleIds = DB::table('vehicles')->where('provider_id', $storeId)->pluck('id')->all();
        $driverIds = DB::table('vehicle_drivers')->where('provider_id', $storeId)->pluck('id')->all();

        // Every trip_status the sidebar counts, so the badge queries have rows to find.
        $statuses = ['pending', 'confirmed', 'ongoing', 'completed', 'canceled', 'payment_failed'];
        $userId = DB::table('users')->min('id');

        for ($i = 0; $i < $rows; $i++) {
            $tripId = self::FIXTURE_ID_BASE + 300 + $i;
            DB::table('trips')->insert([
                'id' => $tripId,
                'user_id' => $userId,
                'provider_id' => $storeId,
                'zone_id' => $zoneId,
                'module_id' => $moduleId,
                'trip_amount' => 250,
                'trip_status' => $statuses[$i % count($statuses)],
                'payment_status' => $i % 2 ? 'paid' : 'unpaid',
                'payment_method' => 'cash_on_delivery',
                'trip_type' => 'hourly',
                'estimated_hours' => 2,
                'scheduled' => $i % 3 === 0 ? 1 : 0,
                'schedule_at' => $now,
                'pending' => $now,
                // The trip views read these as array offsets and fatal on null.
                'user_info' => json_encode([
                    'contact_person_name' => 'Sweep Customer',
                    'contact_person_number' => '+10000000000',
                    'contact_person_email' => 'sweep.customer@example.test',
                ]),
                'pickup_location' => json_encode(['address' => 'Sweep pickup']),
                'destination_location' => json_encode(['address' => 'Sweep destination']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Two line items per trip: the list and details views count these per row, so a
            // single line item would hide the N+1 the loop actually has.
            foreach (array_slice($vehicleIds, 0, 2) as $n => $vehicleId) {
                $detailId = self::FIXTURE_ID_BASE + 400 + ($i * 10) + $n;
                DB::table('trip_details')->insert([
                    'id' => $detailId,
                    'trip_id' => $tripId,
                    'vehicle_id' => $vehicleId,
                    'quantity' => 1,
                    'price' => 125,
                    'original_price' => 125,
                    'calculated_price' => 125,
                    'rental_type' => 'hourly',
                    'estimated_hours' => 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('trip_vehicle_details')->insert([
                    'id' => self::FIXTURE_ID_BASE + 600 + ($i * 10) + $n,
                    'trip_id' => $tripId,
                    'vehicle_id' => $vehicleId,
                    'trip_details_id' => $detailId,
                    'vehicle_driver_id' => $driverIds[($i + $n) % max(1, count($driverIds))] ?? null,
                    'is_completed' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function seedServiceFixture(int $storeId, int $rows): void
    {
        $moduleId = $this->retypeStoreModule($storeId, 'service');
        $zoneId = $this->store->zone_id;
        $now = now();
        $userId = DB::table('users')->min('id');

        for ($i = 1; $i <= $rows; $i++) {
            DB::table('services')->insert([
                'id' => self::FIXTURE_ID_BASE + 100 + $i,
                'store_id' => $storeId,
                'module_id' => $moduleId,
                'name' => "Sweep Service {$i}",
                'slug' => "sweep-service-{$i}",
                'short_description' => 'fixture',
                'base_price' => 500,
                'is_approved' => 1,
                'status' => $i % 4 === 0 ? 0 : 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('servicemen')->insert([
                'id' => self::FIXTURE_ID_BASE + 200 + $i,
                'f_name' => 'Sweep',
                'l_name' => "Serviceman {$i}",
                'phone' => '+200000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'email' => "sweep.serviceman.{$i}@example.test",
                'identity_image' => '[]',
                'password' => bcrypt('password'),
                'zone_id' => $zoneId,
                'provider_id' => $storeId,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $statuses = ['pending', 'confirmed', 'ongoing', 'completed', 'canceled'];

        for ($i = 0; $i < $rows; $i++) {
            DB::table('service_bookings')->insert([
                'id' => self::FIXTURE_ID_BASE + 300 + $i,
                'user_id' => $userId,
                'provider_id' => $storeId,
                'zone_id' => $zoneId,
                'module_id' => $moduleId,
                'booking_amount' => 500,
                'booking_status' => $statuses[$i % count($statuses)],
                'payment_status' => $i % 2 ? 'paid' : 'unpaid',
                'payment_method' => 'cash_on_delivery',
                'quantity' => 1,
                'scheduled' => $i % 3 === 0 ? 1 : 0,
                'schedule_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedReelsFixture(int $storeId, int $rows): void
    {
        $now = now();

        for ($i = 1; $i <= $rows; $i++) {
            DB::table('reels')->insert([
                'id' => self::FIXTURE_ID_BASE + 100 + $i,
                'store_id' => $storeId,
                'module_id' => $this->store->module_id,
                'module_type' => $this->store->module?->module_type,
                'description' => "Sweep reel {$i}",
                'video' => 'sweep.mp4',
                'is_always_visible' => 1,
                'status' => 1,
                'created_by_id' => $this->vendor->id,
                'created_by_type' => 'vendor',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function firstId(string $table, array $where = []): ?int
    {
        try {
            $query = DB::table($table);
            foreach ($where as $column => $value) {
                $query->where($column, $value);
            }
            $id = $query->min('id');

            // Fall back to any row when the store has none of this resource.
            if ($id === null && $where !== []) {
                $id = DB::table($table)->min('id');
            }

            return $id !== null ? (int) $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Dispatch one route and measure it. Always rolls back.
     */
    /**
     * VendorMiddleware logs the user out when session('login_remember_token') does not match
     * the vendor's column. Requests built with Request::create() carry no session cookie, so
     * every request starts an empty session and the mismatch redirects the whole sweep to
     * /login/store — silently, as a 302 that looks exactly like a permission gate.
     *
     * Injecting the token at the end of the `web` group puts it after StartSession and before
     * VendorMiddleware, which is the only window where the session exists but has not been
     * checked yet.
     */
    private function keepVendorSessionAlive(Vendor $vendor): void
    {
        $token = $vendor->login_remember_token;

        app()->bind('sweep.vendor-session', function () use ($token) {
            return new class($token)
            {
                public function __construct(private $token) {}

                public function handle($request, \Closure $next)
                {
                    session(['login_remember_token' => $this->token]);

                    return $next($request);
                }
            };
        });

        app('router')->pushMiddlewareToGroup('web', 'sweep.vendor-session');
    }

    /**
     * A sweep that has been logged out still "succeeds": every route returns a cheap 302 and
     * the totals look excellent. Fail loudly instead of publishing a meaningless measurement.
     */
    private function assertStillAuthenticated(array $results): void
    {
        $loggedOut = array_filter($results, function ($r) {
            return $r['http_status'] === 302
                && $r['redirect']
                && (str_contains($r['redirect'], '/login') || rtrim($r['redirect'], '/') === rtrim(url('/'), '/'));
        });

        if ($results && count($loggedOut) > count($results) / 2) {
            $this->newLine();
            $this->error(sprintf(
                'AUTH FAILED: %d of %d routes redirected to login. These numbers are meaningless.',
                count($loggedOut),
                count($results)
            ));
            $this->warn('VendorMiddleware compares session("login_remember_token") to the vendor row.');
            $this->warn('If the vendor logged in via a browser since the last sweep, re-run — the harness');
            $this->warn('injects the current token, but a stale in-memory value will not match.');
        }
    }

    /**
     * Call frames that belong to this project rather than to the framework — the ones that
     * actually identify which line emitted a query. Compiled Blade views are kept and
     * mapped back to their source path, since that is where most of them come from.
     */
    private function appFrames(int $limit = 6): array
    {
        $frames = [];

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 60) as $frame) {
            $file = $frame['file'] ?? null;
            if (! $file || str_contains($file, '/vendor/')) {
                continue;
            }
            if (str_contains($file, 'VendorPerfSweep.php')) {
                continue;
            }

            if (str_contains($file, '/storage/framework/views/')) {
                $source = $this->compiledViewSource($file);
                $frames[] = ($source ?? basename($file)).':'.($frame['line'] ?? 0);
            } else {
                $frames[] = str_replace(base_path().'/', '', $file).':'.($frame['line'] ?? 0);
            }

            if (count($frames) >= $limit) {
                break;
            }
        }

        return $frames;
    }

    /**
     * Blade appends `/**PATH <source> ENDPATH**\/` as the last line of each compiled view.
     */
    private function compiledViewSource(string $compiled): ?string
    {
        static $cache = [];

        if (array_key_exists($compiled, $cache)) {
            return $cache[$compiled];
        }

        $size = @filesize($compiled) ?: 0;
        $tail = $size > 0
            ? (@file_get_contents($compiled, false, null, max(0, $size - 500)) ?: '')
            : '';

        return $cache[$compiled] = preg_match('#PATH\s+(\S+?\.blade\.php)#', $tail, $m)
            ? str_replace(base_path().'/', '', $m[1])
            : null;
    }

    private function measure(string $name, string $uri): array
    {
        $trace = $this->option('trace');
        $tracing = $trace !== null && $trace !== '' && str_contains($name, $trace);

        $queries = [];
        $listener = function ($query) use (&$queries, $tracing) {
            $sql = $query->sql;
            // Transaction control statements are sweep overhead, not app queries.
            if (preg_match('/^(SAVEPOINT|ROLLBACK|RELEASE SAVEPOINT|COMMIT|BEGIN|START TRANSACTION)/i', trim($sql))) {
                return;
            }
            $queries[] = [
                'sql' => $sql,
                'bindings' => $query->bindings,
                'time' => $query->time,
                'frames' => $tracing ? $this->appFrames() : null,
            ];
        };

        // onceUsingId, not setUser: a real request resolves the vendor through
        // EloquentUserProvider::retrieveById, which hydrates a fresh Vendor and runs its
        // HasStorage global scope. setUser() skipped that entirely, so the sweep could not
        // see duplicates involving the Vendor's own hydration — Debugbar caught one on
        // /vendor-panel/wallet that every sweep had reported as clean.
        // It also guarantees relations start unloaded, which reusing one instance did not.
        // ...and after DB::listen, because in production the session guard resolves the user
        // during the request, so that hydration is part of the request's query log. Setting
        // it beforehand hid the first half of the duplicate pair.
        $this->modelCount = 0;
        $this->detected = [];

        DB::beginTransaction();
        DB::listen($listener);

        Auth::guard('vendor')->onceUsingId($this->vendor->id);

        $status = null;
        $error = null;
        $bytes = 0;
        $redirect = null;
        $start = microtime(true);
        $memBefore = memory_get_usage(true);

        try {
            $kernel = app(HttpKernel::class);
            $request = Request::create($uri, 'GET');
            $response = $kernel->handle($request);
            $status = $response->getStatusCode();
            $body = (string) $response->getContent();
            $bytes = strlen($body);
            $redirect = $response->headers->get('Location');

            if ($dir = $this->option('dump')) {
                if (! is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                // BinaryFileResponse (exports) has an empty body; dump the file instead.
                if ($bytes === 0 && method_exists($response, 'getFile') && $response->getFile()) {
                    $body = (string) file_get_contents($response->getFile()->getPathname());
                }
                file_put_contents($dir.'/'.$name.'.body', $status."\n".$redirect."\n".$body);
            }
        } catch (\Throwable $e) {
            $error = get_class($e).': '.$e->getMessage();
        }

        $elapsed = round((microtime(true) - $start) * 1000, 1);
        $memUsed = round((memory_get_usage(true) - $memBefore) / 1048576, 1);

        DB::rollBack();

        if ($tracing) {
            $this->printTrace($name, $queries);
        }

        return [
            'route' => $name,
            'uri' => $uri,
            'http_status' => $status,
            'redirect' => $redirect,
            'error' => $error,
            'ms' => $elapsed,
            'mem_mb' => $memUsed,
            'response_kb' => round($bytes / 1024, 1),
            'models' => $this->modelCount,
            'queries' => count($queries),
            'db_ms' => round(array_sum(array_column($queries, 'time')), 1),
            'nplus1_detected' => $this->detected,
            'exact_duplicates' => $this->exactDuplicates($queries),
            'nplus1_candidates' => $this->repeatedPatterns($queries),
        ];
    }

    /**
     * For --trace: show where each duplicated query was emitted from. Knowing that a
     * duplicate exists is not enough to fix it, and guessing at the caller has been wrong
     * before — this prints the actual frames for every occurrence.
     */
    private function printTrace(string $route, array $queries): void
    {
        $groups = [];
        foreach ($queries as $q) {
            $groups[$q['sql'].'|'.json_encode($q['bindings'])][] = $q['frames'] ?? [];
        }

        $printed = false;
        foreach ($groups as $key => $occurrences) {
            if (count($occurrences) < 2) {
                continue;
            }

            if (! $printed) {
                $this->newLine();
                $this->line("<fg=yellow>TRACE {$route}</>");
                $printed = true;
            }

            [$sql] = explode('|', $key, 2);
            $this->line('  '.substr(preg_replace('/\s+/', ' ', $sql), 0, 150));
            foreach ($occurrences as $i => $frames) {
                $this->line('    #'.($i + 1).'  '.implode("\n         ", $frames));
            }
            $this->newLine();
        }
    }

    /**
     * Identical SQL + identical bindings run more than once: pure waste.
     */
    private function exactDuplicates(array $queries): array
    {
        $counts = [];
        foreach ($queries as $q) {
            $key = $q['sql'].'|'.json_encode($q['bindings']);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $dupes = [];
        foreach ($counts as $key => $count) {
            if ($count > 1) {
                [$sql] = explode('|', $key, 2);
                $dupes[] = ['sql' => $this->truncate($sql), 'count' => $count, 'wasted' => $count - 1];
            }
        }

        usort($dupes, fn ($a, $b) => $b['wasted'] <=> $a['wasted']);

        return $dupes;
    }

    /**
     * Same SQL shape, different bindings, repeated: classic N+1.
     */
    private function repeatedPatterns(array $queries): array
    {
        $threshold = max(2, (int) $this->option('nplus1'));
        $counts = [];

        foreach ($queries as $q) {
            $counts[$q['sql']] = ($counts[$q['sql']] ?? 0) + 1;
        }

        $patterns = [];
        foreach ($counts as $sql => $count) {
            if ($count >= $threshold) {
                $patterns[] = ['sql' => $this->truncate($sql), 'count' => $count];
            }
        }

        usort($patterns, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $patterns;
    }

    private function truncate(string $sql, int $length = 160): string
    {
        $sql = preg_replace('/\s+/', ' ', trim($sql));

        return strlen($sql) > $length ? substr($sql, 0, $length).' …' : $sql;
    }

    private function report(array $results, array $skipped): void
    {
        usort($results, fn ($a, $b) => $b['queries'] <=> $a['queries']);

        $rows = array_map(fn ($r) => [
            $r['route'],
            $r['http_status'] ?? 'ERR',
            $r['queries'],
            array_sum(array_column($r['exact_duplicates'], 'wasted')),
            count($r['nplus1_candidates']),
            $r['db_ms'],
            $r['ms'],
            $r['mem_mb'],
        ], $results);

        $this->table(
            ['Route', 'HTTP', 'Queries', 'Dup wasted', 'N+1 patterns', 'DB ms', 'Total ms', 'Mem MB'],
            $rows
        );

        $totalQueries = array_sum(array_column($results, 'queries'));
        $this->newLine();
        $this->line("Routes measured: ".count($results)."   Total queries: {$totalQueries}");

        if ($skipped) {
            $this->newLine();
            $this->warn('Skipped ('.count($skipped).'):');
            foreach ($skipped as $s) {
                $this->line("  {$s['route']} — {$s['reason']}");
            }
        }

        $errors = array_filter($results, fn ($r) => $r['error'] !== null || ($r['http_status'] ?? 500) >= 400);
        if ($errors) {
            $this->newLine();
            $this->warn('Non-200 responses ('.count($errors).') — these numbers are not trustworthy:');
            foreach ($errors as $e) {
                $this->line("  {$e['route']} → ".($e['http_status'] ?? 'ERR').' '.($e['error'] ?? ''));
            }
        }
    }
}
