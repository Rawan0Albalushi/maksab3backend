<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SweepsRoutes;
use Tests\TestCase;

/**
 * Two structural rules every panel page has to keep.
 *
 * 1. The layout footer must stay a direct child of <main id="content">.
 * 2. Pagination must sit beside the table wrapper, never inside it.
 *
 * `layouts/admin/app` renders the footer as a sibling of `@yield('content')`,
 * and the theme styles it `position: absolute; bottom: 0`. So the footer is
 * placed against whichever positioned ancestor it ends up inside — and a page
 * that leaves one <div> open swallows it, along with everything else the
 * layout renders after the page's own markup. The visible symptom is the
 * footer sitting across the middle of a long table rather than under it.
 *
 * Nineteen pages were doing this. A blade can be div-balanced by count and
 * still leak — the ride-share banner list closed its toggle wrapper on one
 * column and not the identical one beside it, so it leaked one <div> per row —
 * which is why this is checked against the rendered HTML rather than by
 * counting tags in the source.
 *
 * `.table-responsive` is `overflow-x: auto` with `max-height: 67dvh;
 * overflow-y: auto`, so a `.page-area` inside it scrolls away with the rows
 * instead of staying under the table. Thirty-two pages had it inside. Some of
 * those looked fine only because `.table-responsive.datatable-custom` sets
 * `overflow: visible` and outranks the base rule — the markup was still wrong
 * and would break the moment that override moved, so both are checked.
 */
class PanelLayoutIntegrityTest extends TestCase
{
    use SweepsRoutes;

    private const FOOTER = '<div class="footer">';

    private const MAIN = '<main id="content"';

    public function test_admin_pages_do_not_swallow_the_footer(): void
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

        $this->assertSame([], $result['swallowed'],
            'These pages leave a <div> open, so the layout footer is rendered inside the page content '
            .'and overlaps it. Close the div in the page markup — not by appending </div> at the end, '
            .'which hides a leak inside a loop rather than fixing it.');
        $this->assertSame([], $result['nested'],
            'Pagination is rendered inside .table-responsive on these pages, so it scrolls with the '
            .'table. Move the .page-area block after the </div> that closes the wrapper.');
        $this->assertGreaterThan(100, $result['checked'], 'too few admin pages reached to be a meaningful sweep');
    }

    public function test_vendor_pages_do_not_swallow_the_footer(): void
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

        $this->assertSame([], $result['swallowed'],
            'These pages leave a <div> open, so the layout footer is rendered inside the page content.');
        $this->assertSame([], $result['nested'],
            'Pagination is rendered inside .table-responsive on these pages, so it scrolls with the table.');
    }

    /** @return list<string> */
    private function targets(array $prefixes): array
    {
        $uris = array_keys($this->sweepTargets($prefixes, withParams: false));

        foreach ($this->sweepTargets($prefixes, withParams: true) as $route) {
            $uri = $this->sweepResolveUri($route);

            if ($uri !== null) {
                $uris[] = $uri;
            }
        }

        return array_values(array_unique($uris));
    }

    /**
     * A module the promotion screens can run in, so Happy Hour, BOGO and Bundle
     * are reached rather than 404ing past this check.
     */
    private function promotionModuleId(): ?int
    {
        $types = array_keys(array_filter(
            config('module'),
            fn ($conf) => is_array($conf) && ($conf['promotions'] ?? false)
        ));

        return DB::table('modules')->whereIn('module_type', $types)->where('status', 1)->min('id');
    }

    /**
     * @return array{checked:int, swallowed:list<string>, nested:list<string>}
     */
    private function sweep(array $targets, $user, string $guard, array $session): array
    {
        $checked = 0;
        $swallowed = [];
        $nested = [];

        foreach ($targets as $uri) {
            $html = $this->render($uri, $user, $guard, $session);

            if ($html === null || ! str_contains($html, 'id="v2-shell"')) {
                continue;
            }

            $depth = $this->footerDepth($html);

            if ($depth === null) {
                continue;
            }

            $checked++;

            // Only an unclosed div is reported. The mirror defect — a stray
            // </div>, which reads as a negative depth — leaves the footer where
            // it belongs because browsers drop an unmatched end tag, so it is
            // untidy markup rather than a broken page.
            if ($depth > 0) {
                $swallowed[] = $uri.' (+'.$depth.')';
            }

            if ($this->paginationIsNested($html)) {
                $nested[] = $uri;
            }
        }

        sort($swallowed);
        sort($nested);

        return compact('checked', 'swallowed', 'nested');
    }

    /**
     * Whether the pagination sits inside the scrolling table wrapper instead of
     * beside it.
     */
    private function paginationIsNested(string $html): bool
    {
        $pagination = strpos($html, 'page-area');

        if ($pagination === false) {
            return false;
        }

        preg_match_all('/<div\\b[^>]*>|<\\/div\\s*>/i', substr($html, 0, $pagination), $tags);
        $open = [];

        foreach ($tags[0] as $tag) {
            str_starts_with(strtolower($tag), '</') ? array_pop($open) : $open[] = $tag;
        }

        foreach ($open as $tag) {
            if (str_contains($tag, 'table-responsive')) {
                return true;
            }
        }

        return false;
    }

    /**
     * How many <div>s are still open where the footer appears, counted from
     * <main id="content">. The layout makes the footer a direct child of
     * <main>, so anything above zero is a page that leaked one.
     */
    private function footerDepth(string $html): ?int
    {
        $start = strpos($html, self::MAIN);

        if ($start === false) {
            return null;
        }

        $end = strpos($html, self::FOOTER, $start);

        if ($end === false) {
            return null;
        }

        $slice = substr($html, $start, $end - $start);

        return preg_match_all('/<div\b/i', $slice) - preg_match_all('/<\/div\s*>/i', $slice);
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

    private function report(string $panel, array $result): void
    {
        fwrite(STDERR, sprintf(
            '%s%s: %d page(s) checked, %d swallowing the footer, %d nesting the pagination%s',
            PHP_EOL, $panel, $result['checked'], count($result['swallowed']), count($result['nested']), PHP_EOL
        ));

        foreach (array_slice(array_merge($result['swallowed'], $result['nested']), 0, 20) as $uri) {
            fwrite(STDERR, '  '.$uri.PHP_EOL);
        }
    }
}
