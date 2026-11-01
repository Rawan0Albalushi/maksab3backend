@php
    $is_admin_panel = Auth::guard('admin')->check();
@endphp

<div class="pg-grid">
    @foreach ($items as $item)
        @php
            $module_type = $item->module?->module_type;
            $parent_category = $item?->category?->parent?->name;
            $sub_category = $item?->category?->name;
            $images = is_array($item->images) ? $item->images : [];

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

            $chips = [];
            foreach ($variation_labels as $label) {
                $chips[] = ['label' => $label, 'text' => Str::limit($label, 18), 'class' => ''];
            }
            foreach ($item->tags ?? [] as $tag) {
                $chips[] = ['label' => $tag->tag, 'text' => Str::limit($tag->tag, 14), 'class' => 'pg-chip--tag'];
            }
            $chip_overflow = max(0, count($chips) - 3);
            $chips = array_slice($chips, 0, 3);

            $has_badges = $module_type == 'food' || $item->organic == 1 || $item->is_halal == 1;
            $has_meta = ($module_type != 'food' && $item?->unit) || count($variation_labels) > 0;
            $has_extra = count($chips) > 0;

            $item_url = $is_admin_panel
                ? route('admin.item.item-view', ['id' => $item->id])
                : route('vendor.item.item-view', ['id' => $item->id]);
            $use_url = $is_admin_panel
                ? route('admin.item.edit', ['id' => $item->id, 'product_gellary' => true])
                : route('vendor.item.edit', ['id' => $item->id, 'product_gellary' => true]);
        @endphp

        <article class="pg-card">
            <div class="pg-card__media">
                <img class="onerror-image"
                    src="{{ $item['image_full_url'] ?? asset('public/assets/admin/img/160x160/img2.jpg') }}"
                    data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                    alt="{{ $item?->getRawOriginal('name') }}">

                @if ($has_badges)
                    <div class="pg-card__badges">
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
                @endif

                @if (count($images) > 1)
                    <span class="pg-card__shots"><i class="tio-image"></i> {{ count($images) }}</span>
                @endif

                <button type="button" class="pg-card__quick offcanvas-trigger data-info-show" data-url="{{ $item_url }}"
                    data-target="#offcanvas_common_condition"
                    aria-label="{{ translate('Quick view') }} &ndash; {{ $item?->getRawOriginal('name') }}">
                    <span><i class="tio-visible"></i> {{ translate('Quick view') }}</span>
                </button>
            </div>

            <div class="pg-card__body">
                <span class="pg-card__eyebrow" title="{{ $parent_category }}{{ $parent_category && $sub_category ? ' / ' : '' }}{{ $sub_category }}">
                    @if ($parent_category)
                        {{ $parent_category }}@if ($sub_category)<i>/</i>{{ $sub_category }}@endif
                    @else
                        {{ $sub_category ?? translate('messages.uncategorize') }}
                    @endif
                </span>

                <h3 class="pg-card__title" title="{{ $item?->getRawOriginal('name') }}">
                    {{ $item?->getRawOriginal('name') }}
                </h3>

                <div class="pg-card__price">
                    <span class="pg-card__amount">{{ \App\CentralLogics\Helpers::format_currency($item->price) }}</span>
                    @if ($item->discount > 0)
                        <span class="pg-card__off">
                            {{ $item->discount_type == 'percent' ? $item->discount . '%' : \App\CentralLogics\Helpers::format_currency($item->discount) }}
                            {{ translate('messages.off') }}
                        </span>
                    @endif
                </div>

                @if ($has_meta)
                    <ul class="pg-card__meta">
                        @if ($module_type != 'food' && $item?->unit)
                            <li>{{ translate('Unit') }} <strong>{{ $item?->unit?->unit }}</strong></li>
                        @endif
                        @if (count($variation_labels) > 0)
                            <li><strong>{{ count($variation_labels) }}</strong>
                                {{ strtolower(translate('Variations')) }}</li>
                        @endif
                    </ul>
                @endif

                @if ($has_extra)
                    <div class="pg-card__extra">
                        <div class="pg-chips">
                            @foreach ($chips as $chip)
                                <span class="pg-chip {{ $chip['class'] }}"
                                    title="{{ $chip['label'] }}">{{ $chip['text'] }}</span>
                            @endforeach
                            @if ($chip_overflow > 0)
                                <span class="pg-chip pg-chip--more">+{{ $chip_overflow }}</span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div class="pg-card__foot">
                <a href="#0" class="pg-act pg-act--ghost offcanvas-trigger data-info-show" data-url="{{ $item_url }}"
                    data-target="#offcanvas_common_condition">
                    {{ translate('View details') }}
                </a>
                <a target="_blank" href="{{ $use_url }}" class="pg-act pg-act--solid">
                    {{ translate('Use product') }}
                </a>
            </div>
        </article>
    @endforeach
</div>

<div id="offcanvas_common_condition" class="custom-offcanvas pg-drawer d-flex flex-column justify-content-between">
    <div id="data-view" class="h-100"></div>
</div>
<div id="offcanvasOverlay" class="offcanvas-overlay"></div>
