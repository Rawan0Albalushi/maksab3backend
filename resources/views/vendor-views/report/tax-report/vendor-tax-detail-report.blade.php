@extends('layouts.vendor.app')

@section('title', translate('Tax report'))

@section('vendor_tax_report')
    active
@endsection

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/tax.css') }}">
@endpush

@section('content')
    @php
        $tax_type_labels = [
            'order_wise' => translate('messages.order_wise'),
            'category_wise' => translate('Category wise'),
            'product_wise' => translate('messages.product_wise'),
        ];
        $basic_labels = [
            'order_wise' => translate('Order tax'),
            'category_wise' => translate('messages.Category tax'),
            'product_wise' => translate('messages.product_tax'),
        ];
        $tax_on_labels = [
            'tax_on_additional_charge' => translate('Additional charge'),
            'tax_on_packaging_charge' => translate('Packaging charge'),
            'tax_on_delivery_charge' => translate('Delivery charge'),
        ];
        $order_status_labels = [
            'delivered' => [translate('Delivered'), 'success'],
            'refund_requested' => [translate('Refund requested'), 'warning'],
            'refund_request_canceled' => [translate('Refund request canceled'), 'info'],
        ];
        $payment_status_labels = [
            'paid' => [translate('messages.paid'), 'success'],
            'partially_paid' => [translate('Partially paid'), 'warning'],
            'unpaid' => [translate('messages.unpaid'), 'danger'],
        ];
    @endphp

    <div class="content container-fluid txr">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/outline/report.svg') }}" class="w--26" alt="">
                </span>
                <span>{{ translate('Tax report') }}</span>
            </h1>
            <p class="page-header-desc">{{ translate('The tax charged on your delivered orders, ready for your return.') }}</p>
        </div>

        <div class="txr-query">
            <form action="{{ route('vendor.report.vendorTax') }}" method="get" id="txr-filter" class="txr-query__body">
                @if (request()->filled('search'))
                    <input type="hidden" name="search" value="{{ request('search') }}">
                @endif
                <div class="txr-fields">
                    <div class="txr-field">
                        <label class="form-label" for="dates">{{ translate('Date range') }}</label>
                        <div class="txr-field__control txr-field__control--icon">
                            <i class="tio-calendar-month"></i>
                            <input type="text" id="dates" name="dates" value="{{ $dateRange }}" class="form-control" autocomplete="off" data-no-global-daterangepicker>
                        </div>
                    </div>
                </div>
            </form>
            <div class="txr-query__foot">
                <button type="submit" form="txr-filter" class="btn btn--primary"><i class="tio-filter-list"></i> {{ translate('messages.Filter') }}</button>
            </div>
        </div>

        @include('admin-views.report.tax-report.partials._summary-strip', ['tiles' => [
            [
                'icon' => 'tio-receipt-outlined',
                'value' => number_format($totalOrders),
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

        @if (count($taxSummary))
            <div class="card mb-3">
                <div class="card-body">
                    <span class="d-block fs-12 text-muted text-uppercase mb-2">{{ translate('messages.Tax by rate') }}</span>
                    <div class="row g-2">
                        @foreach ($taxSummary as $tax_row)
                            <div class="col-sm-6 col-lg-4 col-xl-3">
                                <div class="txr-tax__rate border rounded px-3 py-2 h-100">
                                    <div class="txr-tax__name" title="{{ $tax_row->tax_name }}">{{ $tax_row->tax_name }}</div>
                                    <span>{{ \App\CentralLogics\Helpers::format_currency($tax_row->total_tax) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header border-0 py-2">
                <div class="search--button-wrapper">
                    @include('partials._table-head', [
                        'title' => translate('messages.Taxed orders'),
                        'subtitle' => translate('messages.Tax collected on your orders over the selected period.'),
                        'count' => $orders->total(),
                    ])
                    <form class="search-form min--260" action="{{ route('vendor.report.vendorTax') }}" method="get">
                        <input type="hidden" name="dates" value="{{ $dateRange }}" data-no-global-daterangepicker>
                        <div class="input-group input--group">
                            <input type="search" name="search" class="form-control h--40px"
                                   placeholder="{{ translate('Search by order ID') }}"
                                   value="{{ request('search') }}" aria-label="{{ translate('messages.Search') }}">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                    @include('admin-views.report.tax-report.partials._export-dropdown', [
                        'export_route' => 'vendor.report.vendorTaxExport',
                    ])
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive datatable-custom">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ translate('messages.Order ID') }}</th>
                                <th>{{ translate('Order date') }}</th>
                                <th>{{ translate('Tax type') }}</th>
                                <th class="col--numeric">{{ translate('Order amount') }}</th>
                                <th>{{ translate('Tax amount') }}</th>
                                <th class="text-center">{{ translate('messages.Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                @php
                                    $on_labels = ['basic' => $basic_labels[$order->tax_type] ?? $basic_labels['order_wise']] + $tax_on_labels;
                                    $tax_lines = $order->orderTaxes
                                        ->groupBy(fn ($tax) => $tax->tax_on.'|'.$tax->tax_name)
                                        ->map(fn ($group) => [
                                            'basic' => $group->first()->tax_on === 'basic',
                                            'on' => $on_labels[$group->first()->tax_on] ?? ucfirst(str_replace('_', ' ', $group->first()->tax_on)),
                                            'name' => $group->first()->tax_name,
                                            'amount' => \App\CentralLogics\Helpers::format_currency($group->sum('tax_amount')),
                                        ])
                                        ->values();
                                    $tax_total = $tax_lines->isNotEmpty() ? $order->orderTaxes->sum('tax_amount') : $order->total_tax_amount;
                                    $order_status = $order_status_labels[$order->order_status] ?? [ucfirst(str_replace('_', ' ', $order->order_status)), 'info'];
                                    $payment_status = $payment_status_labels[$order->payment_status] ?? [ucfirst(str_replace('_', ' ', $order->payment_status)), 'info'];
                                    $detail = [
                                        'id' => '#'.$order->id,
                                        'date' => \App\CentralLogics\Helpers::date_format($order->created_at).' · '.\App\CentralLogics\Helpers::time_format($order->created_at),
                                        'order_status' => $order_status[0],
                                        'order_tone' => $order_status[1],
                                        'payment_status' => $payment_status[0],
                                        'payment_tone' => $payment_status[1],
                                        'order_amount' => \App\CentralLogics\Helpers::format_currency($order->order_amount),
                                        'tax_total' => \App\CentralLogics\Helpers::format_currency($tax_total),
                                        'lines' => $tax_lines,
                                    ];
                                @endphp
                                <tr>
                                    <td>
                                        <a class="font-weight-bold" href="{{ route('vendor.order.details', ['id' => $order->id]) }}">#{{ $order->id }}</a>
                                    </td>
                                    <td>
                                        <span class="table-when">
                                            <span class="table-when__day">{{ \App\CentralLogics\Helpers::date_format($order->created_at) }}</span>
                                            <span class="table-when__ago text-uppercase">{{ \App\CentralLogics\Helpers::time_format($order->created_at) }}</span>
                                        </span>
                                    </td>
                                    <td>{{ $tax_type_labels[$order->tax_type] ?? $tax_type_labels['order_wise'] }}</td>
                                    <td class="col--numeric">
                                        <span class="txr-amount">{{ \App\CentralLogics\Helpers::format_currency($order->order_amount) }}</span>
                                    </td>
                                    <td>
                                        <div class="txr-tax">
                                            <span class="txr-tax__total">{{ \App\CentralLogics\Helpers::format_currency($tax_total) }}</span>
                                            @foreach ($tax_lines as $line)
                                                <div class="d-flex align-items-baseline gap-2 fs-12" title="{{ $line['on'] }} · {{ $line['name'] }}">
                                                    <span class="text-muted">{{ $line['basic'] ? $line['name'] : $line['on'] }}</span>
                                                    <span class="text--title font-weight-bold">{{ $line['amount'] }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <a class="btn btn-sm mx-auto action-btn action-btn--view offcanvas-trigger" href="#0"
                                           data-target="#txr-order-drawer" data-tax-detail="{{ json_encode($detail) }}"
                                           title="{{ translate('View details') }}">
                                            <i class="tio-visible-outlined"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($orders->isEmpty())
                    <div class="empty--data">
                        <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
                        <h5>{{ translate('messages.No tax found') }}</h5>
                        @if (request()->filled('search'))
                            <p>{{ translate('messages.Nothing matches this search. Try another order ID.') }}</p>
                        @else
                            <p>{{ translate('messages.No taxed orders fall inside this date range. Widen it and filter again.') }}</p>
                        @endif
                    </div>
                @endif
            </div>

            @if ($orders->isNotEmpty())
                <hr>
            @endif
            <div class="page-area">
                {!! $orders->links() !!}
            </div>
        </div>
    </div>

    <div id="txr-order-drawer" class="custom-offcanvas d-flex flex-column" role="dialog" aria-modal="true" aria-labelledby="txr-drawer-title">
        <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
            <h3 class="mb-0" id="txr-drawer-title">{{ translate('Tax details') }}</h3>
            <button type="button" class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary text-dark offcanvas-close fz-15px p-0"
                    aria-label="{{ translate('messages.Close') }}">&times;</button>
        </div>
        <div class="custom-offcanvas-body p-20 overflow-auto">
            <div class="bg--secondary rounded p-20">
                <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                    <h4 class="mb-0">{{ translate('messages.Order ID') }} <span data-detail="id"></span></h4>
                    <span class="badge" data-detail="order_status" data-detail-tone="order_tone"></span>
                </div>
                <div class="fz--14px title-clr mb-2">{{ translate('messages.Date') }}: <span data-detail="date"></span></div>
                <div class="d-flex align-items-center gap-2 fz--14px title-clr mb-20">
                    {{ translate('Payment status') }}: <span class="badge" data-detail="payment_status" data-detail-tone="payment_tone"></span>
                </div>
                <div class="border d-flex align-items-center bg-white-n justify-content-between rounded p-12 mb-20 fz--14px">
                    {{ translate('Order amount') }}
                    <span class="title-clr font-semibold" data-detail="order_amount"></span>
                </div>
                <div class="bg-white-n rounded p-12">
                    <div data-detail-lines></div>
                    <div class="d-flex align-items-center fz--14px border-top pt-2 justify-content-between">
                        {{ translate('Total tax amount') }}
                        <span class="title-clr font-semibold" data-detail="tax_total"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>
@endsection

@push('script_2')
    @php
        $range_labels = [
            'today' => translate('messages.today'),
            'yesterday' => translate('messages.Yesterday'),
            'last_7_days' => translate('messages.Last') . ' ' . \Carbon\CarbonInterval::days(7)->forHumans(['skip' => ['week']]),
            'last_30_days' => translate('messages.Last') . ' ' . \Carbon\CarbonInterval::days(30)->forHumans(['skip' => ['week']]),
            'this_month' => translate('This month'),
            'last_month' => translate('messages.Last month'),
            'custom' => translate('Custom range'),
            'apply' => translate('messages.Apply'),
            'cancel' => translate('messages.Cancel'),
        ];
    @endphp
    <script>
        "use strict";

        $(function () {
            const labels = @json($range_labels);
            const ranges = {};
            ranges[labels.today] = [moment(), moment()];
            ranges[labels.yesterday] = [moment().subtract(1, 'days'), moment().subtract(1, 'days')];
            ranges[labels.last_7_days] = [moment().subtract(6, 'days'), moment()];
            ranges[labels.last_30_days] = [moment().subtract(29, 'days'), moment()];
            ranges[labels.this_month] = [moment().startOf('month'), moment().endOf('month')];
            ranges[labels.last_month] = [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')];

            $('#dates').daterangepicker({
                startDate: moment('{{ $startDate->format('Y-m-d') }}'),
                endDate: moment('{{ $endDate->format('Y-m-d') }}'),
                maxDate: moment(),
                ranges: ranges,
                locale: {
                    format: 'MM/DD/YYYY',
                    customRangeLabel: labels.custom,
                    applyLabel: labels.apply,
                    cancelLabel: labels.cancel
                }
            });

            $(document).on('click', '[data-tax-detail]', function () {
                const detail = $(this).data('tax-detail');
                const $drawer = $('#txr-order-drawer');

                $drawer.find('[data-detail]').each(function () {
                    $(this).text(detail[$(this).data('detail')] ?? '');
                });
                $drawer.find('[data-detail-tone]').each(function () {
                    $(this).attr('class', 'badge badge-soft-' + detail[$(this).data('detail-tone')]);
                });

                const $lines = $drawer.find('[data-detail-lines]').empty();
                const groups = {};
                detail.lines.forEach(function (line) {
                    (groups[line.on] = groups[line.on] || []).push(line);
                });
                Object.keys(groups).forEach(function (on) {
                    $('<div class="fz-12px font-semibold text-muted mb-1"></div>').text(on).appendTo($lines);
                    groups[on].forEach(function (line) {
                        $('<div class="d-flex align-items-center justify-content-between gap-3 fz-12px mb-2"></div>')
                            .append($('<span></span>').text(line.name))
                            .append($('<span class="title-clr font-semibold"></span>').text(line.amount))
                            .appendTo($lines);
                    });
                });
            });
        });
    </script>
@endpush
