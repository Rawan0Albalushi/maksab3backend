<?php

namespace App\Traits\Promotion;

use App\CentralLogics\Helpers;
use App\Models\Bundle;
use App\Models\Cart;
use App\Models\Item;
use Modules\Service\Entities\Service;
use App\Services\Promotion\BundleService;
use App\Support\Promotion\BundleSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use App\Services\Store\StoreScheduleService;

trait HandlesBundleCart
{
    use HandlesFrozenLines;

    protected function resolveBundleForCart($bundleId, array $zoneIds = []): ?Bundle
    {
        return app(BundleService::class)->findForCart($bundleId, $zoneIds);
    }

    /**
     * $heldRows are the cart rows the customer is actually holding for this bundle, when the
     * caller has them. They are what makes an EDITED bundle detectable: the rows freeze the
     * member set at the moment it was added, so if the vendor has since swapped a member out,
     * the cart is holding one thing while the bundle now describes another. Callers without rows
     * (a fresh add, a group about to be rebuilt from the current definition) pass nothing.
     */
    protected function bundleUnavailableReason(
        Bundle $bundle,
        int $quantity,
        ?CarbonInterface $at = null,
        ?Collection $heldRows = null
    ): ?string {
        $at = $at ?: now();
        $isScheduled = $at->greaterThan(now());

        if (! BundleSettings::allowsModule($bundle->module_id)) {
            return translate('messages.This_bundle_is_no_longer_available');
        }

        if (! $bundle->status) {
            return translate('messages.This_bundle_is_no_longer_available');
        }

        if ($bundle->start_date && $bundle->start_date->greaterThan($at)) {
            return $isScheduled
                ? translate('messages.This_bundle_does_not_start_until_after_your_scheduled_time')
                : translate('messages.This_bundle_has_not_started_yet');
        }

        if ($bundle->end_date && $bundle->end_date->lessThan($at)) {
            return $isScheduled
                ? translate('messages.This_bundle_will_have_ended_by_your_scheduled_time')
                : translate('messages.This_bundle_has_already_ended');
        }

        // status is the admin's deactivation flag; active is the vendor's own
        // temporarily-closed toggle (BusinessSettingsController) -- CartValidationTrait
        // and HandlesPromotionEnrollment (Happy Hour/BOGO) already check both, this
        // only checked status, so a temporarily-closed store's bundles stayed orderable.
        if (! $bundle->store || ! $bundle->store->status) {
            return translate('messages.This_store_is_currently_unavailable');
        }

        if (! app(StoreScheduleService::class)->isOpenNow($bundle->store)) {
            return translate('messages.This_store_is_currently_unavailable');
        }

        if ($bundle->items->count() < Bundle::MIN_ITEMS) {
            return translate('messages.This_bundle_is_no_longer_available');
        }

        // Asked before the per-member checks below, which walk the bundle's CURRENT lines: if the
        // member set has moved on, those lines say nothing about what this cart is holding, and
        // reporting a stock shortfall on an item the customer never added would be noise.
        if ($heldRows !== null && $this->bundleMembersChanged($heldRows, $bundle)) {
            return translate('messages.The added bundle has changed. Please add it again.');
        }

        return $this->bundleItemsUnavailableReason($bundle, $quantity);
    }

    protected function bundleItemsUnavailableReason(Bundle $bundle, int $quantity): ?string
    {
        $itemLines = [];

        foreach ($bundle->items as $line) {
            if ($line->isService()) {
                if ($reason = $this->bundleServiceLineReason($line)) {
                    return $reason;
                }

                continue;
            }

            $itemLines[] = $line;
        }

        if (! $itemLines) {
            return null;
        }

        // linesUnavailableReason() (HandlesFrozenLines), not a per-line loop: a bundle can enrol
        // the same item twice over as two separate rows -- the picker writes one row per pick and
        // never merges a repeat pick into an existing row's quantity (see the identical note on
        // bundleMembersChanged()) -- and checking each row's stock against the item's FULL stock
        // independently let a bundle needing 2 units of a 1-in-stock item pass, since neither row
        // alone asked for more than 1. linesUnavailableReason() sums demand per stock pool across
        // every line before checking, which is exactly BOGO's own bundleItemsUnavailableReason()
        // equivalent (HandlesBogoBundles::bogoItemsUnavailableReason()) already does for its
        // buy+get lines, for the same reason.
        return $this->linesUnavailableReason($itemLines, $quantity, 'messages.is_no_longer_available');
    }

    protected function bundleServiceLineReason($line): ?string
    {
        if (! service_addon_active() || ! class_exists(Service::class)) {
            return translate('messages.This_bundle_is_no_longer_available');
        }

        $service = $line->relationLoaded('service') ? $line->service : Service::withoutGlobalScopes()->find($line->service_id);

        if (! $service) {
            return $line->item_name.' '.translate('messages.is_no_longer_available');
        }

        if (! $service->status || ! $service->is_approved) {
            return $service->name.' '.translate('messages.Is currently unavailable');
        }

        if ($this->serviceVariantKey($line->variations) && ! $this->serviceVariant($service, $line->variations)) {
            return $service->name.' '.translate('messages.Is currently unavailable');
        }

        return null;
    }

    /**
     * Whether the cart rows hold a different bundle than the record now describes.
     *
     * Members are compared by IDENTITY -- target id plus variations plus add-ons, the same identity
     * BundleOrderService prices a line by -- and never by bundle_item row id, because syncItems()
     * deletes and recreates every line on every save. Row ids therefore change when the vendor
     * fixes a typo in the description, and an id comparison would call that a changed bundle.
     *
     * Quantities have to be checked too, and they cannot be compared directly: a cart row holds
     * the member quantity multiplied by the number of copies added. So the test is that every
     * member divides into its row by the SAME whole number of copies. That catches a member
     * quantity edit (2 copies of a 1+1 bundle leave rows of 2 and 2, which no longer divide evenly
     * once a member goes to 3) while still accepting an edit that scales the whole bundle, where
     * the rows really do hold a whole number of the new thing.
     */
    protected function bundleMembersChanged(Collection $heldRows, Bundle $bundle): bool
    {
        $held = [];

        foreach ($heldRows as $row) {
            $identity = $this->lineIdentity(
                $row->item_id,
                Helpers::decodeJsonToArray($row->variation),
                Helpers::decodeJsonToArray($row->add_on_ids)
            );

            $held[$identity] = ($held[$identity] ?? 0) + max(1, (int) $row->quantity);
        }

        // Grouped by identity BEFORE comparison, and summed rather than read line by line: a
        // bundle can enrol the same item (or service) twice over as two separate rows -- the
        // picker writes one row per pick and never merges a repeat pick into an existing row's
        // quantity -- and comparing $bundle->items raw, unset()ing $held after the FIRST matching
        // line, read the second occurrence as a member the held cart no longer had. That reported
        // "changed" on a bundle nobody had touched, and no amount of removing and re-adding could
        // ever clear it: the freshly-written cart rows hit the identical bug on the very next
        // check. Summing here first mirrors how $held above already sums duplicate cart rows.
        $required = [];

        foreach ($bundle->items as $line) {
            $identity = $this->lineIdentity($line->targetId(), $line->variations, $line->add_on_ids);
            $required[$identity] = ($required[$identity] ?? 0) + max(1, (int) $line->quantity);
        }

        $copies = null;

        foreach ($required as $identity => $perCopy) {
            if (! isset($held[$identity])) {
                return true;
            }

            if ($held[$identity] % $perCopy !== 0) {
                return true;
            }

            $lineCopies = intdiv($held[$identity], $perCopy);

            if ($copies !== null && $copies !== $lineCopies) {
                return true;
            }

            $copies = $lineCopies;
            unset($held[$identity]);
        }

        // Anything left over is a member the cart holds and the bundle no longer lists.
        return $held !== [];
    }

    /**
     * Clears any of this customer's EXISTING groups of this bundle that no longer match its
     * current member set, before a fresh copy is added.
     *
     * A stale group can never be ordered as it stands -- bundleUnavailableReason() refuses it by
     * name for as long as it sits in the cart (see bundleMembersChanged() above, and
     * BundleOrderService::blockingReason(), which checks every group in the cart and blocks on
     * the first stale one it finds, whichever bundle it belongs to). Left beside a freshly-added,
     * correct copy, it went on blocking checkout even after the customer "fixed" the one they
     * could see: the added bundle has changed. Please add it again told the truth about one row
     * and hid the other. Purging stale groups of the SAME bundle here is what makes "add it
     * again" a full fix rather than a partial one.
     */
    protected function purgeStaleBundleGroups($userId, int $isGuest, Bundle $bundle): void
    {
        $groups = Cart::where('user_id', $userId)
            ->where('is_guest', $isGuest)
            ->where('bundle_id', $bundle->id)
            ->get()
            ->groupBy('bundle_group_id');

        foreach ($groups as $groupId => $groupRows) {
            if ($this->bundleMembersChanged($groupRows, $bundle)) {
                $this->bundleGroupRows($userId, $isGuest, $groupId)->delete();
            }
        }
    }

    protected function lineIdentity($itemId, $variations, $addOnIds): string
    {
        $variations = collect((array) $variations)
            ->map(fn ($group) => data_get($group, 'type')
                ?: data_get($group, 'name').':'.implode(',', (array) data_get($group, 'values.label', [])))
            ->sort()->values()->implode('|');

        $addOns = collect((array) $addOnIds)->map(fn ($id) => (string) $id)->sort()->values()->implode(',');

        return $itemId.'#'.$variations.'#'.$addOns;
    }

    protected function bundleCartRows(
        Bundle $bundle,
        string $groupId,
        int $quantity,
        $userId,
        int $isGuest,
        int $moduleId
    ): array {
        $rows = [];

        foreach ($bundle->items as $line) {
            $rows[] = [
                'user_id' => $userId,
                'is_guest' => $isGuest,
                'module_id' => $moduleId,
                'store_id' => $bundle->store_id,
                'item_id' => $line->targetId(),
                'item_type' => $line->isService() ? Service::class : Item::class,
                'price' => (float) $line->unit_price,
                'quantity' => max(1, (int) $line->quantity) * $quantity,
                'variation' => json_encode($line->variations ?: []),
                'add_on_ids' => json_encode($line->add_on_ids ?: []),
                'add_on_qtys' => json_encode($line->add_on_qtys ?: []),
                'bundle_id' => $bundle->id,
                'bundle_group_id' => $groupId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $rows;
    }

    protected function bundleGroupsInCart($userId, int $isGuest): array
    {
        $rows = Cart::where('user_id', $userId)
            ->where('is_guest', $isGuest)
            ->whereNotNull('bundle_group_id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $bundles = app(BundleService::class)->findManyForCart($rows->pluck('bundle_id')->all());

        $groups = [];

        foreach ($rows->groupBy('bundle_group_id') as $groupId => $groupRows) {
            $first = $groupRows->first();
            $bundle = $bundles[$first->bundle_id] ?? null;

            $groups[$groupId] = [
                'bundle' => $bundle,
                'rows' => $groupRows,
                'quantity' => $this->bundleGroupQuantity($groupRows, $bundle),
            ];
        }

        return $groups;
    }

    protected function bundleGroupQuantity(Collection $rows, ?Bundle $bundle): int
    {
        if (! $bundle || $bundle->items->isEmpty()) {
            return 1;
        }

        $perCopy = $bundle->items->sum(fn ($line) => max(1, (int) $line->quantity));

        return $perCopy > 0 ? max(1, (int) round($rows->sum('quantity') / $perCopy)) : 1;
    }

    protected function bundleCopiesInCart($userId, int $isGuest, int $bundleId, ?string $ignoreGroupId = null): int
    {
        $total = 0;

        foreach ($this->bundleGroupsInCart($userId, $isGuest) as $groupId => $group) {
            if ($groupId === $ignoreGroupId || ! $group['bundle'] || $group['bundle']->id !== $bundleId) {
                continue;
            }

            $total += $group['quantity'];
        }

        return $total;
    }

    protected function bundleGroupRows($userId, int $isGuest, string $groupId)
    {
        return Cart::where('user_id', $userId)
            ->where('is_guest', $isGuest)
            ->where('bundle_group_id', $groupId);
    }
}
