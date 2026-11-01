<div class="card mb-20">
    <div class="card-body">
        <h4 class="surge-section__title">{{ translate('Basic information') }}</h4>
        <p class="surge-section__subtitle">
            {{ translate('messages.Configure the basic details of the ETA configuration rule.') }}</p>

        <div class="surge-section">
            {{-- The tabs head the section rather than sitting inside the field, so the input keeps
                 the full width the design gives it. One input per language, the default first. --}}
            @if ($languages)
                <div class="js-nav-scroller hs-nav-scroller-horizontal">
                    <ul class="nav nav-tabs mb-3 border-0">
                        <li class="nav-item">
                            <a class="nav-link lang_link active" href="#"
                                id="default-link">{{ translate('messages.Default') }}</a>
                        </li>
                        @foreach ($languages as $language)
                            <li class="nav-item">
                                <a class="nav-link lang_link" href="#"
                                    id="{{ $language['code'] }}-link">{{ $language['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="lang_form" id="default-form">
                    <div class="form-group mb-0">
                        <label class="input-label" for="name_default">
                            {{ translate('ETA name') }} ({{ translate('messages.Default') }})
                            <span class="text-danger">*</span>
                            <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                                data-original-title="{{ translate('messages.A label for your own reference. It identifies this configuration in the ETA list and is never shown to customers.') }}"><i
                                    class="tio-info"></i></span>
                        </label>
                        {{-- The mock's placeholder reads "Type your business zone name" — a
                             copy-paste slip from the zone screen. It names this field instead. --}}
                        <input type="text" name="name[]" id="name_default" class="form-control h-45" maxlength="100"
                            placeholder="{{ translate('messages.Type your ETA configuration name') }}"
                            value="{{ old('name.0', $configuration?->getRawOriginal('name')) }}">
                    </div>
                    <input type="hidden" name="lang[]" value="default">
                </div>

                @foreach ($languages as $language)
                    <div class="d-none lang_form" id="{{ $language['code'] }}-form">
                        <div class="form-group mb-0">
                            <label class="input-label" for="name_{{ $language['code'] }}">
                                {{ translate('ETA name') }} ({{ strtoupper($language['code']) }})
                                <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                                    data-original-title="{{ translate('messages.A label for your own reference. It identifies this configuration in the ETA list and is never shown to customers.') }}"><i
                                        class="tio-info"></i></span>
                            </label>
                            <input type="text" name="name[]" id="name_{{ $language['code'] }}"
                                class="form-control h-45" maxlength="100"
                                placeholder="{{ translate('messages.Type your ETA configuration name') }}"
                                value="{{ $translated[$language['code']]['name'] ?? '' }}">
                        </div>
                        <input type="hidden" name="lang[]" value="{{ $language['code'] }}">
                    </div>
                @endforeach
            @else
                <div class="form-group mb-0">
                    <label class="input-label" for="name_default">
                        {{ translate('ETA name') }} <span class="text-danger">*</span>
                        <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.A label for your own reference. It identifies this configuration in the ETA list and is never shown to customers.') }}"><i
                                class="tio-info"></i></span>
                    </label>
                    <input type="text" name="name[]" id="name_default" class="form-control h-45" maxlength="100"
                        placeholder="{{ translate('messages.Type your ETA configuration name') }}"
                        value="{{ old('name.0', $configuration?->getRawOriginal('name')) }}">
                </div>
                <input type="hidden" name="lang[]" value="default">
            @endif
        </div>
    </div>
</div>

<div class="card mb-20">
    <div class="card-body">
        <h4 class="surge-section__title">{{ translate('Zone & module setup') }}</h4>
        {{-- The mock's subtitle reads "Choose where this surge pricing rule will be applied" —
             a slip from the surge screen. It names the ETA configuration here. --}}
        <p class="surge-section__subtitle">
            {{ translate('messages.Choose where this ETA configuration will be applied.') }}</p>

        <div class="surge-section">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="input-label" for="zone_id">{{ translate('Choose zone') }}
                        <span class="text-danger">*</span>
                    </label>
                    <select name="zone_id" id="zone_id" class="form-control js-select2-custom">
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}" {{ $selectedZoneId == $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="input-label" for="module_ids">{{ translate('Choose module') }}
                        <span class="text-danger">*</span>
                        <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.The modules this estimate covers in the chosen zone. A zone and module pair can hold only one ETA configuration.') }}"><i
                                class="tio-info"></i></span>
                    </label>
                    {{-- The theme's HSSelect2.init parses its options out of data-hs-select2-options
                         and ignores data-placeholder, so the placeholder goes there. --}}
                    <select name="module_ids[]" id="module_ids" class="form-control js-select2-custom" multiple
                        data-hs-select2-options='@json(["placeholder" => translate('Choose module'), "closeOnSelect" => false], JSON_HEX_APOS)'>
                        @foreach ($modules as $module)
                            <option value="{{ $module->id }}" data-module-type="{{ $module->module_type }}"
                                {{ in_array($module->id, $selectedModuleIds) ? 'selected' : '' }}>
                                {{ $module->module_name }}</option>
                        @endforeach
                    </select>
                    {{-- Only the modules still unconfigured in this zone are listed; the ones
                         already covered are hidden, not disabled. E1 remains the server-side
                         guard — hiding is not enforcing. --}}
                    <div id="modules-all-configured" class="rule-hint mt-2 {{ count($modules) ? 'd-none' : '' }}">
                        <img src="{{ asset('public/assets/admin/img/svg/bulb.svg') }}" class="svg" alt="">
                        <span>{{ $modulesEmptyMessage }}</span>
                    </div>
                </div>
            </div>

            <div class="rule-hint mt-3">
                <img src="{{ asset('public/assets/admin/img/svg/bulb.svg') }}" class="svg" alt="">
                <span>{{ translate('Only one ETA configuration is allowed for each zone and module combination.') }}</span>
            </div>
        </div>
    </div>
</div>

{{-- Hidden when Parcel is the only picked module: it has no method choice, no preparation
     buffer and no use for these fields at all, so the card that carries them is dropped rather
     than left visible and required for nothing the configuration actually covers. --}}
<div class="card mb-20 {{ empty(array_diff($selectedModuleIds, $parcelModuleIds)) ? 'd-none' : '' }}" id="other-eta-section">
    <div class="card-body">
        <h4 class="surge-section__title">{{ translate('ETA calculation method for other module') }}</h4>
        <p class="surge-section__subtitle">
            {{ translate('messages.Choose how the estimated delivery time (ETA) will be calculated.') }}</p>

        <div class="mb-20">
            <label class="input-label">
                {{ translate('ETA method') }} <span class="text-danger">*</span>
                <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                    data-original-title="{{ translate('Distance based takes the travel time from the map and widens it by the range gap. Fixed delivery time uses the time range the store itself quotes.') }}"><i class="tio-info"></i></span>
            </label>

            <div class="rule-method-grid rule-method-grid--two">
                @foreach ($methodCards as $value => $card)
                    <label class="rule-method {{ $selectedMethod === $value ? 'is-selected' : '' }}">
                        <span class="rule-method__head">
                            <input type="radio" name="calculation_method" value="{{ $value }}"
                                class="form-check-input eta-method-input"
                                {{ $selectedMethod === $value ? 'checked' : '' }}>
                            <span class="rule-method__title">{{ $card['title'] }}</span>
                        </span>
                        <span class="rule-method__hint">{{ $card['hint'] }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="surge-section">
            <div class="row g-3">
                {{-- Every timing is minutes, so "Min" is fixed to the input rather than offered
                     as a choice. Two across while the gap sits beside them, three across once
                     the fixed method drops it. --}}
                @foreach ($timings as $timing)
                    <div class="eta-time-col {{ $timingColumnClass }}">
                        <label class="input-label" for="{{ $timing['name'] }}">
                            {{ $timing['label'] }} <span class="text-danger">*</span>
                            <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                                data-original-title="{{ $timing['tooltip'] }}"><i class="tio-info"></i></span>
                        </label>
                        <div class="input-group eta-minute-input">
                            <input type="number" step="1" min="0" name="{{ $timing['name'] }}"
                                id="{{ $timing['name'] }}" class="form-control h-45"
                                placeholder="{{ translate('Ex') }}: 50" value="{{ $timing['value'] }}">
                            <div class="input-group-append">
                                <span class="input-group-text">{{ translate('messages.Min') }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- Distance based only: the fixed method reads its maximum from the store, so it
                     already has a range and has no gap to set. Unstarred in the design, and
                     optional in the request to match. --}}
                <div class="col-md-6 eta-time-col {{ $isDistanceMethod ? '' : 'd-none' }}" id="time-gap-wrapper">
                    <label class="input-label" for="time_gap">
                        {{ translate('ETA range gap') }} ({{ translate('messages.Min') }})
                        <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.How much longer the maximum estimate runs than the minimum, so the customer is shown a range rather than a single time.') }}"><i
                                class="tio-info"></i></span>
                    </label>
                    <div class="input-group eta-minute-input">
                        <input type="number" step="1" min="0" name="time_gap" id="time_gap" class="form-control h-45"
                            placeholder="{{ translate('Ex') }}: 5" value="{{ $timeGap }}">
                        <div class="input-group-append">
                            <span class="input-group-text">{{ translate('messages.Min') }}</span>
                        </div>
                    </div>
                    {{-- Kept in step with what has been typed, so the admin reads the range the
                         customer would be shown rather than the design's fixed example. --}}
                    <div class="eta-preview">
                        {{ translate('messages.Time will show like this') }}
                        <strong id="eta-preview-range"></strong>
                    </div>
                </div>
            </div>

            {{-- DESIGN RULE E3 — the distance method depends on the map, so the note names what
                 to configure and links to the page that configures it. --}}
            <div class="rule-note mt-3 {{ $isDistanceMethod ? '' : 'd-none' }}" id="eta-map-note">
                <i class="tio-info-outined"></i>
                <span>
                    {{ translate('messages.Google Maps API is required for distance-based ETA calculation. Configure your Google Maps API in') }}
                    <a href="{{ $mapSetupUrl }}">{{ translate('Google Maps setup') }}</a>.
                </span>
            </div>
        </div>
    </div>
</div>

{{-- Parcel has no store delivery-time range to fall back to and no kitchen to prepare
     anything, so it never gets the method choice or the preparation buffer the other card
     offers — it is always travel time from the map plus its own transit buffer, widened by
     its own gap. Shown only while Parcel is one of the picked modules. --}}
<div class="card mb-20 {{ empty(array_intersect($selectedModuleIds, $parcelModuleIds)) ? 'd-none' : '' }}" id="parcel-eta-section">
    <div class="card-body">
        <h4 class="surge-section__title">{{ translate('ETA calculation method for parcel') }}</h4>
        <p class="surge-section__subtitle">
            {{ translate('messages.Set how the ETA will be calculated. This calculate the ETA using the travel time from Google Maps, then add the configured transit buffer times.') }}</p>

        <div class="surge-section">
            <div class="row g-3">
                @foreach ($parcelTimings as $timing)
                    <div class="col-md-4">
                        <label class="input-label" for="{{ $timing['name'] }}">
                            {{ $timing['label'] }} <span class="text-danger">*</span>
                            <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                                data-original-title="{{ $timing['tooltip'] }}"><i class="tio-info"></i></span>
                        </label>
                        <div class="input-group eta-minute-input">
                            <input type="number" step="1" min="0" name="{{ $timing['name'] }}"
                                id="{{ $timing['name'] }}" class="form-control h-45"
                                placeholder="{{ translate('Ex') }}: 50" value="{{ $timing['value'] }}">
                            <div class="input-group-append">
                                <span class="input-group-text">{{ translate('messages.Min') }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="col-md-4">
                    <label class="input-label" for="parcel_time_gap">
                        {{ translate('ETA range gap') }} ({{ translate('messages.Min') }})
                        <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.How much longer the maximum estimate runs than the minimum, so the customer is shown a range rather than a single time.') }}"><i
                                class="tio-info"></i></span>
                    </label>
                    <div class="input-group eta-minute-input">
                        <input type="number" step="1" min="0" name="parcel_time_gap" id="parcel_time_gap"
                            class="form-control h-45" placeholder="{{ translate('Ex') }}: 5" value="{{ $parcelTimeGap }}">
                        <div class="input-group-append">
                            <span class="input-group-text">{{ translate('messages.Min') }}</span>
                        </div>
                    </div>
                    <div class="eta-preview">
                        {{ translate('messages.Time will show like this') }}
                        <strong id="parcel-eta-preview-range"></strong>
                    </div>
                </div>
            </div>

            {{-- Same note as the card above, and same content -- Parcel always needs Google Maps
                 regardless of what the other module's method is set to, so it would otherwise show
                 twice whenever that method is also "Distance Based". Shown only when the card
                 above is hiding its copy ($isDistanceMethod false), so exactly one Google Maps
                 note is ever on the page, never zero and never both. --}}
            <div class="rule-note mt-3 {{ $isDistanceMethod ? 'd-none' : '' }}" id="parcel-eta-map-note">
                <i class="tio-info-outined"></i>
                <span>
                    {{ translate('messages.Google Maps API is required for distance-based ETA calculation. Configure your Google Maps API in') }}
                    <a href="{{ $mapSetupUrl }}">{{ translate('Google Maps setup') }}</a>.
                </span>
            </div>
        </div>
    </div>
</div>

<div class="card mb-20">
    <div class="card-footer border-0">
        <div class="btn--container justify-content-end">
            <button type="reset" class="btn btn--reset min-w-120px">{{ translate('messages.Reset') }}</button>
            <button type="submit" class="btn btn--primary min-w-120px">
                <i class="tio-save"></i> {{ translate('Save information') }}</button>
        </div>
    </div>
</div>
