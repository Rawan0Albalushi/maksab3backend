@extends('layouts.admin.app')

@section('title',translate('messages.Add new main sub category'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- The edit offcanvas (admin-views.category._edit, shared with the main category page)
         is built from the .tps vocabulary, so this page has to load the sheet too. --}}
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
                    {{ translate('messages.Add new main sub category') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('A subcategory sits under a main one and narrows what customers are browsing.') }}</p>
        </div>

        <div class="row g-3">
            <div class="col-12 tps">
                <div class="tps-card">
                    {{-- custom-validation + .error-wrapper: the shared jQuery Validate layer, so a
                         missing default name or main category fails inline instead of after a round
                         trip — and it reveals the language tab an error is hiding behind.
                         CategoryAddRequest still enforces the name server side. --}}
                    <form action="{{ route('admin.category.store') }}" method="post" class="custom-validation"
                        id="sub-category-add-form"
                        data-ajax-form
                        data-ajax-refresh="[data-ajax-region]"
                        data-ajax-reset>
                        @csrf
                        <input type="hidden" name="position" value="1">


                        {{-- One column, not the 8/4 split the main category form uses: that split
                             is there to sit the image uploader beside the name, and this form has
                             no image. Without it the two selects floated at heights that lined up
                             with nothing on the left. Placement is a full-width row underneath
                             instead, so both selects share a baseline. --}}
                        <div class="tps-card__body">
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
                                                            placeholder="{{ translate('messages.Ex') }}: Soft drinks"
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
                                                            placeholder="{{ translate('messages.New main sub category') }}"
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
                                                            placeholder="{{ translate('messages.Ex') }}: Soft drinks"
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
                                            <div class="error-wrapper">
                                                <label class="tps-field__label" for="exampleFormControlSelect1">
                                                    {{ translate('Main category') }}
                                                    <span class="tps-req">*</span>
                                                </label>
                                                <select id="exampleFormControlSelect1" name="parent_id"
                                                    class="form-control js-select2-custom" required>
                                                    <option value="" selected disabled>
                                                        {{ translate('Select main category') }}</option>
                                                    @foreach ($mainCategories as $category)
                                                        <option value="{{ $category['id'] }}">{{ $category['name'] }}
                                                            ({{ Str::limit(Config::get('module.current_module_name'), 15, '...') }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <small class="tps-field__hint">
                                                {{ translate('The main category this one is nested under. It cannot be left empty.') }}
                                            </small>
                                        </div>
                                    </div>
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
                                </div>
                            </div>
                        </div>

                        <div class="tps-card__foot">
                            <span class="tps-foot-note">
                                {{ translate('A subcategory name can only be used once under the same main category.') }}
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
                <div id="subcategory-list-wrapper" data-ajax-region
                    data-ajax-url="{{ url()->full() }}"
                    data-ajax-links=".nav-link, .page-link, .list-reset-search"
                    data-ajax-forms=".search-form">
                    @include('admin-views.category.partials._list-sub', [
                        'categories' => $categories,
                        'status' => $status,
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
    <script src="{{asset('public/assets/admin')}}/js/view-pages/sub-category-index.js"></script>
    <script>
        "use strict";

        $(document).on('ajax:success', '#data-view form', function() {
            $('#content-disable').removeClass('disabled');
        });

        // Same fix as the main category page: the All/Active/Inactive tabs (and search/pagination)
        // are plain links that fully reload this page, and the language tab markup hardcodes
        // "Default" as active on every reload, making it look like switching a list tab reset the
        // selected EN/AR language tab. Remember and restore the selected tab across the reload.
        (function() {
            const STORAGE_KEY = 'admin_category_lang_tab_sub';

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
    </script>
@endpush
