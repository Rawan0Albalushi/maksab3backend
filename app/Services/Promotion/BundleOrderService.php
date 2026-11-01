<?php

namespace App\Services\Promotion;

use App\CentralLogics\Helpers;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use App\Models\Bundle;
use App\Models\Store;
use App\Services\Promotion\BogoOfferCustomerService;
use App\Services\Promotion\BundleService;
use App\Traits\Promotion\HandlesBundleCart;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BundleOrderService
{
    use HandlesBundleCart;

    /** Bundle id => name, for the span of one order. See bundleNameFor(). */
    private array $bundleNames = [];

    public function blockingReason(array $owner, int $storeId, $scheduleAt = null): ?string
    {
        foreach ($this->bundleGroupsInCart($owner['user_id'], (int) $owner['is_guest']) as $group) {
            if (! $group['bundle']) {
                return translate('messages.The added bundle is unavailable.');
            }

            if ($group['bundle']->store_id !== $storeId) {
                continue;
            }

            if ($reason = $this->bundleUnavailableReason(
                $group['bundle'], $group['quantity'], $scheduleAt, $group['rows']
            )) {
                return $reason;
            }
        }

        return null;
    }

    public function frozenPrices($carts, ?int $storeId = null): array
    {
        $prices = [];

        $groups = collect($carts)
            ->filter(fn ($row) => data_get($row, 'bundle_group_id'))
            ->groupBy(fn ($row) => data_get($row, 'bundle_group_id'));

        if ($groups->isEmpty()) {
            return $prices;
        }

        $bundles = app(BundleService::class)->findManyForPricing(
            $groups->map(fn ($rows) => data_get($rows->first(), 'bundle_id'))->all()
        );

        foreach ($groups as $groupId => $rows) {
            $bundle = $bundles[data_get($rows->first(), 'bundle_id')] ?? null;

            if (! $bundle) {
                continue;
            }

            foreach ($bundle->items as $line) {
                $prices[$groupId][$this->lineIdentity($line->item_id, $line->variations, $line->add_on_ids)] = (float) $line->unit_price;
            }
        }

        return $prices;
    }

    public function linePrice(array $frozenPrices, $cart, float $fallback): float
    {
        $groupId = data_get($cart, 'bundle_group_id');

        if (! $groupId) {
            return $fallback;
        }

        $identity = $this->lineIdentity(
            data_get($cart, 'item_id'),
            Helpers::decodeJsonToArray(data_get($cart, 'variation')),
            Helpers::decodeJsonToArray(data_get($cart, 'add_on_ids'))
        );

        return (float) ($frozenPrices[$groupId][$identity] ?? $fallback);
    }

    public function isDiscountable($detail): bool
    {
        return ! data_get($detail, 'bundle_group_id');
    }

    public function clearLineDiscount(array $discount): array
    {
        return [
            'discount_type' => 'vendor',
            'discount_amount' => 0,
            'discount_percentage' => 0,
            'admin_discount_amount' => 0,
            'vendor_discount_amount' => 0,
            'original_discount_type' => $discount['original_discount_type'] ?? null,
        ];
    }

    public function detailColumns($cart): array
    {
        $groupId = data_get($cart, 'bundle_group_id');
        $bundleId = $groupId ? data_get($cart, 'bundle_id') : null;

        return [
            'bundle_id' => $bundleId,
            'bundle_group_id' => $groupId,
            // Frozen here, not read live at display time. The member names are already frozen on
            // bundle_items when the bundle is saved; this is the bundle's own name getting the
            // same treatment, so a later rename cannot rewrite what a past order says it bought.
            'bundle_name' => $bundleId ? $this->bundleNameFor($bundleId) : null,
        ];
    }

    /**
     * The bundle's name at the moment of ordering.
     *
     * Memoised because detailColumns() runs once per LINE and a bundle has several: without the
     * cache a four-item bundle would issue four identical lookups on the way into one order
     * (rule 11). withTrashed() so a bundle deleted between cart and checkout still names itself.
     */
    private function bundleNameFor(mixed $bundleId): ?string
    {
        $key = (int) $bundleId;

        if (! array_key_exists($key, $this->bundleNames)) {
            $this->bundleNames[$key] = Bundle::withTrashed()->whereKey($key)->value('name');
        }

        return $this->bundleNames[$key];
    }

    public function distributeReduction(array $orderDetails, ?Store $store = null): array
    {
        $digits = (int) config('round_up_to_digit', 2);
        $happyHourRate = $this->happyHourRateFor($store);

        $groups = [];

        foreach ($orderDetails as $index => $detail) {
            if ($groupId = data_get($detail, 'bundle_group_id')) {
                $groups[$groupId][$index] = $detail;
            }
        }

        $lines = [];
        $bundleDiscount = 0.0;
        $happyHourDiscount = 0.0;

        if (! $groups) {
            return ['lines' => $lines, 'bundle_discount' => 0.0, 'happy_hour_discount' => 0.0];
        }

        $bundles = app(BundleService::class)->findManyForPricing(
            collect($groups)->map(fn ($rows) => data_get(reset($rows), 'bundle_id'))->all()
        );

        foreach ($groups as $rows) {
            $bundle = $bundles[data_get(reset($rows), 'bundle_id')] ?? null;

            if (! $bundle) {
                continue;
            }

            $lineTotals = [];
            $groupTotal = 0.0;

            foreach ($rows as $index => $detail) {
                $total = (float) $detail['price'] * (int) $detail['quantity'];
                $lineTotals[$index] = $total;
                $groupTotal += $total;
            }

            if ($groupTotal <= 0) {
                continue;
            }

            $parts = $this->groupReduction($bundle, $groupTotal, $happyHourRate, $digits);
            $reduction = $parts['total'];

            if ($reduction <= 0) {
                continue;
            }

            $allocated = 0.0;
            $booked = 0.0;
            $last = array_key_last($lineTotals);

            foreach ($lineTotals as $index => $total) {
                $share = $index === $last
                    ? round($reduction - $allocated, $digits)
                    : round($reduction * ($total / $groupTotal), $digits);

                $allocated += $share;

                $quantity = max(1, (int) $orderDetails[$index]['quantity']);
                $perUnit = round($share / $quantity, $digits);

                $lines[$index] = $perUnit;
                $booked += round($perUnit * $quantity, $digits);
            }

            $booked = round($booked, $digits);

            // What the lines actually carry, split the way groupReduction() split it. Booked off
            // $booked rather than off the two parts directly because per-unit rounding moves a
            // cent or two either way, and the halves have to add back up to what was charged --
            // the bundle's own share is taken at face value and the window keeps the remainder.
            $ownShare = min($parts['bundle'], $booked);

            $bundleDiscount += $ownShare;
            $happyHourDiscount += round($booked - $ownShare, $digits);
        }

        return [
            'lines' => $lines,
            'bundle_discount' => round($bundleDiscount, $digits),
            'happy_hour_discount' => round($happyHourDiscount, $digits),
        ];
    }

    public function happyHourRateFor(?Store $store): ?float
    {
        return $store ? app(BogoOfferCustomerService::class)->happyHourPercentageFor($store) : null;
    }

    /**
     * Fold an order's bundle lines into one entry each, for the customer API.
     *
     * The API's counterpart to orderGroups() below, which answers in the shape the admin and
     * vendor Blades want -- `['plain' => …, 'bundles' => …]`, with a `modal_id` per group. A JSON
     * client needs the opposite: ONE list, in order, where a bundle is a single entry and
     * everything about it hangs off `bundle_details`. Splitting the payload into two arrays would
     * make every client re-interleave them to render a receipt.
     *
     * `bundle_details` is present on EVERY entry and null for an ordinary line, so a client reads
     * one shape and branches on one key -- the same contract `bogo_details` follows.
     *
     * Prices are read back off the ORDER, never recomputed: what was charged is a matter of
     * record, and re-deriving it from today's bundle would make an old receipt disagree with the
     * payment taken for it.
     *
     * Chain this AFTER BogoOrderService::groupOrderDetails(). A line is never both, and a folded
     * BOGO entry carries no bundle_group_id, so it passes through here as an ordinary entry and
     * simply gains a null `bundle_details`.
     */
    public function groupOrderDetails($details): array
    {
        $rows = collect($details);

        if ($rows->every(fn ($detail) => empty($detail->bundle_group_id))) {
            return $rows->each(fn ($detail) => $detail->bundle_details = null)->values()->all();
        }

        $digits = config('round_up_to_digit', 2);

        $ordered = $this->orderedBundles($rows);

        $entries = [];

        foreach ($rows as $detail) {
            if ($detail->bundle_group_id) {
                continue;
            }

            $detail->bundle_details = null;
            $entries[] = $detail;
        }

        foreach ($rows->filter(fn ($d) => $d->bundle_group_id)->groupBy('bundle_group_id') as $groupId => $group) {
            $first = $group->first();

            // Copies, derived from the members rather than stored, so it cannot drift from the
            // rows that were actually charged. The MINIMUM: every member moves together, and a
            // member that somehow lags is the count the customer really received.
            $quantity = max(1, (int) $group->min('quantity'));
            $charged = (float) $group->sum(fn ($d) => (float) $d->price * (int) $d->quantity);
            $reduction = (float) $group->sum(fn ($d) => (float) $d->discount_on_item * (int) $d->quantity);

            // Cloned because the row also appears inside bundle_details.items, and pointing the
            // entry at that object would make the payload reference itself.
            $entry = clone $first;

            // A bundle is not one item, so everything item-specific is nulled -- leaving a real
            // item_id would describe the bundle as whichever member happened to be first.
            foreach (['id', 'item_id', 'item_campaign_id', 'item_details', 'variation', 'add_ons'] as $field) {
                $entry->{$field} = null;
            }

            $entry->price = round($quantity > 0 ? $charged / $quantity : $charged, $digits);
            $entry->quantity = $quantity;
            $entry->bundle_details = [
                'bundle_group_id' => (string) $groupId,
                // The Bundle model's id. Not to be confused with `bundle_details.bundle_id` on a
                // BOGO entry, which is that store's ENROLMENT -- different feature, different row.
                'bundle_id' => $first->bundle_id,
                // The live name is the FALLBACK only, for orders placed before bundle_name
                // existed on the line. A row carrying its own frozen name uses it, so renaming a
                // bundle never rewrites the title on an order that already bought it.
                'name' => $first->bundle_name
                    ?: ($ordered->get($first->bundle_id)?->name ?? translate('messages.Bundle')),
                'image_full_url' => $ordered->get($first->bundle_id)?->image_full_url,
                'quantity' => $quantity,
                'bundle_price' => round($quantity > 0 ? $charged / $quantity : $charged, $digits),
                'total_price' => round($charged, $digits),
                // What the bundle took off, as recorded at placement.
                'discount' => round($reduction, $digits),
                'items' => $group->values()->all(),
            ];

            $entries[] = $entry;
        }

        return $entries;
    }

    public function orderGroups($details): array
    {
        $rows = collect($details);

        $plain = $rows->filter(fn ($detail) => empty($detail->bundle_group_id))->values();
        $grouped = $rows->filter(fn ($detail) => ! empty($detail->bundle_group_id))->groupBy('bundle_group_id');

        if ($grouped->isEmpty()) {
            return ['plain' => $plain, 'bundles' => []];
        }

        $ordered = $this->orderedBundles($rows);

        $bundles = [];

        foreach ($grouped as $groupId => $lines) {
            $quantity = max(1, (int) $lines->min('quantity'));
            $charged = (float) $lines->sum(fn ($detail) => (float) $detail->price * (int) $detail->quantity);
            $bundleId = $lines->first()->bundle_id;

            $bundles[] = [
                'group_id' => (string) $groupId,
                'modal_id' => 'bundle_details_'.Str::slug((string) $groupId),
                'bundle_id' => $bundleId,
                // The live names are the FALLBACK only, for orders placed before bundle_name
                // existed on the line. A row that carries its own frozen name uses it, so
                // renaming a bundle never rewrites the title on an order that already bought it.
                'name' => $lines->first()->bundle_name
                    ?: ($ordered->get($bundleId)?->name ?? translate('messages.Bundle')),
                'image_full_url' => $ordered->get($bundleId)?->image_full_url,
                'quantity' => $quantity,
                'unit_price' => $charged / $quantity,
                'total' => $charged,
                'discount' => (float) $lines->sum(
                    fn ($detail) => (float) $detail->discount_on_item * (int) $detail->quantity
                ),
                'lines' => $lines->map(fn ($detail) => $this->orderLine($detail))->values()->all(),
            ];
        }

        return ['plain' => $plain, 'bundles' => $bundles];
    }

    public function stampEditorBundles($carts)
    {
        foreach ($carts as $key => $row) {
            $groupId = data_get($row, 'bundle_group_id');

            if (! $groupId) {
                continue;
            }

            $copies = $this->copiesInGroup($carts, $groupId);

            $carts[$key] = $this->writeRow($row, [
                'bundle_copy_quantity' => $copies,
                'bundle_unit_quantity' => max(1, (int) round(((int) data_get($row, 'quantity', 1)) / $copies)),
            ]);
        }

        return $carts;
    }

    public function scaleEditorBundle($carts, string $groupId, int $copies)
    {
        $copies = max(1, $copies);

        foreach ($carts as $key => $row) {
            if (data_get($row, 'bundle_group_id') !== $groupId) {
                continue;
            }

            $unit = max(1, (int) (data_get($row, 'bundle_unit_quantity') ?: 1));

            $carts[$key] = $this->writeRow($row, [
                'quantity' => $unit * $copies,
                'bundle_copy_quantity' => $copies,
            ]);
        }

        return $carts;
    }

    public function editorEntries(array $entries): array
    {
        $folded = [];
        $seen = [];

        foreach ($entries as $entry) {
            if (($entry['is_bogo'] ?? false) || ! ($groupId = data_get($entry['detail'], 'bundle_group_id'))) {
                $folded[] = $entry + ['is_bundle' => false];

                continue;
            }

            if (isset($seen[$groupId])) {
                continue;
            }

            $seen[$groupId] = true;

            $lines = collect($entries)
                ->filter(fn ($row) => data_get($row['detail'], 'bundle_group_id') === $groupId)
                ->map(fn ($row) => $row['detail'])
                ->values();

            $copies = max(1, (int) (data_get($entry['detail'], 'bundle_copy_quantity')
                ?: $this->copiesInGroup($lines, $groupId)));

            $folded[] = $entry + [
                'is_bundle' => true,
                'group_id' => $groupId,
                'lines' => $lines,
                'copies' => $copies,
                'title' => $this->bundleName(data_get($entry['detail'], 'bundle_id')),
                'total' => (float) $lines->sum(
                    fn ($row) => ((float) data_get($row, 'price', 0) - (float) (data_get($row, 'discount_on_item') ?? 0))
                        * (int) data_get($row, 'quantity', 0)
                ),
            ];
        }

        return $folded;
    }

    public function copiesFor($rows): array
    {
        $copies = [];

        foreach ($rows as $row) {
            if ($groupId = data_get($row, 'bundle_group_id')) {
                $copies[$groupId] ??= $this->copiesInGroup($rows, (string) $groupId);
            }
        }

        return $copies;
    }

    public function editRefusalReason($order, array $newRows, array $copiesBefore, int $storeId): ?string
    {
        $after = $this->copiesFor($newRows);

        if (! $after) {
            return null;
        }

        $bundleIds = collect($newRows)->pluck('bundle_id')->filter()->unique()->all();
        $bundles = app(BundleService::class)->findManyForPricing($bundleIds);

        foreach ($after as $groupId => $copies) {
            if ($copies <= (int) ($copiesBefore[$groupId] ?? 0)) {
                continue;
            }

            $bundleId = collect($newRows)
                ->first(fn ($row) => data_get($row, 'bundle_group_id') === $groupId)['bundle_id'] ?? null;

            $bundle = $bundles[$bundleId] ?? null;

            if (! $bundle) {
                return translate('messages.The added bundle is unavailable.');
            }

            if ((int) $bundle->store_id !== $storeId) {
                continue;
            }

            if ($reason = $this->bundleUnavailableReason($bundle, $copies, $this->scheduleMoment($order))) {
                return $reason;
            }
        }

        return null;
    }

    private function scheduleMoment($order): ?CarbonInterface
    {
        $at = is_array($order) ? ($order['schedule_at'] ?? null) : ($order->schedule_at ?? null);

        if (! $at) {
            return null;
        }

        $moment = $at instanceof CarbonInterface ? $at : Carbon::parse($at);

        return $moment->isFuture() ? $moment : null;
    }

    private function copiesInGroup($carts, string $groupId): int
    {
        $quantities = collect($carts)
            ->filter(fn ($row) => data_get($row, 'bundle_group_id') === $groupId)
            ->map(fn ($row) => max(1, (int) data_get($row, 'quantity', 1)));

        return $quantities->isEmpty() ? 1 : max(1, (int) $quantities->min());
    }

    private function bundleName($bundleId): ?string
    {
        if (! $bundleId) {
            return null;
        }

        return Bundle::withTrashed()->find($bundleId, ['id', 'name'])?->name;
    }

    private function writeRow($row, array $values)
    {
        foreach ($values as $field => $value) {
            data_set($row, $field, $value);
        }

        return $row;
    }

    private function orderLine($detail): array
    {
        $snapshot = Helpers::decodeJsonToArray($detail->item_details) ?: [];

        // Three shapes reach this. An order detail carries a frozen `item_details` snapshot and an
        // `item` relation; a SERVICE booking detail has neither -- it keeps its own `service_name`
        // and resolves its picture through ServiceBookingDetails::getImageFullUrlAttribute(). The
        // service fallbacks are last so nothing changes for an order, which never gets that far:
        // without them a booking's bundle listed every member as "item" with no picture at all.
        return [
            // Same three-shape fallback as 'name': the frozen snapshot first (what the item was
            // when this line was bought), then whichever live id column this detail actually
            // carries -- an order detail has item_id, a service booking detail has service_id,
            // never both.
            'id' => data_get($snapshot, 'id') ?: ($detail->item_id ?? $detail->service_id),
            'name' => data_get($snapshot, 'name')
                ?: ($detail->item?->name ?? $detail->service_name ?? translate('messages.item')),
            'image' => $detail->item?->image_full_url ?? $detail->image_full_url,
            'quantity' => (int) $detail->quantity,
            'price' => (float) $detail->price,
        ];
    }

    /**
     * The bundles an order's rows point at, keyed by id — for the name fallback and the picture.
     *
     * `image` has to be SELECTED and `storage` eager-loaded: Bundle::image_full_url is an appended
     * accessor that resolves the stored filename against the row's own disk, so a select of id and
     * name alone left it reading a column that was never fetched and every bundle payload shipped
     * a null picture. withTrashed() so a bundle deleted since the order still names and pictures
     * itself — an old receipt has to keep reading correctly.
     */
    private function orderedBundles($rows): Collection
    {
        return Bundle::withTrashed()
            ->with('storage')
            ->whereIn('id', collect($rows)->pluck('bundle_id')->filter()->unique()->all())
            ->get(['id', 'name', 'image'])
            ->keyBy('id');
    }

    /**
     * What comes off one bundle group, split by which promotion took it.
     *
     * The two apply in SEQUENCE, they do not replace one another: the bundle's own percentage
     * comes off the members' base prices first, and a happy hour then comes off what is left. A
     * bundle of 1790 discounted 10% is 1611, and a 15% window takes its 15% off THAT, not off the
     * 1790. This used to let the window's rate stand in for the bundle's -- so a customer ordering
     * during a happy hour silently lost the bundle discount they were shown, and a window rate
     * lower than the bundle's own made the bundle cost MORE while a promotion was running.
     *
     * Deliberately unlike a plain item, where a happy hour replaces the item's own discount rather
     * than stacking (StoreDiscountResolver's precedence table). A bundle's percentage is not a
     * discount the window can outrank -- it is what the bundle IS, the price the customer chose
     * it at -- so the window applies on top of it rather than instead of it.
     *
     * Split rather than totalled because the halves are accounted differently downstream: the
     * window's share is a happy hour, with its own expense type and its own commission base, and
     * the rest is the vendor's bundle discount booked to `bundle_discount_amount`.
     */
    private function groupReduction(Bundle $bundle, float $groupTotal, ?float $happyHourRate, int $digits): array
    {
        $own = min($groupTotal, max(0.0, round(
            $groupTotal * (float) $bundle->discount_percentage / 100, $digits
        )));

        $afterOwn = round($groupTotal - $own, $digits);

        $window = $happyHourRate > 0
            ? min($afterOwn, max(0.0, round($afterOwn * (float) $happyHourRate / 100, $digits)))
            : 0.0;

        return [
            'bundle' => $own,
            'happy_hour' => $window,
            'total' => round($own + $window, $digits),
        ];
    }
}
