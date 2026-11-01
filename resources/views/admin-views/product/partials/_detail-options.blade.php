@php
    $food_variations = $food_variations ?? [];
    $variations = $variations ?? [];
    $addons = $addons ?? collect();
    $chip_groups = collect($chip_groups ?? [])->filter(fn ($group) => count($group['values']));
    $has_options = count($food_variations) || count($variations) || $addons->count() || $chip_groups->count();
@endphp

@if($has_options)
    <div class="card mb-3">
        <div class="idt-card__head">
            <h2 class="idt-card__title">{{ translate('messages.Options and attributes') }}</h2>
        </div>

        <div class="idt-specs">
            @if(count($food_variations))
                <div class="idt-spec">
                    <span class="idt-spec__label">{{ translate('messages.Available Variations') }}</span>
                    <ul class="idt-list">
                        @foreach($food_variations as $variation)
                            @if(isset($variation['price']))
                                <li class="idt-blank">{{ translate('messages.Please update the food variations.') }}</li>
                                @break
                            @endif
                            <li class="idt-list__row">
                                <span>
                                    <b>{{ $variation['name'] }}</b>
                                    — {{ $variation['type'] === 'multi' ? translate('messages.Multiple select') : translate('messages.Single select') }}
                                    @if(($variation['required'] ?? null) === 'on')
                                        ({{ translate('messages.Required.') }})
                                    @endif
                                </span>
                            </li>
                            @foreach($variation['values'] ?? [] as $value)
                                <li class="idt-list__row">
                                    <span>{{ $value['label'] }}</span>
                                    <b>{{ \App\CentralLogics\Helpers::format_currency($value['optionPrice']) }}</b>
                                </li>
                            @endforeach
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(count($variations))
                <div class="idt-spec">
                    <span class="idt-spec__label">{{ translate('messages.Available Variations') }}</span>
                    <ul class="idt-list">
                        @foreach($variations as $variation)
                            <li class="idt-list__row">
                                <span class="text-capitalize">{{ $variation['type'] }}</span>
                                <b>{{ \App\CentralLogics\Helpers::format_currency($variation['price']) }}</b>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($addons->count())
                <div class="idt-spec">
                    <span class="idt-spec__label">{{ translate('Addons') }}</span>
                    <ul class="idt-list">
                        @foreach($addons as $addon)
                            <li class="idt-list__row">
                                <span>{{ $addon['name'] }}</span>
                                <b>{{ \App\CentralLogics\Helpers::format_currency($addon['price']) }}</b>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @foreach($chip_groups as $group)
                <div class="idt-spec">
                    <span class="idt-spec__label">{{ $group['label'] }}</span>
                    <span class="idt-chips">
                        @foreach($group['values'] as $value)
                            <span class="idt-chip">{{ $value }}</span>
                        @endforeach
                    </span>
                </div>
            @endforeach
        </div>
    </div>
@endif
