@extends('layouts.admin.app')

@section('title', translate('messages.Smart banner setup'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script type="text/javascript" src="{{ asset('public/assets/admin/js/moment.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('public/assets/admin/js/daterangepicker.min.js') }}"></script>
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <h3 class="mb-0">{{ translate('messages.Smart banner setup') }} - {{ $zone['name'] }}</h3>
        </div>

        @if (count($banners) > 0 || request()->filled('search'))
            <div class="card">
                <div class="card-header py-2 border-0">
                    <div class="search--button-wrapper">
                        <h5 class="card-title">
                            {{ translate('messages.Smart banner list') }}
                            <span class="badge badge-soft-dark ml-2" id="itemCount">{{ $banners->total() }}</span>
                        </h5>
                        <form class="search-form">
                            <div class="input-group input--group">
                                <input id="datatableSearch_" type="search" name="search" class="form-control"
                                       placeholder="{{ translate('messages.Search by title') }}"
                                       value="{{ request()?->search ?? null }}"
                                       aria-label="{{ translate('messages.Search') }}" required>
                                <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                            </div>
                        </form>
                        <button type="button"
                                class="btn btn--primary offcanvas-trigger smart-banner-create-trigger"
                                data-target="#smartBannerForm_offcanvas">
                            <i class="tio-add mr-1"></i> {{ translate('messages.Add banner') }}
                        </button>
                    </div>
                </div>
                <div id="smart-banner-list-wrapper">
                    @include('admin-views.zone.smart-banner.partials._list', ['banners' => $banners])
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body p-5">
                    <div class="bg-light rounded p-5 text-center">
                        <div class="pt-5">
                            <img class="mb-20"
                                 src="{{ asset('public/assets/admin/img/smart-banner.svg') }}"
                                 alt="empty">
                            <h4 class="mb-3">{{ translate('messages.Add smart banner') }}</h4>
                            <p class="mb-20 fs-12 mx-auto max-w-400px">
                                {{ translate('messages.Set up some banners to showcase modules categories and vendors right from the home page of the customer app.') }}
                            </p>
                            <button type="button"
                                    class="btn btn--primary offcanvas-trigger smart-banner-create-trigger"
                                    data-target="#smartBannerForm_offcanvas">
                                <i class="tio-add mr-1"></i> {{ translate('messages.Add banner') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div id="smartBannerForm_offcanvas" class="custom-offcanvas d-flex flex-column justify-content-between">
        <form id="smart_banner_form"
              action="{{ route('admin.business-settings.zone.smart-banner.store', [$zone['id']]) }}"
              method="post" enctype="multipart/form-data"
              data-store-action="{{ route('admin.business-settings.zone.smart-banner.store', [$zone['id']]) }}">
            @csrf
            <input type="hidden" name="zone_id" value="{{ $zone->id }}">
            <input type="hidden" name="banner_id" id="smart_banner_id" value="">

            <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
                <h3 class="mb-0" id="smart_banner_form_title">{{ translate('messages.Create smart banner') }}</h3>
                <button type="button"
                        class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary text-dark offcanvas-close fz-15px p-0"
                        aria-label="Close">&times;</button>
            </div>

            <div class="custom-offcanvas-body p-3" style="overflow-y: auto;">
                <div class="bg-light rounded p-3 mb-3">
                    <div class="form-group">
                        <label class="input-label">{{ translate('messages.Select module') }} <span class="text-danger">*</span></label>
                        <select name="module_id" id="smart_banner_module" class="form-control js-select2-custom"
                                data-placeholder="{{ translate('messages.Select module') }}">
                            <option value=""></option>
                            @foreach($modules as $module)
                                <option value="{{ $module->id }}" data-type="{{ $module->module_type }}">{{ $module->module_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="input-label">{{ translate('messages.Active days') }} <span class="text-danger">*</span></label>
                        <div class="resturant-type-group flex-md-nowrap border rounded">
                            <label class="form-check w-100 form--check mr-2 mr-md-4">
                                <input class="form-check-input" type="radio" value="everyday" name="active_days" id="active_days_everyday">
                                <span class="form-check-label">{{ translate('messages.everyday') }}</span>
                            </label>
                            <label class="form-check w-100 form--check mr-2 mr-md-4">
                                <input class="form-check-input" type="radio" value="custom_date" name="active_days" id="active_days_custom" checked>
                                <span class="form-check-label">{{ translate('Custom date') }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group" id="smart_banner_date_wrapper">
                        <label class="input-label">{{ translate('messages.Select custom date') }} <span class="text-danger">*</span></label>
                        <div class="position-relative date-range__custom">
                            <i class="tio-calendar-month icon-absolute-on-right"></i>
                            <input type="text" name="date_range" id="smart_banner_date_range"
                                   class="form-control h-45 position-relative bg-transparent no-type"
                                   placeholder="{{ translate('Select date range') }}" autocomplete="off">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="input-label">{{ translate('messages.Active time range') }}</label>
                        <div class="position-relative cursor-pointer">
                            <i class="tio-time icon-absolute-on-right"></i>
                            <input type="text" name="time_range" id="smart_banner_time_range"
                                   class="form-control h-45 position-relative bg-transparent no-type"
                                   placeholder="{{ translate('messages.Select time range') }}" autocomplete="off">
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="input-label">{{ translate('messages.Select position') }} <span class="text-danger">*</span></label>
                        <select name="position" id="smart_banner_position" class="form-control js-select2-custom"
                                data-placeholder="{{ translate('messages.Select position') }}">
                            <option value=""></option>
                            @foreach($positions as $key => $label)
                                <option value="{{ $key }}">{{ $label }} {{ translate('messages.position') }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="bg-light rounded p-3 mb-3">
                    <div class="form-group">
                        <label class="input-label">{{ translate('messages.Redirect to') }} <span class="text-danger">*</span></label>
                        <select name="redirect_type" id="smart_banner_redirect_type" class="form-control js-select2-custom">
                            <option value="category">{{ translate('messages.Category') }}</option>
                            <option value="module_home">{{ translate('messages.Module home') }}</option>
                            <option value="store_page">{{ translate('messages.Store page') }}</option>
                            <option value="offer_page">{{ translate('messages.Offer page') }}</option>
                        </select>
                    </div>

                    <div class="form-group mb-0" id="smart_banner_target_wrapper">
                        <label class="input-label" id="smart_banner_target_label">{{ translate('Select category') }}</label>
                        <select name="redirect_target_id" id="smart_banner_target" class="form-control js-select2-custom"
                                data-placeholder="{{ translate('messages.Select target') }}"
                                data-categories-url="{{ route('admin.business-settings.zone.smart-banner.categories', ['module_id' => 'MODULE_ID']) }}"
                                data-stores-url="{{ route('admin.business-settings.zone.smart-banner.stores', ['module_id' => 'MODULE_ID', 'zone_id' => 'ZONE_ID']) }}">
                            <option value=""></option>
                        </select>
                    </div>
                </div>

                <div class="bg-light rounded p-3 mb-3">
                    @if ($language)
                        <ul class="nav nav-tabs mb-3 border-0">
                            <li class="nav-item">
                                <a class="nav-link lang_link active" href="#" id="default-link">{{ translate('Default') }}({{ strtoupper(app()->getLocale()) }})</a>
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
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.Title') }} ({{ translate('Default') }}) <span class="text-danger">*</span></label>
                            <input type="text" name="title[]" maxlength="50"
                                   class="form-control smart-banner-char-counter"
                                   data-max="50"
                                   placeholder="{{ translate('messages.Your ecommerce venture starts here') }}">
                            <span class="text-right d-block mt-1 char-counter-label">0/50</span>
                        </div>
                        <div class="form-group mb-0">
                            <label class="input-label">{{ translate('Subtitle') }} ({{ translate('Default') }})</label>
                            <input type="text" name="subtitle[]" maxlength="100"
                                   class="form-control smart-banner-char-counter"
                                   data-max="100"
                                   placeholder="{{ translate('messages.Enjoy all services in one platform') }}">
                            <span class="text-right d-block mt-1 char-counter-label">0/100</span>
                        </div>
                        <input type="hidden" name="lang[]" value="default">
                    </div>
                    @foreach ($language as $lang)
                        <div class="d-none lang_form" id="{{ $lang }}-form">
                            <div class="form-group">
                                <label class="input-label">{{ translate('messages.Title') }} ({{ strtoupper($lang) }})</label>
                                <input type="text" name="title[]" maxlength="50"
                                       class="form-control smart-banner-char-counter"
                                       data-max="50"
                                       placeholder="{{ translate('messages.Your ecommerce venture starts here') }}">
                                <span class="text-right d-block mt-1 char-counter-label">0/50</span>
                            </div>
                            <div class="form-group mb-0">
                                <label class="input-label">{{ translate('Subtitle') }} ({{ strtoupper($lang) }})</label>
                                <input type="text" name="subtitle[]" maxlength="100"
                                       class="form-control smart-banner-char-counter"
                                       data-max="100"
                                       placeholder="{{ translate('messages.Enjoy all services in one platform') }}">
                                <span class="text-right d-block mt-1 char-counter-label">0/100</span>
                            </div>
                            <input type="hidden" name="lang[]" value="{{ $lang }}">
                        </div>
                    @endforeach
                </div>

                <div class="bg-light rounded p-3 mb-3 text-center">
                    <h6 class="mb-1">{{ translate('messages.Upload smart banner image') }} <span class="text-danger">*</span></h6>
                    <p class="fs-12 mb-3 text-muted">{{ translate('messages.Upload Banner Image') }}</p>
                    @include('admin-views.partials._image-uploader', [
                        'id' => 'smart_banner_image_input',
                        'name' => 'image',
                        'ratio' => '1:1',
                        'pixel' => '120 x 120',
                        'isRequired' => false,
                        'existingImage' => null,
                        'imageExtension' => '.jpg,.jpeg,.png,.svg',
                        'imageFormat' => 'JPG, JPEG, PNG, SVG',
                        'maxSize' => 2,
                        'textPosition' => 'bottom',
                    ])
                </div>
            </div>

            <div class="offcanvas-footer p-3 d-flex align-items-center justify-content-center gap-3 border-top bg-white">
                <button type="button" class="btn w-100 btn--reset" id="smart_banner_reset_btn"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                <button type="submit" class="btn w-100 btn--primary" id="smart_banner_submit_btn"><i class="tio-add-circle"></i> {{ translate('Add') }}</button>
            </div>
        </form>
    </div>

    <div id="smartBannerView_offcanvas" class="custom-offcanvas d-flex flex-column justify-content-between">
        <div>
            <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
                <h3 class="mb-0">{{ translate('messages.Smart banner view') }}</h3>
                <button type="button"
                        class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary text-dark offcanvas-close fz-15px p-0"
                        aria-label="Close">&times;</button>
            </div>
            <div class="custom-offcanvas-body p-3" id="smart_banner_view_body">
                <div class="text-center py-5" id="smart_banner_view_loading">
                    <span class="app-loader__spinner app-loader__spinner--sm" role="status"
                          aria-label="{{ translate('messages.loading') }}"></span>
                </div>
            </div>
        </div>
        <div class="offcanvas-footer p-3 d-flex align-items-center justify-content-center border-top bg-white">
            <button type="button" class="btn w-100 btn--primary" id="smart_banner_view_edit_btn"
                    data-id="" data-url="">
                <i class="tio-edit"></i> {{ translate('Edit') }}
            </button>
        </div>
    </div>

    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>

    <div class="modal fade" id="smart-banner-error-modal">
        <div class="modal-dialog status-warning-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true" class="tio-clear"></span>
                    </button>
                </div>
                <div class="modal-body pb-5 pt-0">
                    <div class="max-349 mx-auto mb-20">
                        <div class="text-center">
                            <img alt="" src="{{ asset('public/assets/admin/img/modal-error.png') }}" class="mb-20">
                            <h5 class="modal-title">{{ translate('messages.Smart banner overlap') }}</h5>
                        </div>
                        <div class="text-center" id="smart-banner-error-message">
                            <p>{{ translate('messages.This banner overlaps with another in the same position. please change the position or reschedule.') }}</p>
                        </div>
                        <div class="btn--container justify-content-center">
                            <button type="button" class="btn btn--primary min-w-120" data-dismiss="modal">
                                <i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Okay') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        window.smartBannerConfig = {
            zoneId: {{ (int) $zone['id'] }},
            uploadPlaceholder: "{{ asset('public/assets/admin/img/upload-img.png') }}",
            storeAction: "{{ route('admin.business-settings.zone.smart-banner.store', [$zone['id']]) }}",
            updateAction: "{{ route('admin.business-settings.zone.smart-banner.update', ['id' => 'BANNER_ID']) }}",
            editUrl: "{{ route('admin.business-settings.zone.smart-banner.edit', ['id' => 'BANNER_ID']) }}",
            listUrl: "{{ route('admin.business-settings.zone.smart-banner.list', [$zone['id']]) }}",
            labels: {
                storePage: "{{ translate('messages.Store page') }}",
                providerPage: "{{ translate('messages.Provider page') }}",
                selectStore: "{{ translate('Select store') }}",
                selectProvider: "{{ translate('Select provider') }}",
                selectCategory: "{{ translate('Select category') }}",
                pleaseSelectStore: "{{ translate('messages.Please select a store') }}",
                pleaseSelectProvider: "{{ translate('messages.Please select a provider') }}",
                pleaseSelectModule: "{{ translate('messages.Please select a module') }}",
            },
        };
    </script>
    <script src="{{ asset('public/assets/admin/js/view-pages/smart-banner.js') }}"></script>
@endpush
