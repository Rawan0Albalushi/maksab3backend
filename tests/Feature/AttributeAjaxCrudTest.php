<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Attribute;
use Tests\TestCase;

class AttributeAjaxCrudTest extends TestCase
{
    private function actor()
    {
        $admin = Admin::find(1);

        if (! $admin) {
            $this->markTestSkipped('admin id 1 must exist');
        }

        return $this->actingAs($admin, 'admin')
            ->withSession(['login_remember_token' => $admin->login_remember_token]);
    }

    public function test_the_page_ships_the_region_and_the_opted_in_form(): void
    {
        $html = $this->actor()->get('admin/attribute')->assertStatus(200)->getContent();

        $this->assertStringContainsString('id="attribute-list-wrapper"', $html);
        $this->assertStringContainsString('data-ajax-region', $html);
        $this->assertStringContainsString('data-ajax-form', $html);
        $this->assertStringContainsString('data-ajax-remove="closest:tr"', $html);
    }

    public function test_a_fragment_request_answers_with_the_list(): void
    {
        $html = $this->actor()
            ->withHeaders(['X-Ajax-Fragment' => '#attribute-list-wrapper'])
            ->get('admin/attribute?search=zzz-no-attribute-matches-this')
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('No attribute matches your search.', $html);
    }

    public function test_add_and_delete_answer_json(): void
    {
        $name = 'zz-test-attribute-'.uniqid();

        $this->actor()->withHeaders(['X-Ajax-Request' => '1'])
            ->postJson('admin/attribute/store', ['name' => [$name], 'lang' => ['default']])
            ->assertStatus(200)
            ->assertJson(['ok' => true]);

        $created = Attribute::query()->where('name', $name)->first();
        $this->assertNotNull($created);

        $this->actor()->withHeaders(['X-Ajax-Request' => '1'])
            ->postJson('admin/attribute/delete/'.$created->id, ['_method' => 'delete'])
            ->assertStatus(200)
            ->assertJson(['ok' => true]);

        $this->assertNull(Attribute::query()->where('name', $name)->first());
    }

    public function test_the_same_post_without_the_header_still_redirects(): void
    {
        $name = 'zz-test-attribute-'.uniqid();

        $this->actor()
            ->post('admin/attribute/store', ['name' => [$name], 'lang' => ['default']])
            ->assertRedirect();

        Attribute::query()->where('name', $name)->delete();
    }

    public function test_the_list_carries_the_usage_columns_and_no_serial_column(): void
    {
        $html = $this->actor()->get('admin/attribute')->assertStatus(200)->getContent();

        $this->assertStringContainsString('>Used in items</th>', $html);
        $this->assertStringContainsString('>Stores</th>', $html);
        $this->assertStringNotContainsString('>SL</th>', $html);
        $this->assertStringNotContainsString('>ID</th>', $html);
    }
}
