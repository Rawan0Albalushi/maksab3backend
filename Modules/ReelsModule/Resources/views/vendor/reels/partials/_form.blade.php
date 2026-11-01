@php
    $isEdit = isset($isEdit) && $isEdit;
    $alwaysVisible = (int) old('is_always_visible', $reel->is_always_visible ?? 0) === 1;
    $callToActionOn = (int) old('order_now_button', $reel->order_now_button ?? 0) === 1;
    $defaultDescription = old('description.0', $reel->getRawOriginal('description') ?? '');
    $existingDateRange = old('dates');
    $reelVideoMaxSizeMb = max(1, (int) (\App\CentralLogics\Helpers::get_business_settings('reels_max_upload_size_mb') ?: 15));
    $reelMaxDuration = max(1, (int) (\App\CentralLogics\Helpers::get_business_settings('reels_max_duration') ?? 30));
    $reelMaxDurationUnit = \App\CentralLogics\Helpers::get_business_settings('reels_max_duration_unit') ?? 'min';
    $reelMaxDurationSeconds = $reelMaxDurationUnit === 'hour' ? $reelMaxDuration * 3600 : $reelMaxDuration * 60;
    $reelDurationUnitLabel = $reelMaxDurationUnit === 'hour' ? translate('messages.Hour') : translate('messages.minutes');
    $reelDurationText = translate('messages.Max duration') . ': ' . $reelMaxDuration . ' ' . $reelDurationUnitLabel;

    if ($existingDateRange === null && $reel->start_date && $reel->end_date) {
        $existingDateRange = \Carbon\Carbon::parse($reel->start_date)->format('m/d/Y') . ' - ' . \Carbon\Carbon::parse($reel->end_date)->format('m/d/Y');
    }

    $previewStoreName = $store?->name ?? '';
    $previewStoreLogo = $store?->logo_full_url ?? '';
    $productLabel = $productLabel ?? translate('messages.Product');
    $selectProductLabel = translate('Select') . ' ' . $productLabel;
    $selectedProduct = $items->firstWhere('id', (int) ($selectedProductId ?? 0));
@endphp

<input type="hidden" name="store_id" value="{{ \App\CentralLogics\Helpers::get_store_id() }}">

<div class="row g-3">
    <div class="col-xl-8">
        <div class="tps-card">
            <div class="tps-card__body">

                <div class="tps-group">
                    <h3 class="tps-group__label">{{ translate('Reel content') }}</h3>

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

                    <div class="lang_form default-form" id="default-form">
                        <div class="tps-field">
                            <label for="reel_description_default" class="tps-field__label">
                                {{ translate('Short description') }} ({{ translate('Default') }})
                                <span class="tps-req">*</span>
                            </label>
                            <textarea required id="reel_description_default" class="form-control reel-des-textarea" rows="3" maxlength="200"
                                name="description[]" placeholder="{{ translate('messages.Write a short description') }}">{{ $defaultDescription }}</textarea>
                            <div class="rlf-field-foot mt-1">
                                <small class="tps-field__hint">{{ translate('This is the caption printed over the video. Keep it to a line or two — the phone crops the rest.') }}</small>
                                <span class="text-counting">{{ strlen($defaultDescription) }}/200</span>
                            </div>
                        </div>
                        <input type="hidden" name="lang[]" value="default">
                    </div>

                    @foreach ($language as $lang)
                        @php
                            $translatedDescription = old('description.' . ($loop->index + 1));
                            if ($translatedDescription === null) {
                                $translatedDescription = optional(
                                    $reel->translations->first(function ($translation) use ($lang) {
                                        return $translation->locale === $lang && $translation->key === 'description';
                                    })
                                )->value;
                            }
                        @endphp
                        <div class="d-none lang_form" id="{{ $lang }}-form">
                            <div class="tps-field">
                                <label for="reel_description_{{ $lang }}" class="tps-field__label">
                                    {{ translate('Short description') }} ({{ strtoupper($lang) }})
                                </label>
                                <textarea id="reel_description_{{ $lang }}" class="form-control reel-des-textarea" rows="3" maxlength="200"
                                    name="description[]" placeholder="{{ translate('messages.Write a short description') }}">{{ $translatedDescription }}</textarea>
                                <div class="rlf-field-foot mt-1">
                                    <small class="tps-field__hint">{{ translate('Leave it empty to fall back to the default caption.') }}</small>
                                    <span class="text-counting">{{ strlen($translatedDescription ?? '') }}/200</span>
                                </div>
                            </div>
                            <input type="hidden" name="lang[]" value="{{ $lang }}">
                        </div>
                    @endforeach
                </div>

                <div class="tps-group">
                    <h3 class="tps-group__label">{{ translate('Video and cover') }}</h3>

                    <div class="rlf-media">
                        <div class="rlf-slot">
                            <div class="rlf-slot__head">
                                <span class="rlf-slot__icon"><i class="tio-video-camera"></i></span>
                                <div>
                                    <h4 class="rlf-slot__title">
                                        {{ translate('messages.Upload a file') }} <span class="tps-req">*</span>
                                    </h4>
                                    <p class="rlf-slot__hint">{{ translate('Shoot it upright. Anything wider is letterboxed in the app.') }}</p>
                                </div>
                            </div>

                            <div class="reel-upload-box-wrapper">
                                <div class="reel-upload-box {{ $reel->video_full_url ? 'active' : '' }}" data-type="video" data-max-size="{{ $reelVideoMaxSizeMb }}" data-max-duration-seconds="{{ $reelMaxDurationSeconds }}" data-max-duration-label="{{ $reelMaxDuration }} {{ $reelDurationUnitLabel }}" data-original-thumbnail="{{ $reel->thumbnail_full_url ?? $previewStoreLogo }}" data-original-video="{{ $reel->video_full_url ?? '' }}" data-original-video-name="{{ $reel->video ? basename($reel->video) : '' }}" data-original-video-type="{{ $reel->video ? strtoupper(pathinfo($reel->video, PATHINFO_EXTENSION)) : '' }}">
                                    <input type="file" hidden accept="video/*" name="video">

                                    <div class="upload-placeholder text-center" style="{{ $reel->video_full_url ? 'display:none;' : '' }}">
                                        <img src="{{ asset('public/assets/admin/img/reels/video-icon.png') }}" alt="">
                                        <div>
                                            <span class="text-info">{{ translate('Add video') }}</span>
                                            <br>
                                            {{ translate('messages.or drag and drop') }}
                                        </div>
                                    </div>

                                    <div class="upload-wrapper reel-upload-wrapper" style="display: {{ $reel->video_full_url ? 'block' : 'none' }};">
                                        <div class="img-wrapper">
                                            <img src="{{ $reel->thumbnail_full_url ?? $previewStoreLogo }}" alt="">
                                            <div class="reels-play-btn">
                                                <div class="d-flex justify-content-center align-items-center w-100 h-100">
                                                    <i class="tio-play"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <h6 class="fs-10 fw-medium mb-0 reel-title">{{ $reel->video ? basename($reel->video) : '' }}</h6>
                                        <p class="fs-10 mt-2 mb-0"><span class="reel-type">{{ $reel->video ? strtoupper(pathinfo($reel->video, PATHINFO_EXTENSION)) : '' }}</span></p>
                                        <button type="button" class="btn upload-again-btn" aria-label="{{ translate('messages.Upload again') }}">
                                            <i class="tio-edit"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <ul class="rlf-spec">
                                <li>Mp4, MOV, 3GP, GIF</li>
                                <li>Max {{ $reelVideoMaxSizeMb }} MB</li>
                                <li>9:16</li>
                                <li>{{ $reelDurationText }}</li>
                            </ul>
                        </div>

                        <div class="rlf-slot">
                            <div class="rlf-slot__head">
                                <span class="rlf-slot__icon"><i class="tio-image"></i></span>
                                <div>
                                    <h4 class="rlf-slot__title">
                                        {{ translate('Upload thumbnail image') }} <span class="tps-req">*</span>
                                    </h4>
                                    <p class="rlf-slot__hint">{{ translate('The still customers see in the feed before the video starts playing.') }}</p>
                                </div>
                            </div>

                            <div class="reel-upload-box-wrapper">
                                <div class="reel-upload-box {{ $reel->thumbnail_full_url ? 'active' : '' }}" data-type="image" data-max-size="{{ MAX_FILE_SIZE }}" data-ratio="9:16" data-original-thumbnail="{{ $reel->thumbnail_full_url ?? '' }}">
                                    <input type="file" hidden accept="{{ IMAGE_EXTENSION }}" name="thumbnail">

                                    <div class="upload-placeholder text-center" style="{{ $reel->thumbnail_full_url ? 'display:none;' : '' }}">
                                        <img src="{{ asset('public/assets/admin/img/reels/img-icon.png') }}" alt="">
                                        <div>
                                            <span class="text-info">{{ translate('messages.Click to upload') }}</span>
                                            <br>
                                            {{ translate('messages.or drag and drop') }}
                                        </div>
                                    </div>

                                    <div class="upload-wrapper img-upload-wrapper" style="display: {{ $reel->thumbnail_full_url ? 'block' : 'none' }};">
                                        <img src="{{ $reel->thumbnail_full_url }}" alt="">
                                        <button type="button" class="btn upload-again-btn" aria-label="{{ translate('messages.Upload again') }}">
                                            <i class="tio-edit"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <ul class="rlf-spec">
                                <li>{{ IMAGE_FORMAT }}</li>
                                <li>Max {{ MAX_FILE_SIZE }} MB</li>
                                <li>9:16</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="tps-group">
                    <h3 class="tps-group__label">{{ translate('When it runs') }}</h3>

                    <div class="rlf-switchrow mb-3">
                        <div class="rlf-switchrow__text">
                            <h4>{{ translate('Always visible to customers') }}</h4>
                            <p>{{ translate('The reel runs from the moment it is saved until you switch it off in the list.') }}</p>
                        </div>
                        <label class="toggle-switch toggle-switch-sm" for="is_always_visible">
                            <input type="checkbox" id="is_always_visible" name="is_always_visible" value="1" class="toggle-switch-input" {{ $alwaysVisible ? 'checked' : '' }}>
                            <span class="toggle-switch-label">
                                <span class="toggle-switch-indicator"></span>
                            </span>
                        </label>
                    </div>

                    <div class="tps-field">
                        <label class="tps-field__label" for="dates">
                            {{ translate('Reel validity') }} <span class="tps-req">*</span>
                        </label>
                        <div class="rlf-date">
                            <i class="tio-calendar"></i>
                            <input required type="text" id="dates" class="form-control"
                                name="dates" value="{{ $existingDateRange ?? '' }}"
                                data-initial-value="{{ $existingDateRange ?? '' }}"
                                data-no-global-daterangepicker="true"
                                placeholder="{{ translate('messages.Select reels duration') }}" autocomplete="off" {{ $alwaysVisible ? 'disabled' : '' }}>
                        </div>
                        <small class="tps-field__hint">{{ translate('Customers stop seeing the reel after the end date. Switch on always visible above to run it with no end.') }}</small>
                    </div>
                </div>

                <div class="tps-group">
                    <h3 class="tps-group__label">{{ translate('Call to action') }}</h3>

                    <div class="rlf-switchrow">
                        <div class="rlf-switchrow__text">
                            <h4>{{ translate('Call to action button') }}</h4>
                            <p>{{ translate('Puts a button on the reel that takes the customer straight to one item.') }}</p>
                        </div>
                        <label class="toggle-switch toggle-switch-sm" for="call-to-action-toggle">
                            <input type="checkbox" id="call-to-action-toggle" name="order_now_button" value="1" class="toggle-switch-input" {{ $callToActionOn ? 'checked' : '' }}>
                            <span class="toggle-switch-label">
                                <span class="toggle-switch-indicator"></span>
                            </span>
                        </label>
                    </div>

                    <div class="tps-field mt-3" id="product-select-wrapper" style="{{ $callToActionOn ? '' : 'display:none;' }}">
                        <label class="tps-field__label" for="product_id">
                            {{ $productLabel }} <span class="tps-req">*</span>
                        </label>
                        <select class="form-control w-100 js-select2-custom" id="product_id" name="product_id" data-selected-product="{{ $selectedProductId ?? '' }}">
                            <option value="">{{ $selectProductLabel }}</option>
                            @foreach ($items as $item)
                                <option value="{{ $item->id }}" {{ (int) ($selectedProductId ?? 0) === (int) $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                            @endforeach
                        </select>
                        <small class="tps-field__hint">{{ translate('messages.Selected product will be visible in the order') }}</small>
                    </div>
                </div>
            </div>

            <div class="tps-card__foot">
                <span class="tps-foot-note">{{ translate('The reel goes live as soon as it is saved. Switch it off in the list to take it down.') }}</span>
                <button type="reset" id="resetBtn" class="btn btn--reset">
                    <i class="tio-refresh"></i> {{ translate('messages.Reset') }}
                </button>
                <button type="submit" class="btn btn--primary call-demo">
                    <i class="{{ $isEdit ? 'tio-save' : 'tio-checkmark-circle-outlined' }}"></i>
                    {{ $isEdit ? translate('Update') : translate('messages.Submit') }}
                </button>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        @include('reelsmodule::vendor.reels.partials._preview', [
            'previewStoreName' => $previewStoreName,
            'previewStoreLogo' => $previewStoreLogo,
            'defaultDescription' => $defaultDescription,
            'callToActionOn' => $callToActionOn,
            'alwaysVisible' => $alwaysVisible,
            'existingDateRange' => $existingDateRange,
            'selectedProduct' => $selectedProduct,
        ])
    </div>
</div>
