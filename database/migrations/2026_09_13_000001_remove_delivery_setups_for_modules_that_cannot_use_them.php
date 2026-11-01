<?php

use App\Services\System\ModuleService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clears the Delivery Management setups configured for modules that can never use them.
 *
 * Rental, ride-share and service price their own trips and bookings and never reach the core
 * order pipeline, so a delivery rule, ETA configuration, surge price, free delivery or additional
 * charge attached to one is stored and never applied. The pickers no longer offer them and the
 * requests refuse them; this removes what was configured before that was true.
 *
 * Most of these rows exist because the readiness backfill created them: Z3 used to ask EVERY
 * connected module for a delivery rule and an ETA, including modules that could not have either,
 * so a rule was written for each just to let the zone switch on. Z3 asks only capable modules
 * now, which is what makes these safe to drop.
 *
 * CAPABILITY-DRIVEN, not a hard-coded id list — it reads `config('module.<type>.*)` at run time,
 * so it removes exactly what the panel would now refuse, on whatever data it is run against.
 *
 * TWO SHAPES, and the difference matters:
 *   - a parent covering ONLY incapable modules is deleted outright;
 *   - a parent that also covers capable ones keeps its row and loses just the pivot entries,
 *     because deleting it would take a working setup down with it.
 *
 * Reversible: every row removed is recorded verbatim first, and `down()` puts them back.
 */
return new class extends Migration
{
    private const RECORD_KEY = 'delivery_setups_removed_for_incapable_modules';

    /** @var array<string, array{parent:string, pivot:string, fk:string, capability:string}> */
    private const SETUPS = [
        'delivery_rule' => [
            'parent' => 'delivery_rules',
            'pivot' => 'delivery_rule_module',
            'fk' => 'delivery_rule_id',
            'capability' => 'deliveryRuleModuleIds',
        ],
        'eta_configuration' => [
            'parent' => 'eta_configurations',
            'pivot' => 'eta_configuration_module',
            'fk' => 'eta_configuration_id',
            'capability' => 'etaCapableModuleIds',
        ],
        'free_delivery' => [
            'parent' => 'free_deliveries',
            'pivot' => 'free_delivery_module',
            'fk' => 'free_delivery_id',
            'capability' => 'deliveryChargeSetupModuleIds',
        ],
        'additional_delivery_charge' => [
            'parent' => 'additional_delivery_charges',
            'pivot' => 'additional_delivery_charge_module',
            'fk' => 'additional_delivery_charge_id',
            'capability' => 'deliveryChargeSetupModuleIds',
        ],
    ];

    /** Child rows that go with a deleted delivery rule. */
    private const RULE_CHILDREN = [
        'delivery_rule_charges',
        'delivery_rule_weight_charges',
        'delivery_rule_dimension_charges',
    ];

    public function up(): void
    {
        $modules = app(ModuleService::class);
        $removed = [];

        foreach (self::SETUPS as $name => $setup) {
            if (! $this->tablesExist($setup)) {
                continue;
            }

            $capable = $modules->{$setup['capability']}() ?: [0];

            $strayPivots = DB::table($setup['pivot'])
                ->whereNotIn('module_id', $capable)
                ->get();

            if ($strayPivots->isEmpty()) {
                continue;
            }

            // A parent with no capable module left has nothing to price or time for anyone.
            $doomed = $strayPivots
                ->pluck($setup['fk'])
                ->unique()
                ->reject(fn ($id) => DB::table($setup['pivot'])
                    ->where($setup['fk'], $id)
                    ->whereIn('module_id', $capable)
                    ->exists())
                ->values();

            $removed[$name] = [
                'pivots' => $strayPivots->map(fn ($row) => (array) $row)->all(),
                'parents' => DB::table($setup['parent'])->whereIn('id', $doomed)
                    ->get()->map(fn ($row) => (array) $row)->all(),
                'children' => $name === 'delivery_rule' ? $this->ruleChildren($doomed) : [],
            ];

            DB::table($setup['pivot'])->whereNotIn('module_id', $capable)->delete();

            if ($doomed->isNotEmpty()) {
                if ($name === 'delivery_rule') {
                    foreach (self::RULE_CHILDREN as $child) {
                        if (Schema::hasTable($child)) {
                            DB::table($child)->whereIn('delivery_rule_id', $doomed)->delete();
                        }
                    }
                }

                DB::table($setup['parent'])->whereIn('id', $doomed)->delete();
            }
        }

        // Surge stores its modules as JSON on the row rather than in a pivot, so it is rewritten
        // rather than detached. A surge left naming nothing would apply to nothing, so it goes.
        $removed['surge_price'] = $this->pruneSurgePrices($modules->surgeCapableModuleIds() ?: [0]);

        if (array_filter($removed, fn ($entry) => $entry !== [] && $entry !== null)) {
            DB::table('business_settings')->updateOrInsert(
                ['key' => self::RECORD_KEY],
                ['value' => json_encode($removed)],
            );
        }
    }

    /**
     * Puts back exactly what up() took, from the record it wrote.
     *
     * Parents first, then their children and pivots, so nothing is reinserted against a row that
     * does not exist yet.
     */
    public function down(): void
    {
        $recorded = DB::table('business_settings')->where('key', self::RECORD_KEY)->value('value');

        if (! $recorded) {
            return;
        }

        $removed = json_decode($recorded, true) ?: [];

        foreach (self::SETUPS as $name => $setup) {
            $entry = $removed[$name] ?? null;

            if (! $entry || ! $this->tablesExist($setup)) {
                continue;
            }

            $this->restore($setup['parent'], $entry['parents'] ?? []);

            foreach ($entry['children'] ?? [] as $table => $rows) {
                $this->restore($table, $rows);
            }

            $this->restore($setup['pivot'], $entry['pivots'] ?? []);
        }

        foreach ($removed['surge_price']['rows'] ?? [] as $row) {
            DB::table('surge_prices')->where('id', $row['id'])->update(['module_ids' => $row['module_ids']]);
        }

        $this->restore('surge_prices', $removed['surge_price']['deleted'] ?? []);

        DB::table('business_settings')->where('key', self::RECORD_KEY)->delete();
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function ruleChildren(\Illuminate\Support\Collection $ruleIds): array
    {
        $out = [];

        foreach (self::RULE_CHILDREN as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)->whereIn('delivery_rule_id', $ruleIds)->get();

            if ($rows->isNotEmpty()) {
                $out[$table] = $rows->map(fn ($row) => (array) $row)->all();
            }
        }

        return $out;
    }

    /**
     * Surge prices, with incapable modules struck out of their JSON list.
     *
     * @return array{rows:array<int, array<string, mixed>>, deleted:array<int, array<string, mixed>>}
     */
    private function pruneSurgePrices(array $capable): array
    {
        if (! Schema::hasTable('surge_prices')) {
            return ['rows' => [], 'deleted' => []];
        }

        $rewritten = [];
        $deleted = [];

        foreach (DB::table('surge_prices')->get() as $surge) {
            $ids = array_map('intval', (array) json_decode($surge->module_ids ?: '[]', true));
            $kept = array_values(array_intersect($ids, $capable));

            if ($kept === $ids) {
                continue;
            }

            if ($kept === []) {
                $deleted[] = (array) $surge;
                DB::table('surge_prices')->where('id', $surge->id)->delete();

                continue;
            }

            // The original JSON is recorded so down() can put the struck modules back.
            $rewritten[] = ['id' => $surge->id, 'module_ids' => $surge->module_ids];
            DB::table('surge_prices')->where('id', $surge->id)->update(['module_ids' => json_encode($kept)]);
        }

        return ['rows' => $rewritten, 'deleted' => $deleted];
    }

    private function tablesExist(array $setup): bool
    {
        return Schema::hasTable($setup['parent']) && Schema::hasTable($setup['pivot']);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function restore(string $table, array $rows): void
    {
        foreach ($rows as $row) {
            if (! isset($row['id']) || ! DB::table($table)->where('id', $row['id'])->exists()) {
                DB::table($table)->insert($row);
            }
        }
    }
};
