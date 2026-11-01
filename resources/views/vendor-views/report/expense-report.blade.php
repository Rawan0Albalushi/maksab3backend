@extends('layouts.vendor.app')

@section('title', translate('Expense report'))

@push('css_or_js')
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/outline/report.svg') }}" class="w--26" alt="">
                </span>
                <span>
                    {{ translate('Expense report') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('What your discounts, coupons and free deliveries have actually cost you.') }}</p>
        </div>

        @php($is_custom = ($filter ?? 'all_time') === 'custom')
        @php($active_filter_count = ($filter && $filter !== 'all_time') ? 1 : 0)
        @php($type_labels = [
            'discount_on_product' => translate('messages.discount_on_product'),
            'discount_on_trip' => translate('Discount on trip'),
            'discount_on_booking' => translate('Discount on booking'),
            'free_delivery' => translate('Free delivery'),
            'coupon_discount' => translate('Coupon discount'),
            'flash_sale_discount' => translate('messages.flash_sale_discount'),
            'bogo_discount' => translate('BOGO discount'),
            'happy_hour_discount' => translate('Happy hour discount'),
            'bundle_discount' => translate('Bundle discount'),
            'extra_discount' => translate('Extra discount'),
        ])
        @php($is_rental = $module_type == 'rental')
        @php($is_service = $module_type == 'service' && service_addon_active())
        @php($summary_total = $summary->sum('amount'))
        @php($summary_entries = (int) $summary->sum('entries'))

        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap align-items-center gap-4">
                <div class="pr-4 border-right">
                    <span class="d-block fs-12 text-muted text-uppercase">{{ translate('messages.Total expense') }}</span>
                    <span class="d-block h2 mb-0 text--title">{{ \App\CentralLogics\Helpers::format_currency($summary_total) }}</span>
                    <span class="d-block fs-12 text-muted">{{ translate('messages.Entries') }}: {{ number_format($summary_entries) }}</span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($summary as $row)
                        <span class="cell-chip">{{ $type_labels[$row->type] ?? ucfirst(str_replace('_', ' ', $row->type)) }} &middot; {{ \App\CentralLogics\Helpers::format_currency($row->amount) }}</span>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-0 py-2">
                <div class="search--button-wrapper">
                    @include('partials._table-head', [
                        'title' => translate('messages.Expense lists'),
                        'count' => $expense->total(),
                        'count_id' => 'countItems',
                        'subtitle' => translate('messages.Each discount you paid for, newest first.'),
                    ])
                    <form class="search-form">
                        @foreach (request()->only(['filter', 'from', 'to']) as $param => $param_value)
                            <input type="hidden" name="{{ $param }}" value="{{ $param_value }}">
                        @endforeach
                        <div class="input--group input-group input-group-merge input-group-flush">
                            <input name="search" value="{{ request()->search ?? null }}" type="search" class="form-control" placeholder="{{ translate('Search by order ID, phone number or customer name') }}">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                    <div class="hs-unfold">
                        <a class="btn btn-sm btn-white h--40px filter-button-show" href="javascript:;"
                           role="button" aria-expanded="false" aria-controls="datatableFilterSidebar">
                            <i class="tio-filter-list mr-1"></i> {{ translate('messages.Filter') }}
                            @if ($active_filter_count)
                                <span class="badge badge-success badge-pill ml-1">{{ $active_filter_count }}</span>
                            @endif
                        </a>
                    </div>
                    <div class="hs-unfold">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle h--40px" href="javascript:;"
                            data-hs-unfold-options='{"target": "#usersExportDropdown", "type": "css-animation"}'>
                            <i class="tio-download-to mr-1"></i> {{ translate('Export') }}
                        </a>
                        <div id="usersExportDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                            <span class="dropdown-header">{{ translate('Download options') }}</span>
                            <a id="export-excel" class="dropdown-item" href="{{ route('vendor.report.expense-export', ['type' => 'excel', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin/svg/components/excel.svg') }}" alt="">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item" href="{{ route('vendor.report.expense-export', ['type' => 'csv', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin/svg/components/placeholder-csv-format.svg') }}" alt="">
                                CSV
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive datatable-custom">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ $is_rental ? translate('Trip ID') : ($is_service ? translate('Booking ID') : translate('messages.Order ID')) }}</th>
                                <th>{{ translate('Date & time') }}</th>
                                <th>{{ translate('Expense type') }}</th>
                                <th>{{ translate('messages.Customer') }}</th>
                                <th class="col--numeric">{{ translate('messages.Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody id="set-rows">
                            @foreach ($expense as $exp)
                                @php($customer_name = null)
                                @php($customer_phone = null)
                                @php($customer_invalid = false)
                                @if ($exp->order)
                                    @if ($exp->order->is_guest)
                                        @php($address = is_array($exp->order->delivery_address) ? $exp->order->delivery_address : (json_decode($exp->order->delivery_address ?? '', true) ?: []))
                                        @php($customer_name = $address['contact_person_name'] ?? translate('messages.Guest user'))
                                        @php($customer_phone = $address['contact_person_number'] ?? null)
                                    @elseif ($exp->order->customer)
                                        @php($customer_name = trim($exp->order->customer->f_name.' '.$exp->order->customer->l_name))
                                        @php($customer_phone = $exp->order->customer->phone)
                                    @else
                                        @php($customer_invalid = true)
                                    @endif
                                @elseif ($exp->trip)
                                    @php($info = is_array($exp->trip->user_info) ? $exp->trip->user_info : (json_decode($exp->trip->user_info ?? '', true) ?: []))
                                    @if ($exp->trip->customer)
                                        @php($customer_name = $exp->trip->customer->fullName)
                                        @php($customer_phone = $exp->trip->customer->phone)
                                    @else
                                        @php($customer_name = $info['contact_person_name'] ?? translate('messages.Guest user'))
                                        @php($customer_phone = $info['contact_person_phone'] ?? ($info['contact_person_number'] ?? null))
                                    @endif
                                @elseif ($exp->serviceBooking)
                                    @php($info = is_array($exp->serviceBooking->user_info) ? $exp->serviceBooking->user_info : (json_decode($exp->serviceBooking->user_info ?? '', true) ?: []))
                                    @if (!$exp->serviceBooking->is_guest && $exp->serviceBooking->customer)
                                        @php($customer_name = trim($exp->serviceBooking->customer->f_name.' '.$exp->serviceBooking->customer->l_name))
                                        @php($customer_phone = $exp->serviceBooking->customer->phone)
                                    @else
                                        @php($customer_name = $info['contact_person_name'] ?? translate('messages.Guest user'))
                                        @php($customer_phone = $info['contact_person_number'] ?? null)
                                    @endif
                                @else
                                    @php($customer_invalid = true)
                                @endif
                                <tr>
                                    <td>
                                        @if ($is_rental)
                                            @if ($exp->trip_id)
                                                <a class="font-weight-bold" href="{{ route('vendor.trip.details', ['id' => $exp->trip_id]) }}">#{{ $exp->trip_id }}</a>
                                            @else
                                                <span class="badge badge-soft-danger">{{ translate('messages.invalid_trip_data') }}</span>
                                            @endif
                                        @elseif ($is_service)
                                            @if ($exp->service_booking_id)
                                                <a class="font-weight-bold" href="{{ route('vendor.service.booking.details', ['booking' => $exp->service_booking_id]) }}">#{{ $exp->service_booking_id }}</a>
                                            @else
                                                <span class="badge badge-soft-danger">{{ translate('messages.invalid_booking_data') }}</span>
                                            @endif
                                        @else
                                            @if ($exp->order_id)
                                                <a class="font-weight-bold" href="{{ route('vendor.order.details', ['id' => $exp->order_id]) }}">#{{ $exp->order_id }}</a>
                                            @else
                                                <span class="badge badge-soft-danger">{{ translate('messages.Invalid order data') }}</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td>
                                        <span class="table-when">
                                            <span class="table-when__day">{{ \App\CentralLogics\Helpers::date_format($exp->created_at) }}</span>
                                            <span class="table-when__ago text-uppercase">{{ \App\CentralLogics\Helpers::time_format($exp->created_at) }}</span>
                                        </span>
                                    </td>
                                    <td>{{ $type_labels[$exp->type] ?? ucfirst(str_replace('_', ' ', $exp->type)) }}</td>
                                    <td>
                                        @if ($customer_invalid)
                                            <span class="badge badge-soft-danger">{{ translate('messages.Invalid customer data') }}</span>
                                        @else
                                            <span class="d-block text--title">{{ $customer_name }}</span>
                                            @if ($customer_phone)
                                                <a class="d-block fs-12 text-muted" href="tel:{{ $customer_phone }}">{{ $customer_phone }}</a>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="col--numeric">
                                        <span class="d-block text--title font-weight-bold">{{ \App\CentralLogics\Helpers::format_currency($exp->amount) }}</span>
                                        @if ($exp->order && $exp->order->order_amount)
                                            <span class="d-block fs-12 text-muted">{{ translate('Order amount') }}: {{ \App\CentralLogics\Helpers::format_currency($exp->order->order_amount) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (count($expense) === 0)
                    <div class="empty--data">
                        <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
                        @if (request('search'))
                            <h5>{{ translate('messages.No expense matches your search.') }}</h5>
                            <p class="text-muted font-size-sm">{{ translate('Try a different order ID, customer name or phone number.') }}</p>
                        @else
                            <h5>{{ translate('messages.No expense in this period.') }}</h5>
                            <p class="text-muted font-size-sm">{{ translate('Discounts you pay for will show up here.') }}</p>
                        @endif
                    </div>
                @else
                    <div class="page-area px-4 pb-3">
                        {!! $expense->withQueryString()->links() !!}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div id="datatableFilterSidebar" class="filter-drawer sidebar sidebar-bordered sidebar-box-shadow">
        <div class="card card-lg sidebar-card sidebar-footer-fixed">
            @include('partials._filter-drawer-head', [
                'fd_title' => translate('messages.Expense filter'),
                'fd_subtitle' => translate('messages.Narrow the expense list down by date range.'),
            ])

            <form class="card-body sidebar-body sidebar-scrollbar" action="{{ route('vendor.report.expense-report') }}" method="GET" id="expense_filter_form" data-date-range>
                @if (request('search'))
                    <input type="hidden" name="search" value="{{ request('search') }}">
                @endif

                <small class="text-cap mb-3">{{ translate('Date range') }}</small>
                <div class="form-group">
                    <select name="filter" id="expense_filter" class="form-control" data-date-range-select>
                        <option value="" @selected(!$filter || $filter === 'all_time')>{{ translate('All time') }}</option>
                        <option value="this_week" @selected($filter === 'this_week')>{{ translate('This week') }}</option>
                        <option value="this_month" @selected($filter === 'this_month')>{{ translate('This month') }}</option>
                        <option value="this_year" @selected($filter === 'this_year')>{{ translate('This year') }}</option>
                        <option value="previous_year" @selected($filter === 'previous_year')>{{ translate('Previous year') }}</option>
                        <option value="custom" @selected($is_custom)>{{ translate('Custom range') }}</option>
                    </select>
                </div>

                <div class="form-group" data-custom-date @if (!$is_custom) hidden @endif>
                    <div class="fd-daterange">
                        <div>
                            <label class="fd-sublabel" for="from_date">{{ translate('Start date') }}</label>
                            <input type="date" name="from" id="from_date" class="form-control" value="{{ $from }}" @required($is_custom) @disabled(!$is_custom)>
                        </div>
                        <div>
                            <label class="fd-sublabel" for="to_date">{{ translate('End date') }}</label>
                            <input type="date" name="to" id="to_date" class="form-control" value="{{ $to }}" @required($is_custom) @disabled(!$is_custom)>
                        </div>
                    </div>
                </div>

                <div class="card-footer sidebar-footer">
                    <div class="row gx-2">
                        <div class="col">
                            <a class="btn btn-block btn-white" href="{{ route('vendor.report.expense-report', array_filter(request()->only('search'))) }}">
                                <i class="tio-clear-circle-outlined"></i> {{ translate('Clear all') }}
                            </a>
                        </div>
                        <div class="col">
                            <button type="submit" class="btn btn-block btn-primary">
                                <i class="tio-filter-list"></i> {{ translate('messages.Filter') }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('script')
@endpush

@push('script_2')
    <script src="{{asset('public/assets/admin')}}/js/view-pages/vendor/report.js"></script>
@endpush

