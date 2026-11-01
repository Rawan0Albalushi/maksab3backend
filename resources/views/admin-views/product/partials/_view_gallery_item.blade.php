@php
    $module_type = $item->module?->module_type;
    $fallback_image = asset('public/assets/admin/img/160x160/img2.jpg');

    $gallery = collect($item->images_full_url ?? [])->filter()->values()->all();
    if (count($gallery) === 0) {
        $gallery = [$item['image_full_url'] ?? $fallback_image];
    }

    $raw_variations =
        $module_type == 'food'
            ? (is_array($item->food_variations) ? $item->food_variations : json_decode($item->food_variations, true))
            : (is_array($item->variations) ? $item->variations : json_decode($item->variations, true));
    $raw_variations = is_array($raw_variations) ? $raw_variations : [];

    $variation_labels = [];
    foreach ($raw_variations as $variation) {
        if ($module_type == 'food') {
            foreach ($variation['values'] ?? [] as $value) {
                $variation_labels[] = trim(($variation['name'] ?? '') . ' - ' . ($value['label'] ?? ''), ' -');
            }
        } elseif (isset($variation['type'])) {
            $variation_labels[] = $variation['type'];
        }
    }

    $description = trim(strip_tags($item?->getRawOriginal('description') ?? ''));
    $is_long_description = Str::length($description) > 260;

    $use_url = Auth::guard('admin')->check()
        ? route('admin.item.edit', ['id' => $item->id, 'product_gellary' => true])
        : route('vendor.item.edit', ['id' => $item->id, 'product_gellary' => true]);
@endphp

<div class="pg-drawer__inner">
    <div class="pg-drawer__head">
        <div>
            <h3>{{ translate('Product details') }}</h3>
            <span>{{ translate('Check the details before reusing this product\'s information.') }}</span>
        </div>
        <button type="button" class="pg-drawer__close" aria-label="{{ translate('messages.Cancel') }}">&times;</button>
    </div>

    <div class="pg-drawer__body">
        <div class="pg-hero">
            <div class="pg-hero__main">
                <img class="pg-hero__img onerror-image" src="{{ $gallery[0] }}"
                    data-onerror-image="{{ $fallback_image }}" alt="{{ $item?->getRawOriginal('name') }}">

                <div class="pg-hero__badges">
                    @if ($module_type == 'food')
                        <span class="pg-tag {{ $item->veg == 1 ? 'pg-tag--veg' : 'pg-tag--nonveg' }}">
                            {{ $item->veg == 1 ? translate('Veg') : translate('Non veg') }}
                        </span>
                    @endif
                    @if ($item->organic == 1)
                        <span class="pg-tag pg-tag--organic">{{ translate('messages.Organic') }}</span>
                    @endif
                    @if ($item->is_halal == 1)
                        <span class="pg-tag pg-tag--halal">{{ translate('messages.Halal') }}</span>
                    @endif
                </div>
            </div>

            @if (count($gallery) > 1)
                <div class="pg-thumbs">
                    @foreach ($gallery as $key => $photo)
                        <button type="button" class="pg-thumb {{ $key === 0 ? 'is-active' : '' }}"
                            data-src="{{ $photo }}" aria-label="{{ translate('messages.Image') }} {{ $key + 1 }}">
                            <img class="onerror-image" src="{{ $photo }}"
                                data-onerror-image="{{ $fallback_image }}" alt="">
                        </button>
                    @endforeach
                </div>
            @endif

            <h4 class="pg-detail__title">{{ $item?->getRawOriginal('name') }}</h4>

            <div class="pg-detail__price">
                <span class="pg-card__amount">{{ \App\CentralLogics\Helpers::format_currency($item->price) }}</span>
                @if ($item->discount > 0)
                    <span class="pg-card__off">
                        {{ $item->discount_type == 'percent' ? $item->discount . '%' : \App\CentralLogics\Helpers::format_currency($item->discount) }}
                        {{ translate('messages.off') }}
                    </span>
                @endif
            </div>

            @if ($description !== '')
                <div class="pg-desc">
                    <span class="pg-desc__text {{ $is_long_description ? 'pg-desc__text--clamped' : '' }}">{{ $description }}</span>
                    @if ($is_long_description)
                        <button type="button" class="pg-desc__toggle" data-more="{{ translate('messages.Show more') }}"
                            data-less="{{ translate('messages.Show less') }}">{{ translate('messages.Show more') }}</button>
                    @endif
                </div>
            @endif
        </div>

        <div class="pg-section">
            <div class="pg-section__head">{{ translate('General information') }}</div>
            <div class="pg-section__body">
                <div class="pg-row">
                    <span class="pg-row__label">{{ translate('messages.Category') }}</span>
                    <span class="pg-row__value">
                        {{ ($item?->category?->parent ? $item?->category?->parent?->name : $item?->category?->name) ?? translate('messages.uncategorize') }}
                    </span>
                </div>
                <div class="pg-row">
                    <span class="pg-row__label">{{ translate('Subcategory') }}</span>
                    <span class="pg-row__value">
                        {{ $item?->category?->name ?? translate('messages.uncategorize') }}
                    </span>
                </div>
                @if ($module_type == 'food')
                    <div class="pg-row">
                        <span class="pg-row__label">{{ translate('messages.Item type') }}</span>
                        <span class="pg-row__value">
                            {{ $item->veg == 1 ? translate('Veg') : translate('Non veg') }}
                        </span>
                    </div>
                @elseif ($item?->unit)
                    <div class="pg-row">
                        <span class="pg-row__label">{{ translate('Unit') }}</span>
                        <span class="pg-row__value">{{ $item?->unit?->unit }}</span>
                    </div>
                @endif
                @if ($module_type == 'grocery')
                    <div class="pg-row">
                        <span class="pg-row__label">{{ translate('Is organic') }}</span>
                        <span class="pg-row__value">
                            {{ $item->organic == 1 ? translate('messages.Yes') : translate('messages.No') }}
                        </span>
                    </div>
                @endif
            </div>
        </div>

        <div class="pg-section">
            <div class="pg-section__head">{{ translate('Price information') }}</div>
            <div class="pg-section__body">
                <div class="pg-row">
                    <span class="pg-row__label">{{ translate('messages.price') }}</span>
                    <span
                        class="pg-row__value">{{ \App\CentralLogics\Helpers::format_currency($item?->price) }}</span>
                </div>
                <div class="pg-row">
                    <span class="pg-row__label">{{ translate('Discount') }}</span>
                    <span class="pg-row__value">
                        {{ $item->discount_type == 'percent' ? $item->discount . ' %' : \App\CentralLogics\Helpers::format_currency($item->discount) }}
                    </span>
                </div>
            </div>
        </div>

        @if (count($variation_labels) > 0)
            <div class="pg-section">
                <div class="pg-section__head">{{ translate('messages.Available Variations') }}</div>
                <div class="pg-section__body pg-section__body--chips">
                    @foreach ($variation_labels as $label)
                        <span class="pg-chip">{{ $label }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        @if (count($item->tags ?? []) > 0)
            <div class="pg-section">
                <div class="pg-section__head">{{ translate('messages.Tags') }}</div>
                <div class="pg-section__body pg-section__body--chips">
                    @foreach ($item->tags as $tag)
                        <span class="pg-chip pg-chip--tag">{{ $tag->tag }}</span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="pg-drawer__foot">
        <button type="button" class="btn btn--reset pg-drawer__close"><i class="tio-clear-circle-outlined"></i> {{ translate('messages.Cancel') }}</button>
        <a target="_blank" href="{{ $use_url }}" class="btn btn--primary">
            <i class="tio-checkmark-circle-outlined"></i> {{ translate('Use product') }}
        </a>
    </div>
</div>
