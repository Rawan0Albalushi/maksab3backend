@extends('layouts.vendor.app')

@section('title', translate('messages.Add new addon'))

@push('css_or_js')
@endpush

@section('content')
    @php($addonCategoryNames = $addonCategories->pluck('name', 'id'))
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/addon.png') }}" class="w--26" alt="">
                </span>
                <span>{{ translate('messages.Add new addon') }}</span>
            </h1>
            <p class="page-header-desc">{{ translate('Extras a customer can add to one of your items, such as a sauce, a topping or a larger size.') }}</p>
        </div>
        <div class="row g-3">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body">
                        <form action="{{ route('vendor.addon.store') }}" method="post">
                            @csrf
                            @if ($language)
                                <ul class="nav nav-tabs mb-4">
                                    <li class="nav-item">
                                        <a class="nav-link lang_link active offcanvas-close" href="#"
                                            id="default-link">{{ translate('Default') }}</a>
                                    </li>
                                    @foreach ($language as $lang)
                                        <li class="nav-item">
                                            <a class="nav-link lang_link offcanvas-close" href="#"
                                                id="{{ $lang }}-link">{{ $language_labels[$lang] }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            <div class="row">
                                <div class="col-6">
                                    @if ($language)
                                        <div class="form-group lang_form" id="default-form">
                                            <label class="input-label"
                                                for="exampleFormControlInput1">{{ translate('Name') }}
                                                ({{ translate('Default') }})</label>
                                            <input type="text" name="name[]" class="form-control"
                                                placeholder="{{ translate('messages.New addon') }}" maxlength="191">
                                        </div>
                                        <input type="hidden" name="lang[]" value="default">
                                        @foreach ($language as $lang)
                                            <div class="form-group d-none lang_form" id="{{ $lang }}-form">
                                                <label class="input-label"
                                                    for="exampleFormControlInput1">{{ translate('Name') }}
                                                    ({{ strtoupper($lang) }})
                                                </label>
                                                <input type="text" name="name[]" class="form-control"
                                                    placeholder="{{ translate('messages.New addon') }}" maxlength="191">
                                            </div>
                                            <input type="hidden" name="lang[]" value="{{ $lang }}">
                                        @endforeach
                                    @else
                                        <div class="form-group">
                                            <label class="input-label"
                                                for="exampleFormControlInput1">{{ translate('Name') }}</label>
                                            <input type="text" name="name" class="form-control"
                                                placeholder="{{ translate('messages.New addon') }}"
                                                value="{{ old('name') }}" maxlength="191">
                                        </div>
                                        <input type="hidden" name="lang[]" value="default">
                                    @endif
                                </div>

                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.price') }}</label>
                                        <input type="number" min="0" max="999999999999.99" name="price"
                                            step="0.01" value="{{ old('price') }}" class="form-control"
                                            placeholder="100" required>
                                    </div>
                                </div>


                                <div class="col-6">
                                    <div class="form-group">
                                        <span class="mb-2 d-block title-clr fw-normal">{{ translate('Category') }}</span>
                                        <select name="category_id" required class="form-control js-select2-custom"
                                            placeholder="Select Category">
                                            <option selected disabled value="">
                                                {{ translate('Select category') }}</option>
                                            @foreach ($addonCategories as $addonCategory)
                                                <option value="{{ $addonCategory->id }}"> {{ $addonCategory->name }}
                                                </option>
                                            @endforeach
                                        </select>

                                    </div>
                                </div>


                                @if ($productWiseTax)
                                    <div class="col-6">
                                        <div class="form-group">
                                            <span
                                                class="mb-2 d-block title-clr fw-normal">{{ translate('Select tax rate') }}</span>
                                            <select name="tax_ids[]" required id="tax__rate"
                                                class="form-control js-select2-custom" multiple="multiple"
                                                placeholder="{{ translate('Type & select tax rate') }}">
                                                @foreach ($taxVats as $taxVat)
                                                    <option value="{{ $taxVat->id }}"> {{ $taxVat->name }}
                                                        ({{ $taxVat->tax_rate }}%)
                                                    </option>
                                                @endforeach
                                            </select>

                                        </div>
                                    </div>

                                @endif
                            </div>


                            <div class="btn--container justify-content-end">
                                <button type="reset" id="reset_btn"
                                    class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                                <button type="submit"
                                    class="btn btn--primary"><i class="{{ isset($addon) ? 'tio-save' : 'tio-add-circle' }}"></i> {{ isset($addon) ? translate('Update') : translate('Add') }}</button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>

            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header py-2 border-0">
                        <div class="search--button-wrapper">
                            @include('partials._table-head', [
                                'title'    => translate('Addon list'),
                                'subtitle' => translate('messages.Optional extras customers can add to your items at checkout.'),
                                'count'    => $addons->total(),
                                'count_id' => 'itemCount',
                            ])
                            <form id="search-form" class="search-form">
                                <div class="input-group input--group">
                                    <input type="text" id="column1_search" class="form-control"
                                        placeholder="{{ translate('messages.Search by addon name') }}">
                                    <button type="button" class="btn btn--secondary">
                                        <i class="tio-search"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="table-responsive datatable-custom">
                        <table id="columnSearchDatatable"
                            class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                            data-hs-datatables-options='{
                                 "order": [],
                                 "orderCellsTop": true,
                                 "paging":false
                               }'>
                            <thead class="thead-light">
                                <tr>
                                    <th class="border-0 w-20p">{{ translate('Name') }}</th>
                                    <th class="border-0 w-20p">{{ translate('messages.Category') }}</th>
                                    <th class="border-0 w-20p col--numeric">{{ translate('messages.price') }}</th>
                                    @if ($productWiseTax)
                                        <th class="border-0 w-20p">{{ translate('VAT/tax') }}</th>
                                    @endif
                                    <th class="border-0 w-20p text-center">{{ translate('messages.Action') }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($addons as $addon)
                                    <tr>
                                        <td>
                                            <span class="d-block text-title font-semibold" title="{{ $addon['name'] }}">
                                                {{ Str::limit($addon['name'], 30, '...') }}
                                            </span>
                                            <span class="d-block fs-12 text-muted">ID:{{ $addon['id'] }}</span>
                                        </td>

                                        <td>
                                            @if ($addonCategoryNames->get($addon['addon_category_id']))
                                                {{ $addonCategoryNames->get($addon['addon_category_id']) }}
                                            @else
                                                <span class="text-muted font-size-sm">{{ translate('messages.N/A') }}</span>
                                            @endif
                                        </td>

                                        <td class="col--numeric" data-order="{{ $addon['price'] }}">{{ \App\CentralLogics\Helpers::format_currency($addon['price']) }}</td>

                                        @if ($productWiseTax)
                                            <td>
                                                <span class="d-block font-size-sm text-body">
                                                    @forelse ($addon?->taxVats?->pluck('tax.name', 'tax.tax_rate')->toArray() as $key => $item)
                                                        <span> {{ $item }} : <span class="font-bold">
                                                                ({{ $key }}%)
                                                            </span> </span>
                                                        <br>
                                                    @empty
                                                        <span> {{ translate('messages.N/A') }} </span>
                                                    @endforelse
                                                </span>
                                            </td>
                                        @endif

                                        <td>
                                            <div class="btn--container justify-content-center">

                                                <a class="btn btn-sm text-end action-btn action-btn--edit offcanvas-trigger get_data data-info-show"
                                                    data-target="#offcanvas__customBtn3" data-id="{{ $addon['id'] }}"
                                                    data-url="{{ route('vendor.addon.edit', [$addon['id']]) }}"
                                                    href="javascript:" title="{{ translate('Edit addon') }}"><i
                                                        class="tio-edit"></i></a>
                                                <a class="btn action-btn action-btn--delete form-alert"
                                                    href="javascript:" data-id="addon-{{ $addon['id'] }}"
                                                    data-message="{{ translate('Want to delete this addon?') }}"
                                                    title="{{ translate('messages.Delete addon') }}"><i
                                                        class="tio-delete-outlined"></i></a>
                                            </div>
                                            <form action="{{ route('vendor.addon.delete', [$addon['id']]) }}"
                                                method="post" id="addon-{{ $addon['id'] }}">
                                                @csrf @method('delete')
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if (count($addons) !== 0)
                        <hr>
                    @endif
                    <div class="page-area">
                        {!! $addons->links() !!}
                    </div>
                    @if (count($addons) === 0)
                        <div class="empty--data">
                            <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
                            <h5>
                                {{ translate('No data found') }}
                            </h5>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div id="offcanvas__customBtn3" class="custom-offcanvas d-flex flex-column justify-content-between">
        <div id="data-view" class="h-100">
        </div>
    </div>
@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/view-pages/datatable-search.js') }}"></script>
    <script>
        "use strict";
        $(document).on('click', '.data-info-show', function() {
            let id = $(this).data('id');
            let url = $(this).data('url');
            fetch_data(id, url)
        })

        function fetch_data(id, url) {
            $.ajax({
                url: url,
                type: "get",
                beforeSend: function() {
                    $('#data-view').empty();
                    $('#loading').show()
                },
                success: function(data) {
                    console.log(data);

                    $("#data-view").append(data.view);
                    initLangTabs();
                    initSelect2Dropdowns();

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

        $('.offcanvas-trigger').on('click', function(e) {
            e.preventDefault();
            var target = $(this).data('target');
            $(target).addClass('open');
            $('#offcanvasOverlay').addClass('show');
        });

        function initSelect2Dropdowns() {
            $('.js-select2-custom1').select2({
                placeholder: 'Select tax rate',
                allowClear: true
            });
            $('.offcanvas-close, #offcanvasOverlay').on('click', function() {
                $('.custom-offcanvas').removeClass('open');
                $('#offcanvasOverlay').removeClass('show');
            });
            $('.offcanvas-trigger').on('click', function(e) {
                e.preventDefault();
                var target = $(this).data('target');
                $(target).addClass('open');
                $('#offcanvasOverlay').addClass('show');
            });
        }
    </script>
@endpush
