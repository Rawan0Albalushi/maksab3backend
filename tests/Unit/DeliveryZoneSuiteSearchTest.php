<?php

namespace Tests\Unit;

use App\Models\AdditionalDeliveryCharge;
use App\Models\Area;
use App\Models\Bundle;
use App\Models\DeliveryRule;
use App\Models\Dimension;
use App\Models\FreeDelivery;
use App\Models\SurgePrice;
use App\Models\Weight;
use App\Models\ZipCode;
use App\Services\Search\AdminSearchRegistry;
use App\Services\Search\RouteIndex;
use App\Services\Search\SearchContext;
use App\Services\Search\SearchEngine;
use App\Services\Search\SearchKeyword;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * TC_742 — the admin search registry covered none of the ported delivery/promotion features.
 * Verified live earlier this session: searching for a real BOGO offer, surge price, or delivery
 * rule by name returned nothing, while a Zone or Store search worked. AdminSearchRegistry now
 * registers all of it (deliveryZoneSuite()) — this pins the entries that have real seed data to
 * search against, and separately proves the module-relation-scoped ones (EtaConfiguration,
 * FreeDelivery — pivot tables, not a plain module_id column) don't throw.
 */
class DeliveryZoneSuiteSearchTest extends TestCase
{
    use DatabaseTransactions;

    private function engine(?string $moduleType = null, ?int $moduleId = null, bool $inWorkspace = false): SearchEngine
    {
        $context = new SearchContext(
            panelPrefix: 'admin/',
            moduleType: $moduleType,
            moduleId: $moduleId,
            inModuleWorkspace: $inWorkspace,
        );

        return new SearchEngine(new RouteIndex('admin/', [], fn () => true), $context);
    }

    private function uris(array $results): array
    {
        return array_column($results, 'URI');
    }

    public function test_surge_price_is_found_by_its_name(): void
    {
        $surge = SurgePrice::query()->first();

        if (! $surge) {
            $this->markTestSkipped('no surge price seed row');
        }

        $results = $this->engine()->records(new SearchKeyword($surge->surge_price_name), AdminSearchRegistry::all());

        $this->assertContains("admin/delivery-management/surge-price/edit/{$surge->id}", $this->uris($results));
    }

    public function test_delivery_rule_is_found_within_its_own_module_workspace(): void
    {
        $rule = DeliveryRule::query()->first();

        if (! $rule) {
            $this->markTestSkipped('no delivery rule seed row');
        }

        $moduleType = \App\Models\Module::find($rule->module_id)?->module_type;
        $results = $this->engine($moduleType, $rule->module_id, true)
            ->records(new SearchKeyword($rule->name), AdminSearchRegistry::all());

        $this->assertContains("admin/delivery-management/delivery-rule/edit/{$rule->id}", $this->uris($results));
    }

    public function test_delivery_rule_is_not_found_outside_its_module_workspace(): void
    {
        $rule = DeliveryRule::query()->first();

        if (! $rule) {
            $this->markTestSkipped('no delivery rule seed row');
        }

        // Global search context (no module workspace) — moduleScoped() entities disable
        // themselves entirely, matching how coupon/banner already behave.
        $results = $this->engine()->records(new SearchKeyword($rule->name), AdminSearchRegistry::all());

        $this->assertNotContains("admin/delivery-management/delivery-rule/edit/{$rule->id}", $this->uris($results));
    }

    /**
     * Bundle joined the registry later under the same TC_742 follow-up (QA: "Bundle is not
     * showing" in admin search) -- it is module-scoped exactly like Delivery Rule, so it is
     * proved the same way: found inside its own module workspace, absent outside it.
     */
    public function test_bundle_is_found_within_its_own_module_workspace(): void
    {
        $bundle = Bundle::query()->first();

        if (! $bundle) {
            $this->markTestSkipped('no bundle seed row');
        }

        $moduleType = \App\Models\Module::find($bundle->module_id)?->module_type;
        $results = $this->engine($moduleType, $bundle->module_id, true)
            ->records(new SearchKeyword($bundle->name), AdminSearchRegistry::all());

        $this->assertContains("admin/bundle/edit/{$bundle->id}", $this->uris($results));
    }

    public function test_bundle_is_not_found_outside_its_module_workspace(): void
    {
        $bundle = Bundle::query()->first();

        if (! $bundle) {
            $this->markTestSkipped('no bundle seed row');
        }

        // Global search context (no module workspace) — moduleScoped() entities disable
        // themselves entirely, matching how coupon/banner/delivery-rule already behave.
        $results = $this->engine()->records(new SearchKeyword($bundle->name), AdminSearchRegistry::all());

        $this->assertNotContains("admin/bundle/edit/{$bundle->id}", $this->uris($results));
    }

    public function test_zip_code_is_found_by_its_numeric_value(): void
    {
        $zip = ZipCode::query()->first();

        if (! $zip) {
            $this->markTestSkipped('no zip code seed row');
        }

        // Regression: a purely numeric keyword is treated as an id lookup by default, which
        // would search the wrong column entirely for a zip code (its VALUE is numeric, not
        // its row id) without the custom idFilter().
        $results = $this->engine()->records(new SearchKeyword($zip->zip_code), AdminSearchRegistry::all());

        $this->assertContains("admin/delivery-management/zip-code/edit/{$zip->id}", $this->uris($results));
    }

    public function test_area_weight_and_dimension_are_found_by_name(): void
    {
        $weight = Weight::query()->first();
        $dimension = Dimension::query()->first();
        $area = Area::query()->first();

        if ($weight) {
            $results = $this->engine()->records(new SearchKeyword($weight->name), AdminSearchRegistry::all());
            $this->assertContains("admin/delivery-management/weight/edit/{$weight->id}", $this->uris($results));
        }

        if ($dimension) {
            $results = $this->engine()->records(new SearchKeyword($dimension->name), AdminSearchRegistry::all());
            $this->assertContains("admin/delivery-management/dimension/edit/{$dimension->id}", $this->uris($results));
        }

        if ($area) {
            $results = $this->engine()->records(new SearchKeyword($area->name), AdminSearchRegistry::all());
            $this->assertContains("admin/delivery-management/area/edit/{$area->id}", $this->uris($results));
        }
    }

    public function test_free_delivery_and_additional_delivery_charge_are_found_by_their_zone_name(): void
    {
        $freeDelivery = FreeDelivery::with(['zone', 'modules'])->first();
        $adc = AdditionalDeliveryCharge::with('zone')->first();

        if ($freeDelivery && $freeDelivery->modules->isNotEmpty()) {
            $module = $freeDelivery->modules->first();
            $results = $this->engine($module->module_type, $module->id, true)
                ->records(new SearchKeyword($freeDelivery->zone->name), AdminSearchRegistry::all());
            $this->assertContains("admin/delivery-management/free-delivery/edit/{$freeDelivery->id}", $this->uris($results));
        }

        if ($adc) {
            $results = $this->engine()->records(new SearchKeyword($adc->zone->name), AdminSearchRegistry::all());
            $this->assertContains("admin/delivery-management/additional-delivery-charge/edit/{$adc->id}", $this->uris($results));
        }
    }

    public function test_bogo_and_happy_hour_and_eta_entities_run_without_error_even_with_no_matches(): void
    {
        // No BOGO/Happy Hour/ETA seed rows exist in this dataset — this pins that the query
        // itself is well-formed (module_id column vs modules() pivot resolved correctly) rather
        // than asserting a specific match.
        $results = $this->engine('grocery', 1, true)
            ->records(new SearchKeyword('nonexistent search term xyz'), AdminSearchRegistry::all());

        $this->assertIsArray($results);
    }
}
