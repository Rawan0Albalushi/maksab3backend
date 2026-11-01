<?php

namespace App\Services\Zone;

use App\Models\DeliveryRule;
use App\Services\BaseService;
use App\Traits\Zone\ResolvesSoloModuleCoverTrait;
use App\Services\System\DistanceService;
use App\Services\System\ModuleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Owns App\Models\DeliveryRule.
 *
 * Every method takes `(zoneId, moduleId)`. None takes `zoneId` alone — a missing module id in a
 * resolver call is the mart-specific version of mistake M1 (§16.3), because a zone serves several
 * modules and they price differently.
 *
 * NOT YET WIRED INTO PRICING. baseCharge() below is complete and tested, but the fee engine does
 * not call it until S5. Until then the module_zone pivot still decides every fee, and this class
 * only backs the admin screens. That split is deliberate: the schema and the arithmetic land in
 * one section, the behaviour change in the next, each with its own gate.
 */
class DeliveryRuleService extends BaseService
{
    use ResolvesSoloModuleCoverTrait;

    /**
     * The modules this rule is the only active delivery-charge cover for in its zone.
     *
     * The edit form reads it to know which of the picker's modules would go DARK if the admin
     * dropped them and saved — after S19 a module with no delivery rule is not merely unpriced,
     * it is unavailable in the zone.
     *
     * @return array<int, string> module id => module name
     */
    public function soloModules(DeliveryRule $rule): array
    {
        return $this->soloModuleCover($rule->loadMissing('modules'), DeliveryRule::class);
    }

    public function create(array $data): DeliveryRule
    {
        return DB::transaction(function () use ($data) {
            $moduleIds = $this->moduleIds($data);

            // The FIRST rule a (zone, module) has ever had is created active.
            //
            // Rules used to be created switched off across the board, on the reasoning that one
            // priced orders "before its charges were checked". The add form posts area_charges,
            // zip_charges, weight_charges and dimension_charges in the SAME request, and create()
            // syncs them inside this transaction, so a rule is complete the moment it commits and
            // that risk is gone. What remained was the cost: the pair stayed unable to price
            // anything until somebody noticed a second switch, and the form offers no status field
            // for anyone to have chosen that.
            //
            // Only the first. Once a pair has a rule, a new one is a REPLACEMENT, and handing over
            // is updateStatus()'s job — creating it active would silently switch the live one off.
            $status = $data['status'] ?? $this->pairsWithoutAnyRule($data['zone_id'] ?? null, $moduleIds) !== [];

            $rule = DeliveryRule::create($this->attributes($data) + ['status' => $status]);
            $rule->modules()->sync($moduleIds);

            app(DeliveryRuleChargeService::class)->syncForRule($rule, $data['charges'] ?? []);
            $this->syncParcelTiers($rule, $data);

            return $rule->load(['charges', 'modules', 'weightCharges', 'dimensionCharges']);
        });
    }

    public function update(mixed $id, array $data): ?DeliveryRule
    {
        return DB::transaction(function () use ($id, $data) {
            $rule = DeliveryRule::find($id);

            if (! $rule) {
                return null;
            }

            $rule->update($this->attributes($data));
            $rule->modules()->sync($this->moduleIds($data));

            app(DeliveryRuleChargeService::class)->syncForRule($rule, $data['charges'] ?? []);
            $this->syncParcelTiers($rule, $data);

            return $rule->load(['charges', 'modules', 'weightCharges', 'dimensionCharges']);
        });
    }

    public function delete(mixed $id): bool
    {
        return DB::transaction(function () use ($id) {
            $rule = DeliveryRule::find($id);

            if (! $rule) {
                return false;
            }

            // D2: the default zone must always keep one rule.
            if ($this->isLastRuleOfDefaultZone($id)) {
                return false;
            }

            $rule->charges()->delete();
            $rule->modules()->detach();

            return (bool) $rule->delete();
        });
    }

    /**
     * Switching a rule on switches its siblings off — enforced by the model's saved hook, so it
     * holds here and on every other write path (§5.2).
     */
    public function updateStatus(mixed $id, mixed $status): bool
    {
        $rule = DeliveryRule::find($id);

        if (! $rule) {
            return false;
        }

        // D2: switching the default zone's last rule off would leave it unable to price.
        if (! (bool) $status && $this->isLastRuleOfDefaultZone($id)) {
            return false;
        }

        return $rule->update(['status' => (bool) $status]);
    }

    public function find(mixed $id, array $with = ['zone', 'modules', 'charges']): ?DeliveryRule
    {
        return DeliveryRule::with($with)->find($id);
    }

    public function getList(
        array $filters = [],
        array $with = ['zone', 'modules'],
        array $withCount = ['charges'],
        array $paginate = ['per_page' => 25, 'page' => 1]
    ): LengthAwarePaginator {
        return $this->buildQuery($filters, $with, $withCount)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function statusStatistics(array $filters = []): array
    {
        $base = fn () => $this->buildQuery($filters, [], []);

        return [
            'total' => $base()->count(),
            'active' => $base()->where('status', 1)->count(),
            'inactive' => $base()->where('status', 0)->count(),
        ];
    }

    /** The rule that prices this (zone, module) right now, or null if nothing is set up. */
    public function activeRule(mixed $zoneId, mixed $moduleId, array $with = []): ?DeliveryRule
    {
        if (empty($zoneId) || empty($moduleId)) {
            return null;
        }

        return DeliveryRule::with($with)->active()->forZoneModule($zoneId, $moduleId)->first();
    }

    /**
     * The base delivery charge for one order, before vehicle extras and surge (§5.3).
     *
     * Returns NULL when no active rule exists, and null means "I have no opinion" — the caller
     * falls back to module_zone pivot pricing (§4.3). It does not mean zero. Returning 0.0 here
     * would silently make every unconfigured (zone, module) free.
     *
     * When a rule IS active it decides the charge outright; the pivot's per_km_shipping_charge is
     * not consulted at all.
     *
     * `$distanceKm` is measured and therefore kilometres (decision D2). It is converted into the
     * unit `per_km_charge` is quoted in exactly once, here, via DistanceService::chargeable() —
     * because per_km_charge is a SETUP value, stored as typed and read in whatever unit the
     * setting names (D1).
     *
     * Every branch ends at the rule's minimum. That floor is what post-engine discounts must
     * respect, and reading it from the wrong branch is how M7 happened.
     */
    public function baseCharge(
        mixed $zoneId,
        mixed $moduleId,
        float $distanceKm = 0.0,
        mixed $areaId = null,
        mixed $zipCodeId = null
    ): ?float {
        $rule = $this->activeRule($zoneId, $moduleId);

        return $rule ? $this->baseChargeForRule($rule, $distanceKm, $areaId, $zipCodeId) : null;
    }

    /**
     * The same calculation for a rule the caller already has.
     *
     * The fee engine resolves the active rule itself — it needs the rule's pricing method to
     * decide whether the charge scales with distance — and would otherwise call activeRule()
     * twice for one quote. Duplicate queries are defects (architecture rule 11), so the lookup
     * and the calculation are separable.
     */
    public function baseChargeForRule(
        DeliveryRule $rule,
        float $distanceKm = 0.0,
        mixed $areaId = null,
        mixed $zipCodeId = null
    ): float {
        $charge = match ($rule->pricing_method) {
            DeliveryRule::METHOD_DISTANCE => $this->distanceCharge($rule, $distanceKm),
            DeliveryRule::METHOD_AREA => app(DeliveryRuleChargeService::class)->chargeForArea($rule->id, $areaId),
            DeliveryRule::METHOD_ZIP => app(DeliveryRuleChargeService::class)->chargeForZipCode($rule->id, $zipCodeId),
            default => (float) ($rule->fixed_charge ?? 0),
        };

        return max($charge, (float) $rule->minimum_delivery_charge);
    }

    /**
     * The coverage a client must choose from, for the lookup endpoint and the storefront picker
     * (§14.3).
     *
     * Deliberately EMPTY for distance_wise and fixed_amount: those price the whole zone and there
     * is nothing to pick. An empty list is the answer, not a missing one.
     */
    public function coverageForZone(mixed $zoneId, mixed $moduleId): array
    {
        $rule = $this->activeRule($zoneId, $moduleId);

        if (! $rule) {
            return ['type' => null, 'coverage' => []];
        }

        $coverage = match ($rule->pricing_method) {
            DeliveryRule::METHOD_AREA => app(AreaService::class)->activeForZone($zoneId)
                ->map(fn ($area) => ['id' => $area->id, 'name' => $area->name])->values()->all(),
            DeliveryRule::METHOD_ZIP => app(ZipCodeService::class)->activeForZone($zoneId)
                ->map(fn ($zip) => ['id' => $zip->id, 'name' => $zip->zip_code])->values()->all(),
            default => [],
        };

        return ['type' => $rule->pricing_method, 'coverage' => $coverage];
    }

    /**
     * Security (§5.4). A customer can post any area_id or zip_code_id; without this they post the
     * id of a cheaper one from another zone and are charged its rate.
     *
     * Delegates to the service that owns each model rather than querying them here — Area and
     * ZipCode are not this class's to touch.
     */
    public function coverageBelongsToZone(mixed $zoneId, mixed $areaId = null, mixed $zipCodeId = null): bool
    {
        if (! empty($areaId)) {
            return app(AreaService::class)->belongsToZone($zoneId, $areaId);
        }

        if (! empty($zipCodeId)) {
            return app(ZipCodeService::class)->belongsToZone($zoneId, $zipCodeId);
        }

        return false;
    }

    /**
     * §5.4 — the explicit refusal, as opposed to the engine's silent second line.
     *
     * `coverageBelongsToZone()` above stops a forged pick being PRICED; this stops it being
     * ACCEPTED, which is what §5.4 actually specifies. The two failure modes are different:
     *
     *  - a pick that belongs to another zone is a real validation error and is refused on every
     *    path, quote included;
     *  - a MISSING pick is refused only where one is mandatory — placement — because a quote
     *    before the customer has chosen is legitimate.
     *
     * Self-delivery stores bypass the zone rule entirely, so requiring a pick from them would
     * block an order over a field that cannot affect what is charged. A rule that prices the
     * whole zone (distance_wise, fixed_amount) has no table to look a pick up in, so there is
     * nothing to ask for either.
     *
     * @return array{status_code:int,code:string,message:string}|null
     */
    public function coverageSelectionError(
        mixed $zoneId,
        mixed $moduleId,
        bool $isSelfDelivery = false,
        string $orderType = 'delivery',
        mixed $areaId = null,
        mixed $zipCodeId = null,
        bool $requirePick = false,
    ): ?array {
        if ($orderType === 'take_away' || $isSelfDelivery) {
            return null;
        }

        $rule = $this->activeRule($zoneId, $moduleId);

        if (! $rule || ! $rule->usesChargeTable()) {
            return null;
        }

        $isArea = $rule->pricing_method === DeliveryRule::METHOD_AREA;
        $picked = $isArea ? $areaId : $zipCodeId;
        $code = $isArea ? 'area_id' : 'zip_code_id';

        if (! $picked) {
            if (! $requirePick) {
                return null;
            }

            return [
                'status_code' => 403,
                'code' => $code,
                'message' => $isArea
                    ? translate('messages.Please_select_your_area_to_calculate_the_delivery_charge.')
                    : translate('messages.Please_select_your_ZIP_code_to_calculate_the_delivery_charge.'),
            ];
        }

        if ($this->coverageBelongsToZone($zoneId, $isArea ? $picked : null, $isArea ? null : $picked)) {
            return null;
        }

        // The pick is not this zone's -- but that is not always the customer's mistake. A zone can
        // still carry a delivery rule for a module it no longer serves (disconnecting a module in
        // Zone Setup leaves its rule behind), so its coverage keeps being offered to a customer
        // standing there; they choose the only area they are shown, and it is then priced against
        // the zone the ORDER resolved to, which is a different one. Telling them the option is
        // unavailable "in this zone" names a zone they never chose and cannot act on. When the
        // place they picked is somewhere this module does not operate at all, say that instead.
        if (! $this->coverageZoneServesModule($moduleId, $isArea, $picked)) {
            return [
                'status_code' => 403,
                'code' => $code,
                'message' => translate('messages.This_service_is_not_available_in_the_desired_location.'),
            ];
        }

        return [
            'status_code' => 403,
            'code' => $code,
            'message' => translate('messages.The_selected_option_is_not_available_in_this_zone.'),
        ];
    }

    /**
     * Whether the zone the customer's pick belongs to actually serves this module.
     *
     * True when the pick names no zone at all -- a bogus or deleted id is a bad selection, not a
     * location the platform declines to serve, and must keep the selection message.
     */
    private function coverageZoneServesModule(mixed $moduleId, bool $isArea, mixed $picked): bool
    {
        $pickedZoneId = $isArea
            ? app(AreaService::class)->zoneIdFor($picked)
            : app(ZipCodeService::class)->zoneIdFor($picked);

        if (! $pickedZoneId) {
            return true;
        }

        return in_array(
            (int) $moduleId,
            app(ModuleZoneService::class)->connectedModuleIds($pickedZoneId),
            true
        );
    }

    /** Zones that have an active rule for this module — for the readiness badge and lookup (§4.1). */
    public function pricedZoneIds(mixed $moduleId): Collection
    {
        return DeliveryRule::active()
            ->whereHas('modules', fn ($m) => $m->where('modules.id', $moduleId))
            ->pluck('zone_id');
    }

    /**
     * DESIGN RULE D1 — "only ONE Delivery Rule per Zone & Module combination".
     *
     * Stronger than the one-ACTIVE-rule invariant the model enforces: a second rule for a
     * combination cannot be created at all, active or not. Because a rule now claims a SET of
     * modules, the question is overlap — does any existing rule in this zone already claim any
     * of the modules being selected?
     *
     * Returns the module names that clash, so the form can say which ones rather than just "no".
     */
    public function conflictingModuleNames(mixed $zoneId, array $moduleIds, mixed $exceptRuleId = null): array
    {
        if (empty($zoneId) || empty($moduleIds)) {
            return [];
        }

        return DeliveryRule::query()
            ->where('zone_id', $zoneId)
            ->when($exceptRuleId, fn ($q) => $q->whereKeyNot($exceptRuleId))
            ->with(['modules' => fn ($m) => $m->whereIn('modules.id', $moduleIds)])
            ->whereHas('modules', fn ($m) => $m->whereIn('modules.id', $moduleIds))
            ->get()
            ->flatMap(fn ($rule) => $rule->modules->pluck('module_name'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * DESIGN RULE — module selection.
     *
     * "The dropdown displays only modules that do not already have a Delivery Rule for the
     * selected Zone. Modules with an existing Delivery Rule are hidden."
     *
     * Two filters, in order: the modules CONNECTED to the zone in Zone Setup → Connect Module,
     * then of those the ones not already carrying a rule.
     *
     * The UX complement to D1: rather than letting the admin pick a module that is already
     * taken and then refusing the save, the taken ones never appear. D1 still stands as the
     * server-side guard — the dropdown is a convenience, not the enforcement, and a crafted
     * POST must still be refused.
     *
     * `$exceptRuleId` keeps a rule's OWN modules selectable while editing it; without it the
     * edit form would drop every module the rule already holds.
     */
    public function availableModulesForZone(mixed $zoneId, mixed $exceptRuleId = null): Collection
    {
        return $this->modulePickerForZone($zoneId, $exceptRuleId)['modules'];
    }

    /**
     * The module picker in one pass: the modules to offer, and why the list is empty when it is.
     *
     * These were two public methods, and every screen that renders the picker called both — so
     * the whole filter (all modules, the zone's connections, the zone's rules, the rule/module
     * pivot) ran twice per render. They share one query set now. The state still comes back as a
     * key, not a sentence: translating belongs to the layer that renders.
     *
     * @return array{modules: Collection, state: 'available'|'all_taken'|'none_connected'}
     */
    public function modulePickerForZone(mixed $zoneId, mixed $exceptRuleId = null): array
    {
        $all = app(ModuleService::class)->getSelectOptions();

        if (empty($zoneId)) {
            return ['modules' => $all, 'state' => 'available'];
        }

        // ZEROTH filter: only modules a delivery rule can price at all. Rental, ride-share and
        // service price their own trips and bookings and never reach DeliveryChargeService.
        //
        // A module THIS rule already holds is kept in the list even when it is no longer eligible.
        // Eight rules already cover rental and service — the backfill created them to satisfy the
        // readiness rule — and dropping them from the picker would make editing such a rule
        // silently unassign its module on save. They stay visible and removable by hand.
        $capable = app(ModuleService::class)->deliveryRuleModuleIds();
        $retained = $exceptRuleId
            ? (DeliveryRule::with('modules:id')->find($exceptRuleId)?->modules->pluck('id')->map('intval')->all() ?? [])
            : [];

        $all = $all->filter(fn ($module) => in_array((int) $module->id, $capable, true)
            || in_array((int) $module->id, $retained, true));

        // FIRST filter: only modules the zone actually serves. A zone is connected to modules in
        // Zone Setup → Connect Module, and pricing one it does not serve would configure a
        // combination that can never take an order.
        $connected = app(ModuleZoneService::class)->connectedModuleIds($zoneId);

        // SECOND filter: of those, the ones not already carrying a rule.
        $taken = DeliveryRule::query()
            ->where('zone_id', $zoneId)
            ->when($exceptRuleId, fn ($q) => $q->whereKeyNot($exceptRuleId))
            ->with('modules:id')
            ->get()
            ->flatMap(fn ($rule) => $rule->modules->pluck('id'))
            ->unique()
            ->all();

        $available = $all
            ->filter(fn ($module) => in_array($module->id, $connected, true))
            ->reject(fn ($module) => in_array($module->id, $taken, true))
            ->values();

        return [
            'modules' => $available,
            'state' => match (true) {
                $available->isNotEmpty() => 'available',
                $connected === [] => 'none_connected',
                default => 'all_taken',
            },
        ];
    }

    /**
     * DESIGN RULE D7, server side — the picked modules the zone is NOT connected to, by name.
     *
     * The dropdown already hides these, so reaching this means a crafted POST, or a form left open
     * while someone disconnected a module in Zone Setup. A rule for a module the zone does not
     * serve could never fire, so it is refused rather than stored. Named rather than counted, so
     * the admin can see which to deselect instead of guessing.
     */
    public function unconnectedModuleNames(mixed $zoneId, array $moduleIds): array
    {
        if (empty($zoneId) || $moduleIds === []) {
            return [];
        }

        $connected = app(ModuleZoneService::class)->connectedModuleIds($zoneId);

        return app(ModuleService::class)->getSelectOptions()
            ->whereIn('id', $moduleIds)
            ->reject(fn ($module) => in_array($module->id, $connected, true))
            ->pluck('module_name')
            ->all();
    }

    /**
     * Why the module dropdown is empty, when it is.
     *
     * Two different causes need two different messages: a zone with nothing connected sends the
     * admin to Zone Setup, while a zone whose modules are all spoken for is the design's
     * "all combinations already set up". Telling an operator everything is configured when in
     * fact nothing is connected sends them looking in the wrong screen.
     */
    public function moduleAvailabilityState(mixed $zoneId, mixed $exceptRuleId = null): string
    {
        return $this->modulePickerForZone($zoneId, $exceptRuleId)['state'];
    }

    /**
     * The other rules in a zone that could take over from this one.
     *
     * The turn-off dialog makes the admin nominate a replacement, so it needs the zone's other
     * rules. They come from the server rather than the markup because the list is paginated and
     * the detail page holds only one rule — neither carries the full set.
     */
    public function selectableForZone(mixed $zoneId, mixed $exceptRuleId = null): Collection
    {
        if (empty($zoneId)) {
            return collect();
        }

        $outgoing = $exceptRuleId ? DeliveryRule::with('modules:id')->find($exceptRuleId) : null;
        $needed = $outgoing ? $outgoing->modules->pluck('id')->all() : [];

        $candidates = DeliveryRule::query()
            ->where('zone_id', $zoneId)
            ->when($exceptRuleId, fn ($q) => $q->whereKeyNot($exceptRuleId))
            ->with('modules:id')
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($needed === []) {
            return $candidates;
        }

        // Only rules that already cover EVERY module the outgoing rule covers. The controller
        // refuses anything else through modulesLeftUncovered(), so listing them offered the admin
        // seven choices that were all guaranteed to fail. An empty picker is the honest answer:
        // under D1 there is usually no second rule for the pair, and the dialog says so.
        return $candidates->filter(
            fn (DeliveryRule $rule) => array_diff($needed, $rule->modules->pluck('id')->all()) === [],
        )->values();
    }

    /**
     * Switch a rule off by switching another one on, in one transaction.
     *
     * A zone is never left unpriced: the replacement is activated first, and the `saved` hook's
     * one-active-rule invariant then switches the outgoing rule off for its own (zone, module).
     * It is switched off explicitly too, because a replacement covering a DIFFERENT module would
     * not trip that invariant and the zone would briefly carry two active rules.
     */
    public function deactivateWithReplacement(mixed $ruleId, mixed $replacementId): bool
    {
        return DB::transaction(function () use ($ruleId, $replacementId) {
            $rule = DeliveryRule::find($ruleId);
            $replacement = DeliveryRule::find($replacementId);

            // A rule cannot replace itself: activating then deactivating the same row leaves the
            // zone with nothing active, which is the one outcome this whole dialog exists to
            // prevent. The picker already excludes it; this is the server saying so too.
            // Same ZONE is not enough: a rule claims a set of MODULES, and a replacement that
            // covers different ones hands nothing over. Nominating the zone's Pharmacy rule to
            // replace its Grocery rule used to be accepted, which left Grocery in that zone with
            // no active rule at all while the screen reported success.
            if (! $rule || ! $replacement || $replacement->is($rule) || $replacement->zone_id !== $rule->zone_id) {
                return false;
            }

            if ($this->modulesLeftUncovered($rule, $replacement) !== []) {
                return false;
            }

            $replacement->update(['status' => true]);
            $rule->refresh()->update(['status' => false]);

            return true;
        });
    }

    /**
     * The modules this rule covers that the nominated replacement does not.
     *
     * Empty means the hand-over is complete and the rule may be switched off. Anything in it names
     * a module that would be left with no active rule in this zone, which is the outcome the
     * status dialog exists to prevent.
     *
     * @return array<int, string> module names, for the message the dialog shows
     */
    public function modulesLeftUncovered(DeliveryRule $rule, ?DeliveryRule $replacement): array
    {
        $covered = $replacement ? $replacement->modules->pluck('id')->all() : [];

        return $rule->modules
            ->reject(fn ($module) => in_array($module->id, $covered, true))
            // A module another ACTIVE rule already covers is not left uncovered -- the pair keeps
            // a rule whatever this one does, so the hand-over does not have to carry it.
            ->reject(fn ($module) => DeliveryRule::query()
                ->where('zone_id', $rule->zone_id)
                ->where('status', 1)
                ->whereKeyNot($rule->getKey())
                ->whereHas('modules', fn ($q) => $q->where('modules.id', $module->id))
                ->exists())
            ->pluck('module_name')
            ->values()
            ->all();
    }

    /**
     * The same answer as modulesLeftUncovered(rule, null) for a whole page, keyed by rule id.
     *
     * Asked per row on a page of twenty-five, modulesLeftUncovered() would be dozens of queries
     * (rule 11). This reads every active rule for the zones on the page once and decides in PHP,
     * matching EtaConfigurationService::lockedModulesFor() — the two toggles answer the same
     * question the same way.
     *
     * @param  iterable<DeliveryRule>  $rules  rows with `modules` already loaded
     * @return array<int, array<int, string>> rule id => module names it alone covers
     */
    public function lockedModulesFor(iterable $rules): array
    {
        $rules = $this->rowsOf($rules);

        if ($rules->isEmpty()) {
            return [];
        }

        // zone id => [module id => count of ACTIVE rules covering it]
        $activeCover = [];

        foreach (DeliveryRule::query()
            ->whereIn('zone_id', $rules->pluck('zone_id')->unique()->filter()->all())
            ->where('status', 1)
            ->with('modules:id')
            ->get() as $active) {
            foreach ($active->modules as $module) {
                $activeCover[$active->zone_id][$module->id] = ($activeCover[$active->zone_id][$module->id] ?? 0) + 1;
            }
        }

        return $rules->mapWithKeys(fn (DeliveryRule $rule) => [
            $rule->id => $rule->status
                ? $rule->modules
                    ->filter(fn ($module) => ($activeCover[$rule->zone_id][$module->id] ?? 0) <= 1)
                    ->pluck('module_name')
                    ->values()
                    ->all()
                : [],
        ])->all();
    }

    /**
     * The picked modules a delivery rule can never price, by name.
     *
     * `$ruleId` is the rule being edited: modules it already holds are allowed through, so saving
     * an older rule that still covers rental or service is not refused outright. New selections
     * are limited to the capable list.
     *
     * @return array<int, string>
     */
    public function incapableModuleNames(array $moduleIds, mixed $ruleId = null): array
    {
        if ($moduleIds === []) {
            return [];
        }

        $modules = app(ModuleService::class);
        $allowed = $modules->deliveryRuleModuleIds();

        if ($ruleId) {
            $allowed = array_merge($allowed, DeliveryRule::with('modules:id')->find($ruleId)
                ?->modules->pluck('id')->map('intval')->all() ?? []);
        }

        $picked = array_map('intval', $moduleIds);

        return $modules->getSelectOptions()
            ->filter(fn ($module) => in_array((int) $module->id, $picked, true))
            ->reject(fn ($module) => in_array((int) $module->id, $allowed, true))
            ->pluck('module_name')
            ->values()
            ->all();
    }

    /**
     * Of these modules, the ones this zone has no delivery rule for at all.
     *
     * Asked on create to decide whether the new rule is its pair's first. Any rule counts, active
     * or not: a pair that already has an inactive rule has been set up once and its status is the
     * admin's business, not something a second save should overrule.
     *
     * @return array<int, int> module ids
     */
    public function pairsWithoutAnyRule(mixed $zoneId, array $moduleIds): array
    {
        if (! $zoneId || $moduleIds === []) {
            return [];
        }

        $covered = DeliveryRule::query()
            ->where('zone_id', $zoneId)
            ->with('modules:id')
            ->get()
            ->flatMap(fn ($rule) => $rule->modules->pluck('id'))
            ->unique()
            ->all();

        return array_values(array_diff($moduleIds, $covered));
    }

    /**
     * DESIGN RULE D2 — the default zone must always keep one delivery rule.
     *
     * "When attempting to delete or disable the LAST delivery rule assigned to the Default Zone,
     * the system prevents the action." Without a rule the default zone cannot price anything, and
     * the default zone is what customers land in before choosing a location.
     */
    public function isLastRuleOfDefaultZone(mixed $ruleId): bool
    {
        $rule = DeliveryRule::find($ruleId);

        if (! $rule) {
            return false;
        }

        $isDefaultZone = app(ZoneService::class)->isDefault($rule->zone_id);

        if (! $isDefaultZone) {
            return false;
        }

        return DeliveryRule::where('zone_id', $rule->zone_id)->whereKeyNot($ruleId)->doesntExist();
    }

    private function moduleIds(array $data): array
    {
        return array_values(array_filter(array_map('intval', (array) ($data['module_ids'] ?? []))));
    }

    private function distanceCharge(DeliveryRule $rule, float $distanceKm): float
    {
        $chargeable = app(DistanceService::class)->chargeable($distanceKm);
        $raw = $chargeable * (float) ($rule->per_km_charge ?? 0);

        $maximum = $rule->maximum_delivery_charge;

        return ($maximum !== null && $maximum > 0) ? min($raw, (float) $maximum) : $raw;
    }

    /**
     * Does this rule connect a module that carries parcels?
     *
     * Capability-driven (`config('module.<type>.is_parcel')`), never a `module_type === 'parcel'`
     * string test — port doc §16.1. Gates the wizard's two extra steps both in the UI and here,
     * so a crafted POST cannot switch weight pricing on for a food-only rule.
     */
    public function connectsParcel(array $data): bool
    {
        $moduleIds = $this->moduleIds($data);

        if ($moduleIds === []) {
            return false;
        }

        return array_intersect($moduleIds, app(ModuleService::class)->parcelCapableModuleIds()) !== [];
    }

    /**
     * Persist the wizard's Weight Rules and Dimension Rules steps.
     *
     * Everything is written at Submit, not step by step: the wizard holds its state client-side so
     * a half-configured parcel rule never lands in the table (parcel brief §6a question 1).
     *
     * When parcel is NOT connected both tiers are cleared. Otherwise a rule that once connected
     * parcel, and no longer does, would keep charge rows that no screen shows and no order can
     * select.
     */
    private function syncParcelTiers(DeliveryRule $rule, array $data): void
    {
        $parcel = $this->connectsParcel($data);

        app(DeliveryRuleWeightChargeService::class)->syncForRule(
            $rule,
            $data['weight_charges'] ?? [],
            $parcel && ! empty($data['weight_charge_status']),
        );

        app(DeliveryRuleDimensionChargeService::class)->syncForRule(
            $rule,
            $data['dimension_charges'] ?? [],
            $parcel && ! empty($data['dimension_charge_status']),
        );
    }

    private function attributes(array $data): array
    {
        $method = $data['pricing_method'] ?? DeliveryRule::METHOD_FIXED;
        $parcel = $this->connectsParcel($data);

        return [
            'zone_id' => $data['zone_id'],
            // The legacy single column is kept in step with the first selected module for one
            // release, so a half-migrated install that still reads it stays correct (§4.3's
            // treatment of the pivot columns). New code reads the modules() pivot.
            'module_id' => $this->moduleIds($data)[0] ?? ($data['module_id'] ?? null),
            'name' => $data['name'],
            'minimum_delivery_charge' => (float) ($data['minimum_delivery_charge'] ?? 0),
            'pricing_method' => $method,
            // Columns belonging to the methods NOT chosen are nulled rather than left stale, so a
            // rule switched from distance_wise to fixed_amount cannot keep quoting a per-km rate
            // that no screen shows any more.
            'per_km_charge' => $method === DeliveryRule::METHOD_DISTANCE ? (float) ($data['per_km_charge'] ?? 0) : null,
            'maximum_delivery_charge' => $method === DeliveryRule::METHOD_DISTANCE ? $this->nullableFloat($data['maximum_delivery_charge'] ?? null) : null,
            'fixed_charge' => $method === DeliveryRule::METHOD_FIXED ? (float) ($data['fixed_charge'] ?? 0) : null,
            // The wizard's two parcel steps. False whenever parcel is not connected, so
            // disconnecting the module cannot leave a rule quietly charging by weight.
            'weight_charge_status' => $parcel && ! empty($data['weight_charge_status']),
            'dimension_charge_status' => $parcel && ! empty($data['dimension_charge_status']),
        ];
    }

    private function nullableFloat(mixed $value): ?float
    {
        return ($value === null || $value === '') ? null : (float) $value;
    }

    private function buildQuery(array $filters, array $with, array $withCount): Builder
    {
        return DeliveryRule::query()
            ->with($with)
            ->withCount($withCount)
            ->when(! empty($filters['zone_id']), fn ($q) => $q->where('zone_id', $filters['zone_id']))
            ->when(! empty($filters['module_id']), fn ($q) => $q->whereHas('modules', fn ($m) => $m->where('modules.id', $filters['module_id'])))
            ->when(! empty($filters['pricing_method']), fn ($q) => $q->where('pricing_method', $filters['pricing_method']))
            ->when(isset($filters['status']) && $filters['status'] !== '', fn ($q) => $q->where('status', (int) $filters['status']))
            ->when(! empty($filters['search']), fn ($q) => $q->where('name', 'like', '%'.$filters['search'].'%'))
            ->orderBy('id', 'desc');
    }
}
