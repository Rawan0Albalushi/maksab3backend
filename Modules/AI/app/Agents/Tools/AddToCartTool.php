<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use App\CentralLogics\Helpers;
use App\Models\Cart;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use App\Services\Customer\PersonalizationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class AddToCartTool implements Tool
{
    public function __construct(
        private readonly AiResponseContext $context,
        private readonly ?User             $user       = null,
        private readonly ?int              $moduleId   = null,
        private readonly ?string           $guestId    = null,
        private readonly string            $moduleType = 'general',
    ) {}

    public function description(): string
    {
        return 'Add a specific item/product to the customer\'s cart. Works for both authenticated users and guests. If the item has variations (size, colour, weight, flavour, etc.) you MUST pass the chosen variation_type — never add without one when variations exist. For an item with several required option groups pass each choice comma-separated (e.g. "Large, Extra cheese"). Use the item ID returned by SearchProductsTool, GetPopularItemsTool, or GetBestDealsTool.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'item_id'        => $schema->number()->description('The ID of the item to add to cart')->required(),
            'item_name'      => $schema->string()->description('OPTIONAL fallback — the item name the user asked for. If item_id does not resolve, the tool will try to find a matching item by this name inside the current module/zone before failing.')->required()->nullable(),
            'quantity'       => $schema->number()->description('Quantity to add, default 1, or null for default')->required()->nullable(),
            'variation_type' => $schema->string()->description('The exact variation/option label(s) the customer chose (e.g. "250ml", "Black-S", "Large"). For an item with several required option groups pass each choice comma-separated (e.g. "Large, Spicy"). Pass null if the item has no variations.')->required()->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $this->context->recordTool('AddToCartTool');

        if (! $this->user && ! $this->guestId) {
            return 'Cannot add to cart: no customer identity available. The chat session must include either a logged-in user or a guest_id.';
        }

        $args          = $request->all();
        $itemId        = (int) ($args['item_id'] ?? 0);
        $itemName      = isset($args['item_name']) && $args['item_name'] !== null
            ? trim((string) $args['item_name'])
            : null;
        $quantity      = max(1, (int) ($args['quantity'] ?? 1));
        $variationType = isset($args['variation_type']) && $args['variation_type'] !== null
            ? trim((string) $args['variation_type'])
            : null;

        $itemColumns = [
            'id', 'name', 'price', 'maximum_cart_quantity', 'module_id',
            'stock', 'status', 'is_approved', 'variations', 'food_variations',
            'choice_options', 'store_id',
        ];

        /** @var Item|null $item */
        $item = $itemId > 0
            ? Item::withoutGlobalScopes()->find($itemId, $itemColumns)
            : null;

        if (! $item && $itemName !== null && $itemName !== '') {
            $item = Item::withoutGlobalScopes()
                ->when($this->moduleId, fn ($q) => $q->where('module_id', $this->moduleId))
                ->where('name', 'like', "%{$itemName}%")
                ->orderByDesc('order_count')
                ->first($itemColumns);

            if ($item) {
                $itemId = (int) $item->getKey();
            }
        }

        if (! $item) {
            return $itemName
                ? "I couldn't find an item matching \"{$itemName}\" in this section. Could you confirm the name or search again?"
                : "Item #{$itemId} does not exist in our system.";
        }

        if ((int) $item->getAttribute('status') !== 1) {
            return "\"{$item->getAttribute('name')}\" is currently inactive and cannot be added to cart.";
        }

        if ((int) $item->getAttribute('is_approved') !== 1) {
            return "\"{$item->getAttribute('name')}\" is pending approval and cannot be added to cart yet.";
        }

        $store = Store::select(['id', 'name', 'status'])->find((int) $item->getAttribute('store_id'));
        if (! $store || (int) $store->status !== 1) {
            return "\"{$item->getAttribute('name')}\" is from a store that is currently inactive.";
        }

        $name   = $item->getAttribute('name');
        $isFood = $this->moduleType === 'food';

        if ($isFood) {
            $resolution = $this->resolveFoodVariation($item, $variationType);
        } else {
            $resolution = $this->resolveLegacyVariation($item, $variationType);
        }

        if ($resolution['needs_choice']) {
            return $resolution['prompt'];
        }

        $selectedVariation = $resolution['selected'];
        $variationLabel    = $resolution['label'];
        $price             = $resolution['price'];
        $rawStock          = $resolution['stock'];
        $variationNeedle   = $resolution['needle'];

        $tracksStock = (bool) (config('module.' . $this->moduleType . '.stock') ?? false);
        $stock       = $tracksStock
            ? (is_numeric($rawStock) ? (int) $rawStock : 0)
            : -1;

        $maxQty = (int) $item->getAttribute('maximum_cart_quantity');
        $vLabel = $variationLabel !== '' ? " ({$variationLabel})" : '';

        if ($tracksStock && $stock === 0) {
            return "\"{$name}\"{$vLabel} is currently out of stock.";
        }

        if ($stock > 0 && $quantity > $stock) {
            return "Only {$stock} unit(s) of \"{$name}\"{$vLabel} are available. Please reduce the quantity.";
        }

        if ($maxQty > 0 && $quantity > $maxQty) {
            return "Cannot add {$quantity} of {$name} — maximum allowed per cart is {$maxQty}.";
        }

        $moduleId = (int) $item->getAttribute('module_id');

        $isGuest    = $this->user === null;
        $cartUserId = $isGuest ? $this->guestId : $this->user->getKey();

        $itemStoreId = (int) $item->getAttribute('store_id');

        $existing = Cart::where('item_id', $itemId)
            ->where('item_type', Item::class)
            ->where('user_id', $cartUserId)
            ->where('is_guest', $isGuest)
            ->where('module_id', $moduleId)
            ->where('store_id', $itemStoreId)
            ->when($variationNeedle !== '', function ($q) use ($variationNeedle) {
                $q->where('variation', 'like', '%' . $variationNeedle . '%');
            })
            ->first();

        if ($existing) {
            $newQty = (int) $existing->getAttribute('quantity') + $quantity;
            $capped = false;
            if ($maxQty > 0 && $newQty > $maxQty) {
                $newQty = $maxQty;
                $capped = true;
            }
            if ($stock > 0 && $newQty > $stock) {
                $newQty = $stock;
                $capped = true;
            }
            $existing->update(['quantity' => $newQty]);
            $this->publishCartSnapshot($cartUserId, $isGuest, $moduleId);

            $note = $capped ? ' (capped at the maximum allowed)' : '';
            return "Updated cart: {$name}{$vLabel} quantity is now {$newQty}{$note} (price: {$price} each).";
        }

        Cart::create([
            'user_id'     => $cartUserId,
            'is_guest'    => $isGuest,
            'module_id'   => $moduleId,
            'store_id'    => $itemStoreId,
            'item_id'     => $itemId,
            'item_type'   => Item::class,
            'price'       => $price,
            'quantity'    => $quantity,
            'add_on_ids'  => json_encode([]),
            'add_on_qtys' => json_encode([]),
            'variation'   => json_encode($selectedVariation),
        ]);

        if (! $isGuest && $this->user) {
            app(PersonalizationService::class)->recordItemAction((int) $this->user->getKey(), $itemId, 'cart');
        }

        $this->publishCartSnapshot($cartUserId, $isGuest, $moduleId);

        return "Added {$quantity}× {$name}{$vLabel} to your cart (price: {$price} each).";
    }


    /**
     * Resolve a FOOD item's option selection into the cart's stored shape.
     *
     * food_variations definition shape:
     *   [{name, type:"single"|"multi", min, max, required:"on"|"off",
     *     values:[{label, optionPrice}, ...]}]
     *
     * Cart row shape we must write (read by Common\Item\CartProductResource
     * and OrderActionsProvider::liveLinePrice):
     *   [{name, values:{label:[chosenLabel, ...]}}]
     *
     * The customer MUST choose at least one option from EVERY required group —
     * otherwise we return a prompt and DO NOT add.
     *
     * @return array{needs_choice: bool, prompt?: string, selected: array, label: string, price: float, stock: mixed, needle: string}
     */
    private function resolveFoodVariation(Item $item, ?string $choiceRaw): array
    {
        $groups    = $this->parseVariations($item->getAttribute('food_variations'));
        $basePrice = (float) $item->getAttribute('price');

        if (empty($groups)) {
            return $this->noVariationResult($basePrice, $item->getAttribute('stock'));
        }

        $tokens   = $this->splitChoices($choiceRaw);
        $selected = [];
        $labels   = [];
        $extra    = 0.0;
        $missing  = [];

        foreach ($groups as $group) {
            $groupName = (string) ($group['name'] ?? '');
            $values    = is_array($group['values'] ?? null) ? $group['values'] : [];
            $required  = $this->isGroupRequired($group);
            $max       = (int) ($group['max'] ?? 0);

            $chosen = $this->matchGroupLabels($tokens, $values);

            if ($max > 0 && count($chosen) > $max) {
                $missing[] = $group;
                continue;
            }

            if (! empty($chosen)) {
                foreach ($values as $val) {
                    if (in_array((string) ($val['label'] ?? ''), $chosen, true)) {
                        $extra += (float) ($val['optionPrice'] ?? 0);
                    }
                }
                $selected[] = ['name' => $groupName, 'values' => ['label' => array_values($chosen)]];
                $labels     = array_merge($labels, $chosen);
            } elseif ($required) {
                $missing[] = $group;
            }
        }

        if (! empty($missing)) {
            return [
                'needs_choice' => true,
                'prompt'       => $this->buildFoodPrompt($item->getAttribute('name'), (int) $item->getKey(), $missing, $choiceRaw),
                'selected'     => [],
                'label'        => '',
                'price'        => $basePrice,
                'stock'        => $item->getAttribute('stock'),
                'needle'       => '',
            ];
        }

        return [
            'needs_choice' => false,
            'selected'     => $selected,
            'label'        => implode(', ', $labels),
            'price'        => round($basePrice + $extra, 2),
            'stock'        => $item->getAttribute('stock'),
            'needle'       => $labels[0] ?? '',
        ];
    }

    /**
     * Resolve a NON-FOOD item's variant into the cart's stored shape.
     * Variant price REPLACES the base price (each variant carries its own price).
     *
     * @return array{needs_choice: bool, prompt?: string, selected: array, label: string, price: float, stock: mixed, needle: string}
     */
    private function resolveLegacyVariation(Item $item, ?string $choiceRaw): array
    {
        $variations = $this->parseVariations($item->getAttribute('variations'));
        $basePrice  = (float) $item->getAttribute('price');

        if (empty($variations)) {
            return $this->noVariationResult($basePrice, $item->getAttribute('stock'));
        }

        if ($choiceRaw === null || trim($choiceRaw) === '') {
            return [
                'needs_choice' => true,
                'prompt'       => $this->buildVariationPrompt($item->getAttribute('name'), (int) $item->getKey(), $variations),
                'selected'     => [], 'label' => '', 'price' => $basePrice,
                'stock'        => $item->getAttribute('stock'), 'needle' => '',
            ];
        }

        $matched = $this->matchVariation($variations, $choiceRaw);
        if ($matched === null) {
            return [
                'needs_choice' => true,
                'prompt'       => $this->buildVariationPrompt($item->getAttribute('name'), (int) $item->getKey(), $variations, "\"{$choiceRaw}\" is not a valid option."),
                'selected'     => [], 'label' => '', 'price' => $basePrice,
                'stock'        => $item->getAttribute('stock'), 'needle' => '',
            ];
        }

        $variantData = Helpers::variation_price($item, json_encode([['type' => $matched['type']]]));
        $price       = (float) ($variantData['price'] ?? $matched['price'] ?? $basePrice);
        $rawStock    = $variantData['stock'] ?? ($matched['stock'] ?? $item->getAttribute('stock'));

        return [
            'needs_choice' => false,
            'selected'     => [$matched],
            'label'        => (string) ($matched['type'] ?? ''),
            'price'        => $price,
            'stock'        => $rawStock,
            'needle'       => (string) ($matched['type'] ?? ''),
        ];
    }

    /** @return array{needs_choice: bool, selected: array, label: string, price: float, stock: mixed, needle: string} */
    private function noVariationResult(float $basePrice, mixed $stock): array
    {
        return [
            'needs_choice' => false,
            'selected'     => [],
            'label'        => '',
            'price'        => $basePrice,
            'stock'        => $stock,
            'needle'       => '',
        ];
    }


    private function splitChoices(?string $raw): array
    {
        if ($raw === null) {
            return [];
        }
        $parts = preg_split('/\s*[,;\/|]\s*/u', trim($raw)) ?: [];
        return array_values(array_filter(array_map(
            fn ($p) => $this->normalizeLabel($p),
            $parts
        ), fn ($p) => $p !== ''));
    }

    private function normalizeLabel(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s*\([^)]*\)\s*$/u', '', $value) ?? $value;
        $value = preg_replace('/\s*[-+:]\s*[^\p{L}]*$/u', '', $value) ?? $value;
        return strtolower(trim($value));
    }

    /**
     * Resolve which labels in ONE option group the customer chose — safely, with
     * no over-selection. Two passes:
     *   1) exact normalized equality (token === label) — the normal, unambiguous
     *      case ("Half plate", "Large", "Extra sauce").
     *   2) only if pass 1 found nothing: a token that UNIQUELY identifies a single
     *      label in this group via substring (recovers "half" → "Half plate",
     *      "med" → "Medium"). A token that matches several labels (e.g. "plate"
     *      → "Half plate" AND "Full plate") is ambiguous and is skipped, so the
     *      group stays unsatisfied and the customer is re-prompted.
     *
     * @param  string[] $tokens  normalized choice tokens
     * @param  array    $values  the group's option rows ([{label, optionPrice}])
     * @return string[]          chosen label strings (original casing)
     */
    private function matchGroupLabels(array $tokens, array $values): array
    {
        $labels = [];
        foreach ($values as $val) {
            $l = (string) ($val['label'] ?? '');
            if ($l !== '') {
                $labels[$l] = $this->normalizeLabel($l);
            }
        }

        $chosen = [];
        foreach ($labels as $orig => $norm) {
            if ($norm !== '' && in_array($norm, $tokens, true)) {
                $chosen[$orig] = true;
            }
        }
        if (! empty($chosen)) {
            return array_keys($chosen);
        }

        foreach ($tokens as $tok) {
            if (mb_strlen($tok) < 2) {
                continue;
            }
            $hits = [];
            foreach ($labels as $orig => $norm) {
                if ($norm !== '' && (str_contains($norm, $tok) || str_contains($tok, $norm))) {
                    $hits[] = $orig;
                }
            }
            if (count($hits) === 1) {
                $chosen[$hits[0]] = true;
            }
        }

        return array_keys($chosen);
    }

    private function isGroupRequired(array $group): bool
    {
        $required = strtolower((string) ($group['required'] ?? 'off'));
        if (in_array($required, ['on', '1', 'true', 'yes'], true)) {
            return true;
        }
        return (int) ($group['min'] ?? 0) >= 1;
    }

    private function parseVariations(mixed $raw): array
    {
        if (empty($raw)) {
            return [];
        }
        $decoded = \is_array($raw) ? $raw : json_decode((string) $raw, true);
        return \is_array($decoded) ? $decoded : [];
    }

    private function matchVariation(array $variations, string $type): ?array
    {
        $needle = $this->normalizeLabel($type);
        if ($needle === '') {
            return null;
        }
        foreach ($variations as $v) {
            if ($this->normalizeLabel((string) ($v['type'] ?? '')) === $needle) {
                return $v;
            }
        }
        foreach ($variations as $v) {
            $cand = $this->normalizeLabel((string) ($v['type'] ?? ''));
            if ($cand !== '' && (str_contains($cand, $needle) || str_contains($needle, $cand))) {
                return $v;
            }
        }
        return null;
    }


    private function buildVariationPrompt(string $name, int $itemId, array $variations, string $prefix = ''): string
    {
        $options = implode(', ', array_map(fn (array $v) => '"' . ($v['type'] ?? '') . '" (' . ($v['price'] ?? '') . ')', $variations));
        $msg = $prefix ? $prefix . ' ' : '';
        return "{$msg}NOT added yet — {$name} [ID:{$itemId}] has required variations. Please choose one: {$options}";
    }

    /**
     * Build the per-group choice prompt for a food item. Lists EACH still-missing
     * required group separately with its options + prices so the customer (via the
     * model) picks one from every group before the item is added.
     *
     * @param array<int, array> $groups food_variation groups still needing a choice
     */
    private function buildFoodPrompt(string $name, int $itemId, array $groups, ?string $choiceRaw): string
    {
        $blocks = [];
        foreach ($groups as $group) {
            $gname  = (string) ($group['name'] ?? 'Option');
            $values = is_array($group['values'] ?? null) ? $group['values'] : [];
            $opts   = [];
            foreach ($values as $val) {
                $label = (string) ($val['label'] ?? '');
                if ($label === '') {
                    continue;
                }
                $price  = (float) ($val['optionPrice'] ?? 0);
                $opts[] = $price > 0 ? "\"{$label}\" (+{$price})" : "\"{$label}\"";
            }
            $blocks[] = $gname . ' (required, pick one): ' . implode(', ', $opts);
        }

        $prefix = ($choiceRaw !== null && trim($choiceRaw) !== '')
            ? "\"{$choiceRaw}\" doesn't cover every required group yet. "
            : '';

        return "{$prefix}NOT added yet — {$name} [ID:{$itemId}] needs a choice for EACH of these required groups: " . implode(' | ', $blocks);
    }


    /**
     * Re-read the cart after a mutation and publish a fresh snapshot to the
     * response context so the API response carries the post-mutation state,
     * including each row's selected variation label.
     *
     * @param int|null $moduleId Scope the snapshot to the item's own module so a
     *                           freshly-added row still shows in the response.
     */
    private function publishCartSnapshot(int|string $cartUserId, bool $isGuest, ?int $moduleId = null): void
    {
        $moduleId ??= $this->moduleId;

        $carts = Cart::where('user_id', $cartUserId)
            ->where('is_guest', $isGuest)
            ->where('item_type', Item::class)
            ->when($moduleId, fn ($q) => $q->where('module_id', $moduleId))
            ->with(['item:id,name,price,discount,discount_type,store_id,image', 'item.storage'])
            ->get();

        $storeIds   = $carts->pluck('store_id')->filter()->unique()->values()->all();
        $storesById = $storeIds
            ? Store::whereIn('id', $storeIds)->get(['id', 'name'])->keyBy('id')
            : collect();

        $data = [];
        foreach ($carts as $row) {
            /** @var Item|null $itm */
            $itm  = $row->item;
            $qty  = (int) $row->getAttribute('quantity');
            $unit = (float) $row->getAttribute('price');
            if ($itm) {
                $disc     = (float) $itm->getAttribute('discount');
                $discType = (string) $itm->getAttribute('discount_type');
                if ($disc > 0) {
                    $unit = $discType === 'percent'
                        ? round($unit - $unit * $disc / 100, 2)
                        : round($unit - $disc, 2);
                }
            }
            $sid = (int) ($row->getAttribute('store_id') ?? $itm?->getAttribute('store_id') ?? 0);
            $data[] = [
                'cart_id'        => $row->getKey(),
                'item_id'        => (int) $row->getAttribute('item_id'),
                'store_id'       => $sid,
                'store_name'     => $storesById->get($sid)?->name,
                'name'           => $itm?->getAttribute('name') ?? 'Unknown item',
                'variation'      => $this->cartVariationLabel($row->getAttribute('variation')),
                'image'          => $itm?->getAttribute('image'),
                'image_full_url' => $itm?->image_full_url,
                'quantity'       => $qty,
                'unit_price'     => $unit,
                'line_total'     => round($unit * $qty, 2),
            ];
        }
        $this->context->addCartItems($data);
    }

    private function cartVariationLabel(mixed $raw): string
    {
        for ($i = 0; $i < 2 && is_string($raw); $i++) {
            $decoded = json_decode($raw, true);
            if ($decoded === null) {
                break;
            }
            $raw = $decoded;
        }
        if (! is_array($raw) || empty($raw)) {
            return '';
        }

        $parts = [];
        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            if (isset($entry['values']['label'])) {
                $labels = array_filter(array_map('strval', (array) $entry['values']['label']));
                if (! empty($labels)) {
                    $parts[] = implode(', ', $labels);
                }
            } elseif (isset($entry['type']) && $entry['type'] !== '') {
                $parts[] = (string) $entry['type'];
            }
        }

        return implode(' • ', $parts);
    }
}
