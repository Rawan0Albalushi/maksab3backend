<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Concerns\SweepsRoutes;
use Tests\TestCase;

/**
 * Every page that renders the admin or vendor chrome must carry a breadcrumb,
 * and that breadcrumb must say which page it is.
 *
 * The trail is derived from the route rather than declared per page (see
 * BreadcrumbService), so a new screen or a sidebar edit that invalidates
 * config/navigation-map.php can silently leave a page with no trail, or with
 * the same trail as its neighbour. Both are checked here:
 *
 *   - coverage: every panel page renders `nav.bcx`, and only the pages listed
 *     under `breadcrumbs.except` do not;
 *   - identity: no two pages in a panel render the same trail, unless they are
 *     the same view reached by two routes (see ALIASES).
 *
 * The identity half is what makes the trail worth having — "Settings ›
 * Subscription Management › Subscription Packages" once told you nothing about
 * whether you were on the list, the edit form or the create form.
 */
class BreadcrumbSweepTest extends TestCase
{
    use SweepsRoutes;

    private const MARKER = 'class="bcx container-fluid"';

    /**
     * `layouts.admin.print` opens the same `<main id="content" role="main">` as
     * the panel layouts, so that element cannot tell them apart. The sidebar
     * shell can: only the panel layouts render one.
     */
    private const CHROME_MARKERS = ['id="v2-shell"', 'id="sidebarMain"'];

    /** Panel pages sweepTargets skips because their names read as mutating. */
    private const EXTRA = [
        'admin.pos.index',
        'vendor.pos.index',
        'admin.order.generate-invoice',
        'vendor.order.generate-invoice',
        'admin.rental.trip.generate-invoice',
        'admin.business-settings.file-manager.index',
    ];

    /**
     * Controller methods that only render. sweepTargets drops a route whose
     * name or method reads as mutating, which takes every create and edit
     * *form* with it — 123 panel pages, and the ones a breadcrumb matters most
     * on. A GET route landing on one of these is a page, not a write.
     */
    private const READ_METHODS = [
        'create', 'edit', 'show', 'view', 'details', 'index',
        'getAddView', 'getUpdateView', 'addService',
        'bulkImportIndex', 'bulkExportIndex', 'bulk_import_index', 'bulk_export_index',
        'getBulkImportView', 'getBulkExportView', 'backupIndex', 'restoreIndex',
    ];

    /**
     * Distinct routes that render the same view, so one trail for both is the
     * truth rather than a defect. Keyed by the shared view for the record.
     *
     * @var array<string, list<string>>
     */
    private const ALIASES = [
        // The order detail screen, reached from the list and from a transaction.
        'admin-views.order.order-view' => ['admin/order/details/*', 'admin/order/all-details/*'],
        // Zone setup and its instruction tab are one page.
        'admin-views.zone.index' => ['admin/business-settings/zone', 'admin/business-settings/zone/instruction'],
        'admin-views.zone.module-setup' => ['admin/business-settings/zone/module-setup', 'admin/business-settings/zone/module-setup/*'],
        // One dispatch list, with the module in the path.
        'admin-views.order.distaptch_list' => ['admin/dispatch/list/*', 'admin/dispatch/parcel/list/*'],
    ];

    public function test_admin_pages_render_a_breadcrumb(): void
    {
        $this->ensureMemoryLimit('512M');

        $admin = Admin::find(1);
        $this->assertNotNull($admin, 'admin id 1 must exist');

        $result = $this->sweep(
            $this->targets(['admin/', 'taxvat/']),
            $admin,
            'admin',
            ['login_remember_token' => $admin->login_remember_token, 'current_module' => $this->promotionModuleId()]
        );

        $this->report('admin', $result);

        $this->assertSame([], $result['missing'],
            'Every admin page must render a breadcrumb, or be listed in config/breadcrumbs.php "except".');
        $this->assertSame([], $result['unexpected'],
            'Pages listed under breadcrumbs.except must not render a breadcrumb.');
        $this->assertSame([], $this->collisions($result['trails']),
            'Two admin pages render the same trail, so it cannot say which page you are on. '
            .'Give the pages distinct @section(\'title\') values, or add them to ALIASES if they are one view.');
        $this->assertGreaterThan(100, $result['covered'], 'too few admin pages reached to be a meaningful sweep');
    }

    public function test_vendor_pages_render_a_breadcrumb(): void
    {
        $vendorId = DB::table('vendors')
            ->where('status', 1)
            ->whereNotNull('login_remember_token')
            ->value('id');

        if (! $vendorId) {
            $this->markTestSkipped('no active vendor with a session token');
        }

        $vendor = Vendor::find($vendorId);

        $result = $this->sweep(
            $this->targets(['vendor-panel/']),
            $vendor,
            'vendor',
            ['login_remember_token' => $vendor->login_remember_token]
        );

        $this->report('vendor', $result);

        $this->assertSame([], $result['missing'],
            'Every vendor page must render a breadcrumb, or be listed in config/breadcrumbs.php "except".');
        $this->assertSame([], $result['unexpected'],
            'Pages listed under breadcrumbs.except must not render a breadcrumb.');
        $this->assertSame([], $this->collisions($result['trails']),
            'Two vendor pages render the same trail, so it cannot say which page you are on.');
    }

    /**
     * Both halves of the route table. The detail and edit screens are the ones
     * a breadcrumb is worth most on — they are the pages with no sidebar entry
     * of their own — so a sweep that only walks the parameterless routes would
     * be checking the easy half.
     *
     * @return list<string>
     */
    private function targets(array $prefixes): array
    {
        $uris = array_keys($this->sweepTargets($prefixes, withParams: false));

        foreach ($this->sweepTargets($prefixes, withParams: true) as $route) {
            $uri = $this->sweepResolveUri($route);

            if ($uri !== null) {
                $uris[] = $uri;
            }
        }

        foreach ($this->readOnlyRoutes($prefixes) as $route) {
            $uri = str_contains($route->uri(), '{') ? $this->sweepResolveUri($route) : $route->uri();

            if ($uri !== null) {
                $uris[] = $uri;
            }
        }

        // sweepTargets drops anything whose name reads as mutating, which
        // takes the POS terminal and the in-panel invoice preview with it —
        // both are plain reads, and both render the panel shell.
        foreach (self::EXTRA as $name) {
            $route = Route::getRoutes()->getByName($name);

            if ($route === null || ! $this->sweepMatchesPrefix($route->uri(), $prefixes)) {
                continue;
            }

            $uri = str_contains($route->uri(), '{') ? $this->sweepResolveUri($route) : $route->uri();

            if ($uri !== null) {
                $uris[] = $uri;
            }
        }

        return array_values(array_unique($uris));
    }

    /**
     * GET routes whose controller method only renders, whatever their name
     * reads like.
     *
     * @return list<\Illuminate\Routing\Route>
     */
    private function readOnlyRoutes(array $prefixes): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || ! $this->sweepMatchesPrefix($route->uri(), $prefixes)) {
                continue;
            }

            $action = $route->getAction('uses');

            if (! is_string($action) || ! str_contains($action, '@')) {
                continue;
            }

            if (in_array(explode('@', $action, 2)[1], self::READ_METHODS, true)) {
                $routes[] = $route;
            }
        }

        return $routes;
    }

    /**
     * @return array{covered:int, skipped:int, missing:list<string>, unexpected:list<string>, trails:array<string,string>}
     */
    private function sweep(array $targets, $user, string $guard, array $session): array
    {
        $covered = $skipped = 0;
        $missing = $unexpected = [];
        $trails = [];

        foreach ($targets as $uri) {
            $html = $this->render($uri, $user, $guard, $session);

            // A redirect, a JSON endpoint, a download or a print sheet never
            // renders the panel shell, so it has nothing to carry a trail.
            if ($html === null || ! $this->hasChrome($html)) {
                $skipped++;

                continue;
            }

            $hasTrail = str_contains($html, self::MARKER);

            if ($this->suppressed($uri)) {
                if ($hasTrail) {
                    $unexpected[] = $uri;
                }

                continue;
            }

            if (! $hasTrail) {
                $missing[] = $uri;

                continue;
            }

            $covered++;
            $trails[$uri] = $this->trail($html);
        }

        return compact('covered', 'skipped', 'missing', 'unexpected', 'trails');
    }

    /**
     * Pages sharing a trail with another page, aliases excluded.
     *
     * @param  array<string, string>  $trails
     * @return list<string>
     */
    private function collisions(array $trails): array
    {
        $byTrail = [];

        foreach ($trails as $uri => $trail) {
            $byTrail[$trail][] = $uri;
        }

        $out = [];

        foreach ($byTrail as $trail => $uris) {
            if (count($uris) < 2 || $this->aliased($uris)) {
                continue;
            }

            $out[] = $trail.' — '.implode(', ', $uris);
        }

        sort($out);

        return $out;
    }

    /** Whether every path in the group is a known alias of the same view. */
    private function aliased(array $uris): bool
    {
        foreach (self::ALIASES as $patterns) {
            $covered = array_filter($uris, fn ($uri) => Str::is($patterns, $uri));

            if (count($covered) === count($uris)) {
                return true;
            }
        }

        return false;
    }

    /** The trail's labels, joined — what the admin actually reads. */
    private function trail(string $html): string
    {
        if (! preg_match('#<nav class="bcx container-fluid".*?</nav>#si', $html, $nav)) {
            return '';
        }

        preg_match_all('#<li class="bcx__item">(.*?)</li>#si', $nav[0], $items);

        $labels = array_map(
            fn ($item) => trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($item), ENT_QUOTES))),
            $items[1] ?? []
        );

        return implode(' > ', $labels);
    }

    /**
     * A module the promotion screens can run in.
     *
     * `promotion-module` middleware 404s Happy Hour, BOGO and Bundle outside a type whose
     * `promotions` flag is set, and CurrentModule reads the header switcher's choice from the
     * session. Left unset, the sweep drifts onto whatever module the last page selected — which is
     * how every admin promotion screen came to be counted as "not a page" rather than checked.
     */
    private function promotionModuleId(): ?int
    {
        $types = array_keys(array_filter(
            config('module'),
            fn ($conf) => is_array($conf) && ($conf['promotions'] ?? false)
        ));

        return DB::table('modules')->whereIn('module_type', $types)->where('status', 1)->min('id');
    }

    private function hasChrome(string $html): bool
    {
        foreach (self::CHROME_MARKERS as $marker) {
            if (str_contains($html, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function render(string $uri, $user, string $guard, array $session): ?string
    {
        DB::beginTransaction();

        try {
            $response = $this->actingAs($user, $guard)->withSession($session)->get('/'.ltrim($uri, '/'));

            return $response->getStatusCode() === 200 ? $response->getContent() : null;
        } catch (\Throwable $e) {
            return null;
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $this->app->forgetInstance('view');
        }
    }

    private function suppressed(string $uri): bool
    {
        foreach (config('breadcrumbs.except', []) as $pattern) {
            if (Str::is($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }

    private function report(string $panel, array $result): void
    {
        fwrite(STDERR, sprintf(
            '%s%s: %d page(s) with a breadcrumb, %d non-page response(s), %d without%s',
            PHP_EOL, $panel, $result['covered'], $result['skipped'], count($result['missing']), PHP_EOL
        ));

        foreach (array_slice($result['missing'], 0, 20) as $uri) {
            fwrite(STDERR, '  '.$uri.PHP_EOL);
        }
    }
}
