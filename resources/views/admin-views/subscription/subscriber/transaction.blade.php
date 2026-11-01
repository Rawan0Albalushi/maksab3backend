@extends('layouts.admin.app')

@section('title',translate('Subscription transactions'))

@section('subscriberList')
active
@endsection

@push('css_or_js')

@endpush

@section('content')

    @php($warning_days = (int) $subscription_deadline_warning_days)
    @php($plan_labels = ['renew' => translate('Renewal'), 'new_plan' => translate('Migrate to new plan'), 'first_purchased' => translate('Purchased'), 'free_trial' => translate('Free trial')])
    @php($author_labels = ['Admin' => translate('admin'), 'Store' => translate('Store')])
    @php($status_labels = ['success' => translate('success'), 'on_hold' => translate('On hold')])
    @php($status_tones = ['success' => 'success', 'on_hold' => 'info'])
    @php($is_custom = request('filter') === 'custom')
    @php($filter_count = collect(['filter' => 'all_time', 'plan_type' => 'all', 'start_date' => null, 'end_date' => null])->filter(fn ($none, $param) => filled(request($param)) && request($param) !== $none)->count())

    <div class="content container-fluid">
        @include('admin-views.subscription.subscriber.partials._subscriber-nav', [
            'sn_active' => 'transactions',
            'sn_subtitle' => translate('Every subscription payment this store has made, what it covered and how it was paid.'),
        ])

        <ul class="transaction--information text-uppercase">
            <li class="text--info">
                <i class="tio-document-text-outlined"></i>
                <div>
                    <span>{{ translate('Total transactions') }}</span> <strong>{{ $transactions->total() }}</strong>
                </div>
            </li>
            <li class="seperator"></li>
            <li class="text--success">
                <i class="tio-checkmark-circle-outlined success--icon"></i>
                <div>
                    <span>{{ translate('Total paid') }}</span> <strong>{{ \App\CentralLogics\Helpers::format_currency($summary['paid_total'] ?? 0) }}</strong>
                </div>
            </li>
            <li class="seperator"></li>
            <li class="text--warning">
                <i class="tio-atm"></i>
                <div>
                    <span>{{ translate('Last payment') }}</span>
                    <strong>{{ $summary['last_paid_at'] ? \App\CentralLogics\Helpers::date_format($summary['last_paid_at']) : translate('N/A') }}</strong>
                </div>
            </li>
        </ul>

        <div class="card">
            <div class="card-header flex-wrap py-2 border-0">
                <div class="search--button-wrapper">
                    @include('partials._table-head', [
                        'title' => translate('Transaction history'),
                        'subtitle' => translate('Every payment against this subscription, the package it covered and how it was paid.'),
                        'count' => $transactions->total(),
                        'count_id' => 'itemCount',
                    ])

                    <form class="search-form">
                        <input type="hidden" name="filter" value="{{ request('filter') }}">
                        <input type="hidden" name="plan_type" value="{{ request('plan_type') }}">
                        <input type="hidden" name="start_date" value="{{ request('start_date') }}">
                        <input type="hidden" name="end_date" value="{{ request('end_date') }}">
                        <div class="input-group input--group">
                            <input name="search" type="search" value="{{ request('search') }}" class="form-control h--40px" placeholder="{{ translate('Ex') . ' : ' . translate('Search by transaction ID') }}" aria-label="Search here">
                            <button type="submit" class="btn btn--secondary h--40px"><i class="tio-search"></i></button>
                        </div>
                    </form>

                    <div class="hs-unfold">
                        <a class="btn btn-sm btn-white h--40px filter-button-show" href="javascript:;"
                            role="button" aria-expanded="false" aria-controls="datatableFilterSidebar">
                            <i class="tio-filter-list mr-1"></i> {{ translate('Filter') }}
                            @if($filter_count)
                                <span class="badge badge-success badge-pill ml-1">{{ $filter_count }}</span>
                            @endif
                        </a>
                    </div>

                    <div class="hs-unfold">
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
                                href="{{ route('admin.business-settings.subscriptionackage.subscriberTransactionExport', [ 'id' =>$store->id, 'export_type' => 'excel', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin/svg/components/excel.svg') }}"
                                    alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item"
                                href="{{ route('admin.business-settings.subscriptionackage.subscriberTransactionExport', [ 'id' =>$store->id, 'export_type' => 'csv', request()->getQueryString()]) }}">
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
                <div class="table-responsive datatable-custom">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{ translate('Transaction ID') }}</th>
                                <th class="border-0">{{ translate('Transaction date') }}</th>
                                <th class="border-0">{{ translate('Package') }}</th>
                                <th class="border-0">{{ translate('Payment type') }}</th>
                                <th class="border-0 col--numeric">{{ translate('Amount') }}</th>
                                <th class="border-0">{{ translate('Status') }}</th>
                                <th class="border-0 text-center">{{ translate('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transactions as $transaction)
                                @php($paid_at = \Carbon\Carbon::parse($transaction->created_at))
                                @php($expiry = $transaction?->subscription?->expiry_date ? \Carbon\Carbon::parse($transaction->subscription->expiry_date) : null)
                                @php($expiring_soon = $expiry && $transaction->subscription->status == 1 && $expiry->copy()->subDays($warning_days)->isBefore(now()))
                                <tr>
                                    <td>
                                        <span class="d-block text-title font-semibold">#{{ $transaction->id }}</span>
                                        @if($transaction->created_by)
                                            <span class="d-block fs-12 text-muted">{{ translate('Recorded by') }}: {{ $author_labels[$transaction->created_by] ?? $transaction->created_by }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="table-when">
                                            <span class="table-when__day">{{ \App\CentralLogics\Helpers::date_format($paid_at) }}</span>
                                            <span class="table-when__ago">{{ $paid_at->diffForHumans() }}</span>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="d-block text-title font-semibold line--limit-2 max-w-220px" @if($transaction->package) title="{{ $transaction->package->package_name }}" @endif>
                                            {{ $transaction->package?->package_name ?? translate('No data found') }}
                                        </span>
                                        @if($transaction->validity)
                                            <span class="d-block fs-12 text-muted">{{ translate('Validity') }}: {{ \Carbon\CarbonInterval::days($transaction->validity)->forHumans() }}</span>
                                        @endif
                                        @if($transaction->is_trial || $expiring_soon)
                                            <span class="cell-chips d-block mt-1">
                                                @if($transaction->is_trial)
                                                    <span class="cell-chip">{{ translate('Trial') }}</span>
                                                @endif
                                                @if($expiring_soon)
                                                    <span class="cell-chip" title="{{ translate('Expiration date') . ': ' . \App\CentralLogics\Helpers::date_format($expiry) }}">{{ translate('Expiring soon') }}</span>
                                                @endif
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="d-block text-title">{{ $plan_labels[$transaction->plan_type] ?? translate('N/A') }}</span>
                                        @if($transaction->payment_method)
                                            <span class="d-block fs-12 text-muted">{{ translate('Paid by') }} {{ payment_method_label($transaction->payment_method) }}</span>
                                        @endif
                                    </td>
                                    <td class="col--numeric" data-order="{{ $transaction->paid_amount }}">
                                        <span class="d-block text-title font-semibold">{{ \App\CentralLogics\Helpers::format_currency($transaction->paid_amount) }}</span>
                                        @if($transaction->discount > 0)
                                            <span class="d-block fs-12 text-muted">{{ translate('Discount') }}: {{ $transaction->discount }}%</span>
                                        @elseif($transaction->price > 0 && $transaction->price != $transaction->paid_amount)
                                            <span class="d-block fs-12 text-muted">{{ translate('Plan price') }}: {{ \App\CentralLogics\Helpers::format_currency($transaction->price) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-soft-{{ $status_tones[$transaction->payment_status] ?? 'danger' }}">
                                            {{ $status_labels[$transaction->payment_status] ?? $transaction->payment_status }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn action-btn--view" target="_blank" rel="noopener"
                                                title="{{ translate('Download invoice') }}"
                                                href="{{ route('admin.business-settings.subscriptionackage.invoice', $transaction->id) }}">
                                                <i class="tio-receipt-outlined"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if(count($transactions) !== 0)
                <hr>
                @endif
                <div class="page-area">
                    {!! $transactions->withQueryString()->links() !!}
                </div>
                @if(count($transactions) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    @if(request('search') || $filter_count)
                        <h5>{{ translate('No transaction matches these filters.') }}</h5>
                        <p class="text-muted font-size-sm">{{ translate('Clear the search or widen the filters to see more transactions.') }}</p>
                    @else
                        <h5>{{ translate('No transaction yet.') }}</h5>
                        <p class="text-muted font-size-sm">{{ translate('Payments appear here as soon as this store buys or renews a package.') }}</p>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>

    <div id="datatableFilterSidebar" class="filter-drawer sidebar sidebar-bordered sidebar-box-shadow">
        <div class="card card-lg sidebar-card sidebar-footer-fixed">
            @include('partials._filter-drawer-head', [
                'fd_title' => translate('Transaction filter'),
                'fd_subtitle' => translate('Narrow these payments down by date range and what the payment was for.'),
            ])

            <form class="card-body sidebar-body sidebar-scrollbar" method="get">
                <input type="hidden" name="search" value="{{ request('search') }}">

                <small class="text-cap mb-3">{{ translate('Duration') }}</small>
                <div class="form-group">
                    <select class="form-control filter" id="filter" name="filter">
                        <option value="all_time" {{ request('filter') == 'all_time' ? 'selected' : '' }}>{{ translate('All time') }}</option>
                        <option value="this_year" {{ request('filter') == 'this_year' ? 'selected' : '' }}>{{ translate('This year') }}</option>
                        <option value="this_month" {{ request('filter') == 'this_month' ? 'selected' : '' }}>{{ translate('This month') }}</option>
                        <option value="this_week" {{ request('filter') == 'this_week' ? 'selected' : '' }}>{{ translate('This week') }}</option>
                        <option value="custom" {{ $is_custom ? 'selected' : '' }}>{{ translate('messages.Custom') }}</option>
                    </select>
                </div>

                <div class="fd-daterange">
                    <div class="fd-daterange__field">
                        <label class="fd-sublabel" for="date_from">{{ translate('Start date') }}</label>
                        <input type="date" id="date_from" class="form-control" value="{{ request('start_date') }}"
                            @if($is_custom) name="start_date" required @else readonly @endif>
                    </div>
                    <div class="fd-daterange__field">
                        <label class="fd-sublabel" for="date_to">{{ translate('End date') }}</label>
                        <input type="date" id="date_to" class="form-control" value="{{ request('end_date') }}"
                            @if($is_custom) name="end_date" required @else readonly @endif>
                    </div>
                </div>

                <hr class="my-4">

                <small class="text-cap mb-3">{{ translate('Payment type') }}</small>
                <div class="form-group">
                    <select class="form-control" name="plan_type">
                        <option value="all" {{ request('plan_type') == 'all' ? 'selected' : '' }}>{{ translate('All') }}</option>
                        <option value="first_purchased" {{ request('plan_type') == 'first_purchased' ? 'selected' : '' }}>{{ translate('Purchased') }}</option>
                        <option value="renew" {{ request('plan_type') == 'renew' ? 'selected' : '' }}>{{ translate('Renewal') }}</option>
                        <option value="new_plan" {{ request('plan_type') == 'new_plan' ? 'selected' : '' }}>{{ translate('Migrate to new plan') }}</option>
                        <option value="free_trial" {{ request('plan_type') == 'free_trial' ? 'selected' : '' }}>{{ translate('Free trial') }}</option>
                    </select>
                </div>

                <div class="card-footer sidebar-footer">
                    <div class="row gx-2">
                        <div class="col">
                            <a class="btn btn-block btn-white" href="{{ request()->fullUrlWithoutQuery(['filter', 'plan_type', 'start_date', 'end_date', 'page']) }}">
                                <i class="tio-clear-circle-outlined"></i> {{ translate('Clear all') }}
                            </a>
                        </div>
                        <div class="col">
                            <button type="submit" class="btn btn-block btn-primary"><i class="tio-filter-list"></i> {{ translate('Apply filters') }}</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('script_2')
<script>
    $("#date_from").on("change", function () {
        $('#date_to').attr('min', $(this).val());
    });

    $("#date_to").on("change", function () {
        $('#date_from').attr('max', $(this).val());
    });

    $(document).on('change', '.filter', function () {
        if ($(this).val() == 'custom') {
            $('#date_from').removeAttr('readonly').attr('name', 'start_date').attr('required', true);
            $('#date_to').removeAttr('readonly').attr('name', 'end_date').attr('required', true);
        } else {
            $('#date_from').attr('readonly', true).removeAttr('name').removeAttr('required');
            $('#date_to').attr('readonly', true).removeAttr('name').removeAttr('required');
        }
    });
</script>
@endpush
