<?php

namespace App\Services\Zone;

use App\Models\DeliveryRule;
use App\Models\DeliveryRuleDimensionCharge;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

/**
 * Owns App\Models\DeliveryRuleDimensionCharge — what each package size class adds to a rule's base charge.
 *
 * Separate from DeliveryRuleChargeService because it is a separate model and, more importantly, a
 * different KIND of charge: coverage rows select the base, these stack on top of it.
 *
 * Nothing here prices an order yet. `chargeForDimension()` exists so the fee engine has one place to
 * call when S5+ wires the parcel tier in, and so no caller invents its own lookup.
 */
class DeliveryRuleDimensionChargeService extends BaseService
{
    /**
     * Replace a rule's dimension charges with the submitted set.
     *
     * Delete-then-insert for the same reason the coverage rows use it: the wizard always posts the
     * complete list of active size classes, so a row not in the payload is one that was deactivated
     * or deleted. Keeping it would leave a charge nothing can select.
     *
     * Clearing when the step is switched OFF is deliberate — otherwise switching it back on would
     * silently restore amounts the admin last saw months ago, which is the trap §5.3 already
     * closed for coverage.
     */
    public function syncForRule(DeliveryRule $rule, array $charges, bool $enabled): void
    {
        DB::transaction(function () use ($rule, $charges, $enabled) {
            $rule->dimensionCharges()->delete();

            if (! $enabled || empty($charges)) {
                return;
            }

            $rows = [];

            foreach ($charges as $dimensionId => $charge) {
                if (empty($dimensionId)) {
                    continue;
                }

                $rows[] = [
                    'delivery_rule_id' => $rule->id,
                    'dimension_id' => (int) $dimensionId,
                    // A blank input means "no extra for this size", not "skip the row" — the
                    // design's own note says to enter 0 when nothing should be added.
                    'charge' => max(0, (float) $charge),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($rows) {
                DeliveryRuleDimensionCharge::insert($rows);
            }
        });
    }

    /**
     * What one size class adds under this rule. Zero when the band was never priced, matching how an
     * unpriced area behaves (§5.3).
     *
     * Not called by anything yet — the fee engine picks it up when the parcel tier is wired.
     */
    public function chargeForDimension(mixed $ruleId, mixed $dimensionId): float
    {
        if (empty($dimensionId)) {
            return 0.0;
        }

        return (float) DeliveryRuleDimensionCharge::query()
            ->where('delivery_rule_id', $ruleId)
            ->where('dimension_id', $dimensionId)
            ->value('charge');
    }

    /** The rule's charges keyed by dimension id, so the edit form prefills without searching per row. */
    public function keyedByDimension(mixed $ruleId): array
    {
        return DeliveryRuleDimensionCharge::query()
            ->where('delivery_rule_id', $ruleId)
            ->pluck('charge', 'dimension_id')
            ->all();
    }
}
