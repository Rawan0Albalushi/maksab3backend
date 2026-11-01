{{-- $dimension is null on create; every prefill below tolerates that so one partial backs both
     drawers. $languages and $translated arrive built from the controller — see
     DimensionController::drawerData(). --}}
<div class="custom-offcanvas-body p-20">
    <div class="bg--secondary rounded p-20 mb-20">
        @if ($languages)
            <div class="js-nav-scroller hs-nav-scroller-horizontal">
                <ul class="nav nav-tabs mb-4 border-0">
                    <li class="nav-item">
                        <a class="nav-link lang_link active" href="#"
                            id="default-link">{{ translate('Default') }}</a>
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
                        {{ translate('Dimension name') }} ({{ translate('messages.Default') }})
                        <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="name[]" id="name_default" class="form-control h--45px" maxlength="191"
                        placeholder="{{ translate('messages.Type the dimension name') }}"
                        value="{{ $dimension?->getRawOriginal('name') }}">
                </div>
                <input type="hidden" name="lang[]" value="default">
            </div>

            @foreach ($languages as $language)
                <div class="d-none lang_form" id="{{ $language['code'] }}-form">
                    <div class="form-group mb-0">
                        <label class="input-label" for="name_{{ $language['code'] }}">
                            {{ translate('Dimension name') }} ({{ strtoupper($language['code']) }})
                        </label>
                        <input type="text" name="name[]" id="name_{{ $language['code'] }}"
                            class="form-control h--45px" maxlength="191"
                            placeholder="{{ translate('messages.Type the dimension name') }}"
                            value="{{ $translated[$language['code']]['name'] ?? '' }}">
                    </div>
                    <input type="hidden" name="lang[]" value="{{ $language['code'] }}">
                </div>
            @endforeach
        @else
            <div class="form-group mb-0">
                <label class="input-label" for="name_default">{{ translate('Dimension name') }}
                    <span class="text-danger">*</span>
                </label>
                <input type="text" name="name[]" id="name_default" class="form-control h--45px" maxlength="191"
                    placeholder="{{ translate('messages.Type the dimension name') }}"
                    value="{{ $dimension?->getRawOriginal('name') }}">
            </div>
            <input type="hidden" name="lang[]" value="default">
        @endif
    </div>
</div>

    {{-- The measurements sit BELOW the name and outside the language tabs: the numbers are the
         same whichever language the admin is typing in. Length takes a full row of its own and
         Height and Width share the next, matching the design. The unit rides in each input as a
         suffix rather than in the label. The unit follows `dimension_unit`; the numbers are SETUP
         values and are never converted, so a switch changes what they mean, not what they are. --}}
    <div class="bg--secondary rounded p-20">
        <div class="row g-3">
            <div class="col-12">
                <div class="form-group mb-0">
                    <label class="input-label" for="max_length">
                        {{ translate('messages.Max. Length') }}
                        <span class="text-danger">*</span>
                        <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.The longest parcel this class accepts. A parcel over it falls into the next class.') }}"><i
                                class="tio-info"></i></span>
                    </label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" name="max_length" id="max_length"
                            class="form-control h--45px" placeholder="{{ translate('Ex') }}: 50"
                            value="{{ $dimension?->max_length }}">
                        <div class="input-group-append">
                            <span class="input-group-text h--45px">{{ $dimensionUnitLabel }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="form-group mb-0">
                    <label class="input-label" for="max_height">
                        {{ translate('messages.Max. Height') }}
                        <span class="text-danger">*</span>
                        <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.The tallest parcel this class accepts. A parcel over it falls into the next class.') }}"><i
                                class="tio-info"></i></span>
                    </label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" name="max_height" id="max_height"
                            class="form-control h--45px" placeholder="{{ translate('Ex') }}: 50"
                            value="{{ $dimension?->max_height }}">
                        <div class="input-group-append">
                            <span class="input-group-text h--45px">{{ $dimensionUnitLabel }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="form-group mb-0">
                    <label class="input-label" for="max_width">
                        {{ translate('messages.Max. Width') }}
                        <span class="text-danger">*</span>
                        <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.The widest parcel this class accepts. A parcel over it falls into the next class.') }}"><i
                                class="tio-info"></i></span>
                    </label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" name="max_width" id="max_width"
                            class="form-control h--45px" placeholder="{{ translate('Ex') }}: 50"
                            value="{{ $dimension?->max_width }}">
                        <div class="input-group-append">
                            <span class="input-group-text h--45px">{{ $dimensionUnitLabel }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<div class="custom-offcanvas-footer p-20 d-flex justify-content-end gap-2">
    <button type="reset" class="btn btn--reset min-w-120px">{{ translate('messages.Reset') }}</button>
    <button type="submit" class="btn btn--primary min-w-120px">{{ $submitLabel }}</button>
</div>
