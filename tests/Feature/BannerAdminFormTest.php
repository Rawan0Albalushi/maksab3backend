<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Banner;
use Tests\TestCase;

class BannerAdminFormTest extends TestCase
{
    private function admin(): Admin
    {
        $admin = Admin::find(1);
        $this->assertNotNull($admin, 'admin id 1 must exist');

        return $admin;
    }

    private function visit(string $url): string
    {
        $response = $this->actingAs($this->admin(), 'admin')
            ->withSession(['login_remember_token' => $this->admin()->login_remember_token])
            ->get($url);

        $response->assertOk();

        return $response->getContent();
    }

    public function test_add_page_is_on_the_tps_system(): void
    {
        $html = $this->visit('/admin/banner?module_id=1');

        $this->assertStringContainsString('container-fluid tps bnr', $html);
        $this->assertStringContainsString('view-pages/banner-form.css', $html);
        $this->assertStringContainsString('window.bannerFormConfig', $html);
        $this->assertStringContainsString('banner-form.js', $html);
        $this->assertSame(1, substr_count($html, 'page-header-desc'),
            'the page header carries the one description');
        $this->assertStringNotContainsString(
            'tps-card__head',
            substr($html, 0, (int) strpos($html, 'bnr-aside')),
            'the page header names this form; a card header would only repeat it'
        );
    }

    public function test_banner_type_is_three_radio_cards_with_only_its_own_target_shown(): void
    {
        $html = $this->visit('/admin/banner?module_id=1');

        $this->assertStringNotContainsString('id="banner_type"', $html, 'the select is gone');
        foreach (['store_wise', 'item_wise', 'default'] as $type) {
            $this->assertStringContainsString('name="banner_type" value="'.$type.'"', $html);
        }

        $this->assertStringContainsString('id="store_wise">', $html, 'the checked type shows its target');
        $this->assertStringContainsString('id="item_wise" style="display: none;"', $html);
        $this->assertStringContainsString('id="default" style="display: none;"', $html);
    }

    public function test_edit_page_preselects_the_stored_type(): void
    {
        $banners = Banner::where('created_by', 'admin')
            ->get()
            ->unique('type')
            ->take(3);

        $this->assertNotEmpty($banners, 'at least one admin banner must exist');

        foreach ($banners as $banner) {
            $type = in_array($banner->type, ['default', 'default_link'], true) ? 'default' : $banner->type;
            $html = $this->visit('/admin/banner/edit/'.$banner->id.'?module_id='.$banner->module_id);

            $this->assertStringContainsString('container-fluid tps bnr', $html, 'banner '.$banner->id);
            $this->assertStringContainsString('name="banner_type" value="'.$type.'" checked', $html,
                'banner '.$banner->id.' preselects its type');
            $this->assertStringContainsString('id="'.$type.'">', $html,
                'banner '.$banner->id.' shows the target its type needs');
            $this->assertStringContainsString('"isEdit":true', $html, 'banner '.$banner->id);
        }
    }

    public function test_neither_page_still_loads_the_old_scripts(): void
    {
        $banner = Banner::where('created_by', 'admin')->first();
        $this->assertNotNull($banner);

        foreach (['/admin/banner?module_id=1', '/admin/banner/edit/'.$banner->id.'?module_id='.$banner->module_id] as $url) {
            $html = $this->visit($url);

            $this->assertStringNotContainsString('banner-index.js', $html, $url);
            $this->assertStringNotContainsString('banner-edit.js', $html, $url);
        }

        $this->assertStringNotContainsString(
            "on('ready'",
            file_get_contents(public_path('assets/admin/js/view-pages/banner-form.js')),
            'jQuery 3 removed the ready event; keep the form script in $(function () { ... })'
        );
    }
}
