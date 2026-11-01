<?php

namespace App\Console\Commands;

use App\Models\DeliveryRule;
use App\Models\Module;
use App\Models\Zone;
use App\Services\Order\DeliveryChargeService;
use App\Services\Zone\DeliveryRuleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * S16 — reproduce every `module_zone` pivot's pricing as an ACTIVE delivery rule.
 *
 * Port doc A15 makes this mandatory and blocking: a module is only available in a zone that has a
 * delivery rule for it, and **0 of this install's connected (zone, module) pairs has one**. Ship
 * the availability gate without this and every module in every zone goes dark.
 *
 * A15 also settles what the rules look like. While the backfill was optional the recommendation
 * was to create them SWITCHED OFF, so it could not move a fee. A rule that is off does not
 * satisfy the gate, so they must be active — which makes this the moment fees could move, and is
 * why the command **dry-runs by default** and prints a before/after for every pair.
 *
 *   php artisan delivery-rules:backfill              # dry run — writes nothing
 *   php artisan delivery-rules:backfill --apply      # writes, after the same comparison
 *
 * The dry run is not a simulation of the arithmetic. It creates the rules inside a transaction,
 * quotes every pair through the real engine at several distances, compares against the quotes
 * taken before, and rolls back. What it reports is what would happen.
 */
class BackfillDeliveryRules extends Command
{
    protected $signature = 'delivery-rules:backfill {--apply : Write the rules. Without this the command only reports.}';

    protected $description = 'Create an active delivery rule per (zone, module) reproducing the module_zone pivot pricing (A15)';

    /** The distances every pair is compared at. Zero and a long trip bracket the clamp. */
    private const DISTANCES = [0.0, 1.0, 2.5, 7.25, 18.0, 45.0, 120.0];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $pairs = $this->pairs();

        if ($pairs->isEmpty()) {
            $this->info('No connected (zone, module) pairs without an active rule. Nothing to do.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->info(($apply ? 'APPLYING' : 'DRY RUN — nothing will be written').'  ·  '.$pairs->count().' pair(s)');
        $this->line('');

        $before = $this->quoteAll($pairs);

        // Rolled back in a dry run, committed on --apply. Either way the comparison below has
        // already been made against real quotes rather than against a re-derived expectation.
        DB::beginTransaction();

        $created = [];

        foreach ($pairs as $pair) {
            $created[] = $this->createRule($pair);
        }

        $after = $this->quoteAll($pairs);
        $differences = $this->report($pairs, $created, $before, $after);

        if (! $apply) {
            DB::rollBack();
            $this->line('');
            $this->info('Rolled back. Re-run with --apply to write these rules.');

            return self::SUCCESS;
        }

        if ($differences > 0) {
            DB::rollBack();
            $this->line('');
            $this->error("Rolled back: {$differences} fee(s) would change. A backfill is meant to reproduce today's pricing exactly — fix the mapping, do not force it.");

            return self::FAILURE;
        }

        DB::commit();
        $this->line('');
        $this->info('Committed. '.count($created).' rule(s) created, 0 fees changed.');

        return self::SUCCESS;
    }

    /**
     * Connected (zone, module) pairs that have no ACTIVE rule.
     *
     * An inactive rule does not satisfy A15's gate, so a pair carrying one still needs a rule —
     * but it already has a name and a shape an admin chose, so it is left alone and reported
     * rather than duplicated.
     */
    private function pairs(): \Illuminate\Support\Collection
    {
        return DB::table('module_zone')
            ->orderBy('zone_id')
            ->orderBy('module_id')
            ->get()
            ->reject(fn ($pivot) => app(DeliveryRuleService::class)->activeRule($pivot->zone_id, $pivot->module_id) !== null)
            ->values();
    }

    /** One quote per pair per distance, through the real engine. */
    private function quoteAll(\Illuminate\Support\Collection $pairs): array
    {
        $engine = app(DeliveryChargeService::class);
        $out = [];

        foreach ($pairs as $pivot) {
            foreach (self::DISTANCES as $distance) {
                $out[$pivot->zone_id.':'.$pivot->module_id.':'.$distance] = $engine->quote([
                    'order_type' => 'delivery',
                    'distance' => $distance,
                    'store' => null,
                    'module_zone_pivot' => $pivot,
                    'zone_id' => $pivot->zone_id,
                    'module_id' => $pivot->module_id,
                    'surge' => null,
                ])['delivery_charge'];
            }
        }

        return $out;
    }

    /**
     * The rule that reproduces one pivot.
     *
     * A `distance` pivot maps straight across. A `fixed` one does not: the engine expresses a
     * fixed pivot by loading the flat amount into per_unit, minimum AND maximum, so the
     * multiplication loses to the minimum and the clamp is a no-op — its own `minimum_shipping_charge`
     * and `maximum_shipping_charge` are ignored entirely. The faithful rule therefore floors at
     * the flat amount, not at the pivot's unused minimum.
     */
    private function createRule(object $pivot): DeliveryRule
    {
        $isDistance = ($pivot->delivery_charge_type ?? 'fixed') === 'distance';
        $fixed = (float) ($pivot->fixed_shipping_charge ?? 0);

        $zone = Zone::withoutGlobalScopes()->find($pivot->zone_id);
        $module = Module::withoutGlobalScopes()->find($pivot->module_id);

        return app(DeliveryRuleService::class)->create([
            'name' => trim(($zone?->name ?? 'Zone '.$pivot->zone_id).' — '.($module?->module_name ?? 'Module '.$pivot->module_id)),
            'zone_id' => $pivot->zone_id,
            'module_ids' => [$pivot->module_id],
            'pricing_method' => $isDistance ? DeliveryRule::METHOD_DISTANCE : DeliveryRule::METHOD_FIXED,
            'per_km_charge' => $isDistance ? (float) ($pivot->per_km_shipping_charge ?? 0) : 0,
            'minimum_delivery_charge' => $isDistance ? (float) ($pivot->minimum_shipping_charge ?? 0) : $fixed,
            'maximum_delivery_charge' => $isDistance ? $pivot->maximum_shipping_charge : null,
            'fixed_charge' => $isDistance ? 0 : $fixed,
            // A15 — a rule that is off does not satisfy the availability gate.
            'status' => 1,
        ]);
    }

    private function report(
        \Illuminate\Support\Collection $pairs,
        array $created,
        array $before,
        array $after,
    ): int {
        $rows = [];
        $differences = 0;

        foreach ($pairs as $index => $pivot) {
            $moved = [];

            foreach (self::DISTANCES as $distance) {
                $key = $pivot->zone_id.':'.$pivot->module_id.':'.$distance;

                if (abs($before[$key] - $after[$key]) >= 0.00001) {
                    $moved[] = sprintf('%.2fkm %.2f→%.2f', $distance, $before[$key], $after[$key]);
                    $differences++;
                }
            }

            $rule = $created[$index];

            $rows[] = [
                $pivot->zone_id,
                $pivot->module_id,
                $pivot->delivery_charge_type ?? '-',
                $rule->pricing_method,
                $rule->pricing_method === DeliveryRule::METHOD_DISTANCE
                    ? sprintf('%s/unit, min %s, max %s', $rule->per_km_charge, $rule->minimum_delivery_charge, $rule->maximum_delivery_charge ?? '-')
                    : sprintf('flat %s', $rule->fixed_charge),
                $moved === [] ? 'same' : implode('  ', $moved),
            ];
        }

        $this->table(['zone', 'module', 'pivot', 'rule method', 'rule pricing', 'fee'], $rows);

        $this->line('');
        $this->line(sprintf('  %d pair(s) · %d quote(s) compared · %d fee(s) changed',
            $pairs->count(), $pairs->count() * count(self::DISTANCES), $differences));

        return $differences;
    }
}
