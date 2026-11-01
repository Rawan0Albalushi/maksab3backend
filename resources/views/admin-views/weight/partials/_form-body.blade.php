{{-- $weight is null on create; every prefill below tolerates that so one partial backs both
     drawers. $languages and $translated arrive built from the controller — see
     WeightController::drawerData(). --}}
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
                        {{ translate('Weight name') }} ({{ translate('messages.Default') }})
                        <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="name[]" id="name_default" class="form-control h--45px" maxlength="191"
                        placeholder="{{ translate('messages.Type the weight name') }}"
                        value="{{ $weight?->getRawOriginal('name') }}">
                </div>
                <input type="hidden" name="lang[]" value="default">
            </div>

            @foreach ($languages as $language)
                <div class="d-none lang_form" id="{{ $language['code'] }}-form">
                    <div class="form-group mb-0">
                        <label class="input-label" for="name_{{ $language['code'] }}">
                            {{ translate('Weight name') }} ({{ strtoupper($language['code']) }})
                        </label>
                        <input type="text" name="name[]" id="name_{{ $language['code'] }}"
                            class="form-control h--45px" maxlength="191"
                            placeholder="{{ translate('messages.Type the weight name') }}"
                            value="{{ $translated[$language['code']]['name'] ?? '' }}">
                    </div>
                    <input type="hidden" name="lang[]" value="{{ $language['code'] }}">
                </div>
            @endforeach
        @else
            <div class="form-group mb-0">
                <label class="input-label" for="name_default">{{ translate('Weight name') }}
                    <span class="text-danger">*</span>
                </label>
                <input type="text" name="name[]" id="name_default" class="form-control h--45px" maxlength="191"
                    placeholder="{{ translate('messages.Type the weight name') }}"
                    value="{{ $weight?->getRawOriginal('name') }}">
            </div>
            <input type="hidden" name="lang[]" value="default">
        @endif
    </div>

    {{-- The range sits BELOW the name and outside the language tabs: the numbers are the same
         whichever language the admin is typing in. The unit rides in the input as a suffix rather
         than in the label, so the two fields read as one range. Kilograms always — a SETUP value,
         never converted. --}}
    <div class="bg--secondary rounded p-20">
        <div class="row g-3">
            <div class="col-6">
                <div class="form-group mb-0">
                    <label class="input-label" for="from_weight">
                        {{ translate('messages.From') }}
                        <span class="text-danger">*</span>
                        <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.The lightest parcel this class covers. The range starts here and includes this weight.') }}"><i
                                class="tio-info"></i></span>
                    </label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" name="from_weight" id="from_weight"
                            class="form-control h--45px" placeholder="{{ translate('Ex') }}: 50"
                            value="{{ $weight?->from_weight }}">
                        <div class="input-group-append">
                            <span class="input-group-text h--45px">{{ $weightUnitLabel }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="form-group mb-0">
                    <label class="input-label" for="to_weight">
                        {{ translate('messages.To') }}
                        <span class="text-danger">*</span>
                        <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.The heaviest parcel this class covers. A parcel above it falls into the next class.') }}"><i
                                class="tio-info"></i></span>
                    </label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" name="to_weight" id="to_weight"
                            class="form-control h--45px" placeholder="{{ translate('Ex') }}: 50"
                            value="{{ $weight?->to_weight }}">
                        <div class="input-group-append">
                            <span class="input-group-text h--45px">{{ $weightUnitLabel }}</span>
                        </div>
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
