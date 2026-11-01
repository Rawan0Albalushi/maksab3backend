<?php

namespace App\Services\Promotion;

use App\Models\Bundle;
use App\Models\Cart;
use App\Services\BaseService;
use App\Traits\Customer\PersonalizationTrait;
use App\Traits\Promotion\HandlesBundleCart;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BundleCartService extends BaseService
{
    use HandlesBundleCart;
    use PersonalizationTrait;

    public function addBundle(array $payload): array
    {
        $bundle = $this->resolveBundleForCart($payload['bundle_id'], $payload['zone_ids'] ?? []);

        if (! $bundle) {
            return ['status_code' => 404, 'code' => 'bundle', 'message' => translate('No data found')];
        }

        $this->purgeStaleBundleGroups($payload['user_id'], (int) $payload['is_guest'], $bundle);

        $quantity = max(1, (int) ($payload['quantity'] ?? 1));

        $wouldHold = $quantity + $this->bundleCopiesInCart(
            $payload['user_id'], (int) $payload['is_guest'], $bundle->id
        );

        if ($reason = $this->bundleUnavailableReason($bundle, $wouldHold)) {
            return ['status_code' => 403, 'code' => 'bundle', 'message' => $reason];
        }

        $groupId = (string) Str::uuid();

        Cart::insert($this->bundleCartRows(
            $bundle, $groupId, $quantity,
            $payload['user_id'], (int) $payload['is_guest'], (int) $payload['module_id']
        ));

        $this->recordCartPreference($bundle, $payload);

        return [
            'status_code' => 200,
            'bundle_group_id' => $groupId,
            'quantity' => $quantity,
            'store_id' => $bundle->store_id,
        ];
    }

    public function updateBundle(array $payload): array
    {
        $groups = $this->bundleGroupsInCart($payload['user_id'], (int) $payload['is_guest']);
        $group = $groups[$payload['bundle_group_id']] ?? null;

        if (! $group) {
            return ['status_code' => 404, 'code' => 'bundle_group_id', 'message' => translate('No data found')];
        }

        if (! $group['bundle']) {
            return ['status_code' => 403, 'code' => 'bundle', 'message' => translate('messages.The added bundle is unavailable.')];
        }

        $bundle = $group['bundle'];
        $quantity = max(1, (int) $payload['quantity']);

        $wouldHold = $quantity + $this->bundleCopiesInCart(
            $payload['user_id'], (int) $payload['is_guest'], $bundle->id, $payload['bundle_group_id']
        );

        if ($reason = $this->bundleUnavailableReason($bundle, $wouldHold)) {
            return ['status_code' => 403, 'code' => 'bundle', 'message' => $reason];
        }

        DB::transaction(function () use ($payload, $bundle, $quantity) {
            $this->bundleGroupRows($payload['user_id'], (int) $payload['is_guest'], $payload['bundle_group_id'])->delete();

            Cart::insert($this->bundleCartRows(
                $bundle, $payload['bundle_group_id'], $quantity,
                $payload['user_id'], (int) $payload['is_guest'], (int) $payload['module_id']
            ));
        });

        return [
            'status_code' => 200,
            'bundle_group_id' => $payload['bundle_group_id'],
            'quantity' => $quantity,
            'store_id' => $bundle->store_id,
        ];
    }

    /**
     * The `cart` signal the member items would have earned had they been added one by one.
     *
     * A bundle is written with Cart::insert() -- one statement for the whole group rather than a
     * model per line, which is what keeps a half-added bundle from existing -- but that also
     * steps around CartService::create(), the only place that signal is raised. So carting a
     * bundle taught personalisation nothing until the order was delivered, while the same items
     * carted singly taught it immediately.
     *
     * Only on add, mirroring CartService: create() records, update() does not, and re-quantifying
     * a group is an update.
     *
     * Service lines are skipped for the same reason CartService skips them -- it records only for
     * `item_type === Item::class`.
     */
    private function recordCartPreference(Bundle $bundle, array $payload): void
    {
        if ((int) ($payload['is_guest'] ?? 1) === 1 || empty($payload['user_id'])) {
            return;
        }

        foreach ($bundle->items as $line) {
            if ($line->isService() || ! $line->targetId()) {
                continue;
            }

            $this->recordItemAction((int) $payload['user_id'], (int) $line->targetId(), 'cart');
        }
    }

    public function removeBundle(array $payload): array
    {
        $deleted = $this->bundleGroupRows($payload['user_id'], (int) $payload['is_guest'], $payload['bundle_group_id'])->delete();

        if (! $deleted) {
            return ['status_code' => 404, 'code' => 'bundle_group_id', 'message' => translate('No data found')];
        }

        return ['status_code' => 200];
    }

    /**
     * Strand this bundle in every cart holding it, and answer how many rows stayed behind.
     *
     * This used to DELETE those rows, and it ran from the admin's or vendor's request -- so
     * deactivating, editing or deleting a bundle silently emptied strangers' carts. A customer
     * whose only cart item was the bundle was then told "You cannot place empty orders", which
     * blames them for somebody else's edit and leaves nothing on screen to explain.
     *
     * The rows now stay. BundleGroupPresenter flags the group unavailable with a reason, the
     * customer still reaches checkout, and BundleOrderService::blockingReason() refuses the order
     * by name. Same decision as BogoOfferStore::strandCarts(); the two features behave alike.
     */
    public function strandCarts(Bundle $bundle): int
    {
        return Cart::where('bundle_id', $bundle->id)->count();
    }

    public function belongsToBundle(?object $cart): bool
    {
        return (bool) ($cart?->bundle_group_id);
    }
}
