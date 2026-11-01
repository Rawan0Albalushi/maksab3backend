@php
    $bonus = $bonus ?? null;
    $currencySymbol = \App\CentralLogics\Helpers::currency_symbol();
    $bonusType = $bonus?->bonus_type ?? 'percentage';
    $isPercentage = $bonusType === 'percentage';
    $translated = [];

    foreach ($bonus?->translations ?? [] as $translation) {
        $translated[$translation->locale][$translation->key] = $translation->value;
    }
@endphp

<div class="tps-card">
    <div class="tps-card__body">
        <div class="tps-group">
            <p class="tps-group__label">{{ translate('Bonus details') }}</p>

            @if ($language)
                <ul class="nav nav-tabs mb-3 border-0">
                    <li class="nav-item">
                        <a class="nav-link lang_link active" href="#" id="default-link">{{ translate('Default') }}</a>
                    </li>
                    @foreach ($language as $lang)
                        <li class="nav-item">
                            <a class="nav-link lang_link" href="#" id="{{ $lang }}-link">
                                {{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="lang_form" id="default-form">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="tps-field">
                            <label class="tps-field__label" for="default_title">
                                {{ translate('Bonus title') }} ({{ translate('Default') }})
                                <span class="tps-req">*</span>
                            </label>
                            <input type="text" name="title[]" id="default_title" class="form-control" maxlength="255"
                                placeholder="{{ translate('messages.Ex') . ': ' . translate('Eid dhamaka') }}"
                                value="{{ $bonus?->getRawOriginal('title') }}">
                            <small class="tps-field__hint">{{ translate('The customer sees this name on the add money screen.') }}</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="tps-field">
                            <label class="tps-field__label" for="default_description">
                                {{ translate('Short description') }} ({{ translate('Default') }})
                                <span class="tps-opt">({{ translate('Optional') }})</span>
                            </label>
                            <textarea name="description[]" id="default_description" class="form-control" rows="2"
                                placeholder="{{ translate('messages.Ex') . ': ' . translate('Eid dhamaka') }}">{{ $bonus?->getRawOriginal('description') }}</textarea>
                            <small class="tps-field__hint">{{ translate('One line saying what the customer gets.') }}</small>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="lang[]" value="default">
            </div>

            @if ($language)
                @foreach ($language as $lang)
                    <div class="d-none lang_form" id="{{ $lang }}-form">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="tps-field">
                                    <label class="tps-field__label" for="{{ $lang }}_title">
                                        {{ translate('Bonus title') }} ({{ strtoupper($lang) }})
                                    </label>
                                    <input type="text" name="title[]" id="{{ $lang }}_title" class="form-control" maxlength="255"
                                        placeholder="{{ translate('messages.Ex') . ': ' . translate('Eid dhamaka') }}"
                                        value="{{ $translated[$lang]['title'] ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="tps-field">
                                    <label class="tps-field__label" for="{{ $lang }}_description">
                                        {{ translate('Short description') }} ({{ strtoupper($lang) }})
                                    </label>
                                    <textarea name="description[]" id="{{ $lang }}_description" class="form-control" rows="2"
                                        placeholder="{{ translate('messages.Ex') . ': ' . translate('Eid dhamaka') }}">{{ $translated[$lang]['description'] ?? '' }}</textarea>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="lang[]" value="{{ $lang }}">
                    </div>
                @endforeach
            @endif
        </div>

        <div class="tps-group">
            <p class="tps-group__label">{{ translate('Bonus value') }}</p>

            <div class="row g-3 align-items-end">
                <div class="col-xl-6">
                    <div class="tps-field">
                        <span class="tps-field__label">{{ translate('Bonus type') }} <span class="tps-req">*</span></span>
                        <div class="tps-seg">
                            <input type="radio" id="bonus_type_percentage" name="bonus_type" value="percentage"
                                class="js-bonus-type" {{ $isPercentage ? 'checked' : '' }}>
                            <label for="bonus_type_percentage">{{ translate('messages.percentage') }} (%)</label>
                            <input type="radio" id="bonus_type_amount" name="bonus_type" value="amount"
                                class="js-bonus-type" {{ $isPercentage ? '' : 'checked' }}>
                            <label for="bonus_type_amount">{{ translate('Amount') }} ({{ $currencySymbol }})</label>
                        </div>
                        <small class="tps-field__hint">{{ translate('A percentage of what the customer adds, or the same amount every time.') }}</small>
                    </div>
                </div>

                <div class="col-xl-6 col-md-6">
                    <div class="tps-field">
                        <label class="tps-field__label" for="bonus_amount">
                            {{ translate('Bonus amount') }}
                            <span class="wbn-unit" id="bonus_unit">{{ $isPercentage ? '(%)' : '(' . $currencySymbol . ')' }}</span>
                            <span class="tps-req">*</span>
                        </label>
                        <input type="number" name="bonus_amount" id="bonus_amount" class="form-control" required
                            min="0.01" step="0.01" max="{{ $isPercentage ? '100' : '999999999999.99' }}"
                            placeholder="{{ translate('messages.Ex') . ': 10' }}"
                            value="{{ $bonus?->bonus_amount }}">
                        <small class="tps-field__hint">{{ translate('What the customer earns on top of the money they add.') }}</small>
                    </div>
                </div>

                <div class="col-xl-6 col-md-6">
                    <div class="tps-field">
                        <label class="tps-field__label" for="minimum_add_amount">
                            {{ translate('Minimum add money amount') }} ({{ $currencySymbol }})
                            <span class="tps-req">*</span>
                        </label>
                        <input type="number" name="minimum_add_amount" id="minimum_add_amount" class="form-control" required
                            min="0" step="0.01" max="999999999999.99"
                            placeholder="{{ translate('messages.Ex') . ': 100' }}"
                            value="{{ $bonus?->minimum_add_amount }}">
                        <small class="tps-field__hint">{{ translate('Top-ups below this earn nothing.') }}</small>
                    </div>
                </div>

                <div class="col-xl-6 col-md-6">
                    <div class="tps-field">
                        <label class="tps-field__label" for="maximum_bonus_amount">
                            {{ translate('Maximum bonus') }} ({{ $currencySymbol }})
                            <span class="tps-req" id="maximum_bonus_req">*</span>
                        </label>
                        <input type="number" name="maximum_bonus_amount" id="maximum_bonus_amount" class="form-control"
                            min="0" step="0.01" max="999999999999.99"
                            placeholder="{{ translate('messages.Ex') . ': 1000' }}"
                            value="{{ $bonus?->maximum_bonus_amount ?: '' }}"
                            {{ $isPercentage ? 'required' : 'disabled' }}>
                        <small class="tps-field__hint">{{ translate('The most a single top-up can earn. Only a percentage bonus needs a cap.') }}</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="tps-group">
            <p class="tps-group__label">{{ translate('Schedule') }}</p>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="tps-field">
                        <label class="tps-field__label" for="date_from">
                            {{ translate('Start date') }} <span class="tps-req">*</span>
                        </label>
                        <input type="date" name="start_date" id="date_from" class="form-control" required
                            value="{{ $bonus?->start_date?->format('Y-m-d') }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="tps-field">
                        <label class="tps-field__label" for="date_to">
                            {{ translate('messages.Expire date') }} <span class="tps-req">*</span>
                        </label>
                        <input type="date" name="end_date" id="date_to" class="form-control" required
                            value="{{ $bonus?->end_date?->format('Y-m-d') }}">
                        <small class="tps-field__hint">{{ translate('The bonus stops paying out after this day.') }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tps-card__foot">
        <span class="tps-foot-note">{{ translate('The bonus is paid from the admin wallet when a customer tops up.') }}</span>
        <button type="reset" id="reset_btn" class="btn btn--reset">
            <i class="tio-refresh"></i> {{ translate('messages.Reset') }}
        </button>
        <button type="submit" class="btn btn--primary">
            <i class="{{ $submitIcon }}"></i> {{ $submitLabel }}
        </button>
    </div>
</div>
