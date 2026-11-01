@extends('layouts.admin.app')

@section('title', translate('messages.Add new main category'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
@endpush

@section('content')
    <div id="content-disable" class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/outline/category.svg') }}" class="w--26" alt="">
                </span>
                <span>
                    {{ translate('Add Main Category') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('A main category is a top-level group customers browse in the app. subcategories and items sit under it.') }}</p>
        </div>

        <div class="row g-3">
            <div class="col-12 tps">
                <div class="tps-card">
                    {{-- custom-validation + .error-wrapper: the shared jQuery Validate layer, so a
                         missing default name, image or tax rate fails inline instead of after a
                         round trip — and it reveals the language tab an error is hiding behind.
                         CategoryAddRequest still enforces name and image server side. --}}
                    <form action="{{ route('admin.category.store') }}" method="post"
                        enctype="multipart/form-data" class="custom-validation"
                        id="category-add-form"
                        data-ajax-form
                        data-ajax-refresh="[data-ajax-region]"
                        data-ajax-reset>
                        @csrf
                        <input type="hidden" name="position" value="0">

                        <div class="tps-card__body">
                            <div class="row g-3">
                                <div class="col-lg-8">
                                    <div class="tps-group">
                                        <p class="tps-group__label">{{ translate('Category name') }}</p>

                                        @if ($language)
                                            <ul class="nav nav-tabs mb-3 border-0">
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

                                            <div class="lang_form" id="default-form">
                                                <div class="tps-field">
                                                    <div class="error-wrapper">
                                                        <label class="tps-field__label" for="default_name">
                                                            {{ translate('Name') }}
                                                            ({{ translate('Default') }})
                                                            <span class="tps-req" data-toggle="tooltip"
                                                                data-placement="right"
                                                                data-original-title="{{ translate('messages.Required.') }}">*</span>
                                                        </label>
                                                        <input type="text" name="name[]" id="default_name"
                                                            value="{{ old('name.0') }}" class="form-control"
                                                            placeholder="{{ translate('messages.Ex') }}: Beverages"
                                                            maxlength="191" required>
                                                    </div>
                                                    <small class="tps-field__hint">
                                                        {{ translate('This is the name customers see. Two or three words read best on a phone.') }}
                                                    </small>
                                                </div>
                                            </div>
                                            <input type="hidden" name="lang[]" value="default">

                                            @foreach ($language as $key => $lang)
                                                <div class="d-none lang_form" id="{{ $lang }}-form">
                                                    <div class="tps-field">
                                                        <label class="tps-field__label" for="{{ $lang }}_name">
                                                            {{ translate('Name') }} ({{ strtoupper($lang) }})
                                                            <span class="tps-opt">{{ translate('Optional') }}</span>
                                                        </label>
                                                        <input type="text" name="name[]" id="{{ $lang }}_name"
                                                            value="{{ old('name.' . $key + 1) }}" class="form-control"
                                                            placeholder="{{ translate('messages.New main category') }}"
                                                            maxlength="191">
                                                        <small class="tps-field__hint">
                                                            {{ translate('Leave it empty to fall back to the default name.') }}
                                                        </small>
                                                    </div>
                                                </div>
                                                <input type="hidden" name="lang[]" value="{{ $lang }}">
                                            @endforeach
                                        @else
                                            <div id="default-form">
                                                <div class="tps-field">
                                                    <div class="error-wrapper">
                                                        <label class="tps-field__label" for="default_name">
                                                            {{ translate('Name') }}
                                                            <span class="tps-req">*</span>
                                                        </label>
                                                        <input type="text" name="name" id="default_name"
                                                            class="form-control"
                                                            placeholder="{{ translate('messages.Ex') }}: Beverages"
                                                            value="{{ old('name') }}" maxlength="191" required>
                                                    </div>
                                                    <small class="tps-field__hint">
                                                        {{ translate('This is the name customers see. Two or three words read best on a phone.') }}
                                                    </small>
                                                </div>
                                            </div>
                                            <input type="hidden" name="lang[]" value="default">
                                        @endif
                                    </div>

                                    <div class="tps-group">
                                        <p class="tps-group__label">{{ translate('Placement') }}</p>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="tps-field">
                                                    <label class="tps-field__label" for="priority">
                                                        {{ translate('messages.Priority') }}
                                                    </label>
                                                    <select required name="priority" id="priority" class="custom-select">
                                                        <option value="0">{{ translate('messages.Normal') }}</option>
                                                        <option value="1">{{ translate('messages.medium') }}</option>
                                                        <option value="2">{{ translate('messages.High') }}</option>
                                                    </select>
                                                    <small class="tps-field__hint">
                                                        {{ translate('High priority categories are listed first in the app, then medium, then normal.') }}
                                                    </small>
                                                </div>
                                            </div>
                                            @if ($categoryWiseTax)
                                                <div class="col-md-6">
                                                    <div class="tps-field">
                                                        <div class="error-wrapper">
                                                            <label class="tps-field__label" for="tax__rate">
                                                                {{ translate('Select tax rate') }}
                                                                <span class="tps-req">*</span>
                                                            </label>
                                                            <select name="tax_ids[]" id="tax__rate"
                                                                class="form-control js-select2-custom js-select2-counting"
                                                                multiple="multiple" required
                                                                data-placeholder="{{ translate('Type & select tax rate') }}">
                                                                @foreach ($taxVats as $taxVat)
                                                                    <option value="{{ $taxVat->id }}">
                                                                        {{ $taxVat->name }} ({{ $taxVat->tax_rate }}%)
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <small class="tps-field__hint">
                                                            {{ translate('Every item in this category is taxed at the rates you pick here.') }}
                                                        </small>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <div class="tps-group h-100">
                                        <p class="tps-group__label">{{ translate('Artwork') }}</p>
                                        <div class="error-wrapper bg-light rounded p-20 text-center">
                                            <div class="mb-3">
                                                <h6 class="mb-1">{{ translate('Main Category Image') }}
                                                    <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                                        data-original-title="{{ translate('messages.Required.') }}">*</span>
                                                </h6>
                                                <p class="mb-0 fs-12 text-muted">
                                                    {{ translate('Square artwork, shown on the category tile.') }}
                                                </p>
                                            </div>
                                            @include('admin-views.partials._image-uploader', [
                                                'id' => 'category-image-input',
                                                'name' => 'image',
                                                'ratio' => '1:1',
                                                'isRequired' => true,
                                                'existingImage' => '',
                                                'imageExtension' => IMAGE_EXTENSION,
                                                'imageFormat' => IMAGE_FORMAT,
                                                'maxSize' => MAX_FILE_SIZE,
                                                'textPosition' => 'bottom',
                                            ])
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tps-card__foot">
                            <span class="tps-foot-note">
                                {{ translate('A main category name can only be used once in this module.') }}
                            </span>
                            <button type="reset" id="reset_btn" class="btn btn--reset"><i class="tio-refresh"></i>
                                {{ translate('messages.Reset') }}</button>
                            <button type="submit" class="btn btn--primary"><i class="tio-add-circle"></i>
                                {{ translate('Add') }}</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-12">
                <div id="category-list-wrapper" data-ajax-region
                    data-ajax-url="{{ url()->full() }}"
                    data-ajax-links=".nav-link, .page-link, .list-reset-search"
                    data-ajax-forms=".search-form">
                    @include('admin-views.category.partials._list-main', [
                        'categories' => $categories,
                        'status' => $status,
                        'categoryWiseTax' => $categoryWiseTax,
                        'subCategoryCounts' => $subCategoryCounts,
                        'itemCounts' => $itemCounts,
                        'translatedLocales' => $translatedLocales,
                    ])
                </div>
            </div>
        </div>
    </div>

    <div id="offcanvas__categoryBtn" class="custom-offcanvas d-flex flex-column justify-content-between">
        <div id="data-view" class="h-100">
        </div>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>

@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin') }}/js/view-pages/category-index.js"></script>
    <script>
        "use strict";

        // The All/Active/Inactive list tabs (and search/pagination) are plain links that fully
        // reload this page. On every reload the language tab markup hardcodes "Default" as
        // active, so switching a list tab looked like it was resetting whichever language
        // (EN/AR) tab was selected above. Remember the selected language tab across the reload
        // and restore it here — independent of common.js's own click handler (which attaches its
        // listener in a later DOMContentLoaded callback than this one, so re-dispatching a click
        // here would fire before that handler exists).
        (function() {
            const STORAGE_KEY = 'admin_category_lang_tab_main';

            document.addEventListener('DOMContentLoaded', function() {
                const langLinks = document.querySelectorAll('.lang_link');
                if (!langLinks.length) return;

                langLinks.forEach(function(link) {
                    link.addEventListener('click', function() {
                        sessionStorage.setItem(STORAGE_KEY, this.id);
                    });
                });

                const savedId = sessionStorage.getItem(STORAGE_KEY);
                if (!savedId || savedId === 'default-link') return;

                const savedLink = document.getElementById(savedId);
                if (!savedLink) return;

                langLinks.forEach(function(link) {
                    link.classList.remove('active');
                });
                savedLink.classList.add('active');

                document.querySelectorAll('.lang_form').forEach(function(form) {
                    form.classList.add('d-none');
                });

                const lang = savedId.split('-link')[0];
                const form = document.getElementById(lang + '-form');
                if (form) {
                    form.classList.remove('d-none');
                }
            });
        })();


        // Reset only clears what the browser cannot: select2 keeps its rendered chips, and the
        // image uploader is reset by the delegated button[type=reset] handler in
        // upload-single-image.js.
        $('#reset_btn').on('click', function() {
            $('#tax__rate').val(null).trigger('change');
        });


        $(document).on('click', '.data-info-show', function() {
            let id = $(this).data('id');
            let url = $(this).data('url');
            $($(this).data('target')).addClass('open');
            $('#offcanvasOverlay').addClass('show');
            $('body').addClass('modal-open');
            $('#content-disable').addClass('disabled');
            fetch_data(id, url)
        })

        $(document).on('ajax:success', '#data-view form', function() {
            $('#content-disable').removeClass('disabled');
        });

        $(document).on('ajax:success', '#category-add-form', function() {
            $(this).find('.upload-file_custom').each(function() {
                if (typeof resetFileUpload === 'function') {
                    resetFileUpload($(this));
                }
            });
        });

        function fetch_data(id, url) {
            $.ajax({
                url: url,
                type: "get",
                beforeSend: function() {
                    $('#data-view').empty();
                    $('#loading').show()
                },
                success: function(data) {
                    $("#data-view").append(data.view);
                    initLangTabs();
                    initSelect2Dropdowns();
                    if (typeof checkPreExistingImages === 'function') {
                        checkPreExistingImages();
                    }
                },
                complete: function() {
                    $('#loading').hide()
                }
            })
        }


        function initLangTabs() {
            const langLinks = document.querySelectorAll(".lang_link1");
            langLinks.forEach(function(langLink) {
                langLink.addEventListener("click", function(e) {
                    e.preventDefault();
                    langLinks.forEach(function(link) {
                        link.classList.remove("active");
                    });
                    this.classList.add("active");
                    document.querySelectorAll(".lang_form1").forEach(function(form) {
                        form.classList.add("d-none");
                    });
                    let form_id = this.id;
                    let lang = form_id.substring(0, form_id.length - 5);
                    $("#" + lang + "-form1").removeClass("d-none");
                    if (lang === "default") {
                        $(".default-form1").removeClass("d-none");
                    }
                });
            });
        }

        function initSelect2Dropdowns() {
            $('.js-select2-custom1').select2({
                placeholder: '{{ translate('Select tax rate') }}',
                allowClear: true
            });

            $('.offcanvas-close, #offcanvasOverlay').on('click', function() {
                $('.custom-offcanvas').removeClass('open');
                $('#offcanvasOverlay').removeClass('show');
                $('#content-disable').removeClass('disabled');
            });
        }
    </script>
@endpush
