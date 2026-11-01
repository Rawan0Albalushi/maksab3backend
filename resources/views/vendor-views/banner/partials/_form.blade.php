<div class="tps-card">
    <div class="tps-card__body">

        <div class="tps-group">
            <h3 class="tps-group__label">{{ translate('Banner content') }}</h3>

            <div class="tps-field">
                <div class="error-wrapper">
                    <label class="tps-field__label" for="title">
                        {{ translate('Banner title') }} <span class="tps-req">*</span>
                    </label>
                    <input type="text" name="title" id="title" class="form-control" required maxlength="191"
                           data-banner-title value="{{ old('title', $banner?->title) }}"
                           placeholder="{{ translate('Enter title') }}">
                </div>
                <small class="tps-field__hint">{{ translate('The name this banner is listed under in your panel.') }}</small>
            </div>

            <div class="tps-field mt-3">
                <label class="tps-field__label" for="default_link">
                    {{ translate('Redirection URL / link') }}
                    <span class="tps-opt">({{ translate('Optional') }})</span>
                </label>
                <input type="url" name="default_link" id="default_link" class="form-control" maxlength="255"
                       value="{{ old('default_link', $banner?->default_link) }}"
                       placeholder="{{ translate('messages.Enter URL') }}">
                <small class="tps-field__hint">{{ translate('Optional. Paste a full link to open when a customer taps the banner.') }}</small>
            </div>
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
    </div>

    <div class="tps-card__foot">
        <span class="tps-foot-note">{{ translate('The banner is live as soon as it is saved.') }}</span>
        <button type="reset" id="reset_btn" class="btn btn--reset">
            <i class="tio-refresh"></i> {{ translate('messages.Reset') }}
        </button>
        <button type="submit" class="btn btn--primary">
            <i class="{{ $submitIcon }}"></i> {{ $submitLabel }}
        </button>
    </div>
</div>
