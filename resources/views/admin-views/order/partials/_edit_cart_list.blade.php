<?php
$cartItems = $editing ? $cart : $order->details;
// A bundle is one thing the customer chose, so it is one row here too. Listing its members
// separately invited edits the save then has to refuse, and showed the free member at 0.00 as if
// something were wrong. Each entry keeps the group's first line as its key, so the quantity and
// delete handlers below are unchanged -- they now act on the whole group.
$entries = app(\App\Services\Promotion\BogoOrderService::class)
    ->editorEntries($cartItems, (int) $order->store_id);
$entries = app(\App\Services\Promotion\BundleOrderService::class)->editorEntries($entries);
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-10px">
    <span class="fs-16 font-semibold text-dark">@if($order->store && $order->store->module && $order->store->module->module_type == 'food'){{ translate('Food list') }}@else{{ translate('Item list') }}@endif</span>
    <div class="w-20px h-20px text-dark rounded-circle d-flex align-items-center justify-content-center bg-list-count fs-12 font-semibold">
        {{ count($entries) }}
    </div>
</div>

<div class="table-responsive pt-0 card mb-20">
    <table class="table table-border table-thead-bordered table-nowrap table-align-middle card-table dataTable no-footer mb-0">
        <thead class="border-0 initial-94 bg-light">
            <tr>
                <th class="border-0 text-dark">{{ translate('SL') }}</th>
                <th class="border-0 text-dark">{{ translate('Item details') }}</th>
                <th class="border-0 text-dark text-center">{{ translate('QTY') }}</th>
                <th class="border-0 text-dark text-right">{{ translate('Total') }}</th>
                <th class="border-0 text-dark">{{ translate('Action') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($entries as $index => $entry)
                    <?php
                    $isBogo = $entry['is_bogo'];
                    $isBundle = $entry['is_bundle'] ?? false;
                    $isFolded = $isBogo || $isBundle;
                    $key    = $entry['key'];
                    $detail = $entry['detail'];
                    $itemImage = asset('public/assets/admin/img/100x100/2.png');
                    $itemName  = translate('item');
                    $isPreexisting = $editing && isset($detail->id);
                    $productMissing = false;
                    if ($editing) {
                        $hasItem = !empty($detail->item_id) && $detail->item;
                        $hasCampaign = !empty($detail->item_campaign_id) && $detail->campaign;
                        if ($hasItem) {
                            $itemImage = $detail->item?->image_full_url ?? $itemImage;
                            $itemName  = $detail->item?->name ?? $itemName;
                        } elseif ($hasCampaign) {
                            $itemImage = $detail->campaign?->image_full_url ?? $itemImage;
                            $itemName  = $detail->campaign?->title ?? $itemName;
                        } else {
                            $itemDetailsJson = $detail->item_details ?? null;
                            $itemDetailsArr  = is_array($itemDetailsJson) ? $itemDetailsJson : ($itemDetailsJson ? json_decode($itemDetailsJson, true) : null);
                            if (is_array($itemDetailsArr)) {
                                $itemName = $itemDetailsArr['name'] ?? $itemDetailsArr['title'] ?? $itemName;
                            }
                            if ($isPreexisting && (!empty($detail->item_id) || !empty($detail->item_campaign_id))) {
                                $productMissing = true;
                            }
                        }
                    } else {
                        $itemData  = is_array($detail->item_details) ? $detail->item_details : json_decode($detail->item_details, true);
                        $itemName  = $itemData['name'] ?? $itemName;
                        // Not editing means $detail came from $order->details, whose item and
                        // campaign relations the caller eager-loads — no need to re-fetch per row.
                        // Campaign rows have no item_id, so they must fall through to the
                        // campaign, as the editing branch above already does.
                        $itemImage = $detail->item?->image_full_url
                            ?? $detail->campaign?->image_full_url
                            ?? $itemImage;
                    }
                    $unitPrice = $detail->price - ($detail->discount_on_item ?? 0);
                    $lineTotal = $unitPrice * $detail->quantity + ($detail->total_add_on_price ?? 0);
                    $disabledAttr = $productMissing ? 'disabled' : '';
                    $readonlyAttr = $productMissing ? 'readonly' : '';

                    $addonTotal = $detail->total_add_on_price ?? 0;
                    $bogoLines = null;

                    if ($isBogo) {
                        // The offer names the row; its members are listed underneath. The quantity
                        // shown is BUNDLES, and the total is the group's -- a free member
                        // contributing nothing.
                        $itemName  = $entry['title'] ?: translate('BOGO offer');
                        $lineTotal = $entry['total'];
                        $shownQty  = $entry['bundles'];
                        // Per BUNDLE, so the client-side total (unit x qty + addons) stays right
                        // when the quantity control moves without a round trip to the server.
                        $unitPrice = $entry['bundles'] > 0 ? $entry['total'] / $entry['bundles'] : $entry['total'];
                        $addonTotal = (float) $entry['lines']->sum('total_add_on_price');
                        // Every member, with what ONE bundle's worth of each is. The save scrapes
                        // the rendered rows, so a bundle shown as a single row would post only its
                        // first member and silently drop the rest -- which is how a free item
                        // stopped being free. The payload builder expands this back out.
                        $bogoLines = $entry['lines']->map(fn ($l) => [
                            'order_details_id' => $l->id ?? null,
                            'item_id' => $l->item_id,
                            'unit_quantity' => max(1, (int) ($l->bogo_unit_quantity ?: 1)),
                            'variation' => is_string($l->variation ?? null) ? (json_decode($l->variation, true) ?: []) : ($l->variation ?? []),
                            'variant' => is_string($l->variant ?? null) ? (json_decode($l->variant, true) ?: []) : ($l->variant ?? []),
                            'add_ons' => is_string($l->add_ons ?? null) ? (json_decode($l->add_ons, true) ?: []) : ($l->add_ons ?? []),
                            'add_on_qtys' => $l->add_on_qtys ?? [],
                        ])->values();
                        // Nothing on a bundle is editable, so the quick-view drawer stays shut and
                        // a missing product must not disable the controls that remove it.
                        $productMissing = false;
                        $disabledAttr = '';
                        $readonlyAttr = '';
                    } elseif ($isBundle) {
                        $itemName  = $entry['title'] ?: translate('messages.Bundle');
                        $lineTotal = $entry['total'];
                        $shownQty  = $entry['copies'];
                        $unitPrice = $entry['copies'] > 0 ? $entry['total'] / $entry['copies'] : $entry['total'];
                        $addonTotal = 0;
                        $bogoLines = $entry['lines']->map(fn ($l) => [
                            'order_details_id' => $l->id ?? null,
                            'item_id' => $l->item_id,
                            'unit_quantity' => max(1, (int) ($l->bundle_unit_quantity ?: 1)),
                            'variation' => is_string($l->variation ?? null) ? (json_decode($l->variation, true) ?: []) : ($l->variation ?? []),
                            'variant' => is_string($l->variant ?? null) ? (json_decode($l->variant, true) ?: []) : ($l->variant ?? []),
                            'add_ons' => is_string($l->add_ons ?? null) ? (json_decode($l->add_ons, true) ?: []) : ($l->add_ons ?? []),
                            'add_on_qtys' => $l->add_on_qtys ?? [],
                        ])->values();
                        $productMissing = false;
                        $disabledAttr = '';
                        $readonlyAttr = '';
                    } else {
                        $shownQty = $detail->quantity;
                    }

                    $stockLimit = null;
                    if (! $isFolded && ! $productMissing && $detail->item) {
                        $moduleType = $order->store?->module?->module_type;
                        if ($moduleType && data_get(config('module.' . $moduleType), 'stock', false)) {
                            $variants = json_decode($detail->item->variations ?? '', true);
                            $variants = is_array($variants) ? $variants : [];
                            $chosen = is_string($detail->variation ?? null)
                                ? (json_decode($detail->variation, true) ?: [])
                                : ($detail->variation ?? []);
                            $wanted = is_array($chosen) ? ($chosen[0]['type'] ?? null) : null;
                            $available = (int) ($detail->item->stock ?? 0);
                            if ($variants && $wanted !== null) {
                                foreach ($variants as $variantRow) {
                                    if (($variantRow['type'] ?? null) === $wanted) {
                                        $available = (int) ($variantRow['stock'] ?? 0);
                                        break;
                                    }
                                }
                            }
                            $reserved = isset($detail->id)
                                ? (int) ($order->details->firstWhere('id', $detail->id)?->quantity ?? 0)
                                : 0;
                            $stockLimit = $available + $reserved;
                        }
                    }
                    ?>
                    <tr class="custom__tr" data-key="{{ $key }}" data-product-missing="{{ $productMissing ? 1 : 0 }}"
                        data-order-details-id="{{ isset($detail->id) ? $detail->id : '' }}"
                        data-item-id="{{ $detail->item_id }}"
                        data-item-campaign-id="{{ $detail->item_campaign_id }}"
                        data-quantity="{{ $shownQty }}"
                        @if ($stockLimit !== null) data-stock-limit="{{ $stockLimit }}" @endif
                        data-unit-price="{{ $unitPrice }}"
                        data-addon-total="{{ $addonTotal }}"
                        @if ($bogoLines) data-bogo-lines='@json($bogoLines)' @endif
                        data-variation='@json(is_string($detail->variation ?? null) ? (json_decode($detail->variation, true) ?: []) : ($detail->variation ?? []))'
                        data-variant='@json(is_string($detail->variant ?? null) ? (json_decode($detail->variant, true) ?: []) : ($detail->variant ?? []))'
                        data-add-ons='@json(is_string($detail->add_ons ?? null) ? (json_decode($detail->add_ons, true) ?: []) : ($detail->add_ons ?? []))'>
                        <td><div class="text-dark">{{ $index + 1 }}</div></td>
                        <td>
                            <div class="list-items-media min-w-176px d-flex align-items-center gap-2 {{ $productMissing || $isFolded ? '' : 'cursor-pointer quick-view-cart-item' }}" @if (!$productMissing && !$isFolded) data-key="{{ $key }}" @endif>
                                <img width="44" height="44" src="{{ $itemImage }}" alt="image" class="rounded onerror-image" data-onerror-image="{{ asset('public/assets/admin/img/100x100/2.png') }}">
                                <div class="cont d-flex flex-column gap-1">
                                    <p class="fs-12 text-dark mb-0 max-w-187px line--limit-1">{{ $itemName }}</p>
                                    @if ($isBogo)
                                        <span class="badge badge-soft-info fs-10 align-self-start">{{ translate('messages.BOGO') }}</span>
                                        <span class="fs-10 text-muted max-w-187px line--limit-1">
                                            @foreach ($entry['lines'] as $line)
                                                {{ \App\CentralLogics\Helpers::decodeJsonToArray($line->item_details)['name'] ?? ($line->item?->name ?? translate('item')) }}@if ($line->is_free_item) ({{ translate('messages.Free') }})@endif{{ !$loop->last ? ' + ' : '' }}
                                            @endforeach
                                        </span>
                                    @elseif ($isBundle)
                                        <span class="badge badge-soft-info fs-10 align-self-start">{{ translate('messages.Bundle') }}</span>
                                        <span class="fs-10 text-muted max-w-187px line--limit-1">
                                            @foreach ($entry['lines'] as $line)
                                                {{ \App\CentralLogics\Helpers::decodeJsonToArray($line->item_details)['name'] ?? ($line->item?->name ?? translate('item')) }}{{ !$loop->last ? ' + ' : '' }}
                                            @endforeach
                                        </span>
                                    @elseif ($productMissing)
                                        <span class="badge badge-soft-danger fs-10 align-self-start">{{ translate('messages.Item unavailable') }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="product-quantity w-105px mx-auto">
                                <div class="input-group bg-white rounded border d-flex flex-nowrap justify-content-center align-items-center">
                                    <span class="input-group-btn w-30px">
                                        <button class="btn px-2 btn-number w-30px decrease-quantity-button" type="button" data-type="minus" data-key="{{ $key }}" {{ $disabledAttr }}>
                                            <i class="tio-remove fs-16"></i>
                                        </button>
                                    </span>
                                    <input type="number" class="w-30px p-0 border-0 text-center fs-18 update-Quantity text-dark" name="qty[{{ $key }}]" value="{{ $shownQty }}" min="1" @if ($stockLimit !== null) max="{{ $stockLimit }}" @endif {{ $readonlyAttr }}>
                                    <span class="input-group-btn w-30px">
                                        <button class="btn px-2 btn-number increase-quantity-button w-30px" type="button" data-type="plus" data-key="{{ $key }}" {{ $disabledAttr }}>
                                            <i class="tio-add fs-16"></i>
                                        </button>
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="fs-14 text-right text-dark">
                            <div id="item_total_price_{{ $key }}">
                                {{ \App\CentralLogics\Helpers::format_currency($lineTotal) }}
                            </div>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn rounded-circle mx-auto p-1 d-flex align-items-center justify-content-center w-25px h-25px btn-sm btn--danger removeFromCart" data-key="{{ $key }}">
                                <i class="tio-delete text-white"></i>
                            </button>
                        </td>
                    </tr>
            @endforeach
        </tbody>
    </table>
</div>
