<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\Zone\DeliveryRuleService;
use App\Services\Zone\EtaConfigurationService;
use Tests\TestCase;

/**
 * `lockedModulesFor()` used to take whatever the controller happened to hold.
 *
 * The Delivery Rule list passed its PAGINATOR while the ETA list passed `getCollection()`, and
 * `collect($paginator)` returns the pagination envelope rather than the rows — so the closure
 * typed to `DeliveryRule` was handed `current_page`, the integer 1, and the list 500'd.
 */
class LockedModulesInputShapeTest extends TestCase
{
    public function test_the_delivery_rule_list_renders(): void
    {
        $admin = Admin::find(1);
        $this->assertNotNull($admin);

        $this->actingAs($admin, 'admin')
            ->withSession(['current_module' => 1, 'login_remember_token' => $admin->login_remember_token])
            ->get('/admin/delivery-management/delivery-rule')
            ->assertOk();
    }

    public function test_locked_modules_accepts_a_paginator_a_collection_or_an_array(): void
    {
        $service = app(DeliveryRuleService::class);
        $page = $service->getList();

        $fromPaginator = $service->lockedModulesFor($page);
        $fromCollection = $service->lockedModulesFor($page->getCollection());
        $fromArray = $service->lockedModulesFor($page->items());

        $this->assertSame($fromCollection, $fromPaginator, 'a paginator must give the same answer as its rows');
        $this->assertSame($fromCollection, $fromArray);
        $this->assertCount($page->count(), $fromCollection);
    }

    public function test_the_eta_sibling_behaves_identically(): void
    {
        $service = app(EtaConfigurationService::class);
        $page = $service->getList();

        $this->assertSame(
            $service->lockedModulesFor($page->getCollection()),
            $service->lockedModulesFor($page),
            'the two lockedModulesFor() methods must accept the same shapes'
        );
    }

    public function test_an_empty_input_is_an_empty_map(): void
    {
        $this->assertSame([], app(DeliveryRuleService::class)->lockedModulesFor(collect()));
        $this->assertSame([], app(EtaConfigurationService::class)->lockedModulesFor([]));
    }
}
