{{-- Card 1 — Basic Information.

     The mock's subtitle reads "Configure the basic details of the surge pricing rule", which is
     pasted in from the surge screen. It names this screen instead, the same call made on the free
     delivery mocks in S6. --}}
<div class="card mb-20">
    <div class="card-body">
        <h4 class="surge-section__title">{{ translate('Basic information') }}</h4>
        <p class="surge-section__hint">
            {{ translate('messages.Configure_the_basic_details_of_the_additional_delivery_charge.') }}</p>
    </div>

    <div class="card-body border-top">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="form-group mb-0">
                    <label class="input-label" for="zone_id">{{ translate('Select zone') }}
                        <span class="text-danger">*</span>
                    </label>
                    <select name="zone_id" id="zone_id" class="form-control js-select2-custom">
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}"
                                {{ (int) $selectedZoneId === (int) $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group mb-0">
                    <label class="input-label" for="module_ids">{{ translate('Choose module') }}
                        <span class="text-danger">*</span>
                        <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.Only_one_Additional_Delivery_Charge_can_be_created_for_each_Zone_and_Module_combination.') }}">
                            <i class="tio-info text-muted"></i>
                        </span>
                    </label>
                    <select name="module_ids[]" id="module_ids" class="form-control js-select2-custom" multiple
                        data-placeholder="{{ translate('Choose module') }}">
                        @foreach ($modules as $module)
                            <option value="{{ $module->id }}" data-module-type="{{ $module->module_type }}"
                                {{ in_array($module->id, $selectedModuleIds, true) ? 'selected' : '' }}>
                                {{ $module->module_name }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted module-empty-message {{ count($modules) ? 'd-none' : '' }}">
                        {{ $modulesEmptyMessage }}</small>
                </div>
            </div>
        </div>

        {{-- Informational only — Parcel is not filtered out of the picker above and nothing
             here blocks saving it, since Additional Charge shares its module-picker capability
             with Free Delivery rather than gating on this feature specifically. This note exists
             so an admin who selects Parcel understands up front that Express/Slightly Delay have
             no effect on it, rather than discovering it silently at checkout. --}}
        <div class="fs-12 text-dark px-3 py-2 rounded bg-warning-10 mt-20 {{ $modules->whereIn('id', $selectedModuleIds)->contains('module_type', 'parcel') ? '' : 'd-none' }}"
            id="parcel-adc-warning">
            <div class="d-flex align-items-center gap-2 mb-0">
                <span class="text-warning fs-14">
                    <i class="tio-info"></i>
                </span>
                <span class="color-656566">
                    {{ translate('messages.Additional_Charge_is_not_supported_for_the_Parcel_module._Express_and_Slightly_Delay_delivery_options_do_not_apply_to_parcel_orders,_so_any_settings_configured_here_will_be_ignored_for_Parcel.') }}
                </span>
            </div>
        </div>
    </div>
</div>

{{-- Card 2 — Additional Charge Setup.

     The mock's subtitle says "for this delivery rule", pasted in from the delivery rule screen. --}}
<div class="card mb-20">
    <div class="card-body">
        <h4 class="surge-section__title">{{ translate('Additional charge setup') }}</h4>
        <p class="surge-section__hint">
            {{ translate('messages.Configure_additional_delivery_charges_for_this_zone_and_module.') }}</p>
    </div>

    <div class="card-body border-top">
        <div class="adc-panel">
            {{-- Express: pay more, wait less. --}}
            <div class="adc-row">
                <div class="adc-row__label">
                    <h5 class="adc-row__title">{{ translate('Express delivery') }}</h5>
                    <p class="adc-row__hint">
                        {{ translate('messages.Deliver_faster_by_reducing_delivery_time_with_an_additional_charge.') }}</p>
                </div>
                <div class="adc-row__fields">
                    <div class="form-group mb-0">
                        <label class="input-label" for="express_extra_charge">
                            {{ translate('Add extra charge') }} ({{ $currencySymbol }})
                            <span class="text-danger">*</span>
                            <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                                data-original-title="{{ translate('messages.What_the_customer_pays_on_top_of_the_standard_delivery_charge_to_have_the_order_delivered_sooner.') }}">
                                <i class="tio-info text-muted"></i>
                            </span>
                        </label>
                        <input type="number" step="0.01" min="0.01" name="express_extra_charge"
                            id="express_extra_charge" class="form-control h-45"
                            placeholder="{{ translate('messages.Ex') }} : 5" value="{{ $expressExtraCharge }}">
                    </div>
                    <div class="form-group mb-0">
                        <label class="input-label" for="express_reduce_delivery_time">
                            {{ translate('Reduce delivery time') }}
                            <span class="text-danger">*</span>
                            <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                                data-original-title="{{ translate('messages.How_much_sooner_the_order_arrives._It_cannot_be_longer_than_the_minimum_delivery_time_of_this_zone_and_module.') }}">
                                <i class="tio-info text-muted"></i>
                            </span>
                        </label>
                        <div class="input-group adc-input-group">
                            <input type="number" step="1" min="1" name="express_reduce_delivery_time"
                                id="express_reduce_delivery_time" class="form-control h-45"
                                placeholder="{{ translate('messages.Ex') }}: 50" value="{{ $expressReduceTime }}">
                            <div class="input-group-append">
                                <select name="express_reduce_delivery_time_unit" class="form-control h-45 adc-unit">
                                    <option value="min" {{ $expressReduceTimeUnit === 'min' ? 'selected' : '' }}>
                                        {{ translate('messages.Min') }}</option>
                                    <option value="hour" {{ $expressReduceTimeUnit === 'hour' ? 'selected' : '' }}>
                                        {{ translate('messages.Hour') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Slightly delay: wait more, pay less. --}}
            <div class="adc-row">
                <div class="adc-row__label">
                    <h5 class="adc-row__title">{{ translate('Slightly delay delivery') }}</h5>
                    <p class="adc-row__hint">
                        {{ translate('messages.Deliver_a_bit_later_and_offer_a_reduced_delivery_charge.') }}</p>
                </div>
                <div class="adc-row__fields">
                    <div class="form-group mb-0">
                        <label class="input-label" for="delay_reduce_charge">
                            {{ translate('Reduce charge') }} ({{ $currencySymbol }})
                            <span class="text-danger">*</span>
                            <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                                data-original-title="{{ translate('messages.Taken_off_the_standard_delivery_charge._It_cannot_be_more_than_the_minimum_delivery_charge_of_this_zone_and_module.') }}">
                                <i class="tio-info text-muted"></i>
                            </span>
                        </label>
                        <input type="number" step="0.01" min="0.01" name="delay_reduce_charge" id="delay_reduce_charge"
                            class="form-control h-45" placeholder="{{ translate('messages.Ex') }} : 5"
                            value="{{ $delayReduceCharge }}">
                    </div>
                    <div class="form-group mb-0">
                        <label class="input-label" for="delay_add_delivery_time">
                            {{ translate('Add extra delivery time') }}
                            <span class="text-danger">*</span>
                            <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                                data-original-title="{{ translate('messages.How_much_later_the_order_arrives_in_exchange_for_the_reduced_charge.') }}">
                                <i class="tio-info text-muted"></i>
                            </span>
                        </label>
                        <div class="input-group adc-input-group">
                            <input type="number" step="1" min="1" name="delay_add_delivery_time"
                                id="delay_add_delivery_time" class="form-control h-45"
                                placeholder="{{ translate('messages.Ex') }}: 50" value="{{ $delayAddTime }}">
                            <div class="input-group-append">
                                <select name="delay_add_delivery_time_unit" class="form-control h-45 adc-unit">
                                    <option value="min" {{ $delayAddTimeUnit === 'min' ? 'selected' : '' }}>
                                        {{ translate('messages.Min') }}</option>
                                    <option value="hour" {{ $delayAddTimeUnit === 'hour' ? 'selected' : '' }}>
                                        {{ translate('messages.Hour') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- The vehicle filter. Optional, and empty means every category may take the order.
                 The checkboxes are the platform's own vehicle rows, not the mock's list. --}}
            <div class="adc-row adc-row--last">
                <div class="adc-row__label">
                    <h5 class="adc-row__title">
                        {{ translate('messages.Vehicle_Category_for_Express_Delivery') }}
                        <span class="adc-row__optional">({{ translate('messages.Optional') }})</span>
                    </h5>
                    <p class="adc-row__hint">
                        {{ translate('messages.By_this,_when_customer_select_Express_Delivery_the_order_will_only_show_to_the_selected_vehicles_deliveryman.') }}
                    </p>
                </div>
                <div class="adc-row__fields adc-row__fields--vehicles">
                    @forelse ($vehicles as $vehicle)
                        <label class="form-check form--check adc-vehicle mb-0">
                            <input class="form-check-input" type="checkbox" name="vehicle_ids[]"
                                value="{{ $vehicle->id }}"
                                {{ in_array($vehicle->id, $selectedVehicleIds, true) ? 'checked' : '' }}>
                            <span class="form-check-label">{{ $vehicle->type }}</span>
                        </label>
                    @empty
                        <small class="text-muted">
                            {{ translate('messages.No_delivery_vehicle_category_has_been_added_yet.') }}</small>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
