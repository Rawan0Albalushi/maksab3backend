@extends('layouts.admin.app')

@section('title', translate('Parcel transaction report'))

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
                    {{ translate('Parcel transaction report') }}
                    @if ( $from && $to)
                        <span class="mb-0 h6 badge badge-soft-success ml-2"
                              id="itemCount">( {{ $from }} - {{ $to  }} )</span>
                    @endif
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Money in and out on the parcel side, day by day.') }}</p>
        </div>
        <div class="card mb-20">
            <div class="card-body">
                <h4 class="">{{ translate('Search data') }}</h4>
                <form>
                    @csrf
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-3">
                            <select name="module_id" class="form-control js-select2-custom set-filter" data-url="{{ url()->full() }}" data-filter="module_id"
                                    title="{{ translate('messages.Select modules') }}">
                                <option value="" {{ !request('module_id') ? 'selected' : '' }}>
                                    {{ translate('All modules') }}</option>
                                @foreach (\App\CentralLogics\Helpers::modules_list()->where('module_type', 'parcel')->where('status', 1) as $module)
                                    <option value="{{ $module->id }}"
                                        {{ request('module_id') == $module->id ? 'selected' : '' }}>
                                        {{ $module['module_name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <select name="zone_id" class="form-control js-select2-custom set-filter" data-url="{{ url()->full() }}" data-filter="zone_id" id="zone">
                                <option value="all">{{ translate('All zones') }}</option>
                                @foreach (\App\CentralLogics\Helpers::zones_dropdown() as $z)
                                    <option value="{{ $z['id'] }}"
                                        {{ isset($zone) && $zone->id == $z['id'] ? 'selected' : '' }}>
                                        {{ $z['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <select class="form-control set-filter" name="filter" data-url="{{ url()->full() }}" data-filter="filter">
                                <option value="all_time" {{ isset($filter) && $filter == 'all_time' ? 'selected' : '' }}>
                                    {{ translate('All time') }}</option>
                                <option value="this_year" {{ isset($filter) && $filter == 'this_year' ? 'selected' : '' }}>
                                    {{ translate('This year') }}</option>
                                <option value="previous_year"
                                    {{ isset($filter) && $filter == 'previous_year' ? 'selected' : '' }}>
                                    {{ translate('Previous year') }}</option>
                                <option value="this_month"
                                    {{ isset($filter) && $filter == 'this_month' ? 'selected' : '' }}>
                                    {{ translate('This month') }}</option>
                                <option value="this_week" {{ isset($filter) && $filter == 'this_week' ? 'selected' : '' }}>
                                    {{ translate('This week') }}</option>
                                <option value="custom" {{ isset($filter) && $filter == 'custom' ? 'selected' : '' }}>
                                    {{ translate('messages.Custom') }}</option>
                            </select>
                        </div>
                        @if (isset($filter) && $filter == 'custom')
                            <div class="col-sm-6 col-md-3">
                                <input type="date" name="from" id="from_date" class="form-control"
                                       placeholder="{{ translate('Start date') }}" value="{{ $from ?? '' }}" required>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <input type="date" name="to" id="to_date" class="form-control"
                                       placeholder="{{ translate('End date') }}"
                                       value="{{ $to ?? '' }}" required>
                            </div>
                        @endif
                        <div class="col-sm-6 col-md-3 ml-auto">
                            <button type="submit"
                                    class="btn btn-primary btn-block h--45px"><i class="tio-filter-list"></i> {{ translate('Filter') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="mb-20">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <a class="__card-3 h-100" href="#">
                                <img src="{{ asset('/public/assets/admin/img/report/new/trx1.png') }}" class="icon"
                                     alt="report/new">
                                <h3 class="title text-008958">{{ \App\CentralLogics\Helpers::number_format_short($delivered) }}
                                </h3>
                                <h6 class="subtitle">{{ translate('Completed transaction') }}</h6>
                                <div class="info-icon" data-toggle="tooltip" data-placement="top"
                                     data-original-title="{{ translate('When the order is successfully delivered full order amount goes to this section.') }}">
                                    <img src="{{ asset('/public/assets/admin/img/report/new/info1.png') }}"
                                         alt="report/new">
                                </div>
                            </a>
                        </div>
                        <div class="col-sm-6">
                            <a class="__card-3 h-100" href="#">
                                <img src="{{ asset('/public/assets/admin/img/report/new/trx3.png') }}" class="icon"
                                     alt="report/new">
                                <h3 class="title text-FF5A54">{{ \App\CentralLogics\Helpers::number_format_short($canceled) }}
                                </h3>
                                <h6 class="subtitle">{{ translate('Refunded transaction') }}</h6>
                                <div class="info-icon" data-toggle="tooltip" data-placement="top"
                                     data-original-title="{{ translate('Refunded orders show the full amount here, excluding delivery fee and tips.') }}">
                                    <img src="{{ asset('/public/assets/admin/img/report/new/info3.png') }}"
                                         alt="report/new">
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="row g-2">
                        <div class="col-md-12">
                            <div class="__card-vertical">
                                <div class="__card-vertical-img">
                                    <img class="img"
                                         src="{{ asset('/public/assets/admin/img/report/new/admin-earning.png') }}"
                                         alt="">
                                    <h4 class="name">{{ translate('Admin earning') }}</h4>
                                    <div class="info-icon" data-toggle="tooltip" data-placement="right"
                                         data-original-title="{{ translate('Deducting the admin discount from the admin earning amount and goes to this section.') }}">
                                        <img src="{{ asset('/public/assets/admin/img/report/new/info1.png') }}"
                                             alt="report/new">
                                    </div>
                                </div>
                                <h4 class="earning text-0661CB">
                                    {{ \App\CentralLogics\Helpers::number_format_short($admin_earned) }}</h4>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="__card-vertical">
                                <div class="__card-vertical-img">
                                    <img class="img"
                                         src="{{ asset('/public/assets/admin/img/report/new/deliveryman-earning.png') }}"
                                         alt="">
                                    <h4 class="name">{{ translate('Deliveryman earning') }}</h4>
                                    <div class="info-icon" data-toggle="tooltip" data-placement="right"
                                         data-original-title="{{ translate('Deducting the admin commission on the delivery fee, the delivery fee & tips amount goes to earning section.') }}">
                                        <img src="{{ asset('/public/assets/admin/img/report/new/info3.png') }}"
                                             alt="report/new">
                                    </div>
                                </div>
                                <h4 class="earning text-FF7500">
                                    {{ \App\CentralLogics\Helpers::number_format_short($deliveryman_earned) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header border-0 py-2">
                <div class="search--button-wrapper">
                    <h3 class="card-title">
                        {{ translate('messages.Parcel transactions') }} <span
                                class="badge badge-soft-secondary" id="countItems">{{ $order_transactions->total() }}</span>
                    </h3>
                    <form class="search-form">
                        <div class="input--group input-group input-group-merge input-group-flush">
                            <input class="form-control" placeholder="{{ translate('Search by order ID') }}" value="{{ request()?->search ?? null}}" name="search">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                    <div class="hs-unfold ml-3">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle btn export-btn font--sm"
                           href="javascript:;"
                           data-hs-unfold-options="{
                                &quot;target&quot;: &quot;#usersExportDropdown&quot;,
                                &quot;type&quot;: &quot;css-animation&quot;
                            }"
                           data-hs-unfold-target="#usersExportDropdown" data-hs-unfold-invoker="">
                            <i class="tio-download-to mr-1"></i> {{ translate('Export') }}
                        </a>

                        <div id="usersExportDropdown"
                             class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right hs-unfold-content-initialized hs-unfold-css-animation animated hs-unfold-reverse-y hs-unfold-hidden">

                            <span class="dropdown-header">{{ translate('Download options') }}</span>
                            <a id="export-excel" class="dropdown-item"
                               href="{{ route('admin.transactions.report.parcel-transaction-report-export', ['type' => 'excel', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                     src="{{ asset('public/assets/admin/svg/components/excel.svg') }}"
                                     alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item"
                               href="{{ route('admin.transactions.report.parcel-transaction-report-export', ['type' => 'csv', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                     src="{{ asset('public/assets/admin/svg/components/placeholder-csv-format.svg') }}"
                                     alt="Image Description">
                                CSV
                            </a>

                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="datatable" class="table table-thead-bordered table-align-middle card-table">
                        <thead class="thead-light text-nowrap">
                        <tr>
                            <th class="border-0">{{ translate('SL') }}</th>
                            <th class="border-0">{{ translate('messages.Order ID') }}</th>
                            <th class="border-0">{{ translate('Customer name') }}</th>
                            <th class="border-0">{{ translate('Referral discount') }}</th>
                            <th class="border-0">{{ translate('VAT/tax') }}</th>
                            <th class="border-0">{{ translate('Delivery charge') }}</th>
                            <th class="border-0">{{ translate('Order amount') }}</th>
                            <th class="border-0">{{ translate('Admin discount') }}</th>
                            <th class="border-0">{{ translate('Admin commission') }}</th>
                            <th class="border-0">{{ \App\CentralLogics\Helpers::get_business_data('additional_charge_name')??translate('Additional charge') }}</th>
                            <th class="min-w-140 text-capitalize">{{ translate('Commision on delivery charge') }}</th>
                            <th class="min-w-140 text-capitalize">{{ translate('Admin net income') }}</th>
                            <th class="border-0 min-w-120">{{ translate('messages.Amount received by') }}</th>
                            <th class="border-top border-bottom text-capitalize">{{ translate('messages.Payment method') }}</th>
                            <th class="border-0">{{ translate('Payment status') }}</th>
                            <th class="border-0">{{ translate('messages.Action') }}</th>
                        </tr>
                        </thead>
                        <tbody id="set-rows">
                        @foreach ($order_transactions as $k => $ot)
                            <tr scope="row">
                                <td>{{ $k + $order_transactions->firstItem() }}</td>
                                <td><a
                                            href="{{ route('admin.transactions.parcel.order.details', $ot->order_id) }}">{{ $ot->order_id }}</a>
                                </td>
                                <td class="white-space-nowrap">
                                    @php($delivery_address = $ot->order ? (is_array($ot->order->delivery_address) ? $ot->order->delivery_address : json_decode($ot->order->delivery_address, true)) : null)
                                    @if ($ot->order && $ot->order->customer)
                                        <a class="text-body text-capitalize"
                                           href="{{ route('admin.users.customer.view', [$ot->order['user_id']]) }}">
                                            <strong>{{ $ot->order->customer['f_name'] . ' ' . $ot->order->customer['l_name'] }}</strong>
                                        </a>
                                    @elseif (!empty($delivery_address['contact_person_name']))
                                        <strong>{{ $delivery_address['contact_person_name'] }}</strong>
                                    @else
                                        <label class="badge badge-danger">{{ translate('messages.Invalid customer data') }}</label>
                                    @endif
                                </td>
                                <td class="white-space-nowrap">{{ \App\CentralLogics\Helpers::format_currency($ot->order['ref_bonus_amount']) }}</td>
                                <td class="white-space-nowrap">{{ \App\CentralLogics\Helpers::format_currency($ot->tax) }}</td>
                                <td class="white-space-nowrap">{{ \App\CentralLogics\Helpers::format_currency($ot->delivery_charge + ($ot->pro_delivery_discount ?? 0)) }}</td>
                                <td class="white-space-nowrap">{{ \App\CentralLogics\Helpers::format_currency($ot->order_amount) }}</td>

                                <td class="white-space-nowrap">{{ \App\CentralLogics\Helpers::format_currency($ot->admin_expense) }}</td>

                                <td class="white-space-nowrap">{{ \App\CentralLogics\Helpers::format_currency(($ot->admin_commission + $ot->admin_expense) - $ot->delivery_fee_comission -$ot->additional_charge) }}</td>

                                <td class="white-space-nowrap">{{ \App\CentralLogics\Helpers::format_currency(($ot->additional_charge)) }}</td>
                                <td class="white-space-nowrap">{{ \App\CentralLogics\Helpers::format_currency($ot->delivery_fee_comission) }}</td>
                                <td class="white-space-nowrap">{{ \App\CentralLogics\Helpers::format_currency(app(\App\Services\Order\OrderTransactionService::class)->adminNetIncome($ot)) }}</td>

                                @if ($ot->received_by == 'admin')
                                    <td class="text-capitalize white-space-nowrap">{{ translate('messages.admin') }}</td>
                                @elseif ($ot->received_by == 'deliveryman')
                                    <td class="text-capitalize white-space-nowrap">
                                        <div>{{ translate('Deliveryman') }}</div>
                                        <div class="text-right mw--85px">
                                            @if (isset($ot->delivery_man) && $ot->delivery_man->earning == 1)
                                                <span class="badge badge-soft-primary">
                                                    {{translate('Freelancer')}}
                                                </span>
                                            @elseif (isset($ot->delivery_man) && $ot->delivery_man->earning == 0 && $ot->delivery_man->type == 'restaurant_wise')
                                                <span class="badge badge-soft-warning">
                                                    {{translate('messages.Restaurant')}}
                                                </span>
                                            @elseif (isset($ot->delivery_man) && $ot->delivery_man->earning == 0 && $ot->delivery_man->type == 'zone_wise')
                                                <span class="badge badge-soft-success">
                                                    {{translate('messages.admin')}}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                @elseif ($ot->received_by == 'store')
                                    <td class="text-capitalize white-space-nowrap">{{ translate('messages.Store') }}</td>
                                @else
                                    <td></td>
                                @endif
                                <td class="mw--85px text-capitalize min-w-120 ">
                                    {{ payment_method_label($ot->order['payment_method']) }}
                                </td>
                                <td class="text-capitalize white-space-nowrap">
                                    @if ($ot->status)
                                        <span class="badge badge-soft-danger">
                                            {{translate('Refunded')}}
                                        </span>
                                    @else
                                        <span class="badge badge-soft-success">
                                            {{translate('messages.Completed')}}
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    <div class="btn--container justify-content-center">
                                        <a class="btn btn-outline-success square-btn btn-sm mr-1 action-btn"  href="{{route('admin.report.generate-statement',[$ot['id']])}}">
                                            <i class="tio-download-to"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @if (count($order_transactions) !== 0)
                <hr>
            @endif
            <div class="page-area px-3">
                {!! $order_transactions->links() !!}
            </div>
            @if (count($order_transactions) === 0)
                <div class="empty--data">
                    <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
                    <h5>
                        {{ translate('No data found') }}
                    </h5>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('script')
@endpush

@push('script_2')
    <script src="{{ asset('public/assets/admin') }}/vendor/chart.js/dist/Chart.min.js"></script>
    <script src="{{ asset('public/assets/admin') }}/vendor/chartjs-chart-matrix/dist/chartjs-chart-matrix.min.js">
    </script>
    <script src="{{ asset('public/assets/admin') }}/js/hs.chartjs-matrix.js"></script>
    <script src="{{ asset('public/assets/admin') }}/js/view-pages/admin-reports.js"></script>
@endpush
