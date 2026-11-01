@extends('layouts.vendor.app')

@section('title',translate('messages.Item bulk import'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="{{ asset('public/assets/admin/css/tags-input.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/bulk-tools.css') }}">
@endpush

@section('content')
    @include('partials.bulk._import', ['bulk' => [
        'title' => translate('messages.Item bulk import'),
        'subtitle' => translate('Upload a spreadsheet to add new items or update the ones already in your store.'),
        'icon' => asset('public/assets/admin/img/items.png'),
        'action' => route('vendor.item.bulk-import'),
        'summary' => $summary,
        'count_label' => translate('Items in your store'),
        'show_module' => false,
        'mode_add_title' => translate('Add new items'),
        'mode_add_desc' => translate('Every row becomes a new item.'),
        'mode_update_title' => translate('Update existing items'),
        'mode_update_desc' => translate('Rows are matched on their ID and overwritten.'),
        'note_import' => translate('Ids in the file are ignored — new items get fresh ids.'),
        'note_update' => translate('Your file must keep its Id column. Matching items are overwritten and cannot be restored.'),
        'templates' => [
            [
                'label' => translate('Template with current data'),
                'hint' => translate('The heading row plus example rows, so you can see how each column is filled.'),
                'url' => $module_type == 'food'
                    ? asset('public/assets/restaurant_panel/foods_bulk_format.xlsx')
                    : asset('public/assets/restaurant_panel/items_bulk_format.xlsx'),
            ],
            [
                'label' => translate('Empty template'),
                'hint' => translate('Only the heading row — start from a clean sheet.'),
                'url' => asset('public/assets/restaurant_panel/items_bulk_format_nodata.xlsx'),
            ],
        ],
        'aside_title' => translate('How the file must look'),
        'aside_subtitle' => translate('These columns must carry a value in every row.'),
        'columns' => ['Id', 'Name', 'CategoryId', 'SubCategoryId', 'Price', 'StoreId', 'ModuleId', 'Discount', 'DiscountType'],
        'tips' => [
            translate('CategoryId and UnitId come from their own lists — put the right ids in, the file is not checked against names.'),
            translate('Price and discount cannot be negative, and the row number in the error message is the one to fix.'),
            translate('Build variations with the generators below, then paste the generated values into the matching columns.'),
            translate('Image paths come from the item folder in the gallery, and the file name has to stay short.') . ' ' . translate('Character limit') . ': 30',
        ],
        'export_url' => route('vendor.item.bulk-export-index'),
        'export_title' => translate('messages.Export items'),
        'export_desc' => translate('Download your current items as a ready-made file to edit.'),
        'help_title' => translate('Importing items'),
        'help_steps' => [
            translate('Download a template, or export your items to start from the data you already have.'),
            translate('Fill one row per item and keep the column headings exactly as they come.'),
            translate('Pick whether the file adds new items or updates existing ones.'),
            translate('Choose the file and press upload — the whole file is checked before anything is saved.'),
        ],
        'after' => 'vendor-views.product.partials._bulk-import-generators',
    ]])
@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin') }}/js/tags-input.min.js"></script>
    <script>
        "use strict";
        let count = 0;
        let countRow = 0;
        let element = 0;
        $(document).ready(function() {
            @if($module_type== 'food')
            $('#food_variation_section').show();
            $('#attribute_section').hide();
            @else
            $('#food_variation_section').hide();
            $('#attribute_section').show();
            @endif
            $("#add_new_option_button").click(function(e) {
                count++;
                let add_option_view = `
                <div class="card view_new_option mb-2" >
                    <div class="card-header">
                        <label for="" id=new_option_name_` + count + `> {{ translate('Add new') }}</label>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-lg-3 col-md-6">
                                <label for="">{{ translate('Name') }}</label>
                                 <input required name=options[` + count +
                    `][name] class="form-control new_option_name" type="text" data-count="`+
                    count +`">
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group">
                                    <label class="input-label text-capitalize d-flex alig-items-center"><span class="line--limit-1">{{ translate('messages.Selection type') }} </span>
                                    </label>
                                    <div class="resturant-type-group border">
                                        <label class="form-check form--check mr-2 mr-md-4">
                                                <input class="form-check-input show_min_max" data-count="`+count+`" type="radio" value="multi"
                                                name="options[` + count + `][type]" id="type` + count +
                    `" checked
                                                >
                                                <span class="form-check-label">
                                                    {{ translate('Multiple selection') }}
                    </span>
                </label>

                <label class="form-check form--check mr-2 mr-md-4">
                    <input class="form-check-input hide_min_max" data-count="`+count+`" type="radio" value="single"
                                                name="options[` + count + `][type]" id="type` + count +
                    `"
                                                >
                                                <span class="form-check-label">
                                                    {{ translate('Single selection') }}
                    </span>
                </label>
        </div>
    </div>
    </div>
    <div class="col-12 col-lg-6">
    <div class="row g-2">
        <div class="col-sm-6 col-md-4">
            <label for="">{{ translate('Min') }}</label>
                                        <input id="min_max1_` + count + `" required  name="options[` + count + `][min]" class="form-control" type="number" min="1">
                                    </div>
                                    <div class="col-sm-6 col-md-4">
                                        <label for="">{{ translate('Max') }}</label>
                                        <input id="min_max2_` + count + `"   required name="options[` + count + `][max]" class="form-control" type="number" min="1">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="d-md-block d-none">&nbsp;</label>
                                            <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <input id="options[` + count + `][required]" name="options[` +
                    count + `][required]" type="checkbox">
                                                <label for="options[` + count + `][required]" class="m-0">{{ translate('Required.') }}</label>
                                            </div>
                                            <div>
                                                <button type="button" class="btn btn-danger btn-sm delete_input_button"
                                                    title="{{ translate('Delete') }}">
                                                    <i class="tio-add-to-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="option_price_` + count + `" >
                            <div class="border rounded p-3 pb-0 mt-3">
                                <div  id="option_price_view_` + count + `">
                                    <div class="row g-3 add_new_view_row_class mb-3">
                                        <div class="col-md-4 col-sm-6">
                                            <label for="">{{ translate('Option name') }}</label>
                                            <input class="form-control" required type="text" name="options[` +
                    count +
                    `][values][0][label]" id="">
                                        </div>
                                        <div class="col-md-4 col-sm-6">
                                            <label for="">{{ translate('Additional price') }}</label>
                                            <input class="form-control" required type="number" min="0"  step="{{ \App\CentralLogics\Helpers::getDecimalPlaces() }}" max="999999999999.999" name="options[` +
                    count + `][values][0][optionPrice]" id="">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3 p-3 mr-1 d-flex "  id="add_new_button_` + count +
                    `">
                                   <button type="button" class="btn btn--primary btn-outline-primary add_new_row_button" data-count="`+
                    count +`" ><i class="tio-add-circle"></i> {{ translate('Add new option') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;

                $("#add_new_option").append(add_option_view);
            });
        });

        function show_min_max(data) {
            $('#min_max1_' + data).removeAttr("readonly");
            $('#min_max2_' + data).removeAttr("readonly");
            $('#min_max1_' + data).attr("required", "true");
            $('#min_max2_' + data).attr("required", "true");
        }

        function hide_min_max(data) {
            $('#min_max1_' + data).val(null).trigger('change');
            $('#min_max2_' + data).val(null).trigger('change');
            $('#min_max1_' + data).attr("readonly", "true");
            $('#min_max2_' + data).attr("readonly", "true");
            $('#min_max1_' + data).attr("required", "false");
            $('#min_max2_' + data).attr("required", "false");
        }

        $(document).on('change', '.show_min_max', function () {
            let data = $(this).data('count');
            show_min_max(data);
        });

        $(document).on('change', '.hide_min_max', function () {
            let data = $(this).data('count');
            hide_min_max(data);
        });




        function new_option_name(value, data) {
            $("#new_option_name_" + data).empty();
            $("#new_option_name_" + data).text(value)
            console.log(value);
        }

        function removeOption(e) {
            element = $(e);
            element.parents('.view_new_option').remove();
        }

        $(document).on('click', '.delete_input_button', function () {
            let e = $(this);
            removeOption(e);
        });

        function deleteRow(e) {
            element = $(e);
            element.parents('.add_new_view_row_class').remove();
        }

        $(document).on('click', '.deleteRow', function () {
            let e = $(this);
            deleteRow(e);
        });


        function add_new_row_button(data) {
            count = data;
            countRow = 1 + $('#option_price_view_' + data).children('.add_new_view_row_class').length;
            let add_new_row_view = `
        <div class="row add_new_view_row_class mb-3 position-relative pt-3 pt-sm-0">
            <div class="col-md-4 col-sm-5">
                    <label for="">{{ translate('Option name') }}</label>
                    <input class="form-control" required type="text" name="options[` + count + `][values][` +
                countRow + `][label]" id="">
                </div>
                <div class="col-md-4 col-sm-5">
                    <label for="">{{ translate('Additional price') }}</label>
                    <input class="form-control"  required type="number" min="0"  step="{{ \App\CentralLogics\Helpers::getDecimalPlaces() }}" max="999999999999.999" name="options[` +
                count +
                `][values][` + countRow + `][optionPrice]" id="">
                </div>
                <div class="col-sm-2 max-sm-absolute">
                    <label class="d-none d-sm-block">&nbsp;</label>
                    <div class="mt-1">
                        <button type="button" class="btn btn-danger btn-sm deleteRow"
                            title="{{ translate('Delete') }}">
                            <i class="tio-add-to-trash"></i>
                        </button>
                    </div>
            </div>
        </div>`;
            $('#option_price_view_' + data).append(add_new_row_view);

        }

        $(document).on('click', '.add_new_row_button', function () {
            let data = $(this).data('count');
            add_new_row_button(data);
        });

        $(document).on('keyup', '.new_option_name', function () {
            let data = $(this).data('count');
            let value = $(this).val();
            new_option_name(value, data);
        });

        $('#choice_attributes').on('change', function() {
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

        function add_more_customer_choice_option(i, name) {
        let n = name;

        $('#customer_choice_options').append(
            `<div class="__choos-item"><div><input type="hidden" name="choice_no[]" value="${i}"><input type="text" class="form-control d-none" name="choice[]" value="${n}" placeholder="{{ translate('messages.Choice title') }}" readonly> <label class="form-label">${n}</label> </div><div><input type="text" class="form-control combination_update" name="choice_options_${i}[]" placeholder="{{ translate('messages.Enter choice values') }}" data-role="tagsinput"></div></div>`
        );
        $("input[data-role=tagsinput], select[multiple][data-role=tagsinput]").tagsinput();
    }

        function combination_update() {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            $.ajax({
                type: "POST",
                url: "{{ route('vendor.item.variant-combination') }}",
                data: $('#item_form_2').serialize() + '&stock=' + true,
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

        $(document).on('change', '.combination_update', function () {
            combination_update();
        });

        $('#item_form_2').on('submit', function(e) {
            e.preventDefault();
            let formData = new FormData(this);
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: '{{ route('vendor.item.variation-generate') }}',
                data: $('#item_form_2').serialize(),
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
                    $('#variation_output').val(data.variation),
                    $('#choice_output').val(data.choice_options),
                    $('#attributes').val(data.attributes)
                    }
                }
            });
        });

        $('#item_form').on('submit', function(e) {
            e.preventDefault();
            let formData = new FormData(this);
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: '{{ route('vendor.item.food-variation-generate') }}',
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
                        $('#food_variation_outpot').val(data.variation)
                    }
                }
            });
        });


    </script>
@endpush
