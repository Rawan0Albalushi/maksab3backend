<?php

namespace Tests\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

trait SweepsRoutes
{
    protected array $sweepNever = [
        'delete', 'destroy', 'remove', 'truncate', 'drop', 'wipe', 'logout',
        'export', 'download', 'backup', 'excel', 'csv', 'pdf', 'print', 'invoice', 'zip',
    ];

    protected array $sweepUnsafeActions = [
        'delete', 'destroy', 'remove', 'status', 'update', 'clear', 'reset', 'restore',
        'approve', 'deny', 'cancel', 'send', 'sync', 'install', 'export', 'import',
        'download', 'generate', 'store', 'add', 'edit', 'create', 'login', 'logout',
    ];

    protected array $sweepMutationWords = [
        'delete', 'deleted', 'destroy', 'remove', 'removed', 'truncate', 'drop', 'wipe',
        'logout', 'login', 'update', 'updated', 'change', 'changed', 'toggle', 'toggled',
        'reset', 'restore', 'restored', 'approve', 'approved', 'deny', 'denied', 'cancel',
        'canceled', 'cancelled', 'send', 'sent', 'sync', 'install', 'import', 'clear',
        'cleared', 'status', 'publish', 'published', 'unpublish', 'activate', 'activated',
        'deactivate', 'deactivated', 'assign', 'assigned', 'verify', 'verified', 'refund',
        'refunded', 'payout', 'disburse', 'disbursed', 'generate', 'generated', 'switch',
        'set', 'make', 'pay', 'paid', 'settle', 'settled', 'apply', 'applied', 'confirm',
        'confirmed', 'accept', 'accepted', 'reject', 'rejected', 'block', 'blocked',
        'unblock', 'enable', 'disable', 'seed', 'run', 'flush', 'purge', 'revoke',
        'featured', 'priority', 'increment', 'decrement', 'swap', 'reorder',
    ];

    protected array $sweepResourceWriteActions = [
        'store', 'create', 'update', 'destroy', 'delete',
    ];

    protected array $sweepEnumParams = [
        'status' => 'all',
        'type' => 'all',
        'tab' => 'info',
        'sub_tab' => 'info',
        'lang' => 'en',
        'key' => 'name',
        'file_type' => 'excel',
        'social_medium' => 'facebook',
        'storage' => 'public',
        'folder_path' => 'public',
        'file_name' => 'placeholder.png',
        'slug' => 'home',
        'page' => 'about-us',
        'locale' => 'en',
        'user_type' => 'customer',
        'value' => '1',
    ];

    protected array $sweepTableOverrides = [
        'delivery-man' => 'delivery_men',
        'deliveryman' => 'delivery_men',
        'delivery_man' => 'delivery_men',
        'dm' => 'delivery_men',
        'booking' => 'service_bookings',
        'bookings' => 'service_bookings',
        'serviceman' => 'servicemen',
        'provider' => 'stores',
        'vendor' => 'stores',
        'seller' => 'stores',
        'customer' => 'users',
        'user' => 'users',
        'fleet-map-driver-details' => ['table' => 'rider_details', 'column' => 'user_id'],
        'fleet-map-view-single-driver' => ['table' => 'rider_details', 'column' => 'user_id'],
        'item' => 'items',
        'product' => 'items',
        'trip' => 'trips',
        'ride' => 'ride_requests',
        'rider' => ['table' => 'rider_details', 'column' => 'user_id'],
        'driver' => ['table' => 'rider_details', 'column' => 'user_id'],
        'transaction' => 'order_transactions',
        'conversation' => 'conversations',
        'withdraw' => 'withdraw_requests',
        'subscriptionackage' => 'subscription_packages',
        'package' => 'subscription_packages',
        'custom-service-request' => 'service_requests',
        'customservicerequest' => 'service_requests',
        'store-schedule' => 'store_schedule',
        'flash-sale' => 'flash_sales',
        'store-category' => 'store_categories',
        'sub-category' => 'categories',
        'vehicle-category' => 'vehicle_categories',
        'vehicle-brand' => 'vehicle_brands',
        'vehicle-model' => 'vehicle_models',
        'coupon' => 'coupons',
        'campaign' => 'campaigns',
        'banner' => 'banners',
        'advertisement' => 'advertisements',
        'category' => 'categories',
        'zone' => 'zones',
        'module' => 'modules',
        'service' => 'services',
        'vehicle' => 'vehicles',
        'expense' => 'expenses',
        'review' => 'reviews',
        'order' => 'orders',
        'parcel' => 'orders',
        'disbursement' => 'disbursements',
        'wallet' => 'store_wallets',
        'addon' => 'add_ons',
        'store-disbursement' => 'disbursements',
        'dm-disbursement' => 'disbursements',
        'rider-disbursement' => 'disbursements',
        'subscriber-detail' => 'store_subscriptions',
        'subscriber-transactions' => 'store_subscriptions',
        'subscriber-wallet-transactions' => 'store_subscriptions',
        'package-view' => 'subscription_packages',
        'subscription' => 'store_subscriptions',
        'why-choose' => 'module_wise_why_chooses',
        'promotional-banner' => 'admin_promotional_banners',
        'promotional-section' => 'admin_promotional_banners',
        'feature-list' => 'admin_features',
        'criteria-list' => 'admin_special_criterias',
        'special-criteria' => 'flutter_special_criterias',
        'review-list' => 'admin_testimonials',
        'testimonials' => 'admin_testimonials',
        'review-react-list' => 'react_testimonials',
        'faq' => 'f_a_q_s',
        'provide-deliveryman-earnings' => 'provide_d_m_earnings',
        'provide_deliveryman_earning' => 'provide_d_m_earnings',
        'fleet-map-customer-details' => 'users',
        'fleet-map-view-single-customer' => 'users',
        'generate-statement' => 'stores',
        'safety-alert' => 'ride_safety_alerts',
        'cashback' => 'cash_backs',
        'employee' => 'admins',
        'custom-role' => 'employee_roles',
        'role' => 'employee_roles',
        'withdraw-method' => 'withdrawal_methods',
        'plan' => 'pro_customer_subscription_plans',
        'coupon-setup' => 'ride_coupon_setups',
        'discount-setup' => 'ride_discount_setups',
        'safety-alert-reason' => 'ride_safety_alert_reasons',
        'precaution' => 'ride_safety_precautions',
    ];

    protected array $sweepSkipSegments = [
        'admin', 'vendor-panel', 'view', 'edit', 'details', 'detail', 'list', 'show',
        'preview', 'info', 'get', 'ajax', 'data', 'log', 'logs', 'new', 'index',
        'settings', 'setup', 'search', 'filter', 'partial', 'partials', 'quick-view',
        'ride-share', 'rental', 'business-settings', 'react', 'flutter', 'landing-page',
    ];

    private ?array $sweepTables = null;

    /**
     * `Builder::hydrate()` only arms a model's lazy-loading guard when the query returned more
     * than one row, so any page whose seed data happens to yield 0 or 1 row is exempt and its
     * violations stay invisible — which is exactly how a broken disbursement export survived a
     * green sweep. Arming every retrieved model removes that dependency on fixture volume.
     *
     * Call this instead of Model::preventLazyLoading(true) at the top of a sweep.
     */
    protected function sweepArmLazyLoadingGuard(): void
    {
        Model::preventLazyLoading(true);

        Event::listen('eloquent.retrieved: *', function ($event, $models) {
            foreach ((array) $models as $model) {
                if ($model instanceof Model) {
                    $model->preventsLazyLoading = true;
                }
            }
        });
    }

    /**
     * One module id per distinct module_type. Most admin pages are gated on the module in
     * session and render an empty list (or redirect) under the wrong one, so a single module
     * leaves every other module's blades unexercised.
     */
    protected function sweepModuleIds(): array
    {
        $ids = [];

        foreach (DB::table('modules')->select('id', 'module_type')->orderBy('id')->get() as $module) {
            $ids[$module->module_type] ??= (int) $module->id;
        }

        return $ids;
    }

    protected function sweepTargets(array $prefixes, bool $withParams, ?string $namespace = null): array
    {
        $targets = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            $name = (string) $route->getName();

            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if (! $this->sweepMatchesPrefix($uri, $prefixes)) {
                continue;
            }

            if ($namespace !== null && ! str_starts_with((string) $route->getAction('uses'), $namespace)) {
                continue;
            }

            if (str_contains($uri, '{') !== $withParams) {
                continue;
            }

            foreach ($this->sweepNever as $word) {
                if (str_contains(strtolower($name), $word) || str_contains(strtolower($uri), $word)) {
                    continue 2;
                }
            }

            $lastSegment = strtolower((string) last(explode('.', $name ?: str_replace('/', '.', $uri))));

            if (! $withParams && in_array($lastSegment, $this->sweepUnsafeActions, true)) {
                continue;
            }

            if (in_array($lastSegment, $this->sweepResourceWriteActions, true)) {
                continue;
            }

            $action = $route->getAction('uses');

            if ($this->sweepLooksMutating($uri, $name, is_string($action) ? $action : '')) {
                continue;
            }

            $targets[$uri] = $route;
        }

        return $targets;
    }

    protected function sweepLooksMutating(string $uri, string $name, string $action = ''): bool
    {
        $segments = array_filter(
            explode('/', $uri),
            fn ($segment) => $segment !== '' && ! str_contains($segment, '{')
        );

        $segments = array_merge($segments, explode('.', $name));

        if (str_contains($action, '@')) {
            $method = explode('@', $action, 2)[1];
            $segments = array_merge($segments, preg_split('/(?=[A-Z])/', $method) ?: []);
        }

        foreach ($segments as $segment) {
            foreach (preg_split('/[-_]/', strtolower($segment)) as $word) {
                if (in_array($word, $this->sweepMutationWords, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function sweepResolveUri(RoutingRoute $route): ?string
    {
        return $this->sweepResolveUris($route)[0] ?? null;
    }

    /**
     * One uri per id candidate. Resolving only the first row of a table hides any defect that
     * depends on the data — an orphaned child row on record 2 renders fine on record 1 — so
     * the last row is probed as well.
     */
    protected function sweepResolveUris(RoutingRoute $route): array
    {
        $uri = $route->uri();

        if (! preg_match_all('/\{([a-zA-Z_]+)\??\}/', $uri, $matches)) {
            return [$uri];
        }

        $uris = [$uri];

        foreach ($matches[1] as $param) {
            $values = $this->sweepResolveParamValues($route, $param);

            if ($values === []) {
                return [];
            }

            $expanded = [];

            foreach ($uris as $candidate) {
                foreach ($values as $value) {
                    $expanded[] = preg_replace('/\{'.$param.'\??\}/', (string) $value, $candidate, 1);
                }
            }

            $uris = array_values(array_unique($expanded));
        }

        return $uris;
    }

    private function sweepResolveParamValues(RoutingRoute $route, string $param): array
    {
        $first = $this->sweepResolveParam($route, $param);

        if ($first === null) {
            return [];
        }

        if (array_key_exists($param, $this->sweepEnumParams)) {
            return [$first];
        }

        $last = $this->sweepResolveParam($route, $param, last: true);

        return $last === null || $last === $first ? [$first] : [$first, $last];
    }

    private function sweepResolveParam(RoutingRoute $route, string $param, bool $last = false): string|int|null
    {
        if (array_key_exists($param, $this->sweepEnumParams)) {
            return $this->sweepEnumParams[$param];
        }

        $bound = $this->sweepBoundModelKey($route, $param, $last);

        if ($bound !== null) {
            return $bound;
        }

        $named = $this->sweepFirstIdFromSegment(str_replace('_id', '', $param), true, $last);

        if ($named !== null) {
            return $named;
        }

        return $this->sweepFirstIdFromUri($route->uri(), $last);
    }

    private function sweepBoundModelKey(RoutingRoute $route, string $param, bool $last = false): string|int|null
    {
        $action = $route->getAction('uses');

        if (! is_string($action) || ! str_contains($action, '@')) {
            return null;
        }

        [$class, $method] = explode('@', $action, 2);

        if (! class_exists($class) || ! method_exists($class, $method)) {
            return null;
        }

        foreach ((new \ReflectionMethod($class, $method))->getParameters() as $parameter) {
            if ($parameter->getName() !== $param) {
                continue;
            }

            $type = $parameter->getType();

            if (! $type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                return null;
            }

            $model = $type->getName();

            if (! is_subclass_of($model, Model::class)) {
                return null;
            }

            $instance = new $model;

            return DB::table($instance->getTable())
                ->orderBy($instance->getRouteKeyName(), $last ? 'desc' : 'asc')
                ->value($instance->getRouteKeyName());
        }

        return null;
    }

    private function sweepFirstIdFromUri(string $uri, bool $last = false): string|int|null
    {
        $segments = array_filter(
            explode('/', $uri),
            fn ($segment) => $segment !== '' && ! str_contains($segment, '{')
        );

        foreach ([false, true] as $allowWordSplit) {
            foreach (array_reverse($segments) as $segment) {
                if (in_array($segment, $this->sweepSkipSegments, true)) {
                    continue;
                }

                $id = $this->sweepFirstIdFromSegment($segment, $allowWordSplit, $last);

                if ($id !== null) {
                    return $id;
                }
            }
        }

        return null;
    }

    private function sweepFirstIdFromSegment(string $segment, bool $allowWordSplit = true, bool $last = false): string|int|null
    {
        $override = $this->sweepTableOverrides[strtolower($segment)] ?? null;

        if (is_array($override)) {
            if (! in_array($override['table'], $this->sweepTableList(), true)) {
                return null;
            }

            $column = $override['column'] ?? 'id';

            return DB::table($override['table'])
                ->when($override['where'] ?? null, fn ($query, $where) => $query->where($where))
                ->orderBy($column, $last ? 'desc' : 'asc')
                ->value($column);
        }

        $table = $this->sweepTableForSegment($segment, $allowWordSplit);

        if ($table === null) {
            return null;
        }

        return DB::table($table)->orderBy('id', $last ? 'desc' : 'asc')->value('id');
    }

    private function sweepTableForSegment(string $segment, bool $allowWordSplit = true): ?string
    {
        $segment = strtolower($segment);

        if (isset($this->sweepTableOverrides[$segment])) {
            $table = $this->sweepTableOverrides[$segment];

            if (is_array($table)) {
                $table = $table['table'];
            }

            return in_array($table, $this->sweepTableList(), true) ? $table : null;
        }

        $base = str_replace('-', '_', $segment);

        foreach ([Str::plural($base), $base] as $candidate) {
            if (in_array($candidate, $this->sweepTableList(), true)) {
                return $candidate;
            }
        }

        $words = explode('-', $segment);

        if ($allowWordSplit && count($words) > 1) {
            foreach (array_reverse($words) as $word) {
                $table = $this->sweepTableForSegment($word, false);

                if ($table !== null) {
                    return $table;
                }
            }
        }

        return null;
    }

    private function sweepTableList(): array
    {
        return $this->sweepTables ??= array_map(
            fn ($row) => array_values((array) $row)[0],
            DB::select('SHOW TABLES')
        );
    }

    private function sweepMatchesPrefix(string $uri, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($uri, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The probe runs inside a transaction that is always rolled back. The name/uri filters
     * above cannot prove a GET route is read-only — admin/store/verified-seller-all writes on
     * a plain GET — so the sweep must not depend on them for safety.
     */
    /**
     * @return array{lazy: ?string, error: ?string}
     *
     * Returns BOTH halves. It used to return the lazy-load string alone, which meant a caller
     * sweeping hundreds of routes could not tell a page that rendered from one that threw.
     */
    protected function sweepProbe(string $uri, $user, string $guard, array $session): array
    {
        return $this->sweepProbeFor($uri, $user, $guard, $session);
    }

    /**
     * Returns ['lazy' => ?string, 'error' => ?string].
     *
     * 'error' covers null-dereference and type errors in blades — "Trying to access array
     * offset on null" from a row whose relation row was deleted. Those never mention "lazy
     * load", so a probe that only greps for that message renders the page, swallows the real
     * failure and reports success.
     */
    protected function sweepProbeFor(string $uri, $user, string $guard, array $session): array
    {
        // 'threw' is EVERY exception, unclassified. 'error' is the curated subset of messages
        // that read as null-dereference defects, which a page sweep wants because a
        // parameter-driven route probed without parameters lands there too. An EXPORT has no such
        // excuse -- it either returns a file or it is broken -- so ExportSweepTest asserts on this
        // one. Nine exports threw InvalidArgumentException on every request and went unreported
        // for weeks precisely because that message is not in the curated list.
        $result = ['lazy' => null, 'error' => null, 'threw' => null];

        DB::beginTransaction();

        try {
            $this->withoutExceptionHandling();
            $this->actingAs($user, $guard)->withSession($session)->get('/'.ltrim($uri, '/'));
        } catch (\Throwable $e) {
            $message = $uri.'  ==>  '.str_replace(base_path().'/', '', substr($e->getMessage(), 0, 400));

            $result['threw'] = get_class($e).'  '.$message;

            if (str_contains($e->getMessage(), 'lazy load')) {
                $result['lazy'] = $message.$this->sweepAppFrames($e);
            } elseif ($this->sweepIsRuntimeDefect($e)) {
                $result['error'] = get_class($e).'  '.$message;
            }
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $this->app->forgetInstance('view');
            gc_collect_cycles();
        }

        return $result;
    }

    /**
     * Reported, not asserted. The probe calls every route without query input, so a
     * parameter-driven endpoint (get-stock, get-brand-data, quick-view-cart-item) legitimately
     * dereferences null here and would fail an assertion for a reason that is the sweep's
     * fault. Ones naming a real page blade are worth chasing; the rest are probe artefacts.
     */
    protected function assertNoFullUrlDefects(array $errors): void
    {
        $offenders = array_values(array_filter(
            array_keys($errors),
            fn (string $error): bool => str_contains($error, '_full_url')
        ));

        $this->assertSame([], $offenders,
            'A *_full_url key vanished from a serialised payload. An accessor was removed from '
            .'$appends while a consumer still reads it as an array key.');
    }

    protected function reportRuntimeDefects(array $errors): void
    {
        if (! $errors) {
            return;
        }

        fwrite(STDERR, '  '.count($errors).' runtime error(s) seen while probing (not asserted '
            .'- parameter-driven endpoints probed without parameters land here too):'.PHP_EOL);

        foreach (array_keys($errors) as $error) {
            fwrite(STDERR, '    '.$error.PHP_EOL);
        }
    }

    protected array $sweepApiGuards = [
        'auth:api' => 'auth:api',
        'vendor.api' => 'vendor.api',
        'dm.api' => 'dm.api',
        'serviceman.api' => 'serviceman.api',
        'apiGuestCheck' => 'apiGuestCheck',
    ];

    /**
     * The API partitions by guard, and each guard authenticates differently: Passport for
     * customers, a bearer column for vendors, and a request parameter for delivery men and
     * servicemen. A probe that sends the wrong credential gets a 401 and exercises nothing.
     */
    protected function sweepGuardFor(RoutingRoute $route): string
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            if (isset($this->sweepApiGuards[$middleware])) {
                return $this->sweepApiGuards[$middleware];
            }
        }

        return 'public';
    }

    protected function sweepApiCredentials(): array
    {
        return [
            'auth:api' => DB::table('users')->orderBy('id')->value('id'),
            'vendor.api' => DB::table('vendors')->whereNotNull('auth_token')->orderBy('id')->value('auth_token'),
            'dm.api' => DB::table('delivery_men')->whereNotNull('auth_token')->orderBy('id')->value('auth_token'),
            'serviceman.api' => DB::table('servicemen')->whereNotNull('auth_token')->orderBy('id')->value('auth_token'),
        ];
    }

    /**
     * Mirrors sweepProbeFor(): the request runs inside a transaction that is always rolled
     * back, and the same lazy/runtime split applies. Only the dispatch differs — JSON plus the
     * headers ModuleCheckMiddleware and LocalizationMiddleware require.
     */
    protected function sweepApiProbeFor(string $uri, string $guard, array $context): array
    {
        $result = ['lazy' => null, 'error' => null];

        $headers = [
            'X-localization' => 'en',
            'moduleId' => (string) $context['moduleId'],
            'zoneId' => (string) $context['zoneId'],
            'Accept' => 'application/json',
        ];

        $target = '/'.$uri;
        $credential = $context['credentials'][$guard] ?? null;

        if ($guard === 'vendor.api') {
            $headers['Authorization'] = 'Bearer '.$credential;
            $headers['vendorType'] = 'owner';
        }

        if (in_array($guard, ['dm.api', 'serviceman.api'], true)) {
            $target .= (str_contains($target, '?') ? '&' : '?').'token='.$credential;
        }

        DB::beginTransaction();

        try {
            $this->withoutExceptionHandling();

            $test = $guard === 'auth:api' && $context['apiUser'] !== null
                ? $this->actingAs($context['apiUser'], 'api')
                : $this;

            $test->getJson($target, $headers);
        } catch (\Throwable $e) {
            $message = $uri.'  ==>  '.str_replace(base_path().'/', '', substr($e->getMessage(), 0, 400));

            if (str_contains($e->getMessage(), 'lazy load')) {
                $result['lazy'] = $message.$this->sweepAppFrames($e);
            } elseif ($this->sweepIsRuntimeDefect($e)) {
                $result['error'] = get_class($e).'  '.$message;
            }
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            gc_collect_cycles();
        }

        return $result;
    }

    /**
     * The violation message names the model and relation but not the call site, and every
     * frame below it is framework code. These are the first frames that belong to this
     * repository, which is where the missing eager load has to be added.
     */
    protected function sweepAppFrames(\Throwable $e, int $limit = 3): string
    {
        $base = base_path();
        $frames = [];

        foreach ($e->getTrace() as $frame) {
            $file = $frame['file'] ?? null;

            if ($file === null
                || ! str_starts_with($file, $base)
                || str_contains($file, '/vendor/')
                || str_contains($file, '/tests/')) {
                continue;
            }

            $frames[] = str_replace($base.'/', '', $file).':'.($frame['line'] ?? '?');

            if (count($frames) >= $limit) {
                break;
            }
        }

        return $frames === [] ? '' : '  @  '.implode(' <- ', $frames);
    }

    private function sweepIsRuntimeDefect(\Throwable $e): bool
    {
        $message = $e->getMessage();

        foreach ([
            'Trying to access array offset on null',
            'Attempt to read property on null',
            'must be of type',
            'on null',
            'Undefined array key',
            'Undefined property',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return $e instanceof \TypeError;
    }
}
