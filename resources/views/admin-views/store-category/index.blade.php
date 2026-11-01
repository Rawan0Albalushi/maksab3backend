@extends('layouts.admin.app')

@section('title', \App\CentralLogics\Helpers::moduleStoreLabel() . ' ' . translate('Categories'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/store-category.css') }}">
@endpush

@section('content')
    @php
        $store_label = \App\CentralLogics\Helpers::moduleStoreLabel();
        $items_linkable = config('module.current_module_type') !== 'service';
        $drawer_clear = request()->fullUrlWithoutQuery(['store_id', 'priority', 'status', 'usage', 'page']);
    @endphp

    <div id="content-disable" class="content container-fluid stc">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/outline/category.svg') }}" class="w--26" alt="">
                </span>
                <span>{{ $store_label . ' ' . translate('Categories') }}</span>
            </h1>
            <p class="page-header-desc">{{ translate('The categories stores are filed under, which decides where customers find them.') }}</p>
        </div>

        <div class="tps mb-3">
            <div class="tps-card">
                <form action="{{ route('admin.store-category.store') }}" method="post" enctype="multipart/form-data"
                    class="custom-validation"
                    id="store-category-add-form"
                    data-ajax-form
                    data-ajax-refresh="[data-ajax-region]"
                    data-ajax-reset>
                    @csrf
                    <div class="tps-card__body">
                        <div class="row g-3">
                            <div class="col-lg-8">
                                <div class="tps-group">
                                    <p class="tps-group__label">{{ translate('Category name') }}</p>

                                    @if ($language)
                                        <ul class="nav nav-tabs mb-3 border-0">
                                            <li class="nav-item">
                                                <a class="nav-link lang_link active" href="#" id="default-link">{{ translate('Default') }}</a>
                                            </li>
                                            @foreach ($language as $lang)
                                                <li class="nav-item">
                                                    <a class="nav-link lang_link" href="#" id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                                </li>
                                            @endforeach
                                        </ul>

                                        <div class="lang_form" id="default-form">
                                            <div class="tps-field">
                                                <div class="error-wrapper">
                                                    <label class="tps-field__label" for="default_name">
                                                        {{ translate('Name') }} ({{ translate('Default') }})
                                                        <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                                            data-original-title="{{ translate('messages.Required.') }}">*</span>
                                                    </label>
                                                    <input type="text" name="name[]" id="default_name" value="{{ old('name.0') }}"
                                                        class="form-control" placeholder="{{ translate('messages.Ex') }}: Beverages"
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
                                                        placeholder="{{ translate('messages.Ex') }}: Beverages" maxlength="191">
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
                                                    <input type="text" name="name[]" id="default_name" class="form-control"
                                                        placeholder="{{ translate('messages.Ex') }}: Beverages"
                                                        value="{{ old('name.0') }}" maxlength="191" required>
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
                                                    <label class="tps-field__label" for="store_id">
                                                        {{ $store_label }}
                                                        <span class="tps-req">*</span>
                                                    </label>
                                                    <select required name="store_id" id="store_id"
                                                        class="form-control js-store-select2-ajax"
                                                        data-placeholder="{{ translate('Select') . ' ' . $store_label }}"></select>
                                                </div>
                                                <small class="tps-field__hint">
                                                    {{ translate('messages.A category belongs to one store and groups what that store sells.') }}
                                                </small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="tps-field">
                                                <label class="tps-field__label" for="priority">{{ translate('messages.Priority') }}</label>
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

                            <div class="col-lg-4">
                                <div class="tps-group h-100 d-flex flex-column">
                                    <p class="tps-group__label">{{ translate('Artwork') }}</p>
                                    <div class="error-wrapper bg-light rounded p-20 text-center flex-grow-1 d-flex flex-column justify-content-center">
                                        <div class="mb-3">
                                            <h6 class="tps-field__label justify-content-center mb-1">
                                                {{ $store_label . ' ' . translate('Category image') }}
                                                <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                                    data-original-title="{{ translate('messages.Required.') }}">*</span>
                                            </h6>
                                            <p class="mb-0 fs-12 text-muted">
                                                {{ translate('Square artwork, shown on the category tile.') }}
                                            </p>
                                        </div>
                                        @include('admin-views.partials._image-uploader', [
                                            'id' => 'store-category-image-input',
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
                            {{ translate('Categories are per store, so the same name can be used again in another store.') }}
                        </span>
                        <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                        <button type="submit" class="btn btn--primary"><i class="tio-add-circle"></i> {{ translate('Add') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="store-category-index" data-ajax-region
            data-ajax-url="{{ url()->full() }}"
            data-ajax-links=".page-link, .list-reset-search"
            data-ajax-forms=".search-form">

            @include('admin-views.store-category.partials._summary', [
                'summary' => $summary,
                'filters' => $filters,
            ])

            <div class="card">
                <div class="card-header py-2 border-0">
                    <div class="search--button-wrapper">
                        @include('partials._table-head', [
                            'subtitle' => translate('messages.Categories that group what a store sells, for browsing in the customer app.'),
                            'count' => null,
                        ])

                        <form class="search-form w-340-lg">
                            @if(request('module_id'))
                                <input type="hidden" name="module_id" value="{{ request('module_id') }}">
                            @endif
                            @foreach(['store_id', 'priority', 'status', 'usage'] as $scalar)
                                @if($filters[$scalar] !== null)
                                    <input type="hidden" name="{{ $scalar }}" value="{{ $filters[$scalar] }}">
                                @endif
                            @endforeach
                            <div class="input-group input--group">
                                <input type="search" name="search" value="{{ $filters['search'] }}" class="form-control h-40"
                                    placeholder="{{ translate('messages.Search') . ' ' . $store_label . ' ' . translate('Categories') }}"
                                    aria-label="{{ translate('messages.Search') . ' ' . $store_label . ' ' . translate('Categories') }}">
                                <button type="submit" class="btn btn--primary h-40"><i class="tio-search"></i></button>
                            </div>
                        </form>

                        @if($filters['search'])
                            <a class="btn btn--primary ml-2 list-reset-search" href="{{ request()->fullUrlWithoutQuery(['search', 'page']) }}">
                                <i class="tio-refresh"></i> {{ translate('messages.Reset') }}
                            </a>
                        @endif

                        <div class="hs-unfold ml-2">
                            <a class="btn btn-sm btn-white text-title min-height-40 filter-button-show" href="javascript:;"
                                role="button" aria-expanded="false" aria-controls="datatableFilterSidebar">
                                <i class="tio-filter-list mr-1 text-title"></i> {{ translate('messages.Filter') }}
                                @if($filter_count)
                                    <span class="badge badge-success badge-pill ml-1">{{ $filter_count }}</span>
                                @endif
                            </a>
                        </div>

                        <div class="hs-unfold ml-2">
                            <a class="js-hs-unfold-invoker btn btn-sm btn-white text-title dropdown-toggle font-medium min-height-40"
                                href="javascript:;"
                                data-hs-unfold-options='{"target":"#storeCategoryExportDropdown","type":"css-animation"}'>
                                <i class="tio-download-to mr-1 text-title"></i> {{ translate('messages.Export') }}
                            </a>
                            <div id="storeCategoryExportDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                                <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                                <a class="dropdown-item" href="{{ route('admin.store-category.export', array_merge(request()->query(), ['type' => 'excel'])) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="excel">
                                    Excel
                                </a>
                                <a class="dropdown-item" href="{{ route('admin.store-category.export', array_merge(request()->query(), ['type' => 'csv'])) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg" alt="csv">
                                    CSV
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive datatable-custom">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{ translate('messages.Category') }}</th>
                                <th class="border-0">{{ $store_label }}</th>
                                <th class="border-0 col--numeric">{{ translate('messages.Items') }}</th>
                                <th class="border-0">{{ translate('messages.Translations') }}</th>
                                <th class="border-0">{{ translate('messages.Priority') }}</th>
                                <th class="border-0">{{ translate('messages.Status') }}</th>
                                <th class="border-0">{{ translate('messages.Created at') }}</th>
                                <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
                            </tr>
                        </thead>
                        <tbody id="set-rows">
                            @foreach ($categories as $category)
                                @include('admin-views.store-category.partials._row', [
                                    'category' => $category,
                                    'filters' => $filters,
                                    'translated_locales' => $translated_locales,
                                    'items_linkable' => $items_linkable,
                                ])
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if(count($categories) !== 0)
                    <hr>
                @endif
                <div class="page-area">
                    {!! $categories->links() !!}
                </div>

                @if (count($categories) === 0)
                    <div class="empty--data">
                        <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
                        @if($filters['search'] || $filter_count)
                            <h5>{{ translate('messages.No category matches these filters.') }}</h5>
                            <p class="text-muted font-size-sm">{{ translate('messages.Clear the search or widen the filters to see more.') }}</p>
                        @else
                            <h5>{{ translate('messages.No category yet.') }}</h5>
                            <p class="text-muted font-size-sm">{{ translate('messages.Add the first one with the form above.') }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div id="datatableFilterSidebar" class="filter-drawer sidebar sidebar-bordered sidebar-box-shadow">
        <div class="card card-lg sidebar-card sidebar-footer-fixed">
            @include('partials._filter-drawer-head', [
                'fd_title' => translate('messages.Category filter'),
                'fd_subtitle' => translate('messages.Narrow the list down by store, priority, status and usage.'),
            ])

            <form class="card-body sidebar-body sidebar-scrollbar" method="get" id="store_category_filter_form">
                @if(request('module_id'))
                    <input type="hidden" name="module_id" value="{{ request('module_id') }}">
                @endif
                <input type="hidden" name="search" value="{{ $filters['search'] }}" id="filter-search-value">

                <small class="text-cap mb-3">{{ $store_label }}</small>
                <div class="form-group">
                    <select name="store_id" id="store-category-store-filter" class="form-control js-store-select2-ajax"
                            data-placeholder="{{ translate('Select') . ' ' . $store_label }}">
                        @if($store)
                            <option value="{{ $store->id }}" selected>{{ $store->name }}</option>
                        @else
                            <option value="all" selected>{{ translate('All stores') }}</option>
                        @endif
                    </select>
                </div>

                <hr class="my-4">

                <small class="text-cap mb-3">{{ translate('messages.Priority') }}</small>
                <div class="form-group">
                    <select name="priority" class="form-control js-select2-custom">
                        <option value="all">{{ translate('messages.Any priority') }}</option>
                        <option value="2" {{ $filters['priority'] === 2 ? 'selected' : '' }}>{{ translate('messages.High') }}</option>
                        <option value="1" {{ $filters['priority'] === 1 ? 'selected' : '' }}>{{ translate('messages.medium') }}</option>
                        <option value="0" {{ $filters['priority'] === 0 ? 'selected' : '' }}>{{ translate('messages.Normal') }}</option>
                    </select>
                </div>

                <small class="text-cap mb-3">{{ translate('messages.Status') }}</small>
                <div class="form-group">
                    <select name="status" class="form-control js-select2-custom">
                        <option value="all">{{ translate('messages.Any status') }}</option>
                        <option value="1" {{ $filters['status'] === 1 ? 'selected' : '' }}>{{ translate('messages.Active') }}</option>
                        <option value="0" {{ $filters['status'] === 0 ? 'selected' : '' }}>{{ translate('messages.Inactive') }}</option>
                    </select>
                </div>

                <small class="text-cap mb-3">{{ translate('messages.Usage') }}</small>
                <div class="form-group">
                    <select name="usage" class="form-control js-select2-custom">
                        <option value="all">{{ translate('messages.Any usage') }}</option>
                        <option value="used" {{ $filters['usage'] === 'used' ? 'selected' : '' }}>{{ translate('messages.With items') }}</option>
                        <option value="empty" {{ $filters['usage'] === 'empty' ? 'selected' : '' }}>{{ translate('messages.Without items') }}</option>
                    </select>
                </div>

                <div class="card-footer sidebar-footer">
                    <div class="row gx-2">
                        <div class="col">
                            <a class="btn btn-block btn-white" href="{{ $drawer_clear }}">
                                <i class="tio-clear-circle-outlined"></i> {{ translate('Clear all') }}
                            </a>
                        </div>
                        <div class="col">
                            <button type="submit" class="btn btn-block btn-primary"><i class="tio-filter-list"></i> {{ translate('messages.Filter') }}</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="offcanvas__storeCategoryBtn" class="custom-offcanvas d-flex flex-column justify-content-between">
        <div id="data-view" class="h-100"></div>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>
@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin') }}/js/view-pages/category-index.js"></script>
    <script>
        "use strict";

        function initStoreSelect2Ajax(selector) {
            $(selector).select2({
                placeholder: $(selector).data('placeholder') || '{{ translate('Select') . ' ' . \App\CentralLogics\Helpers::moduleStoreLabel() }}',
                allowClear: false,
                ajax: {
                    url: '{{ route('admin.store.get-stores') }}',
                    dataType: 'json',
                    delay: 250,
                    data: params => ({
                        q: params.term,
                        module_type: '{{ config('module.current_module_type') }}',
                        include_addon_providers: 1,
                        show_active: 1,
                        page: params.page
                    }),
                    processResults: data => ({ results: data })
                }
            });
        }

        initStoreSelect2Ajax('.js-store-select2-ajax');

        function initLangTabs() {
            const langLinks = document.querySelectorAll('#data-view .lang_link1');
            langLinks.forEach(function (langLink) {
                langLink.addEventListener('click', function (e) {
                    e.preventDefault();
                    langLinks.forEach(link => link.classList.remove('active'));
                    this.classList.add('active');
                    document.querySelectorAll('#data-view .lang_form1').forEach(form => form.classList.add('d-none'));
                    const lang = this.id.substring(0, this.id.length - 5);
                    $('#' + lang + '-form1').removeClass('d-none');
                });
            });
        }

        function initSelect2Dropdowns() {
            $('.offcanvas-close, #offcanvasOverlay').off('click.offcanvas').on('click.offcanvas', function () {
                $('.custom-offcanvas').removeClass('open');
                $('#offcanvasOverlay').removeClass('show');
                $('#content-disable').removeClass('disabled');
            });
        }

        $(document).on('click', '.data-info-show', function () {
            let id = $(this).data('id');
            let url = $(this).data('url');
            $($(this).data('target')).addClass('open');
            $('#offcanvasOverlay').addClass('show');
            $('body').addClass('modal-open');
            $('#content-disable').addClass('disabled');
            fetch_data(id, url);
        });

        $(document).on('ajax:success', '#data-view form', function () {
            $('#content-disable').removeClass('disabled');
        });

        $(document).on('ajax:success', '.search-form', function () {
            $('#filter-search-value').val($(this).find('input[name="search"]').val());
        });

        function fetch_data(id, url) {
            $.ajax({
                url: url,
                type: "get",
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    $("#data-view").html(data.view);
                    if (typeof initLangTabs === 'function') initLangTabs();
                    if (typeof initSelect2Dropdowns === 'function') initSelect2Dropdowns();
                    if (typeof checkPreExistingImages === 'function') checkPreExistingImages();
                    initStoreSelect2Ajax('#data-view .js-store-select2-ajax');
                },
                complete: function () {
                    $('#loading').hide();
                }
            });
        }
    </script>
@endpush
