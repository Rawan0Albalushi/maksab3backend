@php
    // $area is null on create. Every prefill below tolerates that so one partial
    // can back both the add and the edit drawer.
    $translated = [];
    foreach ($area->translations ?? [] as $translation) {
        $translated[$translation->locale][$translation->key] = $translation->value;
    }
@endphp

<div class="custom-offcanvas-body p-20">
    {{-- Zone sits outside the language tabs: it is the same choice whichever
         language the admin is typing in. --}}
    <div class="bg--secondary rounded p-20 mb-20">
        <div class="form-group mb-0">
            <label class="input-label" for="zone_id">{{ translate('messages.Zone') }}
                <span class="text-danger">*</span>
            </label>
            <select name="zone_id" id="zone_id" class="form-control js-select2-custom"
                data-placeholder="{{ translate('Select zone') }}">
                <option value="">{{ translate('Select zone') }}</option>
                @foreach ($zones as $zone)
                    <option value="{{ $zone->id }}" {{ ($area->zone_id ?? null) == $zone->id ? 'selected' : '' }}>
                        {{ $zone->name }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="bg--secondary rounded p-20">
        @if ($language)
            <div class="js-nav-scroller hs-nav-scroller-horizontal">
                <ul class="nav nav-tabs mb-4 border-0">
                    <li class="nav-item">
                        <a class="nav-link lang_link active" href="#"
                            id="default-link">{{ translate('Default') }}</a>
                    </li>
                    @foreach ($language as $lang)
                        <li class="nav-item">
                            <a class="nav-link lang_link" href="#"
                                id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="lang_form" id="default-form">
                <div class="form-group">
                    <label class="input-label" for="name_default">
                        {{ translate('Area name') }} ({{ translate('messages.Default') }})
                        <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="name[]" id="name_default" class="form-control h--45px" maxlength="191"
                        placeholder="{{ translate('messages.Type your business area name') }}"
                        value="{{ $area?->getRawOriginal('name') }}">
                </div>
                <div class="form-group mb-0">
                    <label class="input-label" for="display_name_default">
                        {{ translate('messages.Display name') }} ({{ translate('messages.Default') }})
                        <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="display_name[]" id="display_name_default" class="form-control h--45px"
                        maxlength="191" placeholder="{{ translate('messages.Type area display name') }}"
                        value="{{ $area?->getRawOriginal('display_name') }}">
                </div>
                <input type="hidden" name="lang[]" value="default">
            </div>

            @foreach ($language as $lang)
                <div class="d-none lang_form" id="{{ $lang }}-form">
                    <div class="form-group">
                        <label class="input-label" for="name_{{ $lang }}">
                            {{ translate('Area name') }} ({{ strtoupper($lang) }})
                        </label>
                        <input type="text" name="name[]" id="name_{{ $lang }}" class="form-control h--45px"
                            maxlength="191" placeholder="{{ translate('messages.Type your business area name') }}"
                            value="{{ $translated[$lang]['name'] ?? '' }}">
                    </div>
                    <div class="form-group mb-0">
                        <label class="input-label" for="display_name_{{ $lang }}">
                            {{ translate('messages.Display name') }} ({{ strtoupper($lang) }})
                        </label>
                        <input type="text" name="display_name[]" id="display_name_{{ $lang }}"
                            class="form-control h--45px" maxlength="191"
                            placeholder="{{ translate('messages.Type area display name') }}"
                            value="{{ $translated[$lang]['display_name'] ?? '' }}">
                    </div>
                    <input type="hidden" name="lang[]" value="{{ $lang }}">
                </div>
            @endforeach
        @else
            <div class="form-group">
                <label class="input-label" for="name_default">{{ translate('Area name') }}
                    <span class="text-danger">*</span>
                </label>
                <input type="text" name="name[]" id="name_default" class="form-control h--45px" maxlength="191"
                    placeholder="{{ translate('messages.Type your business area name') }}"
                    value="{{ $area?->getRawOriginal('name') }}">
            </div>
            <div class="form-group mb-0">
                <label class="input-label" for="display_name_default">{{ translate('messages.Display name') }}
                    <span class="text-danger">*</span>
                </label>
                <input type="text" name="display_name[]" id="display_name_default" class="form-control h--45px"
                    maxlength="191" placeholder="{{ translate('messages.Type area display name') }}"
                    value="{{ $area?->getRawOriginal('display_name') }}">
            </div>
            <input type="hidden" name="lang[]" value="default">
        @endif
    </div>
</div>

<div class="custom-offcanvas-footer p-20 d-flex justify-content-end gap-2">
    <button type="reset" class="btn btn--reset min-w-120px">{{ translate('messages.Reset') }}</button>
    <button type="submit" class="btn btn--primary min-w-120px">{{ $submitLabel }}</button>
</div>
