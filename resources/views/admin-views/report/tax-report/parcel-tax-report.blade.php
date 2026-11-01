@extends('layouts.admin.app')

@section('title', translate('Parcel tax report'))

@section('parcel_tax_report')
    active
@endsection
@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/tax.css') }}">
@endpush

@section('content')
    @php
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
    @endphp
    <div class="content container-fluid txr">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/outline/report.svg') }}" class="w--26" alt="">
                </span>
                <span>{{ translate('Parcel tax report') }}</span>
            </h1>
            <p class="page-header-desc">{{ translate('The tax collected on parcel deliveries, ready for your return.') }}</p>
        </div>

            <div class="txr-query">
                <form action="" method="get" id="txr-filter" class="txr-query__body">
                    <div class="txr-fields">
                        <div class="txr-field">
                            <label class="form-label" for="dates">{{ translate('Date range') }}</label>
                            <div class="txr-field__control txr-field__control--icon">
                                <i class="tio-calendar-month"></i>
                                <input type="text" id="dates" data-title="{{ translate('Select date range') }}" name="dates"
                                       value="{{ $dateRange ?? null }}" class="date-range-picker form-control" autocomplete="off" data-no-global-daterangepicker>
                            </div>
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
                <div class="card-header border-0 py-2">
                    <div class="search--button-wrapper">
                        @include('partials._table-head', [
                            'title'    => translate('Parcel tax report'),
                            'subtitle' => translate('messages.Tax collected on parcel delivery orders.'),
                            'count'    => $orders->total(),
                        ])

                        @include('admin-views.report.tax-report.partials._export-dropdown', [
                            'export_route' => 'admin.transactions.report.parcel-wise-tax-export',
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
                                    <th>{{ translate('Customer') }}</th>
                                    <th>{{ translate('Parcel category') }}</th>
                                    <th>{{ translate('Status') }}</th>
                                    <th class="col--numeric">{{ translate('Order amount') }}</th>
                                    <th>{{ translate('Tax amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($orders as $order)
                                    @php
                                        $tax_lines = $order->orderTaxes
                                            ->groupBy(fn ($tax) => $tax->tax_on.'|'.$tax->tax_name)
                                            ->map(fn ($group) => [
                                                'basic' => $group->first()->tax_on === 'basic',
                                                'on' => $tax_on_labels[$group->first()->tax_on] ?? ucfirst(str_replace('_', ' ', $group->first()->tax_on)),
                                                'name' => $group->first()->tax_name,
                                                'amount' => \App\CentralLogics\Helpers::format_currency($group->sum('tax_amount')),
                                            ])
                                            ->values();
                                        $tax_total = $tax_lines->isNotEmpty() ? $order->orderTaxes->sum('tax_amount') : $order->total_tax_amount;
                                        $order_status = $order_status_labels[$order->order_status] ?? [ucfirst(str_replace('_', ' ', $order->order_status)), 'info'];
                                    @endphp
                                    <tr>
                                        <td>
                                            <a class="font-weight-bold" href="{{ route('admin.parcel.order.details', ['id' => $order->id]) }}">#{{ $order->id }}</a>
                                        </td>
                                        <td>
                                            <span class="table-when">
                                                <span class="table-when__day">{{ \App\CentralLogics\Helpers::date_format($order->created_at) }}</span>
                                                <span class="table-when__ago text-uppercase">{{ \App\CentralLogics\Helpers::time_format($order->created_at) }}</span>
                                            </span>
                                        </td>
                                        <td>
                                            @if ($order->is_guest)
                                                <span class="text-muted">{{ translate('Guest user') }}</span>
                                            @elseif ($order->customer)
                                                <span class="txr-payee">
                                                    <span class="txr-payee__name">{{ $order->customer->f_name }} {{ $order->customer->l_name }}</span>
                                                    <span class="txr-payee__sub">{{ $order->customer->phone }}</span>
                                                </span>
                                            @else
                                                <span class="text-muted">{{ translate('Invalid customer data') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $order->parcel_category?->name ?? '—' }}</td>
                                        <td>
                                            <span class="badge badge-soft-{{ $order_status[1] }}">{{ $order_status[0] }}</span>
                                        </td>
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
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($orders->isEmpty())
                        <div class="empty--data">
                            <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
                            <h5>{{ translate('messages.No parcel tax found') }}</h5>
                            <p>{{ translate('messages.No taxed parcel orders fall inside this date range. Widen it and filter again.') }}</p>
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
    </script>
@endpush
