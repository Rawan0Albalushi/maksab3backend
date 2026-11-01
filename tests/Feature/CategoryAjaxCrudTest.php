<?php

namespace Tests\Feature;

use App\Http\Middleware\AjaxActionResponse;
use App\Models\Admin;
use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryAjaxCrudTest extends TestCase
{
    private const PAGE = '/admin/category/add?position=0&module_id=1';

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::find(1);
        $this->assertNotNull($this->admin, 'admin id 1 must exist');

        Storage::fake(config('filesystems.default'));
        Storage::fake('s3');
        Storage::fake('public');
    }

    private function panel(array $headers = [])
    {
        return $this->actingAs($this->admin, 'admin')
            ->withSession([
                'login_remember_token' => $this->admin->login_remember_token,
                '_previous' => ['url' => url(self::PAGE)],
            ])
            ->withHeaders($headers + ['Referer' => url(self::PAGE)]);
    }

    private function ajaxHeaders(): array
    {
        return [
            AjaxActionResponse::HEADER => '1',
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }

    private function catName(): string
    {
        return 'ajaxcat-'.uniqid();
    }

    private function forget(string $name): void
    {
        Category::withoutGlobalScopes()->where('name', $name)->forceDelete();
    }

    public function test_the_page_ships_the_region_and_the_ajax_form(): void
    {
        $html = $this->panel()->get(self::PAGE)->assertOk()->getContent();

        $this->assertStringContainsString('data-ajax-region', $html, 'the list is a refreshable region');
        $this->assertStringContainsString('data-ajax-links', $html, 'tabs and pagination refresh in place');
        $this->assertStringContainsString('data-ajax-forms', $html, 'search refreshes in place');
        $this->assertStringContainsString('id="category-add-form"', $html);
        $this->assertStringContainsString('data-ajax-form', $html, 'the add form and the delete forms opt in');
        $this->assertStringContainsString('data-ajax-remove="closest:tr"', $html, 'a deleted row goes at once');

        $this->assertStringNotContainsString(
            'category-list-ajax.js',
            $html,
            'the bespoke list script is retired — a surviving copy double-fires every tab click'
        );
    }

    public function test_the_status_switch_asks_for_a_refresh_only_on_a_filtered_tab(): void
    {
        $all = $this->panel()->get(self::PAGE.'&status=all')->assertOk()->getContent();
        $active = $this->panel()->get(self::PAGE.'&status=active')->assertOk()->getContent();

        $switchesOn = $this->statusSwitches($all);
        $this->assertNotEmpty($switchesOn, 'the All tab still renders status switches');

        foreach ($switchesOn as $tag) {
            $this->assertStringNotContainsString(
                'data-ajax-refresh',
                $tag,
                'on All the row keeps its place, so a refresh would be a request that changes nothing'
            );
        }

        $switchesFiltered = $this->statusSwitches($active);

        if ($switchesFiltered === []) {
            $this->markTestSkipped('no active category to render a switch for');
        }

        foreach ($switchesFiltered as $tag) {
            $this->assertStringContainsString(
                'data-ajax-refresh',
                $tag,
                'a row deactivated on the Active tab no longer belongs in the list'
            );
        }
    }

    private function statusSwitches(string $html): array
    {
        preg_match_all('/<input\b[^>]*\bredirect-url\b[^>]*>/', $html, $matches);

        return $matches[0];
    }

    public function test_a_fragment_request_answers_with_the_list_alone(): void
    {
        $body = $this->panel([
            AjaxActionResponse::FRAGMENT_HEADER => '[data-ajax-region]',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->get(self::PAGE)
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<html', $body, 'the controller already answers ajax with the partial');
        $this->assertStringContainsString('nav-tabs', $body, 'and the partial carries the tabs the region re-binds');
    }

    public function test_create_answers_json_and_the_category_lands(): void
    {
        $name = $this->catName();

        $response = $this->panel($this->ajaxHeaders())->post('/admin/category/add', [
            'name' => [$name],
            'lang' => ['default'],
            'position' => 0,
            'priority' => 0,
            'image' => UploadedFile::fake()->image('category.png', 100, 100),
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true, 'type' => 'success']);

        $this->assertNotNull(
            Category::withoutGlobalScopes()->where('name', $name)->first(),
            'the controller still does its work'
        );

        $this->forget($name);
    }

    public function test_create_reports_validation_errors_per_field(): void
    {
        $response = $this->panel($this->ajaxHeaders())->post('/admin/category/add', [
            'name' => [''],
            'lang' => ['default'],
            'position' => 0,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['ok' => false]);

        $errors = $response->json('errors');
        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors, 'the client places each message under its own input');
    }

    public function test_a_rejected_upload_is_reported_as_a_failure(): void
    {
        $response = $this->panel($this->ajaxHeaders())->post('/admin/category/add', [
            'name' => [$this->catName()],
            'lang' => ['default'],
            'position' => 0,
            'priority' => 0,
            'image' => UploadedFile::fake()->create('notes.txt', 8, 'text/plain'),
        ]);

        $this->assertContains($response->getStatusCode(), [200, 422]);
        $response->assertJson(['ok' => false]);
        $this->assertNotEmpty($response->json('errors'));
        $this->assertNotSame('success', $response->json('type'));
    }

    public function test_update_answers_json_without_a_redirect_to_follow(): void
    {
        $name = $this->catName();
        $this->panel()->post('/admin/category/add', [
            'name' => [$name],
            'lang' => ['default'],
            'position' => 0,
            'priority' => 0,
            'image' => UploadedFile::fake()->image('category.png', 100, 100),
        ]);

        $category = Category::withoutGlobalScopes()->where('name', $name)->first();
        $this->assertNotNull($category);

        $renamed = $name.'-edited';

        $response = $this->panel($this->ajaxHeaders())->post('/admin/category/update/'.$category->id, [
            'name' => [$renamed],
            'lang' => ['default'],
            'priority' => 0,
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true, 'type' => 'success']);
        $this->assertNull($response->json('redirect'), 'a followed redirect would reload the page');
        $this->assertNotNull($response->json('redirect_to'), 'it is still reported');

        $this->assertSame(
            $renamed,
            Category::withoutGlobalScopes()->find($category->id)->name
        );

        $this->forget($renamed);
        $this->forget($name);
    }

    public function test_delete_answers_json_and_removes_the_row(): void
    {
        $name = $this->catName();
        $this->panel()->post('/admin/category/add', [
            'name' => [$name],
            'lang' => ['default'],
            'position' => 0,
            'priority' => 0,
            'image' => UploadedFile::fake()->image('category.png', 100, 100),
        ]);

        $category = Category::withoutGlobalScopes()->where('name', $name)->first();
        $this->assertNotNull($category);

        $response = $this->panel($this->ajaxHeaders())->delete('/admin/category/delete/'.$category->id);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        $this->assertNull(Category::withoutGlobalScopes()->find($category->id));
    }

    public function test_a_refused_delete_reports_not_ok_so_the_row_survives(): void
    {
        $parentName = $this->catName();
        $this->panel()->post('/admin/category/add', [
            'name' => [$parentName],
            'lang' => ['default'],
            'position' => 0,
            'priority' => 0,
            'image' => UploadedFile::fake()->image('category.png', 100, 100),
        ]);

        $parent = Category::withoutGlobalScopes()->where('name', $parentName)->first();
        $this->assertNotNull($parent);

        $childName = $this->catName();
        $this->panel()->post('/admin/category/add', [
            'name' => [$childName],
            'lang' => ['default'],
            'position' => 1,
            'parent_id' => $parent->id,
            'priority' => 0,
        ]);

        $child = Category::withoutGlobalScopes()->where('name', $childName)->first();
        $this->assertNotNull($child, 'the sub category has to exist for the refusal to happen');

        $response = $this->panel($this->ajaxHeaders())->delete('/admin/category/delete/'.$parent->id);

        $response->assertOk();
        $response->assertJson(['ok' => false]);
        $this->assertNotNull(
            Category::withoutGlobalScopes()->find($parent->id),
            'the parent is still there, so the row must not have been removed'
        );

        Category::withoutGlobalScopes()->whereIn('id', [$child->id, $parent->id])->forceDelete();
    }
}
