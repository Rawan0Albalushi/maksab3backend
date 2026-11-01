@extends('layouts.vendor.app')

@section('title', translate('messages.Add new item'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="{{ asset('public/assets/admin/css/tags-input.min.css') }}" rel="stylesheet">
    <link href="{{ asset('public/assets/admin/css/AI/animation/product/ai-sidebar.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/custom.css') }}">
    <link href="{{ asset('public/assets/admin/css/third-party-setup.css') }}" rel="stylesheet">
    <link href="{{ asset('public/assets/admin/css/view-pages/item-form.css') }}" rel="stylesheet">
@endpush

@section('content')

    <div class="content container-fluid tps itf">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/items.png') }}" class="w--22" alt="">
                </span>
                <span>
                    {{ translate('messages.Add new item') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Add something for customers to buy, with its price, images and any variations.') }}</p>
        </div>
        <form id="item_form" enctype="multipart/form-data" class="validate-form" data-ajax="true">
            <input type="hidden" id="request_type" value="vendor">
            <input type="hidden" id="store_id" value="{{ $store_id }}">
            <input type="hidden" id="module_type" value="{{ $module_type }}">

            <div class="row g-3">
                @includeif('admin-views.product.partials._title_and_discription')

                @includeif('admin-views.product.partials._category_and_general')

                <div class="col-md-6">
                    <div class="tps-card h-100 item-form__media">
                        <div class="tps-card__head">
                            <div class="tps-card__titles">
                                <h2 class="tps-card__title">{{ translate('Item thumbnail') }}
                                    @if ($module_type != 'food')
                                        <span class="tps-req">*</span>
                                    @endif
                                </h2>
                                <p class="tps-card__subtitle">
                                    {{ translate('The main image customers see in listings and on the item page.') }}
                                </p>
                            </div>
                        </div>
                        <div class="tps-card__body">
                            <div class="error-wrapper item-form__artwork">
                                @include('admin-views.partials._image-uploader', [
                                    'id' => 'image-input',
                                    'name' => 'image',
                                    'ratio' => '1:1',
                                    'isRequired' => $module_type == 'food' ? false : true,
                                    'existingImage' => null,
                                    'imageExtension' => IMAGE_EXTENSION,
                                    'imageFormat' => IMAGE_FORMAT,
                                    'maxSize' => MAX_FILE_SIZE,
                                    'textPosition' => 'bottom',
                                ])
                            </div>
                        </div>
                    </div>
                </div>

                @include('admin-views.product.partials._product-video')
                @include('admin-views.partials._multiple-image-uploader', [
                    'rootId' => 'product-multiple-image-uploader',
                    'containerId' => 'product-additional-images',
                    'title' => translate('Product additional images'),
                    'description' => translate('Upload additional images.') . ' ' . IMAGE_FORMAT . ' image, max ' . MAX_FILE_SIZE . ' MB (ratio ' . '1:1' . ')' ,
                    'fieldName' => 'item_images[]',
                    'maxCount' => 5,
                    'rowHeight' => '120px',
                    'groupClassName' => 'spartan_item_wrapper size--md',
                    'maxSize' => MAX_FILE_SIZE,
                    'placeholderImage' => asset('public/assets/admin/img/400x400/coba-placeholder.png'),
                    'dropFileLabel' => 'Drop Here',
                    'extensionErrorMessage' => translate('Please upload a file in a supported format') . ': PNG, JPG',
                    'sizeErrorMessage' => translate('messages.File size too big'),
                    'resetButtonSelector' => '#reset_btn',
                ])

                @includeif('admin-views.product.partials._price_and_stock')

                @if ($module_type == 'food')
                    @includeif('admin-views.product.partials._food_variations')
                @else
                    @includeif('admin-views.product.partials._other_variations')
                @endif

                @includeif('admin-views.product.partials._ai_sidebar')

                @if ($module_type == 'ecommerce')
                    <div class="col-12">
                        @includeIf('admin-views.business-settings.landing-page-settings.partial._meta_data')
                    </div>
                @endif

                <div class="col-12 item-form__actions">
                    <div class="tps-card">
                        <div class="tps-card__foot">
                            <span class="tps-foot-note">
                                {{ translate('Fields marked with * are required.') }}
                            </span>
                            <button type="reset" id="reset_btn" class="btn btn--reset"><i class="tio-refresh"></i>
                                {{ translate('messages.Reset') }}</button>
                            <button type="submit" class="btn btn--primary" id="submit_btn"><i
                                    class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Submit') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <span id="message-enter-choice-values" data-text="{{ translate('Enter choice values') }}"></span>

@endsection

@push('script_2')
    @include('admin-views.product.partials._shared-script-assets', [
        'moduleType' => $module_type,
        'viewPageScript' => 'public/assets/admin/js/view-pages/vendor/product-index.js',
    ])

    <script>
        "use strict";

        mod_type = "{{ $module_type }}";

        $('.js-select2-custom').each(function() {
            let select2 = $.HSCore.components.HSSelect2.init($(this));
        });

        @include('admin-views.product.partials._shared-variation-builder-script')

        function combination_update() {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            $.ajax({
                type: "POST",
                url: '{{ route('vendor.item.variant-combination') }}',
                data: $('#item_form').serialize() + '&stock={{ $module_data['stock'] }}',
                beforeSend: function() {
                    $('#loading').show();
                },
                success: function(data) {
                    $('#loading').hide();
                    $('#variant_combination').html(data.view);
                    if (data.length < 1) {
                        $('input[name="current_stock"]').attr("readonly", false);
                    }
                }
            });
        }

        $('#brand_id').select2({
            ajax: {
                url: '{{ route('vendor.item.getBrandList') }}',
                data: function(params) {
                    return {
                        q: params.term,
                        page: params.page,
                    };
                },
                processResults: function(data) {
                    return {
                        results: data
                    };
                },
                __port: function(params, success, failure) {
                    let $request = $.ajax(params);

                    $request.then(success);
                    $request.fail(failure);

                    return $request;
                }
            }
        });

        let form_submitted = false;
        $('#item_form').on('submit', function(e) {
            e.preventDefault();

            if (form_submitted) return false;
            form_submitted = true;
            $('#submit_btn').prop('disabled', true);

            if (typeof FormValidation != 'undefined' && !FormValidation.validateForm(this)) {
                form_submitted = false;
                $('#submit_btn').prop('disabled', false);
                return false;
            }

            let formData = new FormData(this);
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: '{{ route('vendor.item.store') }}',
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                beforeSend: function() {
                    $('#loading').show();
                },
                success: function(data) {
                    $('#loading').hide();
                    if (data.errors) {
                        $('#submit_btn').prop('disabled', false);
                        form_submitted = false;
                        for (let i = 0; i < data.errors.length; i++) {
                            toastr.error(data.errors[i].message, {
                                CloseButton: true,
                                ProgressBar: true
                            });
                        }
                    }
                    if (data.product_approval) {
                        toastr.success(data.product_approval, {
                            CloseButton: true,
                            ProgressBar: true
                        });
                        setTimeout(function() {
                            location.href = '{{ route('vendor.item.pending_item_list') }}';
                        }, 2000);
                    }
                    if (data.success) {
                        toastr.success(data.success, {
                            CloseButton: true,
                            ProgressBar: true
                        });
                        setTimeout(function() {
                            location.href = '{{ route('vendor.item.list') }}';
                        }, 2000);
                    }
                },
                error: function() {
                    $('#loading').hide();
                    $('#submit_btn').prop('disabled', false);
                    form_submitted = false;
                    toastr.error('{{ translate('messages.Something went wrong') }}');
                }
            });
        });

        $('#reset_btn').click(function() {
            $('#category_id').val(null).trigger('change');
            $('#sub-categories').val(null).trigger('change');
            $('#unit').val(null).trigger('change');
            $('#veg').val(0).trigger('change');
            $('#addons').val(null).trigger('change');
            $('#discount_type').val(null).trigger('change');
            $('#choice_attributes').val(null).trigger('change');
            $('#customer_choice_options').empty().trigger('change');
            $('#variant_combination').empty().trigger('change');
        })
    </script>
@endpush
