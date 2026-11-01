<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The horizontal tab strips are driven by one shared layer —
 * `public/assets/admin/js/tab-scroller.js` plus the `.tabs-scroller` rules in
 * `admin-tabs.css` — loaded from both panel layouts.
 *
 * Two things are worth guarding. The layouts must keep loading it, and no
 * panel view may re-declare the inline handler it replaced: a page-local
 * `.btn-click-prev` binding sets an inline `display` on the arrow wrappers,
 * which outranks the shared sheet's state classes and silently keeps that one
 * screen on the old one-pill-per-click behaviour.
 */
class TabScrollerSweepTest extends TestCase
{
    private const SCRIPT = 'js/tab-scroller.js';

    /**
     * Views allowed to bind `.btn-click-prev` / `.btn-click-next` themselves.
     *
     * The image uploaders are `div.tabs-inner` galleries, not `ul.nav-tabs`,
     * so the shared script leaves them alone and they keep their own scoped
     * handler. Vendor registration extends `layouts.landing.app`, which loads
     * neither admin-tabs.css nor the shared script. RideShare's trip log
     * scrolls a `.ride-process-steps` row that is not a tab strip at all.
     */
    private const OWN_HANDLER = [
        'resources/views/admin-views/partials/_multiple-image-uploader.blade.php',
        'resources/views/vendor-views/auth/general-info.blade.php',
        'Modules/RideShare/public/assets/js/view-pages/trip-log.js',
    ];

    public function test_both_panel_layouts_load_the_shared_scroller(): void
    {
        foreach (['admin', 'vendor'] as $panel) {
            $layout = File::get(base_path("resources/views/layouts/{$panel}/app.blade.php"));

            $this->assertStringContainsString(self::SCRIPT, $layout, "{$panel} layout must load the tab scroller");
            $this->assertStringNotContainsString(
                "document.querySelector('.tabs-inner')",
                $layout,
                "{$panel} layout must not keep the inline handler the shared script replaced"
            );
        }
    }

    public function test_no_panel_view_rebinds_the_tab_arrows(): void
    {
        $offenders = [];

        foreach (['resources/views', 'Modules'] as $root) {
            foreach (File::allFiles(base_path($root)) as $file) {
                if (! in_array($file->getExtension(), ['php', 'js'], true)) {
                    continue;
                }

                $relative = str_replace(base_path().'/', '', $file->getPathname());
                if (in_array($relative, self::OWN_HANDLER, true)) {
                    continue;
                }

                $body = $file->getContents();
                if (str_contains($body, 'btn-click-prev') && preg_match('/btn-click-prev[\'"]\s*\)/', $body)) {
                    $offenders[] = $relative;
                }
            }
        }

        $this->assertSame([], $offenders, 'these bind the tab arrows themselves instead of using tab-scroller.js');
    }

    public function test_a_long_tab_strip_still_renders_with_its_arrows(): void
    {
        $admin = Admin::find(1);
        $this->assertNotNull($admin, 'admin id 1 must exist');

        $html = $this->actingAs($admin, 'admin')
            ->withSession(['login_remember_token' => $admin->login_remember_token])
            ->get('admin/business-settings/pages/react-landing-page-settings/meta-data')
            ->getContent();

        $this->assertStringContainsString(self::SCRIPT, $html);
        $this->assertStringContainsString('css/admin-tabs.css', $html);
        $this->assertStringContainsString('arrow-area', $html);
        $this->assertStringContainsString('btn-click-next', $html);
        $this->assertMatchesRegularExpression(
            '/nav-link active"\s*\n?\s*href="[^"]*react-landing-page-settings\/meta-data"/',
            $html,
            'the Meta Data pill must be the active one — the script reveals whichever pill carries .active'
        );
    }
}
