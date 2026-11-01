@extends('layouts.admin.app')

@section('title', translate('React landing page'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">

@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header pb-0">
            <div class="d-flex flex-wrap justify-content-between">
                <div>
                    <h1 class="page-header-title">
                    <span class="page-header-icon">
                        <img src="{{asset('public/assets/admin/img/outline/landing.svg')}}" class="w--26" alt="">
                    </span>
                        <span>
                        {{ translate('React landing page') }}
                    </span>
                    </h1>
                    <p class="page-header-desc">{{ translate('The client logos shown as proof on the react landing page.') }}</p>
                </div>
                <div class="text--primary-2 py-1 d-flex flex-wrap align-items-center" type="button" data-toggle="modal"
                     data-target="#how-it-works">
                    <strong class="mr-2">{{translate('See how it works')}}</strong>
                    <div>
                        <i class="tio-info-outined"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="mb-20 mt-2">
            <div class="js-nav-scroller hs-nav-scroller-horizontal">
                @include('admin-views.business-settings.landing-page-settings.top-menu-links.react-landing-page-links')
            </div>
        </div>
        <div class="card py-3 px-xxl-4 px-3 mb-20">
            <div class="d-flex flex-sm-nowrap flex-wrap gap-3 align-items-center justify-content-between">
                <div class="">
                    <h3 class="mb-1">{{ translate('Popular Clients Section') }}</h3>
                    <p class="mb-0 gray-dark fs-12">
                        {{ translate('See how this section will look to customers.') }}
                    </p>
                </div>
                <div class="max-w-300px ml-sm-auto">
                    <button type="button" class="btn btn-outline-primary py-2 fs-12 px-3 offcanvas-trigger"
                            data-target="#clients_section">
                        <i class="tio-invisible"></i> {{ translate('Section Preview') }}
                    </button>
                </div>
            </div>
        </div>
        @php($popular_client_section_status = \App\Models\DataSetting::where('type', 'react_landing_page')->where('key', "popular_client_section_status")->first())
        <div class="card py-3 px-xxl-4 px-3 mb-15 mt-4">
            <div class="row g-3 align-items-center justify-content-between">
                <div class="col-xxl-9 col-lg-8 col-md-7 col-sm-6">
                    <div class="">
                        <h3 class="mb-1">{{ translate('Show Popular Client Section') }}</h3>
                        <p class="mb-0 gray-dark fs-12">
                            {{ translate('If you turn off the availability status, this section will not show on the website') }}
                        </p>
                    </div>
                </div>
                <div class="col-xxl-3 col-lg-4 col-md-5 col-sm-6">
                    <div class="py-2 px-3 rounded d-flex justify-content-between border align-items-center w-300">
                        <h5 class="text-capitalize fw-normal mb-0">{{ translate('Status') }}</h5>

                        <form
                            action="{{ route('admin.business-settings.statusUpdate', ['type' => 'react_landing_page', 'key' => 'popular_client_section_status']) }}"
                            method="get" id="CheckboxStatus_form">
                        </form>
                        <label class="toggle-switch toggle-switch-sm" for="CheckboxStatus">
                            <input type="checkbox" data-id="CheckboxStatus" data-type="status"
                                   data-image-on="{{ asset('/public/assets/admin/img/status-ons.png') }}"
                                   data-image-off="{{ asset('/public/assets/admin/img/off-danger.png') }}"
                                   data-title-on="{{ translate('Do you want to turn on this section?') }}"
                                   data-title-off="{{ translate('Do you want to turn off this section?') }}"
                                   data-text-on="<p>{{ translate('If you turn this on, the section will be shown on the React landing page.') }}"
                                   data-text-off="<p>{{ translate('If you turn this off, the section will not be shown on the React landing page.') }}</p>"
                                   class="toggle-switch-input  status dynamic-checkbox" id="CheckboxStatus"
                                {{ $popular_client_section_status?->value ? 'checked' : '' }}>
                            <span class="toggle-switch-label text">
                                <span class="toggle-switch-indicator"></span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-20">
            <form action="{{ route('admin.business-settings.react-landing-page-settings-update', 'popular-client-section') }}"
                  method="POST" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    <div class="mb-20">
                        <h3 class="mb-1">{{ translate('Popular Clients Section Content') }}</h3>
                        <p class="mb-0 gray-dark fs-12">
                            {{ translate('Showcase your top clients and partners to build trust and credibility.') }}
                        </p>
                    </div>
                    @php($language = \App\CentralLogics\Helpers::get_business_settings('language', false) ?? null)
                    @php($popular_client_title = \App\Models\DataSetting::withoutGlobalScope('translate')->where('type', 'react_landing_page')->where('key', 'popular_client_title')->first())
                    @php($popular_client_sub_title = \App\Models\DataSetting::withoutGlobalScope('translate')->where('type', 'react_landing_page')->where('key', 'popular_client_sub_title')->first())
                    <?php

                    use App\CentralLogics\Helpers;
                    use App\Models\DataSetting;

                    $popularClientImages = DataSetting::withStorage()->where('type', 'react_landing_page')
                        ->where('key', 'popular_client_image')
                        ->whereNotNull('value')
                        ->where('value', '!=', '0')
                        ->get()
                        ->map(function ($item) {
                            return [
                                'url' => Helpers::get_full_url('popular_client_section', $item->value,$item?->storage[0]?->value ?? 'public' ,'react_landing_page'),
                                'filename' => $item->value,
                                'path' => 'popular_client_section/'
                            ];
                        })
                        ->toArray();
                    ?>
                    <div class="bg--secondary h-100 rounded p-md-4 p-3">
                        @if($language)
                            <ul class="nav nav-tabs mb-4 border-0">
                                <li class="nav-item">
                                    <a class="nav-link lang_link active" href="#"
                                       id="default-link">{{translate('Default')}}</a>
                                </li>
                                @foreach (json_decode($language) as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link lang_link" href="#"
                                           id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <div class="row g-3">
                            @if ($language)
                                <div class="col-md-12 lang_form default-form">
                                    <div class="row g-1">
                                        <div class="col-12">
                                            <label for="popular_client_title"
                                                   class="form-label">{{translate('Title')}}
                                                ({{ translate('Default') }})
                                                <span class="form-label-secondary" data-toggle="tooltip"
                                                      data-placement="right"
                                                      data-original-title="{{ translate('Character limit') }}: 100">
                                                    <i class="tio-info color-A7A7A7"></i>
                                                </span><span class="form-label-secondary text-danger"
                                                             data-toggle="tooltip" data-placement="right"
                                                             data-original-title="{{ translate('messages.Required.')}}"> *
                                                </span>
                                            </label>
                                            <input id="popular_client_title" type="text" maxlength="100"
                                                   name="popular_client_title[]"
                                                   value="{{ $popular_client_title?->getRawOriginal('value') ?? '' }}"
                                                   class="form-control"
                                                   placeholder="{{translate('Enter title')}}">
                                            <span
                                                class="text-right text-counting color-A7A7A7 d-block mt-1">0/50</span>
                                        </div>
                                        <div class="col-12">
                                            <label for="popular_client_sub_title"
                                                   class="form-label">{{translate('Sub Title')}}
                                                ({{ translate('Default') }})
                                                <span class="form-label-secondary" data-toggle="tooltip"
                                                      data-placement="right"
                                                      data-original-title="{{ translate('Character limit') }}: 200">
                                                    <i class="tio-info color-A7A7A7"></i>
                                                </span><span class="form-label-secondary text-danger"
                                                             data-toggle="tooltip" data-placement="right"
                                                             data-original-title="{{ translate('messages.Required.')}}"> *
                                                </span>
                                            </label>
                                            <input id="popular_client_sub_title" type="text" maxlength="200"
                                                   name="popular_client_sub_title[]"
                                                   value="{{ $popular_client_sub_title?->getRawOriginal('value') ?? '' }}"
                                                   class="form-control"
                                                   placeholder="{{translate('Enter subtitle')}}">
                                            <span
                                                class="text-right text-counting color-A7A7A7 d-block mt-1">0/200</span>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="lang[]" value="default">

                                @foreach(json_decode($language) as $lang)
                                        <?php
                                        $popular_client_title_translate = [];
                                        $popular_client_sub_title_translate = [];

                                        if (isset($popular_client_title->translations) && count($popular_client_title->translations)) {
                                            foreach ($popular_client_title->translations as $t) {
                                                if ($t->locale == $lang && $t->key == 'popular_client_title') {
                                                    $popular_client_title_translate[$lang]['value'] = $t->value;
                                                }
                                            }
                                        }

                                        if (isset($popular_client_sub_title->translations) && count($popular_client_sub_title->translations)) {
                                            foreach ($popular_client_sub_title->translations as $t) {
                                                if ($t->locale == $lang && $t->key == 'popular_client_sub_title') {
                                                    $popular_client_sub_title_translate[$lang]['value'] = $t->value;
                                                }
                                            }
                                        }
                                        ?>
                                    <div class="col-md-12 d-none lang_form" id="{{$lang}}-form">
                                        <div class="row g-1">
                                            <div class="col-12">
                                                <label for="popular_client_title{{$lang}}"
                                                       class="form-label">{{translate('Title')}}
                                                    ({{strtoupper($lang)}})<span class="form-label-secondary"
                                                                                 data-toggle="tooltip"
                                                                                 data-placement="right"
                                                                                 data-original-title="{{ translate('Character limit') }}: 100">
                                                        <i class="tio-info color-A7A7A7"></i>
                                                    </span>
                                                </label>
                                                <input id="popular_client_title{{$lang}}" type="text" maxlength="100"
                                                       name="popular_client_title[]"
                                                       value="{{ $popular_client_title_translate[$lang]['value'] ?? '' }}"
                                                       class="form-control"
                                                       placeholder="{{translate('Enter title')}}">
                                                <span
                                                    class="text-right text-counting color-A7A7A7 d-block mt-1">0/100</span>
                                            </div>
                                            <div class="col-12">
                                                <label for="popular_client_sub_title{{$lang}}"
                                                       class="form-label">{{translate('Sub Title')}}
                                                    ({{strtoupper($lang)}})<span class="form-label-secondary"
                                                                                 data-toggle="tooltip"
                                                                                 data-placement="right"
                                                                                 data-original-title="{{ translate('Character limit') }}: 200">
                                                        <i class="tio-info color-A7A7A7"></i>
                                                    </span>
                                                </label>
                                                <input id="popular_client_sub_title{{$lang}}" type="text"
                                                       maxlength="200"
                                                       name="popular_client_sub_title[]"
                                                       value="{{ $popular_client_sub_title_translate[$lang]['value'] ?? '' }}"
                                                       class="form-control"
                                                       placeholder="{{translate('Enter subtitle')}}">
                                                <span
                                                    class="text-right text-counting color-A7A7A7 d-block mt-1">0/200</span>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{$lang}}">
                                @endforeach
                            @else
                                <div class="col-12">
                                    <div class="mb-2">
                                        <label for="popular_client_title"
                                               class="form-label">{{translate('Title')}}</label>
                                        <input id="popular_client_title" maxlength="100" type="text"
                                               name="popular_client_title[]" class="form-control"
                                               placeholder="{{translate('Enter title')}}">
                                        <span
                                            class="text-right text-counting color-A7A7A7 d-block mt-1">0/100</span>
                                    </div>
                                    <div class="mb-4">
                                        <label for="popular_client_sub_title"
                                               class="form-label">{{translate('Sub Title')}}</label>
                                        <input id="popular_client_sub_title" maxlength="200" type="text"
                                               name="popular_client_sub_title[]" class="form-control"
                                               placeholder="{{translate('Enter subtitle')}}">
                                        <span
                                            class="text-right text-counting color-A7A7A7 d-block mt-1">0/200</span>
                                    </div>
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                            @endif
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <div class="">
                                <h3 class="mb-1">{{ translate('Clients Section Image') }}</h3>
                                <p class="mb-0 gray-dark fs-12">
                                    {{ translate('Showcase your top clients and partners to build trust and credibility.') }}
                                </p>
                            </div>
                        </div>
                        <div class="card-body">




                            <div class="bg--secondary h-100 rounded p-md-4 p-3">
                                <div class="mb-20">
                                    <h5 class="mb-1">{{ translate('Clients Section Image') }}</h5>
                                    <p class="mb-0 gray-dark fs-12">
                                        {{ 'JPG, JPEG, PNG, GIF' . ' image, max ' . 2 . ' MB' }}
                                    </p>
                                </div>
                                <div class="d-flex spartan_customize_style flex-wrap __gap-12px __new-coba" id="coba">

                                </div>
                                <div id="removed-images-container"></div>
                            </div>
                        </div>
                    </div>
                    <div class="btn--container justify-content-end mt-20">
                        <button type="reset" class="btn btn--reset mb-2"><i class="tio-refresh"></i> {{translate('Reset')}}</button>
                        <button type="submit" class="btn btn--primary mb-2"><i class="tio-save"></i> {{translate('Save')}}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="clients_section" class="custom-offcanvas offcanvas-750 d-flex flex-column justify-content-between">
        <form action="{{ route('taxvat.store') }}" method="post">
            <div>
                <div
                    class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
                    <div class="py-1">
                        <h3 class="mb-0 line--limit-1">{{ translate('messages.Popular Clients Section Preview') }}</h3>
                    </div>
                    <button type="button"
                            class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary text-dark offcanvas-close fz-15px p-0"
                            aria-label="Close">
                        &times;
                    </button>
                </div>
                <div class="custom-offcanvas-body custom-offcanvas-body-100  p-20">
                    <section class="common-section-view bg-white border rounded-10">
                        <div class="mb-4 text-center">
                            <h2 class="mb-lg-1 mb-1 fs-24">
                                {!! \App\CentralLogics\Helpers::highlightWords($popular_client_title?->value ?? 'Our Popular $Clients$') !!}
                            </h2>
                            <p class="text-title fs-14 m-0">
                                {{$popular_client_sub_title?->value ?? 'Trusted by leading brands for fast and reliable delivery services.'}}
                            </p>
                        </div>
                        <div class="common-carousel-wrapper position-relative">
                            @if(!empty($popularClientImages))
                                <div class="clients-preview-slide owl-theme owl-carousel">
                                    @foreach ($popularClientImages as $popular_client_image)
                                        <div class="items__">
                                            <div class="p-xxl-3 p-2 d-center h-135px bg--secondary rounded">
                                                <img width="110" height="100"
                                                     src="{{ $popular_client_image['url'] ?? asset('/public/assets/admin/img/400x400/react-new-slide1.jpg') }}"
                                                     alt="" class="rounded">
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="clients-preview-slide owl-theme owl-carousel">
                                    <div class="items__">
                                        <div class="p-xxl-3 p-2 d-center h-135px bg--secondary rounded">
                                            <img wdith="110" height="100"
                                                 src="{{ asset('/public/assets/admin/img/400x400/react-new-slide1.jpg') }}"
                                                 alt="" class="rounded">
                                        </div>
                                    </div>
                                    <div class="items__">
                                        <div class="p-xxl-3 p-2 d-center h-135px bg--secondary rounded">
                                            <img wdith="110" height="100"
                                                 src="{{ asset('/public/assets/admin/img/400x400/react-new-slide2.jpg') }}"
                                                 alt="" class="rounded">
                                        </div>
                                    </div>
                                    <div class="items__">
                                        <div class="p-xxl-3 p-2 d-center h-135px bg--secondary rounded">
                                            <img wdith="110" height="100"
                                                 src="{{ asset('/public/assets/admin/img/400x400/react-new-slide3.jpg') }}"
                                                 alt="" class="rounded">
                                        </div>
                                    </div>
                                    <div class="items__">
                                        <div class="p-xxl-3 p-2 d-center h-135px bg--secondary rounded">
                                            <img wdith="110" height="100"
                                                 src="{{ asset('/public/assets/admin/img/400x400/react-new-slide4.jpg') }}"
                                                 alt="" class="rounded">
                                        </div>
                                    </div>
                                    <div class="items__">
                                        <div class="p-xxl-3 p-2 d-center h-135px bg--secondary rounded">
                                            <img wdith="110" height="100"
                                                 src="{{ asset('/public/assets/admin/img/400x400/react-new-slide5.jpg') }}"
                                                 alt="" class="rounded">
                                        </div>
                                    </div>
                                    <div class="items__">
                                        <div class="p-xxl-3 p-2 d-center h-135px bg--secondary rounded">
                                            <img wdith="110" height="100"
                                                 src="{{ asset('/public/assets/admin/img/400x400/react-new-slide6.jpg') }}"
                                                 alt="" class="rounded">
                                        </div>
                                    </div>
                                    <div class="items__">
                                        <div class="p-xxl-3 p-2 d-center h-135px bg--secondary rounded">
                                            <img wdith="110" height="100"
                                                 src="{{ asset('/public/assets/admin/img/400x400/react-new-slide7.jpg') }}"
                                                 alt="" class="rounded">
                                        </div>
                                    </div>
                                    <div class="items__">
                                        <div class="p-xxl-3 p-2 d-center h-135px bg--secondary rounded">
                                            <img wdith="110" height="100"
                                                 src="{{ asset('/public/assets/admin/img/400x400/react-new-slide8.jpg') }}"
                                                 alt="" class="rounded">
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <div class="custom-owl-nav z-2">
                                <button type="button" class="custom-prev__ btn border-0 outline-none p-2"><i
                                        class="tio-chevron-left"></i></button>
                                <button type="button" class="custom-next__ btn border-0 outline-none p-2"><i
                                        class="tio-chevron-right"></i></button>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </form>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>
@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/spartan-multi-image-picker.js') }}"></script>
    <script>
        $(function () {
            let existingImages = @json($popularClientImages);

            $("#coba").spartanMultiImagePicker({
                fieldName: 'image[]',
                maxCount: 12,
                rowHeight: '176px',
                groupClassName: 'spartan_item_wrapper',
                placeholderImage: {
                    image: '{{ asset('public/assets/admin/img/new-component.png') }}',
                    width: '100%',
                    style: 'object-fit: cover;'
                },
                dropFileLabel: "Drop file here or click to upload"
            });

            if (existingImages.length > 0) {
                const $wrapper = $('#coba');

                existingImages.forEach((img) => {
                    const html = `
        <div class="spartan_item_wrapper">
            <div class="spartan_item" style="position: relative; border-radius:10px;">
                <img src="${img.url}" style="width:100%; height:132px; object-fit:cover;">
                <button type="button" class="spartan_delete spartan_remove_row_edit"
                        data-image="${img.url}" data-filename="${img.filename}"
                        style="position:absolute; top:5px; right:5px; z-index:10;">
                    <i class="tio-add-to-trash"></i>
                </button>
            </div>
        </div>
        `;
                    $wrapper.prepend(html);
                });
            }

            $(document).on('click', '.spartan_delete', function () {
                const filename = $(this).data('filename');
                if (filename) {
                    $('#removed-images-container').append(
                        `<input type="hidden" name="remove_existing_images[]" value="${filename}">`
                    );
                }
                $(this).closest('.spartan_item_wrapper').remove();
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.remove_btn').forEach(function (button) {
                button.addEventListener('click', function () {
                    const cardNumber = this.dataset.card;
                    const input = document.getElementById('popular_client_image_card_' + cardNumber);
                    if (input) {
                        input.disabled = false;
                    }
                });
            });
        });
    </script>
@endpush
