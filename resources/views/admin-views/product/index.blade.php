@extends('layouts.admin.app')

@section('title', translate('messages.Add new item'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="{{ asset('public/assets/admin/css/tags-input.min.css') }}" rel="stylesheet">
    <link href="{{ asset('public/assets/admin/css/AI/animation/product/ai-sidebar.css') }}" rel="stylesheet">
    <link href="{{ asset('public/assets/admin/css/third-party-setup.css') }}" rel="stylesheet">
    <link href="{{ asset('public/assets/admin/css/view-pages/item-form.css') }}" rel="stylesheet">
@endpush

@section('content')
    <div class="content container-fluid tps itf">
        <div class="page-header">
            <div class="row align-items-center g-2">
                <div class="col-lg-7 col-md-6 col-12">
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <img src="{{ asset('public/assets/admin/img/items.png') }}" class="w--22" alt="">
                        </span>
                        <span>{{ translate('messages.Add new item') }}</span>
                    </h1>
                    <p class="page-header-desc">{{ translate('Add something for customers to buy, with its price, images and any variations.') }}</p>
                </div>
                <div class="col-lg-5 col-md-6 col-12">
                    <div class="d-flex flex-wrap justify-content-md-end align-items-center gap-2">
                        <a href="{{ route('admin.item.product_gallery') }}" class="btn btn--primary mb-0">
                            <i class="tio-add-circle"></i> {{ translate('Add Info From Gallery') }}
                        </a>
                        <button type="button"
                            class="btn btn--primary-light {{ Config::get('module.current_module_type') == 'food' ? 'foodModalShow' : 'attributeModalShow' }}">
                            <i class="tio-info-outined"></i> {{ translate('See how it works') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <form id="item_form" enctype="multipart/form-data" class="validate-form" data-ajax="true">

            <div class="row g-3">

                <input type="hidden" id="request_type" value="admin">
                <input type="hidden" id="module_type" value="{{ Config::get('module.current_module_type') }}">

                @php($openai_config = \App\CentralLogics\Helpers::get_business_settings('openai_config'))
                @includeif('admin-views.product.partials._title_and_discription')

                @includeif('admin-views.product.partials._category_and_general')

                <div class="col-md-6">
                    <div class="tps-card h-100 item-form__media">
                        <div class="tps-card__head">
                            <div class="tps-card__titles">
                                <h2 class="tps-card__title">{{ translate('Item thumbnail') }}
                                    @if (Config::get('module.current_module_type') != 'food')
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
                                    'isRequired' => Config::get('module.current_module_type') == 'food' ? false : true,
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

                @if (Config::get('module.current_module_type') == 'food')
                    @includeif('admin-views.product.partials._food_variations')
                @else
                    @includeif('admin-views.product.partials._other_variations')
                @endif

                @includeif('admin-views.product.partials._ai_sidebar')
                @if (Config::get('module.current_module_type') == 'ecommerce')
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
                            <button type="submit" id="submitButton" class="btn btn--primary"><i
                                    class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Submit') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>


    </div>


    <div class="modal" id="food-modal">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-body">
                    <button type="button" class="close foodModalClose" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <div class="embed-responsive embed-responsive-16by9">
                        <iframe class="embed-responsive-item" src="https://www.youtube.com/embed/IkoF9gPH6zs"
                            title="YouTube video player" frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            allowfullscreen></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="attribute-modal">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-body">
                    <button type="button" class="close attributeModalClose" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <div class="embed-responsive embed-responsive-16by9">
                        <iframe class="embed-responsive-item" src="https://www.youtube.com/embed/xG8fO7TXPbk"
                            title="YouTube video player" frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            allowfullscreen></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <span id="message-enter-choice-values" data-text="{{ translate('Enter choice values') }}"></span>
@endsection


@push('script_2')
    @include('admin-views.product.partials._shared-script-assets', [
        'moduleType' => Config::get('module.current_module_type'),
        'viewPageScript' => 'public/assets/admin/js/view-pages/product-index.js',
    ])


    <script>
        "use strict";

        $(document).on('change', '#discount_type', function() {
            let data = document.getElementById("discount_type");
            if (data.value === 'amount') {
                $('#symble').text("({{ \App\CentralLogics\Helpers::currency_symbol() }})");
            } else {
                $('#symble').text("(%)");
            }
        });


        @include('admin-views.product.partials._shared-variation-builder-script')


        $('#store_id').on('change', function() {
            let route = '{{ url('/') }}/admin/store/get-addons?data[]=0&store_id=' + $(this).val();
            let id = 'add_on';
            getRestaurantData(route, id);

            loadStoreCategories($(this).val());
        });

        function loadStoreCategories(storeId) {
            let $select = $('#store_category_id');
            if (!$select.length) return;
            let $col = $('#store_category_col');
            $select.empty().append(
                '<option value="">{{ translate('Select store category') }}</option>'
            );
            if (!storeId) {
                $select.prop('required', false);
                $('.store-category-required-mark').hide();
                $col.hide();
                return;
            }
            let url = $select.data('url');
            if (!url) return;
            $.get(url, { store_id: storeId }, function(data) {
                const categories = (data && data.categories) ? data.categories : (Array.isArray(data) ? data : []);
                categories.forEach(function(cat) {
                    $select.append($('<option>', { value: cat.id, text: cat.name }));
                });
                $select.trigger('change');
                const required = !!(data && data.has_categories);
                $select.prop('required', required);
                $('.store-category-required-mark').toggle(required);
                $col.toggle(required);
            });
        }

        function modulChange(id) {
            $.get({
                url: "{{ url('/') }}/admin/business-settings/module/show/" + id,
                dataType: 'json',
                success: function(data) {
                    module_data = data.data;
                    console.log(module_data)
                    stock = module_data.stock;
                    module_type = data.type;
                    if (stock) {
                        $('#stock_input').show();
                    } else {
                        $('#stock_input').hide();
                    }
                    if (module_data.add_on) {
                        $('#addon_input').show();
                    } else {
                        $('#addon_input').hide();
                    }

                    if (module_data.item_available_time) {
                        $('#time_input').show();
                    } else {
                        $('#time_input').hide();
                    }

                    if (module_data.veg_non_veg) {
                        $('#veg_input').show();
                    } else {
                        $('#veg_input').hide();
                    }
                    if (module_data.unit) {
                        $('#unit_input').show();
                    } else {
                        $('#unit_input').hide();
                    }
                    if (module_data.common_condition) {
                        $('#condition_input').show();
                    } else {
                        $('#condition_input').hide();
                    }
                    if (module_data.brand) {
                        $('#brand_input').show();
                    } else {
                        $('#brand_input').hide();
                    }
                    combination_update();
                    if (module_type == 'food') {
                        $('#food_variation_section').show();
                        $('#attribute_section').hide();
                    } else {
                        $('#food_variation_section').hide();
                        $('#attribute_section').show();
                    }
                    if (module_data.organic) {
                        $('#organic').show();
                    } else {
                        $('#organic').hide();
                    }
                    if (module_data.basic) {
                        $('#basic').show();
                    } else {
                        $('#basic').hide();
                    }
                    if (module_data.nutrition) {
                        $('#nutrition').show();
                    } else {
                        $('#nutrition').hide();
                    }
                    if (module_data.allergy) {
                        $('#allergy').show();
                    } else {
                        $('#allergy').hide();
                    }
                },
            });
            module_id = id;
        }

        modulChange({{ Config::get('module.current_module_id') }});

        $('#condition_id').select2({
            ajax: {
                url: '{{ url('/') }}/admin/common-condition/get-all',
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

        $('#brand_id').select2({
            ajax: {
                url: '{{ url('/') }}/admin/brand/get-all',
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

        $('#store_id').select2({
            ajax: {
                url: '{{ route('admin.store.get-stores') }}',
                data: function(params) {
                    return {
                        q: params.term,
                        page: params.page,
                        module_id: {{ Config::get('module.current_module_id') }},
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

        $('#category_id').select2({
            ajax: {
                url: '{{ url('/') }}/admin/item/get-categories?parent_id=0',
                data: function(params) {
                    return {
                        q: params.term,
                        page: params.page,
                        module_id: {{ Config::get('module.current_module_id') }},
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

        $('#sub-categories').select2({
            ajax: {
                url: '{{ url('/') }}/admin/item/get-categories',
                data: function(params) {
                    return {
                        q: params.term,
                        page: params.page,
                        module_id: {{ Config::get('module.current_module_id') }},
                        parent_id: parent_category_id,
                        sub_category: true
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

        $('#choice_attributes').on('change', function() {
            if (module_id == 0) {
                toastr.error('{{ translate('messages.Select a module') }}', {
                    CloseButton: true,
                    ProgressBar: true
                });
                $(this).val("");
                return false;
            }
            $('#customer_choice_options').html(null);
            $('#variant_combination').html(null);
            $.each($("#choice_attributes option:selected"), function() {
                if ($(this).val().length > 50) {
                    toastr.error(
                        '{{ translate('Variation name is too long') }}. {{ translate('Character limit') }}: 50', {
                            CloseButton: true,
                            ProgressBar: true
                        });
                    return false;
                }
                add_more_customer_choice_option($(this).val(), $(this).text());
            });
        });

        function combination_update() {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            $.ajax({
                type: "POST",
                url: "{{ route('admin.item.variant-combination') }}",
                data: $('#item_form').serialize() + '&stock=' + stock,
                beforeSend: function() {
                    $('#loading').show();
                },
                success: function(data) {
                    $('#loading').hide();
                    $('#variant_combination').html(data.view);
                    if (data.length < 1) {
                        $('input[name="current_stock"]').attr("readonly", false);
                        $('input[name="current_stock"]').val(0);
                    }
                    update_qty();
                }
            });
        }

        update_qty();

        function update_qty() {
            let total_qty = 0;
            let qty_elements = $('input[name^="stock_"]');
            for (let i = 0; i < qty_elements.length; i++) {
                total_qty += parseInt(qty_elements.eq(i).val() || 0);
            }
            if (qty_elements.length > 0) {
                $('input[name="current_stock"]').attr("readonly", true);
                $('input[name="current_stock"]').val(total_qty);
            } else {
                $('input[name="current_stock"]').attr("readonly", false);
            }
        }

        $(document).on('keyup', 'input[name^="stock_"]', function() {
            let total_qty = 0;
            let qty_elements = $('input[name^="stock_"]');
            for (let i = 0; i < qty_elements.length; i++) {
                total_qty += parseInt(qty_elements.eq(i).val() || 0);
            }
            $('input[name="current_stock"]').val(total_qty);
        });

        $('#item_form').on('submit', function(e) {
            $('#submitButton').attr('disabled', true);
            e.preventDefault();
            if (typeof FormValidation != 'undefined' && !FormValidation.validateForm(this)) {
                return false;
            }

            let $form = $(this);
            if (!$form.valid()) {
                return false;
            }


            let formData = new FormData(this);
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: '{{ route('admin.item.store') }}',
                data: $('#item_form').serialize(),
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
                        for (let i = 0; i < data.errors.length; i++) {
                            toastr.error(data.errors[i].message, {
                                CloseButton: true,
                                ProgressBar: true
                            });
                        }
                    } else {
                        toastr.success("{{ translate('Added successfully') }}", {
                            CloseButton: true,
                            ProgressBar: true
                        });
                        setTimeout(function() {
                            location.href =
                                "{{ route('admin.item.list') }}";
                        }, 1000);
                    }
                }
            });
        });

        $('#reset_btn').click(function() {
            $('#module_id').val(null).trigger('change');
            $('#store_id').val(null).trigger('change');
            $('#category_id').val(null).trigger('change');
            $('#sub-categories').val(null).trigger('change');
            $('#unit').val(null).trigger('change');
            $('#veg').val(0).trigger('change');
            $('#add_on').val(null).trigger('change');
            $('#discount_type').val(null).trigger('change');
            $('#choice_attributes').val(null).trigger('change');
            $('#customer_choice_options').empty().trigger('change');
            $('#variant_combination').empty().trigger('change');
            $('#viewer').attr('src', "{{ asset('public/assets/admin/img/upload.png') }}");
        })
    </script>
@endpush
