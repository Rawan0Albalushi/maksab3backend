<div class="card">
    <div class="card-body">
        {{-- The tabs head the card rather than sitting inside the field, so the Vehicle Type input
             keeps the third of the row the design gives it. Only the type is translatable; the
             coverage band, the weight and the dimension links are the same in every language. --}}
        @if ($languages)
            <div class="js-nav-scroller hs-nav-scroller-horizontal">
                <ul class="nav nav-tabs mb-4 border-0">
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
        @endif

        <div class="row g-3">
            <div class="col-md-4">
                @if ($languages)
                    <div class="lang_form" id="default-form">
                        <div class="form-group mb-0">
                            <label class="input-label" for="type_default">
                                {{ translate('Vehicle type') }} ({{ translate('messages.Default') }})
                                <span class="text-danger">*</span>
                            </label>
                            {{-- The mock's placeholder reads "Type your business zone name" — a
                                 copy-paste slip from the zone screen. It names this field instead. --}}
                            <input type="text" name="type[]" id="type_default" class="form-control h--45px"
                                maxlength="191" placeholder="{{ translate('Ex: bike') }}"
                                value="{{ old('type.0', $vehicle?->getRawOriginal('type')) }}">
                        </div>
                        <input type="hidden" name="lang[]" value="default">
                    </div>

                    @foreach ($languages as $language)
                        <div class="d-none lang_form" id="{{ $language['code'] }}-form">
                            <div class="form-group mb-0">
                                <label class="input-label" for="type_{{ $language['code'] }}">
                                    {{ translate('Vehicle type') }} ({{ strtoupper($language['code']) }})
                                </label>
                                <input type="text" name="type[]" id="type_{{ $language['code'] }}"
                                    class="form-control h--45px" maxlength="191"
                                    placeholder="{{ translate('Ex: bike') }}"
                                    value="{{ $translated[$language['code']]['type'] ?? '' }}">
                            </div>
                            <input type="hidden" name="lang[]" value="{{ $language['code'] }}">
                        </div>
                    @endforeach
                @else
                    <div class="form-group mb-0">
                        <label class="input-label" for="type_default">
                            {{ translate('Vehicle type') }} <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="type[]" id="type_default" class="form-control h--45px"
                            maxlength="191" placeholder="{{ translate('Ex: bike') }}"
                            value="{{ old('type.0', $vehicle?->getRawOriginal('type')) }}">
                    </div>
                    <input type="hidden" name="lang[]" value="default">
                @endif
            </div>

            <div class="col-md-4">
                <div class="form-group mb-0">
                    <label class="input-label" for="starting_coverage_area">
                        {{ translate('messages.Minimum Coverage Area') }}({{ $distanceUnitLabel }})
                        <span class="text-danger">*</span>
                        <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.The shortest delivery distance this vehicle category is used for. Bands may overlap each other.') }}"><i
                                class="tio-info"></i></span>
                    </label>
                    <input type="number" step="0.01" min="0" name="starting_coverage_area"
                        id="starting_coverage_area" class="form-control h--45px"
                        placeholder="{{ translate('Ex') }}: 0"
                        value="{{ old('starting_coverage_area', $vehicle?->starting_coverage_area) }}">
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-group mb-0">
                    <label class="input-label" for="maximum_coverage_area">
                        {{ translate('messages.Maximum Coverage Area') }}({{ $distanceUnitLabel }})
                        <span class="text-danger">*</span>
                        <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.The farthest delivery distance this vehicle category is used for. Must be greater than the minimum.') }}"><i
                                class="tio-info"></i></span>
                    </label>
                    <input type="number" step="0.01" min="0" name="maximum_coverage_area"
                        id="maximum_coverage_area" class="form-control h--45px"
                        placeholder="{{ translate('Ex') }}: 10"
                        value="{{ old('maximum_coverage_area', $vehicle?->maximum_coverage_area) }}">
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-group mb-0">
                    <label class="input-label" for="max_weight">
                        {{ translate('messages.Max. Weight') }}
                        <span class="text-danger">*</span>
                        <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.The heaviest load a vehicle in this category is trusted with.') }}"><i
                                class="tio-info"></i></span>
                    </label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" name="max_weight" id="max_weight"
                            class="form-control h--45px" placeholder="{{ translate('Ex') }}: 50"
                            value="{{ old('max_weight', $vehicle?->max_weight) }}">
                        <div class="input-group-append">
                            <span class="input-group-text h--45px">{{ $weightUnitLabel }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-group mb-0">
                    <label class="input-label" for="dimension_ids">
                        {{ translate('Dimension connect') }}
                        @if (count($dimensions))
                            <span class="text-danger">*</span>
                        @endif
                        <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.The package size classes a vehicle in this category can carry.') }}"><i
                                class="tio-info"></i></span>
                    </label>
                    {{-- The theme's HSSelect2.init parses its options out of data-hs-select2-options
                         and ignores data-placeholder, so the placeholder goes there. --}}
                    <select name="dimension_ids[]" id="dimension_ids" class="form-control js-select2-custom" multiple
                        data-hs-select2-options='@json(["placeholder" => translate('Choose dimension'), "closeOnSelect" => false], JSON_HEX_APOS)'>
                        @foreach ($dimensions as $dimension)
                            <option value="{{ $dimension->id }}"
                                {{ in_array($dimension->id, $selectedDimensionIds) ? 'selected' : '' }}>
                                {{ $dimension->name }}</option>
                        @endforeach
                    </select>
                    {{-- Nothing to connect yet. The field is left un-required in that case rather
                         than blocking the whole screen — see VehicleCategoryAddRequest. --}}
                    @if (!count($dimensions))
                        <div class="rule-hint mt-2">
                            <img src="{{ asset('public/assets/admin/img/svg/bulb.svg') }}" class="svg" alt="">
                            <span>{{ translate('messages.No dimension has been set up yet.') }}
                                <a href="{{ route('admin.business-settings.zone.dimension.list') }}">{{ translate('Add one from dimension setup.') }}</a></span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="btn--container justify-content-end mt-4">
            <a href="{{ route('admin.business-settings.zone.vehicle-category.list') }}"
                class="btn btn--reset min-w-120px">{{ translate('messages.Cancel') }}</a>
            <button type="submit" class="btn btn--primary min-w-120px">{{ translate('messages.Submit') }}</button>
        </div>
    </div>
</div>
