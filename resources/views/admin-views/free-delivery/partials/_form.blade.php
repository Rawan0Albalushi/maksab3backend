<div class="card mb-20">
    <div class="card-body">
        <h4 class="surge-section__title">{{ translate('Zone & module setup') }}</h4>
        <p class="surge-section__subtitle">
            {{ translate('messages.Choose where this free delivery rule will be applied.') }}</p>

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
                            data-original-title="{{ translate('messages.The modules this setup frees in the chosen zone. A zone and module pair can hold only one free delivery setup.') }}"><i
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
                    {{-- Only the modules still free in this zone are listed; the ones already
                         covered are hidden, not disabled. F1 remains the server-side guard. --}}
                    <div id="modules-all-configured" class="rule-hint mt-2 {{ count($modules) ? 'd-none' : '' }}">
                        <img src="{{ asset('public/assets/admin/img/svg/bulb.svg') }}" class="svg" alt="">
                        <span>{{ $modulesEmptyMessage }}</span>
                    </div>
                </div>
            </div>

            <div class="rule-hint mt-3">
                <img src="{{ asset('public/assets/admin/img/svg/bulb.svg') }}" class="svg" alt="">
                <span>{{ translate('Only one free delivery configuration is allowed for each zone and module combination.') }}</span>
            </div>

            {{-- Informational only — Parcel isn't filtered out of the picker above and nothing
                 here blocks saving it, since a parcel order has no vendor store and the checkout
                 free-delivery check is store-scoped. This note exists so an admin who selects
                 Parcel understands up front that the setup will have no effect there, rather than
                 discovering it silently at checkout. --}}
            <div class="fs-12 text-dark px-3 py-2 rounded bg-warning-10 mt-3 {{ $modules->whereIn('id', $selectedModuleIds)->contains('module_type', 'parcel') ? '' : 'd-none' }}"
                id="parcel-fd-warning">
                <div class="d-flex align-items-center gap-2 mb-0">
                    <span class="text-warning fs-14">
                        <i class="tio-info"></i>
                    </span>
                    <span class="color-656566">
                        {{ translate('messages.Free_Delivery_is_not_currently_applied_at_checkout_for_the_Parcel_module._Any_setup_created_here_for_Parcel_will_have_no_effect_on_parcel_orders.') }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-20">
    <div class="card-body">
        <h4 class="surge-section__title">{{ translate('Free delivery configuration') }}</h4>
        {{-- The mock's subtitle reads "Configure when the surge pricing should become active" —
             a copy-paste slip from the surge screen. It describes free delivery here. --}}
        <p class="surge-section__subtitle">
            {{ translate('messages.Configure when the free delivery should become active.') }}</p>

        <div class="surge-section">
            <label class="input-label">
                {{ translate('Free delivery type') }} <span class="text-danger">*</span>
                <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                    data-original-title="{{ translate('messages.Whether delivery is free on every order in this zone, or only once the order reaches the minimum amount.') }}"><i
                        class="tio-info"></i></span>
            </label>

            <div class="rule-method-grid rule-method-grid--two">
                @foreach ($typeCards as $value => $card)
                    <label class="rule-method {{ $selectedType === $value ? 'is-selected' : '' }}">
                        <span class="rule-method__head">
                            <input type="radio" name="type" value="{{ $value }}"
                                class="form-check-input free-delivery-type-input"
                                {{ $selectedType === $value ? 'checked' : '' }}>
                            <span class="rule-method__title">{{ $card['title'] }}</span>
                        </span>
                        <span class="rule-method__hint">{{ $card['hint'] }}</span>
                    </label>
                @endforeach
            </div>

            {{-- The hint is type-sensitive: "all store" states who bears the cost (F3), while
                 "specific criteria" explains the amount field below it. --}}
            <div class="rule-hint mt-3">
                <img src="{{ asset('public/assets/admin/img/svg/bulb.svg') }}" class="svg" alt="">
                <span id="free-delivery-type-hint"></span>
            </div>

            <div id="minimum-amount-wrapper"
                class="mt-3 {{ $selectedType === \App\Models\FreeDelivery::TYPE_CRITERIA ? '' : 'd-none' }}">
                <label class="input-label" for="minimum_order_amount">
                    {{ translate('Minimum order amount') }} ({{ $currencySymbol }})
                    <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                        data-original-title="{{ translate('messages.What the order must reach, after discounts, before delivery becomes free.') }}"><i
                            class="tio-info"></i></span>
                </label>
                <input type="number" step="0.01" min="0" name="minimum_order_amount" id="minimum_order_amount"
                    class="form-control h-45" placeholder="{{ translate('Ex') }}: 5"
                    value="{{ $minimumOrderAmount }}"
                    {{ $selectedType === \App\Models\FreeDelivery::TYPE_CRITERIA ? 'required' : '' }}>
                {{-- A blank threshold here used to make Specific Criteria behave exactly like Free
                     Delivery for all Store, with nothing on screen explaining why — the request
                     now requires this field for a Specific Criteria setup, so the field itself
                     says what it needs instead. FreeDelivery::frees() still reads a legacy row's
                     NULL as "always free": that model behaviour is unchanged, only the FORM no
                     longer lets a new save leave it blank. --}}
                <div class="rule-hint mt-2">
                    <img src="{{ asset('public/assets/admin/img/svg/bulb.svg') }}" class="svg" alt="">
                    <span>{{ translate('Required for specific criteria — this is what tells it apart from free delivery for all store.') }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card-footer border-0">
        <div class="btn--container justify-content-end">
            <button type="reset" class="btn btn--reset min-w-120px">{{ translate('messages.Reset') }}</button>
            <button type="submit" class="btn btn--primary min-w-120px">
                <i class="tio-save"></i> {{ translate('Save information') }}</button>
        </div>
    </div>
</div>
