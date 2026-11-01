@php
    // Everything here comes from the frozen snapshot, so an edited or deleted food cannot
    // change what this enrolment shows.
    // variationLabels() covers both shapes; reading values.label directly dropped the whole
    // selection for non-food lines, which store a flat {type} instead of named groups.
    $variationLabels = $item->variationLabels();

    $addOnLabels = collect($item->add_on_ids ?? [])
        ->map(fn ($id) => $addOnNames[$id] ?? null)
        ->filter()
        ->all();

    $subLine = implode(' | ', array_filter([
        implode(', ', $addOnLabels),
        implode(', ', $variationLabels),
    ]));
@endphp

<div class="d-flex align-items-center gap-2">
    <img class="avatar avatar-sm rounded onerror-image"
         data-onerror-image="{{asset('public/assets/admin/img/100x100/1.png')}}"
         src="{{ $item->item_image_full_url }}" alt="food">
    <div>
        <span class="d-block text-body">{{ Str::limit($item->item_name, 20, '...') }}</span>
        @if($subLine)
            <span class="d-block font-size-sm opacity-75">{{ $subLine }}</span>
        @else
            <span class="d-block font-size-sm opacity-75">{{ translate('QTY') }} : {{ $item->quantity }}</span>
        @endif
    </div>
</div>
