{{-- Measurement Units — part of the Business Info form, saved by the page's own submit.

     Switching a unit changes only the unit stored numbers are READ in. Nothing is converted and
     nothing is rewritten, so there is no confirmation step and no preview: an admin who typed 40
     still has 40, now meaning 40 miles instead of 40 km. That is the behaviour the QA cases
     (TC_01, TC_02) describe and the behaviour the rest of the platform already assumes. --}}
<div class="shadow-sm p-xxl-20 p-xl-3 p-2 bg-white mb-20" id="measurement_units_section">
    <div class="mb-20">
        <h4 class="mb-1">
            {{ translate('Measurement units') }}
        </h4>
        <p class="mb-0 fs-12">
            {{ translate('The units every rate, coverage area, weight class and dimension class is read in. Changing one changes what those stored numbers mean.') }}
        </p>
    </div>
    <div class="bg-light2 rounded p-xxl-20 p-3">
        <div class="row g-3">
            <div class="col-sm-6 col-md-4 col-xl-4">
                <div class="form-group mb-0">
                    <label class="input-label" for="distance_unit">{{ translate('Distance unit') }}
                        <span class="text-danger">*</span>
                    </label>
                    <select name="distance_unit" id="distance_unit"
                        class="form-control js-select2-custom h--45px">
                        <option value="km" {{ $distanceUnit === 'km' ? 'selected' : '' }}>
                            {{ translate('messages.Kilometre') }} (km)</option>
                        <option value="mi" {{ $distanceUnit === 'mi' ? 'selected' : '' }}>
                            {{ translate('messages.Mile') }} (mi)</option>
                    </select>
                </div>
            </div>
            <div class="col-sm-6 col-md-4 col-xl-4">
                <div class="form-group mb-0">
                    <label class="input-label" for="weight_unit">{{ translate('Weight unit') }}
                        <span class="text-danger">*</span>
                    </label>
                    <select name="weight_unit" id="weight_unit"
                        class="form-control js-select2-custom h--45px">
                        <option value="kg" {{ $weightUnit === 'kg' ? 'selected' : '' }}>
                            {{ translate('messages.Kilogram') }} (kg)</option>
                        <option value="lb" {{ $weightUnit === 'lb' ? 'selected' : '' }}>
                            {{ translate('messages.Pound') }} (lb)</option>
                    </select>
                </div>
            </div>
            <div class="col-sm-6 col-md-4 col-xl-4">
                <div class="form-group mb-0">
                    <label class="input-label" for="dimension_unit">{{ translate('Dimension unit') }}
                        <span class="text-danger">*</span>
                    </label>
                    <select name="dimension_unit" id="dimension_unit"
                        class="form-control js-select2-custom h--45px">
                        <option value="cm" {{ $dimensionUnit === 'cm' ? 'selected' : '' }}>
                            {{ translate('messages.Centimetre') }} (cm)</option>
                        <option value="in" {{ $dimensionUnit === 'in' ? 'selected' : '' }}>
                            {{ translate('messages.Inch') }} (in)</option>
                    </select>
                </div>
            </div>
            <div class="col-12">
                <div class="rule-hint mb-0">
                    <img src="{{ asset('public/assets/admin/img/svg/bulb.svg') }}" class="svg" alt="">
                    <span>{{ translate('Rates, coverage areas, weight classes and dimension classes are stored exactly as you typed them. Changing a unit changes what those numbers mean, not the numbers themselves.') }}</span>
                </div>
            </div>
        </div>


    </div>
</div>
