@php
    $ownerLabel = $isServiceModule ? translate('Provider') : translate('messages.Store');
    $ownerPlaceholder = $isServiceModule ? translate('Select provider') : translate('Select store');
    $itemLabel = $isServiceModule ? translate('Select service') : translate('messages.Select item');
    $selectedType = $banner ? (in_array($banner->type, ['default', 'default_link']) ? 'default' : $banner->type) : 'store_wise';
    $titleTranslations = [];
    foreach ($banner?->translations ?? [] as $translation) {
        if ($translation->key === 'title') {
            $titleTranslations[$translation->locale] = $translation->value;
        }
    }
    $selectedStore = $banner && $banner->type === 'store_wise' ? \App\Models\Store::find($banner->data) : null;
@endphp

<div class="tps-card">
    <div class="tps-card__body">

        <div class="tps-group">
            <h3 class="tps-group__label">{{ translate('Banner content') }}</h3>

            @if ($language)
                <ul class="nav nav-tabs mb-3 border-0">
                    <li class="nav-item">
                        <a class="nav-link lang_link active" href="#" id="default-link">{{ translate('Default') }}</a>
                    </li>
                    @foreach ($language as $lang)
                        <li class="nav-item">
                            <a class="nav-link lang_link" href="#"
                               id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                        </li>
                    @endforeach
                </ul>

                <div class="lang_form" id="default-form">
                    <div class="tps-field">
                        <label class="tps-field__label" for="default_title">
                            {{ translate('messages.Title') }} ({{ translate('Default') }})
                            <span class="tps-req">*</span>
                        </label>
                        <input type="text" name="title[]" id="default_title" class="form-control" required
                               maxlength="191" data-banner-title
                               value="{{ $banner?->getRawOriginal('title') }}"
                               placeholder="{{ translate('messages.New banner') }}">
                        <small class="tps-field__hint">{{ translate('The name this banner is listed under in admin.') }}</small>
                    </div>
                    <input type="hidden" name="lang[]" value="default">
                </div>

                @foreach ($language as $lang)
                    <div class="d-none lang_form" id="{{ $lang }}-form">
                        <div class="tps-field">
                            <label class="tps-field__label" for="{{ $lang }}_title">
                                {{ translate('messages.Title') }} ({{ strtoupper($lang) }})
                            </label>
                            <input type="text" name="title[]" id="{{ $lang }}_title" class="form-control"
                                   maxlength="191" value="{{ $titleTranslations[$lang] ?? '' }}"
                                   placeholder="{{ translate('messages.New banner') }}">
                            <small class="tps-field__hint">{{ translate('Leave it empty to fall back to the default title.') }}</small>
                        </div>
                        <input type="hidden" name="lang[]" value="{{ $lang }}">
                    </div>
                @endforeach
            @else
                <div id="default-form">
                    <div class="tps-field">
                        <label class="tps-field__label" for="default_title">
                            {{ translate('messages.Title') }} ({{ translate('Default') }})
                            <span class="tps-req">*</span>
                        </label>
                        <input type="text" name="title[]" id="default_title" class="form-control" required
                               maxlength="191" data-banner-title
                               value="{{ $banner?->getRawOriginal('title') }}"
                               placeholder="{{ translate('messages.New banner') }}">
                        <small class="tps-field__hint">{{ translate('The name this banner is listed under in admin.') }}</small>
                    </div>
                    <input type="hidden" name="lang[]" value="default">
                </div>
            @endif
        </div>

        <div class="tps-group">
            <h3 class="tps-group__label">{{ translate('messages.Banner image') }}</h3>

            <div class="bnr-drop">
                <div class="bnr-drop__media">
                    @include('admin-views.partials._image-uploader', [
                        'id' => 'banner-image',
                        'name' => 'image',
                        'ratio' => '3:1',
                        'isRequired' => empty($banner?->image_full_url),
                        'existingImage' => $banner?->image_full_url ?? '',
                        'imageExtension' => IMAGE_EXTENSION,
                        'imageFormat' => IMAGE_FORMAT,
                        'maxSize' => MAX_FILE_SIZE,
                        'textPosition' => 'bottom',
                        'show_clear_button' => false,
                    ])
                </div>
                <p class="tps-field__hint m-0 text-center">
                    {{ translate('Keep the message in the middle of the artwork. Narrow phones crop the sides, so text near an edge is the first thing customers lose.') }}
                </p>
            </div>
        </div>

        <div class="tps-group">
            <h3 class="tps-group__label">{{ translate('Where it appears') }}</h3>

            <div class="tps-field">
                <label class="tps-field__label" for="zone">
                    {{ translate('messages.Zone') }} <span class="tps-req">*</span>
                </label>
                <select name="zone_id" id="zone" class="form-control js-select2-custom" required>
                    <option value="" disabled {{ $banner?->zone_id ? '' : 'selected' }}>{{ translate('Select') }}</option>
                    @foreach ($zones as $zone)
                        @if (!auth('admin')?->user()?->zone_id || auth('admin')->user()->zone_id == $zone->id)
                            <option value="{{ $zone['id'] }}"
                                {{ $banner?->zone_id == $zone->id || (!$banner && auth('admin')?->user()?->zone_id == $zone->id) ? 'selected' : '' }}>
                                {{ $zone['name'] }}
                            </option>
                        @endif
                    @endforeach
                </select>
                <small class="tps-field__hint">{{ translate('Only customers ordering inside this zone see the banner, and it is what the store and item lists below are filtered by.') }}</small>
            </div>

            <div class="tps-field mt-3">
                <label class="tps-field__label">
                    {{ translate('Banner type') }} <span class="tps-req">*</span>
                </label>
                <div class="bnr-types">
                    <label class="tps-choice">
                        <input type="radio" name="banner_type" value="store_wise" {{ $selectedType === 'store_wise' ? 'checked' : '' }}>
                        <span class="tps-choice__box">
                            <span class="bnr-type__icon"><i class="tio-shop"></i></span>
                            <span>
                                <span class="tps-choice__title">{{ $isServiceModule ? translate('Provider wise') : translate('messages.store_wise') }}</span>
                                <span class="tps-choice__desc">{{ $isServiceModule ? translate('Tapping it opens the provider page.') : translate('Tapping it opens the store page.') }}</span>
                            </span>
                        </span>
                    </label>
                    <label class="tps-choice">
                        <input type="radio" name="banner_type" value="item_wise" {{ $selectedType === 'item_wise' ? 'checked' : '' }}>
                        <span class="tps-choice__box">
                            <span class="bnr-type__icon"><i class="tio-shopping-basket"></i></span>
                            <span>
                                <span class="tps-choice__title">{{ $isServiceModule ? translate('Service wise') : translate('messages.item_wise') }}</span>
                                <span class="tps-choice__desc">{{ $isServiceModule ? translate('Tapping it opens one service.') : translate('Tapping it opens one item.') }}</span>
                            </span>
                        </span>
                    </label>
                    <label class="tps-choice">
                        <input type="radio" name="banner_type" value="default" {{ $selectedType === 'default' ? 'checked' : '' }}>
                        <span class="tps-choice__box">
                            <span class="bnr-type__icon"><i class="tio-link"></i></span>
                            <span>
                                <span class="tps-choice__title">{{ translate('Default') }}</span>
                                <span class="tps-choice__desc">{{ translate('Artwork only, or a link you paste in.') }}</span>
                            </span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="bnr-target">
                <div class="tps-field" id="store_wise"@if($selectedType !== 'store_wise') style="display: none;"@endif>
                    <label class="tps-field__label" for="store_id">
                        {{ $ownerLabel }} <span class="tps-req">*</span>
                    </label>
                    <select name="store_id" id="store_id" class="js-data-example-ajax form-control"
                            data-placeholder="{{ $ownerPlaceholder }}">
                        <option value=""></option>
                        @if ($selectedStore)
                            <option value="{{ $selectedStore->id }}" data-verified="{{ (int) $selectedStore->verified_seller }}" selected>
                                {{ $selectedStore->name }}
                            </option>
                        @endif
                    </select>
                    <small class="tps-field__hint">{{ translate('Pick a zone first — this list only holds what trades in it.') }}</small>
                </div>

                <div class="tps-field" id="item_wise"@if($selectedType !== 'item_wise') style="display: none;"@endif>
                    <label class="tps-field__label" for="choice_item">
                        {{ $itemLabel }} <span class="tps-req">*</span>
                    </label>
                    <select name="item_id" id="choice_item" class="form-control js-select2-custom"></select>
                    <small class="tps-field__hint">{{ translate('The store each one belongs to is shown in brackets.') }}</small>
                </div>

                <div class="tps-field" id="default"@if($selectedType !== 'default') style="display: none;"@endif>
                    <label class="tps-field__label" for="default_link">
                        {{ translate('messages.Default link') }}
                        <span class="tps-opt">({{ translate('Optional') }})</span>
                    </label>
                    <input type="text" name="default_link" id="default_link" class="form-control"
                           value="{{ $banner?->default_link }}" placeholder="{{ translate('messages.Default link') }}">
                    <small class="tps-field__hint">{{ translate('Leave it empty and the banner is artwork that does nothing when tapped.') }}</small>
                </div>
            </div>
        </div>
    </div>

    <div class="tps-card__foot">
        <span class="tps-foot-note">{{ translate('The banner is live as soon as it is saved. Switch it off in the list to take it down.') }}</span>
        <button type="reset" id="reset_btn" class="btn btn--reset">
            <i class="tio-refresh"></i> {{ translate('messages.Reset') }}
        </button>
        <button type="submit" class="btn btn--primary">
            <i class="{{ $submitIcon }}"></i> {{ $submitLabel }}
        </button>
    </div>
</div>
