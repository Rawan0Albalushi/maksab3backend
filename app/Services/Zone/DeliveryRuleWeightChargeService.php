<?php

namespace App\Services\Zone;

use App\Models\DeliveryRule;
use App\Models\DeliveryRuleWeightCharge;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

/**
 * Owns App\Models\DeliveryRuleWeightCharge — what each weight band adds to a rule's base charge.
 *
 * Separate from DeliveryRuleChargeService because it is a separate model and, more importantly, a
 * different KIND of charge: coverage rows select the base, these stack on top of it.
 *
 * Nothing here prices an order yet. `chargeForWeight()` exists so the fee engine has one place to
 * call when S5+ wires the parcel tier in, and so no caller invents its own lookup.
 */
class DeliveryRuleWeightChargeService extends BaseService
{
    /**
     * Replace a rule's weight charges with the submitted set.
     *
     * Delete-then-insert for the same reason the coverage rows use it: the wizard always posts the
     * complete list of active bands, so a row not in the payload is one whose band was deactivated
     * or deleted. Keeping it would leave a charge nothing can select.
     *
     * Clearing when the step is switched OFF is deliberate — otherwise switching it back on would
     * silently restore amounts the admin last saw months ago, which is the trap §5.3 already
     * closed for coverage.
     */
    public function syncForRule(DeliveryRule $rule, array $charges, bool $enabled): void
    {
        DB::transaction(function () use ($rule, $charges, $enabled) {
            $rule->weightCharges()->delete();

            if (! $enabled || empty($charges)) {
                return;
            }

            $rows = [];

            foreach ($charges as $weightId => $charge) {
                if (empty($weightId)) {
                    continue;
                }

                $rows[] = [
                    'delivery_rule_id' => $rule->id,
                    'weight_id' => (int) $weightId,
                    // A blank input means "no extra for this band", not "skip the row" — the
                    // design's own note says to enter 0 when nothing should be added.
                    'charge' => max(0, (float) $charge),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($rows) {
                DeliveryRuleWeightCharge::insert($rows);
            }
        });
    }

    /**
     * What one band adds under this rule. Zero when the band was never priced, matching how an
     * unpriced area behaves (§5.3).
     *
     * Not called by anything yet — the fee engine picks it up when the parcel tier is wired.
     */
    public function chargeForWeight(mixed $ruleId, mixed $weightId): float
    {
        if (empty($weightId)) {
            return 0.0;
        }

        return (float) DeliveryRuleWeightCharge::query()
            ->where('delivery_rule_id', $ruleId)
            ->where('weight_id', $weightId)
            ->value('charge');
    }

    /** The rule's charges keyed by band id, so the edit form prefills without searching per row. */
    public function keyedByWeight(mixed $ruleId): array
    {
        return DeliveryRuleWeightCharge::query()
            ->where('delivery_rule_id', $ruleId)
            ->pluck('charge', 'weight_id')
            ->all();
    }
}
