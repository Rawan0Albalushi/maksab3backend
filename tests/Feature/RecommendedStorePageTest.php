<?php

namespace Tests\Feature;

use App\Models\Admin;
use Tests\TestCase;

class RecommendedStorePageTest extends TestCase
{
    private function admin(): Admin
    {
        $admin = Admin::find(1);
        $this->assertNotNull($admin, 'admin id 1 must exist');

        return $admin;
    }

    private function page(string $query = ''): string
    {
        $response = $this->actingAs($this->admin(), 'admin')
            ->withSession(['login_remember_token' => $this->admin()->login_remember_token])
            ->get('/admin/store/recommended-store?module_id=2'.$query);

        $response->assertOk();

        return $response->getContent();
    }

    public function test_page_renders_the_picker_the_shuffle_bar_and_the_list(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('recommended-store.css', $html);
        $this->assertStringContainsString('tps-card rcs', $html, 'the picker is a tps card');
        $this->assertStringContainsString('rcs-results', $html);
        $this->assertStringContainsString('tps-switchbar', $html, 'shuffle is its own switch bar');
        $this->assertStringContainsString('id="store_shffle_form"', $html);
        $this->assertStringContainsString('>Recommended', $html, 'the toggle column is named');
        $this->assertStringNotContainsString('>SL<', $html, 'the SL column is gone');
        $this->assertStringNotContainsString('Recommended store list subtitle', $html,
            'the placeholder subtitle no longer reaches the browser');
    }

    public function test_the_picker_markup_is_balanced(): void
    {
        $html = $this->page();
        $picker = substr($html, strpos($html, 'tps-card rcs'));
        $picker = substr($picker, 0, strpos($picker, 'tps-switchbar'));

        $this->assertSame(
            substr_count($picker, '<div'),
            substr_count($picker, '</div>'),
            'the picker used to leave three divs open, which nested the list card inside it'
        );
    }

    public function test_the_shuffle_switch_targets_its_own_input(): void
    {
        $html = $this->page();

        $this->assertMatchesRegularExpression(
            '/<input[^>]*id="store_shffle"[^>]*data-id="store_shffle"/s',
            $html,
            'common.js resolves data-id as an element id, so it has to name the input'
        );
        $this->assertSame(1, substr_count($html, 'dynamic-checkbox'),
            'the class belongs on the input only — it used to sit on the label as well and fire twice');
    }

    public function test_the_search_field_offers_a_reset_and_an_empty_state(): void
    {
        $html = $this->page('&search=a-store-that-does-not-exist');

        $this->assertStringContainsString('No store matches this search', $html);
        $this->assertStringContainsString('empty--data', $html);
        $this->assertStringContainsString('module_id=2', $html,
            'the reset link keeps the module the page was opened for');
    }
}
