@php
    // $zipCode is null on create. Both prefills tolerate that so one partial can
    // back the add and the edit drawer.
@endphp

<div class="custom-offcanvas-body p-20">
    {{-- A zip code carries no translatable text, so there are no language tabs
         here — unlike the Area drawer. --}}
    <div class="bg--secondary rounded p-20">
        <div class="form-group">
            <label class="input-label" for="zone_id">{{ translate('messages.Zone') }}
                <span class="text-danger">*</span>
            </label>
            <select name="zone_id" id="zone_id" class="form-control js-select2-custom"
                data-placeholder="{{ translate('messages.Select zone') }}">
                <option value="">{{ translate('messages.Select zone') }}</option>
                @foreach ($zones as $zone)
                    <option value="{{ $zone->id }}" {{ ($zipCode->zone_id ?? null) == $zone->id ? 'selected' : '' }}>
                        {{ $zone->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group mb-0">
            <label class="input-label" for="zip_code">{{ translate('Zip code') }}
                <span class="text-danger">*</span>
            </label>
            <input type="text" name="zip_code" id="zip_code" class="form-control h--45px" maxlength="20"
                placeholder="{{ translate('Type your zip code') }}" value="{{ $zipCode->zip_code ?? '' }}">
        </div>
    </div>
</div>

<div class="custom-offcanvas-footer p-20 d-flex justify-content-end gap-2">
    <button type="reset" class="btn btn--reset min-w-120px">{{ translate('messages.Reset') }}</button>
    <button type="submit" class="btn btn--primary min-w-120px">{{ $submitLabel }}</button>
</div>
