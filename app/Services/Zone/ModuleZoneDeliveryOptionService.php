<?php

namespace App\Services\Zone;

use App\Models\AdditionalDeliveryCharge;
use App\Models\ModuleZoneDeliveryOption;
use App\Services\BaseService;

/**
 * The read seam the order paths use to ask "what saver offers does this (zone, module) make?".
 *
 * **The answers now come from `additional_delivery_charges`, not from the table this class is
 * named after.** The Additional Charge screen owns that data; `module_zone_delivery_options` is
 * left in the database with its rows intact, but nothing reads or writes it any more.
 *
 * The name is kept for now because five order-path call sites reach through it and their
 * behaviour must not shift while storage moves underneath — renaming is a separate, mechanical
 * change. What those callers get back is unchanged in shape: ModuleZoneDeliveryOption instances
 * with `extra_charge`, `reduce_charge` and raw minute values, built in memory rather than loaded.
 *
 * Writes live on AdditionalDeliveryChargeService. This class only reads.
 */
class ModuleZoneDeliveryOptionService extends BaseService
{
    /**
     * How a setup's two offers get stable ids: `setup id * 10 + offset`.
     *
     * The POS and the apps render the offer list, then post back the id of the one the customer
     * picked, so an id has to survive a round trip inside one request. It is never stored —
     * `orders.delivery_type` keeps the type string — so the scheme only has to be reversible,
     * not permanent. Setup 4 therefore offers ids 41 (express) and 42 (slightly delay).
     *
     * Old `module_zone_delivery_options` ids were small integers, so a stale client id lands on
     * setup 0, finds nothing, and falls back to standard delivery rather than mispricing.
     */
    private const ID_STRIDE = 10;

    private const OFFSETS = [
        ModuleZoneDeliveryOption::TYPE_EXPRESS => 1,
        ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY => 2,
    ];

    /** The offer of one type, or null when the platform makes none here. */
    public function findForModuleZoneType(mixed $moduleId, mixed $zoneId, mixed $type): ?ModuleZoneDeliveryOption
    {
        return $this->optionsFor($moduleId, $zoneId)->firstWhere('delivery_type', $type);
    }

    /** Every offer this (zone, module) makes, standard first — the order the POS lists them in. */
    public function optionsFor(mixed $moduleId, mixed $zoneId): \Illuminate\Support\Collection
    {
        $setup = app(AdditionalDeliveryChargeService::class)->activeSetup($zoneId, $moduleId);

        if (! $setup) {
            return collect();
        }

        $options = collect([$this->row($setup, ModuleZoneDeliveryOption::TYPE_STANDARD)]);

        if ($setup->offersExpress()) {
            $options->push($this->row($setup, ModuleZoneDeliveryOption::TYPE_EXPRESS));
        }

        if ($setup->offersDelay()) {
            $options->push($this->row($setup, ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY));
        }

        return $options;
    }

    /** An offer id back to its type, for the value a POS or app posts. */
    public function findDeliveryType(mixed $id): ?string
    {
        $offset = ((int) $id) % self::ID_STRIDE;

        return array_search($offset, self::OFFSETS, true) ?: null;
    }

    /**
     * One offer as the shape callers already read.
     *
     * `setRawAttributes(..., true)` syncs the original array too, so `getRawOriginal()` returns
     * the stored minutes — which is how every caller reads the time fields, because the model's
     * accessor hands back a value/unit pair meant for the form.
     */
    private function row(AdditionalDeliveryCharge $setup, string $type): ModuleZoneDeliveryOption
    {
        $express = $type === ModuleZoneDeliveryOption::TYPE_EXPRESS;
        $delay = $type === ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY;

        return (new ModuleZoneDeliveryOption)->setRawAttributes([
            'id' => $setup->id * self::ID_STRIDE + (self::OFFSETS[$type] ?? 0),
            'module_id' => null,
            'zone_id' => $setup->zone_id,
            'delivery_type' => $type,
            // Standard carries neither, by definition — it is the absence of an offer.
            'extra_charge' => $express ? $setup->express_extra_charge : null,
            'reduce_charge' => $delay ? $setup->delay_reduce_charge : null,
            'add_delivery_time' => $delay ? (int) $setup->delay_add_delivery_time : 0,
            'reduce_delivery_time' => $express ? (int) $setup->express_reduce_delivery_time : 0,
        ], true);
    }
}
