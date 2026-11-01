@extends('layouts.admin.app')

@section('title', translate('Vendor tax report'))

@section('vendor_tax_report')
    active
@endsection
@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/tax.css') }}">
@endpush

@section('content')
    <div class="content container-fluid txr">


        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/outline/report.svg') }}" class="w--26" alt="">
                </span>
                <span>{{ translate('Vendor tax report') }}</span>
            </h1>
            <p class="page-header-desc">{{ translate('The tax collected on each store\'s sales, ready for your return.') }}</p>
        </div>

            <div class="txr-query">
                <form action="" method="get" id="txr-filter" class="txr-query__body">
                    {{-- One label element and one field wrapper for both controls: the
                         date field used `label.form-label` and the vendor picker a bare
                         `span.title-clr`, so the two labels were different sizes and the
                         inputs under them started at different heights. --}}
                    <div class="txr-fields">
                        <div class="txr-field">
                            <label class="form-label" for="dates">{{ translate('Date range') }}</label>
                            <div class="txr-field__control txr-field__control--icon">
                                <i class="tio-calendar-month"></i>
                                <input type="text" id="dates" data-title="{{ translate('Select date range') }}" name="dates"
                                       value="{{ $dateRange ?? null }}" class="date-range-picker form-control">
                            </div>
                        </div>
                        <div class="txr-field">
                            <label class="form-label" for="store_id">{{ translate('Select vendor') }}</label>
                            {{-- No `w-100` here. select2 leaves this <select> in the page,
                                 absolutely positioned and clipped out of sight, and bootstrap's
                                 `.w-100 { width: 100% !important }` lands after select2's
                                 `.select2-hidden-accessible { width: 1px !important }` in the
                                 cascade — so the hidden element stayed a full viewport wide and
                                 stuck out ~885px past the right edge, which is what made the
                                 whole page scroll sideways. `.form-control`/`.custom-select`
                                 already size it without `!important`, so select2 wins over them. --}}
                            <select name="store_id" id="store_id" data-placeholder="{{ translate('Select vendor') }}"
                                class="js-data-example-ajax form-control custom-select custom-select-color border rounded">
                                @if (isset($store))
                                    <option value="{{ $store->id }}" data-verified="{{ (int) $store->verified_seller }}" selected>{{ $store->name }}</option>
                                @else
                                    <option value="all" selected>{{ translate('messages.All vendors') }}</option>
                                @endif
                            </select>
                        </div>
                    </div>
                </form>
                <div class="txr-query__foot">
                    <button type="submit" form="txr-filter" class="btn btn--primary"><i class="tio-filter-list"></i> {{ translate('Filter') }}</button>
                </div>
            </div>
            @include('admin-views.report.tax-report.partials._summary-strip', ['tiles' => [
                [
                    'icon' => 'tio-receipt-outlined',
                    'value' => $totalOrders,
                    'label' => translate('messages.Total orders'),
                ],
                [
                    'icon' => 'tio-money', 'tone' => 'income',
                    'value' => \App\CentralLogics\Helpers::format_currency($totalOrderAmount),
                    'label' => translate('messages.Total order amount'),
                ],
                [
                    'icon' => 'tio-dollar-outlined', 'tone' => 'tax',
                    'value' => \App\CentralLogics\Helpers::format_currency($totalTax),
                    'label' => translate('Total tax amount'),
                ],
            ]])
            <div class="card">
                {{-- Was an `<h4>` beside `_table-head`, which renders its own title —
                     two headings for one table. --}}
                <div class="card-header border-0 py-2">
                    <div class="search--button-wrapper">
                        @include('partials._table-head', [
                            'title'    => translate('Store tax report'),
                            'subtitle' => translate('messages.Tax collected per store over the selected period.'),
                            'count'    => null,
                        ])

                        <form class="search-form min--260">
                            <div class="input-group input--group">
                                <input id="datatableSearch_" type="search" name="search" class="form-control h--40px"
                                    placeholder="{{ translate('Search by vendor name') }} "
                                    value="{{ request()?->search ?? null }}"
                                    aria-label="{{ translate('messages.Search') }}">
                                <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                            </div>
                        </form>
                        @if (request()->input('search'))
                            <button type="reset" class="btn btn--primary ml-2 location-reload-to-base"
                                data-url="{{ url()->full() }}"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                        @endif
                        @include('admin-views.report.tax-report.partials._export-dropdown', [
                            'export_route' => 'admin.transactions.report.vendorWiseTaxExport',
                        ])
                    </div>
                </div>

                <div class="card-body p-0">
                <div class="table-responsive datatable-custom">
                    <table id="datatable"
                        class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table fz--14px">
                        <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{ translate('SL') }}</th>
                                <th class="border-0">{{ translate('Vendor information') }}</th>
                                <th class="border-0">{{ translate('Total order') }}</th>
                                <th class="border-0">{{ translate('Total order amount') }}</th>
                                <th class="border-0">{{ translate('Tax amount') }}</th>
                                <th class="border-0 text-end">{{ translate('Action') }}</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($stores as $key => $store)
                                <tr>
                                    <td>
                                        {{ $key + $stores->firstItem() }}
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.store.view', $store->store_id) }}" target="_blank" rel="noopener noreferrer"
                                           class="txr-payee" title="{{ $store->store_name }}">
                                            <span class="txr-payee__name">{{ $store->store_name }}</span>
                                            <span class="txr-payee__sub">{{ $store->store_phone }}</span>
                                        </a>
                                    </td>
                                    <td>
                                        {{ $store->total_orders }}
                                    </td>
                                    <td>
                                        <span class="txr-amount txr-amount--income">{{ \App\CentralLogics\Helpers::format_currency($store->total_order_amount) }}</span>
                                    </td>
                                    <td>
                                        @php($sum_tax_amount=collect($store->tax_data)->sum('total_tax_amount'))
                                        {{-- The order total and the itemised tax rows are two separate SUMs
                                             over floats, so a store whose tax is fully itemised still leaves a
                                             fraction of a cent between them. Rounded at the precision the
                                             amount is printed with, that residue is nothing and the line is
                                             dropped — only a remainder that shows as money is worth a row. --}}
                                        @php($untaxed_remainder = round($store->store_total_tax_amount - $sum_tax_amount, config('round_up_to_digit')))

                                        <div class="txr-tax">
                                            @if ($untaxed_remainder > 0)
                                                <span class="txr-tax__total">
                                                    {{ translate('Total tax') }}
                                                    <span>{{ \App\CentralLogics\Helpers::format_currency($untaxed_remainder) }}</span>
                                                </span>
                                            @endif
                                            @if ($sum_tax_amount > 0)
                                                <span class="txr-tax__total">
                                                    {{ translate('Sum of taxes') }}
                                                    <span>{{ \App\CentralLogics\Helpers::format_currency($sum_tax_amount) }}</span>
                                                </span>
                                                @foreach ($store->tax_data as $tax)
                                                    <span class="txr-tax__rate">
                                                        <span class="txr-tax__name" title="{{ $tax['tax_name'] }}">{{ $tax['tax_name'] }}</span>
                                                        <span>{{ \App\CentralLogics\Helpers::format_currency($tax['total_tax_amount']) }}</span>
                                                    </span>
                                                @endforeach
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <a class="btn btn-sm action-btn action-btn--view" target="_blank"
                                                title="{{ translate('View details') }}"
                                                href="{{ route('admin.transactions.report.vendorTax', ['id' => $store->store_id, 'dates' => $dateRange]) }}">
                                                <i class="tio-visible-outlined"></i>
                                            </a>
                                            <a class="btn btn-sm btn--success action-btn btn-outline-success"
                                                title="{{ translate('messages.Export') }}"
                                                href="{{ route('admin.transactions.report.vendorTaxExport', array_merge(request()->except(['page', 'export_type']), ['export_type' => 'excel', 'id' => $store->store_id])) }}">
                                                <i class="tio-download-to"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach

                        </tbody>
                    </table>
                </div>
                @if (count($stores) === 0)
                    <div class="empty--data">
                        <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
                        <h5>{{ translate('messages.No vendor tax found') }}</h5>
                        <p>
                            @if(request()->filled('search'))
                                {{ translate('messages.Nothing matches this search. Try another vendor name.') }}
                            @else
                                {{ translate('messages.No taxed orders fall inside this date range. Widen it, or pick a different vendor.') }}
                            @endif
                        </p>
                    </div>
                @endif
                </div>

                <div class="page-area">
                    {!! $stores->links() !!}
                </div>
            </div>
    </div>

@endsection

@push('script_2')
    <script>

        "use strict";
        $(function() {
            $('input[name="dates"]').daterangepicker({
                startDate: moment('{{ $startDate }}'),
                endDate: moment('{{ $endDate }}'),
                maxDate: moment(),
                locale: {
                    format: 'MM/DD/YYYY'
                }
            });
        });
        $(document).on('ready', function() {
            $('.js-data-example-ajax').select2({
                ajax: {
                    url: '{{ route('admin.store.get-stores') }}',
                    data: function(params) {
                        return {
                            q: params.term, // search term
                            all: true,

                            page: params.page
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
        });
    </script>
@endpush
