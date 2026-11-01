{{--
    The Buy Food / Get Food lists of one enrolment, shared by the admin and vendor drawers so
    the two cannot drift apart.

    Every row is read from the frozen snapshot on the enrolment, never from the menu: a food
    edited after the store joined must not change what this enrolment says it agreed to.

    A row carries the food it points at and the selection frozen against it, and clicking one
    reopens the picker's own food config modal in read-only mode - the same panel used to choose
    the food in the first place, with its options ticked, inert, and nothing to submit.

    Expects: $enrollment, $addOnNames (id => name), $surface (css class for the row badge).
    Optional: $addOnPrices (id => price).
--}}
@php
    $surface = $surface ?? 'bg-light';
    $addOnPrices = $addOnPrices ?? collect();
@endphp

{{-- The blurbs the design carries, and the same two the vendor item picker already uses. The
     get side described buying -- "Customers must buy all selected Items" -- under the heading
     for what the customer receives, which is the opposite of what that side means. --}}
@foreach([
    ['buy', $enrollment->buyItems, translate('Buy item'), translate('messages.Customers must buy selected items in specific quantities for the BOGO offer')],
    ['get', $enrollment->getItems, translate('Get item'), translate('messages.Customers receive the item in the specified quantity with this BOGO offer')],
] as [$type, $items, $heading, $blurb])
    <h5 class="fs-18 font-bold mb-2 {{ $type === 'get' ? 'mt-4' : '' }}">{{ $heading }}</h5>
    <p class="fs-14 text-muted mb-3">{{ $blurb }}</p>

    <div class="bg-white rounded-8">
        @forelse($items as $item)
            @php
                // Both variation shapes, formatted by the model: a non-food line carries a
                // {type} key with no group name, and formatting it as one printed a bare " : ".
                $variationLines = collect($item->variationDisplayLines());

                // Name, how many of it, and what it costs - the quantity and the price were
                // both missing, so an add-on read as a bare word with no bearing on the total.
                $addOnQtys = collect($item->add_on_qtys ?? [])->values();
                $addOns = collect($item->add_on_ids ?? [])->values()
                    ->map(function ($id, $index) use ($addOnNames, $addOnPrices, $addOnQtys) {
                        if (! isset($addOnNames[$id])) {
                            return null;
                        }

                        return [
                            'name' => $addOnNames[$id],
                            'quantity' => (int) ($addOnQtys[$index] ?? 1),
                            'price' => isset($addOnPrices[$id]) ? (float) $addOnPrices[$id] : null,
                        ];
                    })
                    ->filter()->values();

                // What the line is worth. A get item is priced at 0 because it is the give-away,
                // so its worth lives in original_price - and that is the figure worth showing.
                $worth = (float) ($item->original_price ?? $item->price);

                // What the food config modal needs to reopen this line read-only: the food to
                // fetch, and the selection frozen on the enrolment to tick inside it.
                $payload = [
                    'item_id' => (int) $item->item_id,
                    'store_id' => (int) $enrollment->store_id,
                    'is_free' => $type === 'get',
                    'price' => \App\CentralLogics\Helpers::format_currency($worth),
                    'selected_variations' => $item->variations ?? [],
                    'add_on_ids' => collect($item->add_on_ids ?? [])->map('intval')->values()->all(),
                    'add_on_qtys' => collect($item->add_on_qtys ?? [])->map('intval')->values()->all(),
                ];
            @endphp

            <div class="d-flex align-items-center gap-3 p-3 pointer bogo-item-row {{ $loop->last ? '' : 'border-bottom' }}"
                 role="button" tabindex="0"
                 title="{{ translate('messages.View item details') }}"
                 data-item="{{ json_encode($payload) }}">
                <img class="rounded-8 onerror-image flex-shrink-0"
                     style="width:50px;height:50px;object-fit:cover"
                     data-onerror-image="{{ asset('public/assets/admin/img/100x100/1.png') }}"
                     src="{{ $item->item_image_full_url }}"
                     alt="food">

                <div class="flex-grow-1" style="min-width:0">
                    <span class="d-block fs-15 mb-1">{{ $item->item_name }}</span>
                    @foreach($variationLines as $line)
                        <span class="d-block fs-13 text-muted">{{ $line }}</span>
                    @endforeach
                    @if($addOns->count())
                        <span class="d-block fs-13 text-muted">
                            {{ translate('Add ons') }} :
                            {{ $addOns->map(fn ($a) => $a['name'].' x'.$a['quantity'])->implode(', ') }}
                        </span>
                    @endif
                </div>

                {{-- How many of it, per the design. The money moved to the side total below: a
                     price on every row asked the reader to add them up, and the figure they
                     actually need -- what the bundle costs and what it gives away -- is one
                     number per side. Each line's own price is still a click away, in the
                     read-only config modal this row opens. --}}
                <span class="{{ $surface }} rounded-8 d-center fs-15 flex-shrink-0 font-medium"
                      style="min-width:40px;height:36px;padding:0 10px">{{ $item->quantity }}</span>
            </div>
        @empty
            <p class="fs-14 text-muted mb-0 p-3">{{ translate('No data found') }}</p>
        @endforelse
    </div>

    @if($items->count())
        @php
            // original_price on both sides, never price: a get line is stored at 0 because it is
            // the give-away, and totalling that would report the bundle as costing the store
            // nothing. What it gives away is exactly what is worth naming here.
            $sideTotal = $items->sum(fn ($line) => (float) ($line->original_price ?? $line->price) * $line->quantity);
        @endphp

        <div class="d-flex align-items-center justify-content-end gap-2 fs-15 mt-3">
            <span class="opacity-75">
                {{ $type === 'buy' ? translate('Total buy amount') : translate('Total get amount') }} :
            </span>
            @if($type === 'get')
                {{-- Free beside the struck-through worth, so the give-away is legible as a
                     give-away rather than as a zero. --}}
                <strong>{{ translate('messages.Free') }}</strong>
                <del class="opacity-75">{{ \App\CentralLogics\Helpers::format_currency($sideTotal) }}</del>
            @else
                <strong>{{ \App\CentralLogics\Helpers::format_currency($sideTotal) }}</strong>
            @endif
        </div>
    @endif
@endforeach
