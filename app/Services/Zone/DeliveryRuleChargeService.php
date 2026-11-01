<?php

namespace App\Services\Zone;

use App\Models\DeliveryRule;
use App\Models\DeliveryRuleCharge;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

/**
 * Owns App\Models\DeliveryRuleCharge — the priced coverage rows hanging off a delivery rule.
 *
 * Separate from DeliveryRuleService because it is a separate model, and because the sync logic
 * is substantial enough that folding it in would bury the rule service's own methods.
 */
class DeliveryRuleChargeService extends BaseService
{
    /**
     * Replace a rule's charge rows with the submitted set.
     *
     * Delete-then-insert rather than upsert-and-diff: the admin form always posts the complete
     * list of the zone's areas (or ZIPs), so any row not in the payload is one the admin removed
     * or one whose coverage was deactivated. Keeping it would leave a charge nothing can select.
     *
     * A rule priced by distance or a fixed amount has no coverage rows at all, and switching a
     * rule to one of those methods clears them — otherwise switching back would silently restore
     * charges the admin last saw months ago.
     */
    public function syncForRule(DeliveryRule $rule, array $charges): void
    {
        DB::transaction(function () use ($rule, $charges) {
            $rule->charges()->delete();

            if (! $rule->needsCoveragePick() || empty($charges)) {
                return;
            }

            $isAreaWise = $rule->pricing_method === DeliveryRule::METHOD_AREA;
            $rows = [];

            foreach ($charges as $coverageId => $charge) {
                if (empty($coverageId)) {
                    continue;
                }

                $rows[] = [
                    'delivery_rule_id' => $rule->id,
                    'area_id' => $isAreaWise ? (int) $coverageId : null,
                    'zip_code_id' => $isAreaWise ? null : (int) $coverageId,
                    'charge' => max(0, (float) $charge),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($rows) {
                DeliveryRuleCharge::insert($rows);
            }
        });
    }

    /**
     * What one area costs under this rule.
     *
     * Returns 0.0 for an area the admin never priced — §5.3's "0 when unpriced". That is a
     * product decision, not an oversight: the rule's own minimum still applies on top, so an
     * unpriced area falls back to the floor rather than shipping free.
     */
    public function chargeForArea(mixed $ruleId, mixed $areaId): float
    {
        if (empty($areaId)) {
            return 0.0;
        }

        return (float) DeliveryRuleCharge::query()
            ->where('delivery_rule_id', $ruleId)
            ->where('area_id', $areaId)
            ->value('charge');
    }

    /** As chargeForArea(), for ZIP codes. */
    public function chargeForZipCode(mixed $ruleId, mixed $zipCodeId): float
    {
        if (empty($zipCodeId)) {
            return 0.0;
        }

        return (float) DeliveryRuleCharge::query()
            ->where('delivery_rule_id', $ruleId)
            ->where('zip_code_id', $zipCodeId)
            ->value('charge');
    }

    /**
     * The rule's charges keyed by coverage id, for re-populating the edit form without the Blade
     * having to search a collection per row.
     */
    public function keyedByCoverage(mixed $ruleId, bool $areaWise): array
    {
        $column = $areaWise ? 'area_id' : 'zip_code_id';

        return DeliveryRuleCharge::query()
            ->where('delivery_rule_id', $ruleId)
            ->whereNotNull($column)
            ->pluck('charge', $column)
            ->all();
    }
}
