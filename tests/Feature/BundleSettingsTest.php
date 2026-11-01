<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\Bundle;
use App\Models\Store;
use App\Support\Promotion\BundleSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BundleSettingsTest extends TestCase
{
    use DatabaseTransactions;

    private function store(bool $status, array $enabledTypes): void
    {
        $map = [];

        foreach (BundleSettings::moduleTypes() as $type) {
            $map[$type] = in_array($type, $enabledTypes, true) ? 1 : 0;
        }

        Helpers::businessUpdateOrInsert(['key' => BundleSettings::STATUS_KEY], ['value' => $status ? 1 : 0]);
        Helpers::businessUpdateOrInsert(['key' => BundleSettings::MODULES_KEY], ['value' => json_encode($map)]);
        Helpers::clearBusinessSettingsCache();
    }

    protected function tearDown(): void
    {
        Helpers::clearBusinessSettingsCache();
        parent::tearDown();
    }

    public function test_the_checkbox_list_comes_from_config_like_every_other_module_setting(): void
    {
        $this->assertSame(['grocery', 'food', 'pharmacy', 'ecommerce', 'service'], BundleSettings::moduleTypes());
    }

    public function test_the_types_that_cannot_carry_a_bundle_are_excluded(): void
    {
        foreach (['parcel', 'rental', 'ride-share'] as $type) {
            $this->assertNotContains($type, BundleSettings::moduleTypes(),
                "{$type} has no per-line price for a bundle to be built from.");
        }

        $this->assertContains('service', BundleSettings::moduleTypes(),
            'a service is priced per line, so it can be bundled');
    }

    public function test_a_module_must_be_both_enabled_and_ticked(): void
    {
        $this->store(status: true, enabledTypes: ['grocery']);

        $this->assertSame(['grocery'], BundleSettings::enabledModuleTypes());
        $this->assertNotContains('food', BundleSettings::enabledModuleTypes());
    }

    public function test_the_master_switch_empties_the_list_rather_than_flagging_it(): void
    {
        $this->store(status: false, enabledTypes: ['grocery', 'food']);

        $this->assertFalse(BundleSettings::enabled());
        $this->assertSame([], BundleSettings::enabledModuleTypes());
        $this->assertSame([], BundleSettings::availableModuleIds());
    }

    public function test_enabled_types_resolve_to_the_module_ids_a_query_can_filter_on(): void
    {
        $this->store(status: true, enabledTypes: ['grocery']);

        $expected = \App\Models\Module::where('module_type', 'grocery')->pluck('id')->map('intval')->all();

        $this->assertSame($expected, BundleSettings::availableModuleIds());
        $this->assertNotEmpty($expected, 'The fixture needs at least one grocery module.');
    }

    public function test_a_blank_or_unknown_module_is_refused(): void
    {
        $this->store(status: true, enabledTypes: ['grocery']);

        $this->assertFalse(BundleSettings::allowsModule(null));
        $this->assertFalse(BundleSettings::allowsModule(''));
        $this->assertFalse(BundleSettings::allowsModule(999999));
    }

    public function test_the_available_scope_hides_bundles_in_a_module_that_is_not_ticked(): void
    {
        $store = Store::withoutGlobalScopes()->whereNotNull('module_id')->first();

        if (! $store) {
            $this->markTestSkipped('No store seeded.');
        }

        $type = \App\Models\Module::whereKey($store->module_id)->value('module_type');

        if (! in_array($type, BundleSettings::moduleTypes(), true)) {
            $this->markTestSkipped('The seeded store is not in a bundle-capable module.');
        }

        $bundle = Bundle::create([
            'store_id' => $store->id,
            'module_id' => $store->module_id,
            'name' => 'ZZ Scope Bundle',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(30),
        ]);

        $this->store(status: true, enabledTypes: [$type]);
        $this->assertTrue(Bundle::available()->whereKey($bundle->id)->exists());

        $this->store(status: false, enabledTypes: [$type]);
        $this->assertFalse(Bundle::available()->whereKey($bundle->id)->exists(), 'The master switch hides them.');

        $this->store(status: true, enabledTypes: []);
        $this->assertFalse(Bundle::available()->whereKey($bundle->id)->exists(), 'An unticked module hides them.');
    }

    public function test_the_stored_map_matches_the_shape_used_by_the_other_module_setting(): void
    {
        $this->store(status: true, enabledTypes: ['grocery', 'pharmacy']);

        $map = BundleSettings::selectedModules();

        $this->assertSame(['grocery', 'food', 'pharmacy', 'ecommerce', 'service'], array_keys($map), 'Same key-per-type shape as extra_packaging_data.');
        $this->assertSame(1, $map['grocery']);
        $this->assertSame(0, $map['food']);
    }
}
