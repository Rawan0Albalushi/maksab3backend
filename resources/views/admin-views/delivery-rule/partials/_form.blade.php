<div class="card mb-20">
    <div class="card-body">
        <h4 class="surge-section__title">{{ translate('Basic information') }}</h4>
        <p class="surge-section__subtitle">
            {{ translate('messages.Create a delivery pricing rule for a specific module and coverage.') }}</p>

        <div class="surge-section">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="input-label" for="name">{{ translate('Rule name') }}
                        <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="name" id="name" class="form-control h-45" maxlength="191"
                        placeholder="{{ translate('messages.Type your rule name') }}"
                        value="{{ old('name', $rule->name ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="input-label" for="zone_id">{{ translate('Select zone') }}</label>
                    <select name="zone_id" id="zone_id" class="form-control js-select2-custom">
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}" {{ $selectedZoneId == $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="input-label" for="module_ids">{{ translate('Choose module to connect') }}
                        <span class="text-danger">*</span>
                    </label>
                    {{-- The theme's HSSelect2.init parses its options out of
                         data-hs-select2-options and ignores data-placeholder, so the placeholder
                         goes there. Re-initialising the control afterwards instead leaves the
                         theme's instance and a second, narrow one side by side. --}}
                    <select name="module_ids[]" id="module_ids" class="form-control js-select2-custom" multiple
                        data-hs-select2-options='@json(["placeholder" => translate('Choose module'), "closeOnSelect" => false], JSON_HEX_APOS)'>
                        @foreach ($modules as $module)
                            <option value="{{ $module->id }}" {{ in_array($module->id, $selectedModuleIds) ? 'selected' : '' }}>
                                {{ $module->module_name }}</option>
                        @endforeach
                    </select>
                    {{-- $modules holds only the modules still FREE in the selected zone; the ones
                         already carrying a rule are hidden, not disabled. When none are left this
                         says so, rather than leaving an empty control. --}}
                    <div id="modules-all-configured" class="rule-hint mt-2 {{ count($modules) ? 'd-none' : '' }}">
                        <img src="{{ asset('public/assets/admin/img/svg/bulb.svg') }}" class="svg" alt="">
                        <span>{{ $modulesEmptyMessage }}</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="input-label" for="minimum_delivery_charge">
                        {{ translate('messages.Minimum Delivery Charge') }}({{ $currencySymbol }})
                        <span class="text-danger">*</span>
                        <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.The floor for this rule. A charge that works out lower — including after a discount — is lifted to this amount.') }}"><i
                                class="tio-info"></i></span>
                    </label>
                    <input type="number" step="0.01" min="0" name="minimum_delivery_charge"
                        id="minimum_delivery_charge" class="form-control h-45"
                        placeholder="{{ translate('Ex') }}: 5"
                        value="{{ old('minimum_delivery_charge', $rule->minimum_delivery_charge ?? '') }}">
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-20">
    <div class="card-body">
        {{-- The wizard, and its stepper, exist only when a parcel-capable module is connected.
             Hidden by default and revealed by the form script as the module multi-select changes
             — see _form-scripts.blade.php. Without parcel this whole block stays hidden and the
             screen behaves exactly as it did before: one page, one Save Information button. --}}
        <div id="rule-stepper" class="rule-stepper d-none mb-20">
            @foreach ($wizardSteps as $index => $stepLabel)
                @if ($index > 0)
                    <span class="rule-stepper__line" data-line="{{ $index + 1 }}"></span>
                @endif
                <span class="rule-stepper__step {{ $index === 0 ? 'is-current' : '' }}" data-step-marker="{{ $index + 1 }}">
                    <span class="rule-stepper__badge">
                        <i class="tio-done"></i>
                        <span class="rule-stepper__number">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    </span>
                    <span class="rule-stepper__label">{{ $stepLabel }}</span>
                </span>
            @endforeach
        </div>

        <div class="rule-step" data-step="1">
        <h4 class="surge-section__title">{{ translate('Delivery pricing method') }}</h4>
        <p class="surge-section__subtitle">
            {{ translate('messages.Choose how this rule calculates the base delivery charge.') }}</p>

        <div class="surge-section mb-20">
            <label class="input-label">
                {{ translate('Delivery method') }} <span class="text-danger">*</span>
                <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                    data-original-title="{{ translate('messages.How this zone prices a delivery: by distance travelled, by a flat amount, or by the area or ZIP code the order is going to.') }}"><i
                        class="tio-info"></i></span>
            </label>

            <div class="rule-method-grid">
                @foreach ($pricingMethods as $value => $card)
                    <label class="rule-method {{ $selectedMethod === $value ? 'is-selected' : '' }}">
                        <span class="rule-method__head">
                            <input type="radio" name="pricing_method" value="{{ $value }}"
                                class="form-check-input rule-method-input" {{ $selectedMethod === $value ? 'checked' : '' }}>
                            <span class="rule-method__title">{{ $card['title'] }}</span>
                        </span>
                        <span class="rule-method__hint">{{ $card['hint'] }}</span>
                    </label>
                @endforeach
            </div>

            {{-- The bulb is markup, so the script fills the span, not this. --}}
            <div class="rule-hint">
                <img src="{{ asset('public/assets/admin/img/svg/bulb.svg') }}" class="svg" alt="">
                <span id="method-hint"></span>
            </div>
        </div>

        {{-- Area wise --}}
        <div class="surge-section rule-pane" data-pane="{{ \App\Models\DeliveryRule::METHOD_AREA }}">
            <div id="area-charge-wrapper">
                @include('admin-views.delivery-rule.partials._charge-table', [
                    'rows' => $areas,
                    'labelKey' => 'name',
                    'charges' => $areaCharges,
                    'fieldName' => 'area_charges',
                    'heading' => translate('Area name'),
                    'title' => translate('Area wise charge setup'),
                    'description' => translate('messages.Configure fixed delivery charges for each service area within a zone.'),
                    'hintText' => translate('messages.All areas under this zone will be listed automatically. Set the delivery charge for each area.'),
                    'hintLinkText' => translate('Setup area'),
                    'emptyLink' => route('admin.business-settings.zone.area.list'),
                    'emptyText' => translate('No areas have been created for this zone. To set up area wise charges, first create an area for this zone from'),
                    'emptyLinkText' => translate('Area setup'),
                ])
            </div>
        </div>

        {{-- Zip code wise --}}
        <div class="surge-section rule-pane d-none" data-pane="{{ \App\Models\DeliveryRule::METHOD_ZIP }}">
            <div id="zip-charge-wrapper">
                @include('admin-views.delivery-rule.partials._charge-table', [
                    'rows' => $zipCodes,
                    'labelKey' => 'zip_code',
                    'charges' => $zipCharges,
                    'fieldName' => 'zip_charges',
                    'heading' => translate('Zip code'),
                    'title' => translate('Zip code wise charge setup'),
                    'description' => translate('messages.Configure fixed delivery charges for each ZIP code within a zone.'),
                    'hintText' => translate('messages.All ZIP codes under this zone will be listed automatically. Set the delivery charge for each code.'),
                    'hintLinkText' => translate('Setup zip code'),
                    'emptyLink' => route('admin.business-settings.zone.zip-code.list'),
                    'emptyText' => translate('No ZIP codes have been created for this zone. To set up ZIP code wise charges, first create a ZIP code for this zone from'),
                    'emptyLinkText' => translate('Zip code setup'),
                ])
            </div>
        </div>

        {{-- Distance wise --}}
        <div class="surge-section rule-pane d-none" data-pane="{{ \App\Models\DeliveryRule::METHOD_DISTANCE }}">
            <h5 class="surge-section__title">{{ translate('Distance wise charge setup') }}</h5>
            <p class="surge-section__subtitle">
                {{ translate('messages.Charges scale with the delivery distance, bounded by the maximum.') }}</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="input-label" for="per_km_charge">
                        {{ translate('Per unit delivery charge') }} ({{ $currencySymbol }}/{{ $distanceUnitLabel }})
                        <span class="text-danger">*</span>
                        <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.The rate charged for each unit of distance travelled. The base charge is this rate multiplied by the delivery distance.') }}"><i
                                class="tio-info"></i></span>
                    </label>
                    <input type="number" step="0.01" min="0" name="per_km_charge" id="per_km_charge"
                        class="form-control h-45" placeholder="{{ translate('Ex') }}: 5"
                        value="{{ old('per_km_charge', $rule->per_km_charge ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="input-label" for="maximum_delivery_charge">
                        {{ translate('Maximum delivery charge') }}({{ $currencySymbol }})
                        {{-- Optional, and the star that used to sit here said otherwise. The
                             request rule is `nullable`, the column is nullable, and
                             DeliveryRuleService::distanceCharge() treats a missing OR zero
                             maximum as no cap at all. --}}
                        <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.The ceiling for the distance charge. Leave it blank for no upper limit.') }}"><i
                                class="tio-info"></i></span>
                    </label>
                    <input type="number" step="0.01" min="0" name="maximum_delivery_charge"
                        id="maximum_delivery_charge" class="form-control h-45"
                        placeholder="{{ translate('Ex') }}: 5"
                        value="{{ old('maximum_delivery_charge', $rule->maximum_delivery_charge ?? '') }}">
                </div>
            </div>
        </div>

        {{-- Fixed amount --}}
        <div class="surge-section rule-pane d-none" data-pane="{{ \App\Models\DeliveryRule::METHOD_FIXED }}">
            <div class="row g-3 align-items-center">
                <div class="col-md-5">
                    <h5 class="surge-section__title">{{ translate('Fixed charge setup') }}</h5>
                    {{-- The old subtitle read "for service areas", which describes Area Wise
                         rather than this method — a fixed charge ignores the area entirely. --}}
                    <p class="surge-section__subtitle mb-0">
                        {{ translate('messages.One delivery charge for every order under this rule.') }}</p>
                    {{-- Area Wise and Zip Code Wise each carry a note explaining how their
                         charges are applied; this method had none, so the two things that make it
                         different — distance is ignored, and the parcel tiers are still added on
                         top — were nowhere on the screen. --}}
                    <div class="rule-hint mt-2">
                        <img src="{{ asset('public/assets/admin/img/svg/bulb.svg') }}" class="svg" alt="">
                        <span>{{ translate('messages.This charge applies to every order in the selected zone whatever the distance. Weight and dimension charges, when configured, are added on top of it.') }}</span>
                    </div>
                </div>
                <div class="col-md-7">
                    <label class="input-label" for="fixed_charge">
                        {{ translate('Delivery charge') }} ({{ $currencySymbol }})
                        <span class="text-danger">*</span>
                        <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.The delivery charge for every order under this rule, whatever the distance.') }}"><i
                                class="tio-info"></i></span>
                    </label>
                    <input type="number" step="0.01" min="0" name="fixed_charge" id="fixed_charge"
                        class="form-control h-45" placeholder="{{ translate('Ex') }}: 5"
                        value="{{ old('fixed_charge', $rule->fixed_charge ?? '') }}">
                </div>
            </div>
        </div>
        </div>{{-- /step 1 --}}

        {{-- Step 2 — Weight Rules. Rendered always so the inputs exist for the browser to post,
             hidden until the wizard is active AND this step is the current one. --}}
        <div class="rule-step d-none" data-step="2">
            @include('admin-views.delivery-rule.partials._parcel-step', [
                'fieldName' => 'weight',
                'enabled' => $weightChargeStatus,
                'rows' => $weightBands,
                'labels' => $weightBandLabels,
                'charges' => $weightCharges,
                'setupTitle' => translate('Weight rules setup'),
                'setupSubtitle' => translate('messages.Enable weight-based pricing for deliveries.'),
                'setupHint' => translate('messages.When enabled, delivery charges are calculated based on the selected package weight. Apply additional charges based on the configured weight ranges.'),
                'tableTitle' => translate('Weight rules'),
                'tableSubtitle' => translate('messages.Configure additional delivery charges based on the total weight of the order.'),
                'tableHint' => translate('Weight charge is an additional delivery fee. It will be added to the base delivery charge after the distance or fixed charge is calculated.'),
                'listTitle' => translate('Weight rule setup'),
                'listSubtitle' => translate('messages.Add weight ranges and define the additional delivery charge for each range.'),
                'listNote' => translate('messages.Ranges are matched top-to-bottom. Use the last row for orders exceeding the maximum defined weight.'),
                'heading' => $weightRangeHeading,
                'emptyText' => translate('messages.No weight ranges have been created yet. To charge by weight, first create them from'),
                'emptyLink' => $weightSetupRoute,
                'emptyLinkText' => translate('Weight setup'),
            ])
        </div>

        {{-- Step 3 — Dimension Rules. --}}
        <div class="rule-step d-none" data-step="3">
            @include('admin-views.delivery-rule.partials._parcel-step', [
                'fieldName' => 'dimension',
                'enabled' => $dimensionChargeStatus,
                'rows' => $dimensionSizes,
                'labels' => $dimensionSizeLabels,
                'charges' => $dimensionCharges,
                'setupTitle' => translate('Dimension rules setup'),
                'setupSubtitle' => translate('Configure the additional delivery charge for each package dimension. These charges are added to the delivery charge.'),
                'setupHint' => translate('When enabled, delivery charges are calculated based on package dimensions. Set dimension delivery charge to automatically apply the appropriate additional delivery charge.'),
                'tableTitle' => translate('Dimension rules'),
                'tableSubtitle' => translate('Configure the additional delivery charge for each package dimension. These charges are added to the base delivery charge.'),
                'tableHint' => translate('The dimension charge is an additional delivery fee. It will be added to the base delivery charge after the distance or fixed delivery charge is calculated.'),
                'listTitle' => translate('Dimension rule setup'),
                'listSubtitle' => translate('Set up dimension wise delivery charge'),
                'listNote' => $dimensionRuleNote,
                'heading' => translate('Dimension name'),
                'emptyText' => translate('messages.No dimensions have been created yet. To charge by package size, first create them from'),
                'emptyLink' => $dimensionSetupRoute,
                'emptyLinkText' => translate('Dimension setup'),
            ])
        </div>
    </div>

    <div class="card-footer border-0">
        <div class="btn--container justify-content-end">
            {{-- Outside the wizard this is the only footer and it saves. Inside it, the script
                 swaps in Previous / Next, and on the last step the primary button becomes
                 Submit rather than Next. --}}
            <a href="{{ route('admin.business-settings.zone.delivery-rule.list') }}" id="rule-back-link"
                class="btn btn--reset min-w-120px">{{ translate('messages.Previous') }}</a>
            <button type="button" id="rule-step-prev"
                class="btn btn--reset min-w-120px d-none">{{ translate('messages.Previous') }}</button>
            <button type="button" id="rule-step-next" class="btn btn--primary min-w-120px d-none">
                {{ translate('messages.Next') }}</button>
            <button type="submit" id="rule-submit" class="btn btn--primary min-w-120px">
                <i class="tio-save"></i> <span id="rule-submit-label">{{ translate('Save information') }}</span></button>
        </div>
    </div>
</div>
