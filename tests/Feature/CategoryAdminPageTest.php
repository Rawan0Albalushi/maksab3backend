<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use Tests\TestCase;

class CategoryAdminPageTest extends TestCase
{
    private function admin(): Admin
    {
        $admin = Admin::find(1);
        $this->assertNotNull($admin, 'admin id 1 must exist');

        return $admin;
    }

    public function test_main_category_page_renders(): void
    {
        $response = $this->actingAs($this->admin(), 'admin')
            ->withSession(['login_remember_token' => $this->admin()->login_remember_token])
            ->get('/admin/category/add?position=0&module_id=1');

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('tps-card__body', $html, 'add form uses the tps card');
        $this->assertStringNotContainsString('tps-card__head', $html,
            'the page header names this form; a card header would only repeat it');
        $this->assertSame(1, substr_count($html, 'page-header-desc'),
            'the page header carries the one description');
        $this->assertStringContainsString('Main Category List', $html);
        $this->assertStringNotContainsString('>SL<', $html, 'the SL column is gone');
        foreach (['Translations', 'Sub categories', 'Items', 'Created'] as $column) {
            $this->assertStringContainsString('>'.$column, $html, "column {$column} is present");
        }
    }

    public function test_main_category_list_ajax_partial_renders(): void
    {
        $response = $this->actingAs($this->admin(), 'admin')
            ->withSession(['login_remember_token' => $this->admin()->login_remember_token])
            ->get('/admin/category/add?position=0&status=active', ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $this->assertStringContainsString('cell-chip', $response->getContent());
    }

    public function test_sub_category_page_renders(): void
    {
        $response = $this->actingAs($this->admin(), 'admin')
            ->withSession(['login_remember_token' => $this->admin()->login_remember_token])
            ->get('/admin/category/add?position=1&module_id=1');

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('tps-card__body', $html, 'add form uses the tps card');
        $this->assertStringNotContainsString('tps-card__head', $html,
            'the page header names this form; a card header would only repeat it');
        $this->assertSame(1, substr_count($html, 'page-header-desc'),
            'the page header carries the one description');
        $this->assertStringContainsString('Main sub category list', $html);
        $this->assertStringNotContainsString('>SL<', $html, 'the SL column is gone');
        foreach (['Main Sub Category', 'Main Category', 'Translations', 'Items', 'Created'] as $column) {
            $this->assertStringContainsString('>'.$column, $html, "column {$column} is present");
        }
        // The parent select is what makes this form different from the main one.
        $this->assertStringContainsString('name="parent_id"', $html);
        $this->assertStringContainsString('value="1"', $html, 'position stays 1');
    }

    public function test_sub_category_list_ajax_partial_renders(): void
    {
        $response = $this->actingAs($this->admin(), 'admin')
            ->withSession(['login_remember_token' => $this->admin()->login_remember_token])
            ->get('/admin/category/add?position=1&status=active', ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $this->assertStringContainsString('priority-select', $response->getContent());
    }

    /**
     * A main category counts everything rolling up to it; a sub category counts only what is
     * filed directly on it. Counting a sub over top_category_id would report zero for every row.
     */
    public function test_item_counts_use_the_right_column_per_level(): void
    {
        $service = app(\App\Services\Item\CategoryService::class);

        $sub = Category::withoutGlobalScope('translate')
            ->where('position', 1)
            ->whereIn('id', function ($query) {
                $query->select('category_id')->from('items')->whereNotNull('category_id');
            })
            ->first();
        $this->assertNotNull($sub, 'need a sub category with items');

        $direct = $service->getItemCounts([$sub->id], 'category_id');
        $this->assertGreaterThan(0, $direct[$sub->id] ?? 0);

        $this->assertSame([], $service->getItemCounts([], 'category_id'));
        $this->expectException(\InvalidArgumentException::class);
        $service->getItemCounts([$sub->id], 'store_id');
    }

    public function test_edit_offcanvas_renders_for_main_and_sub(): void
    {
        $main = Category::withoutGlobalScope('translate')->where('position', 0)->first();
        $sub = Category::withoutGlobalScope('translate')->where('position', 1)->first();
        $this->assertNotNull($main);
        $this->assertNotNull($sub);

        foreach ([$main, $sub] as $category) {
            $response = $this->actingAs($this->admin(), 'admin')
                ->withSession(['login_remember_token' => $this->admin()->login_remember_token])
                ->get('/admin/category/update/'.$category->id);

            $response->assertOk();
            $view = $response->json('view');
            $this->assertIsString($view);
            $this->assertStringContainsString('tps-switchbar', $view);
            $this->assertStringContainsString('name="priority"', $view);
            $this->assertStringContainsString('name="parent_id"', $view);
            if ($category->position === 1) {
                $this->assertStringContainsString('tps-readonly--text', $view, 'a sub names its main category');
            }
            $this->assertStringNotContainsString('@php', $view, 'blade directives must be compiled');
        }
    }
}
