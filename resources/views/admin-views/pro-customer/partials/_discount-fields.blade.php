<div class="row g-3 align-items-end">
    <div class="col-xl-4 col-md-6">
        <div class="tps-field">
            <label class="tps-field__label" for="{{ $fieldId }}_percentage">
                {{ translate('messages.Discount (%)') }} <span class="tps-req">*</span>
                <span class="form-label-secondary" data-toggle="tooltip" data-placement="top"
                    data-title="{{ $pctTooltip }}"><i class="tio-info text-muted"></i></span>
            </label>
            <input type="number" id="{{ $fieldId }}_percentage" name="{{ $prefix }}[percentage]"
                class="form-control" min="{{ $minStep }}" step="{{ $minStep }}" max="100"
                placeholder="{{ translate('messages.Ex') . ': 5' }}"
                value="{{ $config['percentage'] ?? '' }}">
        </div>
    </div>

    <div class="col-xl-4 col-md-6">
        <div class="tps-field">
            <label class="tps-field__label" for="{{ $fieldId }}_max_amount">
                {{ translate('messages.Up To Discount Amount') }} ({{ $currencySymbol }}) <span class="tps-req">*</span>
                <span class="form-label-secondary" data-toggle="tooltip" data-placement="top"
                    data-title="{{ $maxTooltip }}"><i class="tio-info text-muted"></i></span>
            </label>
            <input type="number" id="{{ $fieldId }}_max_amount" name="{{ $prefix }}[max_amount]"
                class="form-control" min="{{ $minStep }}" step="{{ $minStep }}"
                placeholder="{{ translate('messages.Ex') . ': 50' }}"
                value="{{ $config['max_amount'] ?? '' }}">
        </div>
    </div>

    <div class="col-xl-4 col-md-6">
        <div class="tps-field">
            <div class="tps-field__label justify-content-between">
                <label class="mb-0 d-flex align-items-center gap-1" for="{{ $fieldId }}_min_order_amount">
                    {{ $minLabel }} ({{ $currencySymbol }})
                    <span class="form-label-secondary" data-toggle="tooltip" data-placement="top"
                        data-title="{{ $minTooltip }}"><i class="tio-info text-muted"></i></span>
                </label>
                <label class="toggle-switch toggle-switch-sm m-0">
                    <input type="checkbox" name="{{ $prefix }}[min_order_status]" value="1"
                        class="toggle-switch-input js-min-toggle"
                        {{ ($config['min_order_status'] ?? 0) ? 'checked' : '' }}>
                    <span class="toggle-switch-label text"><span class="toggle-switch-indicator"></span></span>
                </label>
            </div>
            <input type="number" id="{{ $fieldId }}_min_order_amount" name="{{ $prefix }}[min_order_amount]"
                class="form-control js-min-field" min="{{ $minStep }}" step="{{ $minStep }}"
                placeholder="{{ translate('messages.Ex') . ': 100' }}"
                value="{{ $config['min_order_amount'] ?? '' }}"
                {{ ($config['min_order_status'] ?? 0) ? '' : 'disabled' }}>
        </div>
    </div>
</div>
