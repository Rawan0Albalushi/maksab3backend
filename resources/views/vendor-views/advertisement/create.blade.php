@extends('layouts.vendor.app')

@section('title',translate('Create advertisement'))

@section('advertisement_create')
active
@endsection

@push('css_or_js')
    <link rel="stylesheet" type="text/css" href="{{asset('public/assets/admin/css/daterangepicker.css')}}"/>
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/third-party-setup.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/view-pages/advertisement-form.css')}}">
@endpush

@section('content')
@php($is_provider_context = $store_data?->module_type === 'service')
@php($store_promotion_label = $is_provider_context ? translate('Provider promotion') : translate('Store promotion'))
<div class="content container-fluid tps advf">

    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon"><img src="{{asset('public/assets/admin/img/outline/advertisement.svg')}}" alt=""></span>
                <span>{{ translate('Create advertisement') }}</span>
            </h1>
            <p class="page-header-desc">{{ translate('Pay to put your store in front of more customers, and choose how long it runs.') }}</p>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('vendor.advertisement.index') }}" class="btn btn--reset">
                <i class="tio-arrow-backward"></i> {{ translate('messages.Back') }}
            </a>
        </div>
    </div>

    <form id="create-add-form" action="{{ route('vendor.advertisement.store') }}" method="POST"
          enctype="multipart/form-data" class="row g-3">
        @csrf
        <input type="hidden" value="{{ $store_id }}" name="store_id">

        <div class="col-xl-8">
            <div class="tps-card">
                <div class="tps-card__body">

                    <div class="tps-group">
                        <h3 class="tps-group__label">{{ translate('Advertisement content') }}</h3>

                        @if ($language)
                            <ul class="nav nav-tabs mb-3 border-0">
                                <li class="nav-item">
                                    <a class="nav-link lang_link active" href="#" id="default-link">{{ translate('Default') }}</a>
                                </li>
                                @foreach ($language as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link lang_link" href="#" id="{{ $lang }}-link">{{ $language_labels[$lang] }}</a>
                                    </li>
                                @endforeach
                            </ul>

                            <div class="lang_form" id="default-form">
                                <div class="tps-field mb-3">
                                    <label class="tps-field__label" for="title_default">
                                        {{ translate('Advertisement title') }} ({{ translate('Default') }})
                                        <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                              data-original-title="{{ translate('messages.Required.') }}">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="title_default" name="title[]"
                                           value="{{ old('title.0') }}" placeholder="{{ translate('Exclusive offer') }}"
                                           maxlength="255" required data-preview-text="preview-title">
                                    <small class="tps-field__hint">{{ translate('The headline customers read first. Keep it under a handful of words.') }}</small>
                                </div>
                                <div class="tps-field">
                                    <label class="tps-field__label" for="description_default">
                                        {{ translate('Short description') }} ({{ translate('Default') }})
                                        <span class="tps-opt">({{ translate('Optional') }})</span>
                                    </label>
                                    <textarea class="form-control resize-none" id="description_default" rows="2"
                                              placeholder="{{ translate('Get discount') }}" name="description[]"
                                              data-preview-text="preview-description">{{ old('description.0') }}</textarea>
                                    <small class="tps-field__hint">{{ translate('One line under the title. Leave it empty to show the title on its own.') }}</small>
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                            </div>

                            @foreach ($language as $key => $lang)
                                <div class="d-none lang_form" id="{{ $lang }}-form">
                                    <div class="tps-field mb-3">
                                        <label class="tps-field__label" for="title_{{ $lang }}">
                                            {{ translate('Advertisement title') }} ({{ strtoupper($lang) }})
                                            <span class="tps-opt">({{ translate('Optional') }})</span>
                                        </label>
                                        <input type="text" class="form-control" id="title_{{ $lang }}" name="title[]"
                                               value="{{ old('title.' . ($key + 1)) }}" placeholder="{{ translate('Exclusive offer') }}"
                                               maxlength="255" data-preview-text="preview-title">
                                        <small class="tps-field__hint">{{ translate('Leave it empty to fall back to the default title.') }}</small>
                                    </div>
                                    <div class="tps-field">
                                        <label class="tps-field__label" for="description_{{ $lang }}">
                                            {{ translate('Short description') }} ({{ strtoupper($lang) }})
                                            <span class="tps-opt">({{ translate('Optional') }})</span>
                                        </label>
                                        <textarea class="form-control resize-none" id="description_{{ $lang }}" rows="2"
                                                  placeholder="{{ translate('Get discount') }}" name="description[]"
                                                  data-preview-text="preview-description">{{ old('description.' . ($key + 1)) }}</textarea>
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{ $lang }}">
                                </div>
                            @endforeach
                        @else
                            <div class="tps-field mb-3">
                                <label class="tps-field__label" for="title_default">
                                    {{ translate('Advertisement title') }}
                                    <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                          data-original-title="{{ translate('messages.Required.') }}">*</span>
                                </label>
                                <input type="text" class="form-control" id="title_default" name="title[]"
                                       value="{{ old('title.0') }}" placeholder="{{ translate('Exclusive offer') }}"
                                       maxlength="255" required data-preview-text="preview-title">
                                <small class="tps-field__hint">{{ translate('The headline customers read first. Keep it under a handful of words.') }}</small>
                            </div>
                            <div class="tps-field">
                                <label class="tps-field__label" for="description_default">
                                    {{ translate('Short description') }}
                                    <span class="tps-opt">({{ translate('Optional') }})</span>
                                </label>
                                <textarea class="form-control resize-none" id="description_default" rows="2"
                                          placeholder="{{ translate('Get discount') }}" name="description[]"
                                          data-preview-text="preview-description">{{ old('description.0') }}</textarea>
                                <small class="tps-field__hint">{{ translate('One line under the title. Leave it empty to show the title on its own.') }}</small>
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                        @endif
                    </div>

                    <div class="tps-group">
                        <h3 class="tps-group__label">{{ translate('Schedule') }}</h3>
                        <div class="row g-3">
                            <div class="col-md-7">
                                <div class="tps-field">
                                    <label class="tps-field__label" for="dates">
                                        {{ translate('Validity') }}
                                        <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                              data-original-title="{{ translate('messages.Required.') }}">*</span>
                                    </label>
                                    <div class="tps-input-wrap advf-date">
                                        <input type="text" class="form-control" id="dates" name="dates" required
                                               placeholder="{{ translate('Select date') }}">
                                        <i class="tio-calendar-month advf-date__icon"></i>
                                    </div>
                                    <small class="tps-field__hint">{{ translate('The advertisement runs from the first day to the last, both included.') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tps-group">
                        <h3 class="tps-group__label">{{ translate('Format and media') }}</h3>

                        <div class="advf-types">
                            <label class="tps-choice">
                                <input type="radio" name="advertisement_type" value="store_promotion" checked>
                                <span class="tps-choice__box">
                                    <span class="advf-type__icon"><i class="tio-shop"></i></span>
                                    <span>
                                        <span class="tps-choice__title">{{ $store_promotion_label }}</span>
                                        <span class="tps-choice__desc">{{ translate('A cover image and a logo, shown with the rating customers already trust.') }}</span>
                                    </span>
                                </span>
                            </label>
                            <label class="tps-choice">
                                <input type="radio" name="advertisement_type" value="video_promotion">
                                <span class="tps-choice__box">
                                    <span class="advf-type__icon"><i class="tio-video-camera-outlined"></i></span>
                                    <span>
                                        <span class="tps-choice__title">{{ translate('Video promotion') }}</span>
                                        <span class="tps-choice__desc">{{ translate('A short clip that plays in place of the cover image.') }}</span>
                                    </span>
                                </span>
                            </label>
                        </div>

                        <div class="promotion-typewise-upload-box" id="profile-upload-box">
                            <div class="advf-drop">
                                <div class="advf-drop__grid">
                                    <div class="advf-drop__slot">
                                        <p class="advf-drop__name">
                                            {{ translate('Profile image') }}
                                            <span class="advf-drop__ratio">1:1</span>
                                        </p>
                                        <div class="advf-drop__media">
                                            <div class="upload-file">
                                                <input type="file" class="cover_attachment js-upload-input"
                                                       data-target="profile-prev-image" name="profile_image"
                                                       aria-label="{{ translate('Profile image') }}"
                                                       accept=".webp, .png,.jpg,.jpeg,.gif, |image/*">
                                                <div class="upload-file__img">
                                                    <img src="{{asset('public/assets/admin/img/media/upload-file.png')}}" alt="">
                                                </div>
                                                <button class="remove-file-button" type="button">
                                                    <i class="tio-clear"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <p class="advf-drop__meta">
                                            {{ translate('Supported formats') . ': PNG, JPG, JPEG, WEBP' }}
                                            <br>
                                            Maximum 2 MB
                                        </p>
                                    </div>
                                    <div class="advf-drop__slot advf-drop__slot--banner">
                                        <p class="advf-drop__name">
                                            {{ translate('Upload cover') }}
                                            <span class="advf-drop__ratio">2:1</span>
                                        </p>
                                        <div class="advf-drop__media">
                                            <div class="upload-file">
                                                <input type="file" class="cover_attachment js-upload-input"
                                                       data-target="main-image" name="cover_image"
                                                       aria-label="{{ translate('Upload cover') }}"
                                                       accept=".webp, .png,.jpg,.jpeg,.gif, |image/*">
                                                <div class="upload-file__img upload-file__img_banner aspect-2-1">
                                                    <img src="{{asset('public/assets/admin/img/media/banner-upload-file.png')}}" alt="">
                                                </div>
                                                <button class="remove-file-button" type="button">
                                                    <i class="tio-clear"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <p class="advf-drop__meta">
                                            {{ translate('Supported formats') . ': PNG, JPG, JPEG, WEBP' }}
                                            <br>
                                            Maximum 2 MB
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="advf-checks">
                                <p class="advf-checks__label">{{ translate('Show review') }} &amp; {{ translate('Ratings') }}</p>
                                <label class="form-check form--check-2">
                                    <input type="checkbox" class="form-check-input" value="1" name="review" checked>
                                    <span class="form-check-label">{{ translate('review') }}</span>
                                </label>
                                <label class="form-check form--check-2">
                                    <input type="checkbox" class="form-check-input" value="1" name="rating" checked>
                                    <span class="form-check-label">{{ translate('Rating') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="promotion-typewise-upload-box" id="video-upload-box">
                            <div class="advf-drop">
                                <div class="advf-drop__grid">
                                    <div class="advf-drop__slot advf-drop__slot--wide">
                                        <p class="advf-drop__name">
                                            {{ translate('Upload your video') }}
                                            <span class="advf-drop__ratio">16:9</span>
                                        </p>
                                        <div class="advf-drop__media">
                                            <div class="upload-file">
                                                <input type="file" class="video_attachment" name="video_attachment"
                                                       aria-label="{{ translate('Upload your video') }}"
                                                       accept="video/mp4, video/webm, video/mkv">
                                                <div class="upload-file__img upload-file__img_banner upload-file__video-not-playable h-140">
                                                    <img src="{{asset('public/assets/admin/img/media/video-banner.png')}}" alt="">
                                                </div>
                                                <button class="remove-file-button" type="button">
                                                    <i class="tio-clear"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <p class="advf-drop__meta">
                                            {{ translate('Supported formats') }}: MP4, WEBM, MKV
                                            <br>
                                            {{ translate('Maximum size') }}: 5 MB
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tps-card__foot">
                    <span class="tps-foot-note">{{ translate('The admin approves your advertisement before customers see it.') }}</span>
                    <button type="reset" id="reset_btn" class="btn btn--reset">
                        <i class="tio-refresh"></i> {{ translate('Reset') }}
                    </button>
                    <button type="submit" class="btn btn--primary">
                        <i class="tio-checkmark-circle-outlined"></i> {{ translate('Submit') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="advf-aside">
                <div class="tps-card">
                    <div class="tps-card__head">
                        <span class="tps-card__brand"><i class="tio-devices-apple"></i></span>
                        <div class="tps-card__titles">
                            <h2 class="tps-card__title">{{ translate('Advertisement preview') }}</h2>
                            <p class="tps-card__subtitle">{{ translate('How the advertisement looks to customers in the app.') }}</p>
                        </div>
                    </div>
                    <div class="tps-card__body">
                        <div class="advf-stage text-8797AB">
                            <div id="video-preview-box" class="video-preview-box">
                                <div class="bg--secondary rounded">
                                    <div class="video h-200">
                                        <video controls>
                                            {{ translate('Your browser does not support the video tag.') }}
                                        </video>
                                    </div>
                                    <div class="prev-video-box rounded bg-white px-3 py-4 position-relative gap-4 mt-n2">
                                        <div class="profile-img"></div>
                                        <div class="d-flex align-items-center justify-content-between gap-2">
                                            <div class="d-flex flex-column gap-2 flex-grow-1">
                                                <div class="preview-title w-100">
                                                    <h5 class="main-text pe-4">{{ translate('Title') }}</h5>
                                                    <div class="placeholder-text bg--secondary p-2 w-50"></div>
                                                </div>
                                                <div class="preview-description w-100">
                                                    <div class="main-text line-limit-2">{{ translate('messages.Description') }}</div>
                                                    <div class="placeholder-text bg--secondary p-2 w-75"></div>
                                                </div>
                                                <div class="preview-description w-100">
                                                    <div class="placeholder-text bg--secondary p-2 w-65"></div>
                                                </div>
                                            </div>
                                            <a class="btn btn--primary py-2 px-3 cursor-auto">
                                                <span class="tio-arrow-forward"></span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id="profile-preview-box" class="profile-preview-box">
                                <div class="bg--secondary rounded">
                                    <div class="main-image rounded min-h-200" style="background: url('') center center / cover no-repeat"></div>
                                    <div class="rounded bg-white px-3 py-4 position-relative mt-n2">
                                        <div class="preview-title preview-description">
                                            <div class="wishlist-btn bg--secondary placeholder-text"></div>
                                            <div class="static-text wishlist-btn-2" style="display: block;">
                                                <div class="h-100 w-100 d-flex align-items-center justify-content-center">
                                                    <i class="tio-heart-outlined"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-between gap-2">
                                            <div class="profile-prev-image bg--secondary me-xl-3" style="background: url('') center center / cover no-repeat"></div>
                                            <div class="review-rating-demo">
                                                <div class="rating-text static-text">
                                                    <div class="rating-number d-flex align-items-center">
                                                        <i class="tio-star"></i><span>{{ $rating }}</span>
                                                    </div>
                                                </div>
                                                <span class="review--text static-text">({{ $review }}+)</span>
                                            </div>
                                            <div class="w-0 d-flex flex-column gap-2 flex-grow-1">
                                                <div class="d-flex justify-content-between">
                                                    <div class="preview-title w-100">
                                                        <h5 class="main-text pe-4">{{ translate('Title') }}</h5>
                                                        <div class="placeholder-text bg--secondary p-2 w-50"></div>
                                                    </div>
                                                </div>
                                                <div class="preview-description w-100">
                                                    <div class="main-text line-limit-2">{{ translate('messages.Description') }}</div>
                                                    <div class="placeholder-text bg--secondary p-2 w-75"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tps-note tps-note--info mt-3">
                    <i class="tio-info-outined"></i>
                    <p>{{ translate('The preview updates as you type and upload. Your rating and reviews come from your store and cannot be edited here.') }}</p>
                </div>
            </div>
        </div>
    </form>

</div>
@endsection

@push('script_2')

    <script type="text/javascript" src="{{asset('public/assets/admin/js/moment.min.js')}}"></script>
    <script type="text/javascript" src="{{asset('public/assets/admin/js/daterangepicker.min.js')}}"></script>

    <script>
        "use strict";

        const advertisementStoreUrl = '{{ route('vendor.advertisement.store') }}';
        const advertisementListUrl = '{{ route('vendor.advertisement.index') }}?type=pending&new_ad=1';

        const selectedPromotionType = () => $('input[name="advertisement_type"]:checked').val();

        const handleUploadBox = () => {
            if (selectedPromotionType() === 'video_promotion') {
                $('#video-upload-box, #video-preview-box').show();
                $('#profile-upload-box, #profile-preview-box').hide();
            } else {
                $('#video-upload-box, #video-preview-box').hide();
                $('#profile-upload-box, #profile-preview-box').show();
            }
        };

        const resetTextHandlr = () => {
            $('[data-preview-text]').each(function () {
                const target = $(this).data('preview-text');
                const value = $(this).val();
                if (value) {
                    $('.' + target).each(function () {
                        $(this).find('.main-text').text(value);
                        $(this).find('.placeholder-text').hide();
                        $(this).find('.static-text').show();
                    });
                }
            });
        };

        const syncReviewRating = () => {
            const reviewOn = $('[name="review"]').is(':checked');
            const ratingOn = $('[name="rating"]').is(':checked');

            reviewOn ? $('.review--text').show() : $('.review--text').hide();
            ratingOn ? $('.rating-text').show() : $('.rating-text').hide();
            $('.review-rating-demo').css('opacity', reviewOn || ratingOn ? '1' : '0');
        };

        $(function () {
            $.ajaxSetup({
                headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
            });

            $('input[name="dates"]').daterangepicker({
                minDate: new Date(),
                startDate: moment().startOf('hour'),
                endDate: moment().startOf('hour').add(10, 'day'),
            });

            handleUploadBox();
            resetTextHandlr();
            syncReviewRating();
        });

        $('input[name="advertisement_type"]').on('change', function () {
            handleUploadBox();
            $('.remove-file-button').click();
        });

        $('[name="review"], [name="rating"]').on('change', syncReviewRating);

        $('[data-preview-text]').on('input', function (event) {
            const target = $(this).data('preview-text');

            $('.' + target).each(function () {
                if (event.target.value) {
                    $(this).find('.main-text').text(event.target.value);
                    $(this).find('.placeholder-text').hide();
                    $(this).find('.static-text').show();
                } else {
                    $(this).find('.main-text').text('');
                    $(this).find('.placeholder-text').show();
                    $(this).find('.static-text').hide();
                }
            });
        });

        $('.video_attachment').on('change', function (event) {
            const videoEl = $('.video > video');
            const file = event.target.files[0];
            if (!file) return;

            const blobURL = URL.createObjectURL(file);
            videoEl.css('display', 'block');
            videoEl.attr('src', blobURL);
            videoEl.siblings('.play-icon').hide();

            const uploadFile = $(this).closest('.upload-file');
            uploadFile.find('.upload-file__img').html('<video src="' + blobURL + '" controls></video>');
            uploadFile.find('.remove-file-button').show();
        });

        $('.js-upload-input').on('change', function (event) {
            const file = event.target.files[0];
            if (!file) return;

            const target = $(this).data('target');
            const blobURL = URL.createObjectURL(file);
            const uploadFile = $(this).closest('.upload-file');

            uploadFile.find('.upload-file__img').html('<img src="' + blobURL + '" alt="">');
            uploadFile.find('.remove-file-button').show();
            $('#profile-preview-box').find('.' + target).css('background', 'url(' + blobURL + ') no-repeat center center / cover');
        });

        $(document).on('click', '.remove-file-button', function () {
            const uploadFile = $(this).closest('.upload-file');
            const videoInput = uploadFile.find('.video_attachment');

            $(this).hide();

            if (videoInput.length) {
                const videoEl = $('.video > video');
                videoInput.val('');
                videoEl.css('display', 'none').attr('src', '');
                videoEl.siblings('.play-icon').show();
                uploadFile.find('.upload-file__img')
                    .html('<img src="{{asset('public/assets/admin/img/media/video-banner.png')}}" alt="">');
                return;
            }

            const uploadInput = uploadFile.find('.js-upload-input');
            const target = uploadInput.data('target');
            const placeholder = target === 'main-image'
                ? '{{asset('public/assets/admin/img/media/banner-upload-file.png')}}'
                : '{{asset('public/assets/admin/img/media/upload-file.png')}}';

            uploadInput.val('');
            uploadFile.find('.upload-file__img').html('<img src="' + placeholder + '" alt="">');
            $('#profile-preview-box').find('.' + target).css('background', 'rgba(117, 133, 144, 0.1)');
        });

        $('#create-add-form').on('reset', function () {
            window.location.reload();
        });

        $('#create-add-form').on('submit', function (event) {
            event.preventDefault();

            const $button = $(this).find('button[type="submit"]');
            $button.prop('disabled', true);

            $.post({
                url: advertisementStoreUrl,
                data: new FormData(this),
                cache: false,
                contentType: false,
                processData: false,
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    if (data.errors) {
                        $button.prop('disabled', false);
                        for (let i = 0; i < data.errors.length; i++) {
                            toastr.error(data.errors[i].message, {CloseButton: true, ProgressBar: true});
                        }
                        return;
                    }

                    toastr.success(data.message, {CloseButton: true, ProgressBar: true});
                    setTimeout(function () {
                        location.href = advertisementListUrl;
                    }, 2000);
                },
                error: function () {
                    $button.prop('disabled', false);
                },
                complete: function () {
                    $('#loading').hide();
                }
            });
        });
    </script>
@endpush
