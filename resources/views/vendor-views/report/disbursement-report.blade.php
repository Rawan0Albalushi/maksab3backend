@extends('layouts.vendor.app')

@section('title', translate('Disbursement report'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/disbursement.css') }}">
@endpush

@section('content')
    @php($is_custom = ($filter ?? 'all_time') === 'custom')
    @php($is_filtered = $status !== 'all' || $payment_method_id !== 'all' || ($filter ?? 'all_time') !== 'all_time' || request('search'))
    @php($payout_count = fn ($count) => translate('messages.Payouts') . ': ' . number_format((int) $count))
    <div class="content container-fluid sdb">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/report/new/disburstment.png') }}" class="w--22" alt="">
                </span>
                <span>{{ translate('Disbursement report') }}</span>
            </h1>
            <p class="page-header-desc">{{ translate('Every payout you have received, and when it cleared.') }}</p>
        </div>

        <div class="mb-20">
            @include('admin-views.disbursement.partials._summary-strip', [
                'payout_summary' => $payout_summary,
                'lead_value' => number_format((int) $payout_summary->sum('payouts')),
                'lead_label' => translate('messages.Payouts in this period'),
                'released_label' => translate('messages.Received'),
                'payout_count' => $payout_count,
            ])
        </div>

        <div class="card mb-20">
            <div class="card-body">
                <div class="mb-3">
                    <h4 class="mb-1">{{ translate('Filter data') }}</h4>
                    <p class="fs-12 text-muted mb-0">{{ translate('Narrow the payout list down by status, payment method and date.') }}</p>
                </div>
                <form method="get" action="{{ route('vendor.report.disbursement-report') }}">
                    @if (request('search'))
                        <input type="hidden" name="search" value="{{ request('search') }}">
                    @endif
                    <div class="__bg-F8F9FC-card">
                        <div class="row g-3 align-items-end" data-date-range>
                            <div class="col-sm-6 col-xl-3">
                                <label class="input-label" for="payment_method_id">{{ translate('messages.Payment method') }}</label>
                                <select name="payment_method_id" id="payment_method_id" class="form-control custom-select h--45px">
                                    <option value="all">{{ translate('All payment method') }}</option>
                                    @foreach ($withdrawal_methods as $item)
                                        <option value="{{ $item['id'] }}" @selected((string) $payment_method_id === (string) $item['id'])>{{ $item['method_name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <label class="input-label" for="payout_status">{{ translate('messages.Status') }}</label>
                                <select name="status" id="payout_status" class="form-control custom-select h--45px">
                                    <option value="all" @selected($status === 'all')>{{ translate('All status') }}</option>
                                    <option value="pending" @selected($status === 'pending')>{{ translate('messages.Pending') }}</option>
                                    <option value="completed" @selected($status === 'completed')>{{ translate('messages.Completed') }}</option>
                                    <option value="canceled" @selected($status === 'canceled')>{{ translate('messages.Canceled') }}</option>
                                </select>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <label class="input-label" for="payout_filter">{{ translate('Date range') }}</label>
                                <select name="filter" id="payout_filter" class="form-control custom-select h--45px" data-date-range-select>
                                    <option value="all_time" @selected(($filter ?? 'all_time') === 'all_time')>{{ translate('All time') }}</option>
                                    <option value="this_week" @selected($filter === 'this_week')>{{ translate('This week') }}</option>
                                    <option value="this_month" @selected($filter === 'this_month')>{{ translate('This month') }}</option>
                                    <option value="this_year" @selected($filter === 'this_year')>{{ translate('This year') }}</option>
                                    <option value="previous_year" @selected($filter === 'previous_year')>{{ translate('Previous year') }}</option>
                                    <option value="custom" @selected($is_custom)>{{ translate('Custom range') }}</option>
                                </select>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <div class="d-flex gap-2">
                                    <a href="{{ route('vendor.report.disbursement-report') }}" class="btn btn--reset h--45px flex-grow-1 d-inline-flex align-items-center justify-content-center"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</a>
                                    <button type="submit" class="btn btn--primary h--45px flex-grow-1"><i class="tio-filter-list"></i> {{ translate('messages.Filter') }}</button>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-3" data-custom-date @if (!$is_custom) hidden @endif>
                                <label class="input-label" for="from_date">{{ translate('Start date') }} <span class="text-danger">*</span></label>
                                <input type="date" name="from" id="from_date" class="form-control h--45px" value="{{ $from }}" @required($is_custom) @disabled(!$is_custom)>
                            </div>
                            <div class="col-sm-6 col-xl-3" data-custom-date @if (!$is_custom) hidden @endif>
                                <label class="input-label" for="to_date">{{ translate('End date') }} <span class="text-danger">*</span></label>
                                <input type="date" name="to" id="to_date" class="form-control h--45px" value="{{ $to }}" @required($is_custom) @disabled(!$is_custom)>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-0 py-2">
                <div class="search--button-wrapper">
                    @include('partials._table-head', [
                        'title' => translate('Total disbursements'),
                        'count' => $disbursements->total(),
                        'count_id' => 'countItems',
                        'subtitle' => translate('messages.Each payout the admin has run for your store, newest first.'),
                    ])
                    <form class="search-form">
                        @foreach (request()->only(['filter', 'from', 'to', 'status', 'payment_method_id']) as $param => $param_value)
                            <input type="hidden" name="{{ $param }}" value="{{ $param_value }}">
                        @endforeach
                        <div class="input--group input-group input-group-merge input-group-flush">
                            <input type="search" class="form-control" value="{{ request('search') }}" placeholder="{{ translate('messages.Search by payout ID') }}" name="search">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                    <div class="hs-unfold">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle h--40px" href="javascript:;"
                           data-hs-unfold-options='{"target": "#usersExportDropdown", "type": "css-animation"}'>
                            <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                        </a>
                        <div id="usersExportDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                            <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                            <a id="export-excel" class="dropdown-item" href="{{ route('vendor.report.disbursement-report-export', ['type' => 'excel', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin/svg/components/excel.svg') }}" alt="">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item" href="{{ route('vendor.report.disbursement-report-export', ['type' => 'csv', request()->getQueryString()]) }}">
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
                                <th>{{ translate('messages.Payout ID') }}</th>
                                <th>{{ translate('messages.Created at') }}</th>
                                <th class="col--numeric">{{ translate('Disburse amount') }}</th>
                                <th>{{ translate('messages.Payment method') }}</th>
                                <th>{{ translate('messages.Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($disbursements as $disbursement)
                                @php($method_fields = json_decode($disbursement->withdraw_method?->method_fields ?? '', true) ?: [])
                                @php($account_number = $method_fields['account_number'] ?? null)
                                @php($account_name = $method_fields['account_name'] ?? null)
                                @php($account = is_scalar($account_number) && trim((string) $account_number) !== '' ? '•••• '.substr((string) $account_number, -4) : (is_scalar($account_name) ? $account_name : null))
                                <tr>
                                    <td><span class="sdb-id">#{{ $disbursement->disbursement_id }}</span></td>
                                    <td>
                                        <span class="table-when">
                                            <span class="table-when__day">{{ \App\CentralLogics\Helpers::date_format($disbursement->created_at) }}</span>
                                            <span class="table-when__ago text-uppercase">{{ \App\CentralLogics\Helpers::time_format($disbursement->created_at) }}</span>
                                        </span>
                                    </td>
                                    <td class="col--numeric">
                                        <span class="sdb-amount">{{ \App\CentralLogics\Helpers::format_currency($disbursement->disbursement_amount) }}</span>
                                    </td>
                                    <td>
                                        <span class="sdb-method">
                                            <i class="tio-credit-card"></i>
                                            <span class="sdb-method__text">
                                                @if ($disbursement->withdraw_method)
                                                    <span class="sdb-method__name">{{ $disbursement->withdraw_method->method_name }}</span>
                                                    @if ($account)
                                                        <span class="sdb-method__acc">{{ $account }}</span>
                                                    @endif
                                                @else
                                                    <span class="sdb-method__name">{{ translate('messages.Payment method removed') }}</span>
                                                @endif
                                            </span>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="sdb-status">
                                            @include('admin-views.disbursement.partials._status-pill', ['status' => $disbursement->status])
                                            @if ($disbursement->status !== 'pending' && $disbursement->updated_at)
                                                <span class="sdb-status__when">{{ translate('messages.Processed') }}: {{ \App\CentralLogics\Helpers::time_date_format($disbursement->updated_at) }}</span>
                                            @endif
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (count($disbursements) === 0)
                    @include('admin-views.disbursement.partials._empty', [
                        'empty_title' => translate('messages.No payout found'),
                        'empty_body' => $is_filtered
                            ? translate('messages.Nothing matches this filter. Try another status, payment method or date range.')
                            : translate('messages.Your payouts appear here once the admin runs a disbursement.'),
                    ])
                @else
                    <div class="page-area px-4 pb-3">
                        {!! $disbursements->links() !!}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/view-pages/vendor/report.js') }}"></script>
@endpush
