@extends('layouts.admin.app')

@section('title',translate('Subscriber list'))

@section('subscriberList')
active
@endsection

@push('css_or_js')

@endpush

@section('content')

    @php($is_provider = in_array(request()->module, [1, 'rental', 'service']))
    @php($warning_days = (int) ($data['deadline_warning_days'] ?? 7))
    @php($canceled_by_labels = ['admin' => translate('admin'), 'store' => translate('Store'), 'none' => translate('N/A')])

    <div class="content container-fluid">
        <div class="page-header">
            <div class="d-flex flex-wrap justify-content-between align-items-center py-2">
                <div class="flex-grow-1">
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <img src="{{ asset('public/assets/admin/img/store.png') }}" class="w--26" alt="">
                        </span>
                        <span>
                            {{ $is_provider ? translate('Subscribed provider list') : translate('Subscribed store list') }}
                            <span class="badge badge-soft-dark ml-2" id="itemCount">{{ $subscribers->total() }}</span>
                        </span>
                    </h1>
                    <p class="page-header-desc">{{ translate('Stores on a subscription, the package each is on and when it renews.') }}</p>
                </div>
                <div class="min--200">
                    <select name="zone_id" class="form-control js-select2-custom set-filter" data-url="{{ url()->full() }}" data-filter="zone_id" id="zone">
                        <option value="all">{{translate('All zones')}}</option>
                        @foreach(\App\CentralLogics\Helpers::zones_dropdown() as $z)
                            <option value="{{$z['id']}}" {{ request()?->zone_id == $z['id']?'selected':''}}>
                                {{($z['name'])}}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @if (addon_published_status('Rental') || addon_published_status('Service'))
        <ul class="nav nav-tabs border-0 nav--tabs nav--pills mb-4">
            <li class="nav-item">
                <a class="nav-link {{ !$is_provider ? 'active' : '' }}" href="{{ route('admin.business-settings.subscriptionackage.subscriberList') }}">{{ translate('Order module') }}</a>
            </li>

            @if (addon_published_status('Rental'))
            <li class="nav-item">
                <a class="nav-link {{ in_array(request()->module, [1, 'rental']) ? 'active' : '' }}" href="{{ route('admin.business-settings.subscriptionackage.subscriberList', ['module' => 'rental']) }}">{{ translate('Rental module') }}</a>
            </li>
            @endif

            @if (addon_published_status('Service'))
            <li class="nav-item">
                <a class="nav-link {{ request()->module == 'service' ? 'active' : '' }}" href="{{ route('admin.business-settings.subscriptionackage.subscriberList', ['module' => 'service']) }}">{{ translate('Service module') }}</a>
            </li>
            @endif
        </ul>
        @endif

        <div class="mb-20">
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <a class="__card-2 __bg-1" href="#">
                        <h4 class="title text--title">{{ $data['total_subscribed_user'] }}</h4>
                        <span class="subtitle">{{ translate('Total subscribed user') }}</span>
                        <img src="{{asset('public/assets/admin/img/subscription-plan/subscribed-user.png')}}" alt="report/new" class="card-icon" width="35px">
                    </a>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <a class="__card-2 __bg-3" href="#">
                        <h4 class="title text--title">{{ $data['active_subscription'] }}</h4>
                        <span class="subtitle">{{ translate('Active subscriptions') }}</span>
                        <img src="{{asset('public/assets/admin/img/subscription-plan/active-user.png')}}" alt="report/new" class="card-icon" width="35px">
                    </a>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <a class="__card-2 __bg-6" href="#">
                        <h4 class="title text--title">{{ $data['expired_subscription'] }}</h4>
                        <span class="subtitle">{{ translate('Expired subscription') }}</span>
                        <img src="{{asset('public/assets/admin/img/subscription-plan/expired-user.png')}}" alt="report/new" class="card-icon" width="35px">
                    </a>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <a class="__card-2 __bg-4" href="#">
                        <h4 class="title text--title">{{ $data['expired_soon'] }}</h4>
                        <span class="subtitle">{{ translate('Expiring soon') }} </span>
                        <img src="{{asset('public/assets/admin/img/subscription-plan/expired-soon.png')}}" alt="report/new" class="card-icon" width="35px">
                    </a>
                </div>
            </div>
        </div>
        <ul class="transaction--information text-uppercase">
            <li class="text--info">
                <i class="tio-document-text-outlined"></i>
                <div>
                    <span> {{ translate('Total transactions') }} </span> <strong> {{ $data['total_transactions']  }}</strong>
                </div>
            </li>
            <li class="seperator"></li>
            <li class="text--success">
                <i class="tio-checkmark-circle-outlined success--icon"></i>
                <div>
                    <span> {{ translate('Total earning') }} </span> <strong> {{ \App\CentralLogics\Helpers::format_currency($data['total_paid_amount'])  }}</strong>
                </div>
            </li>
            <li class="seperator"></li>
            <li class="text--warning">
                <i class="tio-atm"></i>
                <div>
                    <span> {{ translate('EARNED THIS MONTH') }} </span> <strong> {{ \App\CentralLogics\Helpers::format_currency($data['current_month_paid_amount'])  }}</strong>
                </div>
            </li>
        </ul>
        <div class="card">
            <div class="card-header flex-wrap py-2 border-0">
                <div class="search--button-wrapper">
                    @include('partials._table-head', [
                        'subtitle' => $is_provider
                            ? translate('Every provider on a paid or trial package, what it pays and when the package lapses.')
                            : translate('Every store on a paid or trial package, what it pays and when the package lapses.'),
                        'count' => null,
                    ])

                    <div class="max-sm-flex-1">
                        <select   name="subscription_type"  data-url="{{ url()->full() }}" data-filter="subscription_type" class="custom-select h--40px py-0 status-filter set-filter" >
                            <option {{ request()?->subscription_type == 'all' ? 'selected' : '' }}  value="all">
                                {{ translate('All') }}
                            </option>
                            <option {{ request()?->subscription_type == 'active' ? 'selected' : '' }}  value="active">
                                {{ translate('Active') }}
                            </option>
                            <option {{ request()?->subscription_type == 'expired' ? 'selected' : '' }}  value="expired">
                                {{ translate('Expired') }}
                            </option>
                            <option {{ request()?->subscription_type == 'cancaled' ? 'selected' : '' }}  value="cancaled">
                                {{ translate('Canceled') }}
                            </option>
                            <option {{ request()?->subscription_type == 'free_trial' ? 'selected' : '' }}  value="free_trial">
                                {{ translate('Free trial') }}
                            </option>

                        </select>
                    </div>
                    <form class="search-form">
                        <input type="hidden" name="module" value="{{ request()->module }}">
                        <div class="input-group input--group">
                            <input name="search" type="search" value="{{ request()?->search }}" class="form-control h--40px" placeholder="{{ translate('Search by name or package') }}" aria-label="Search here">
                            <button type="submit" class="btn btn--secondary h--40px"><i class="tio-search"></i></button>
                        </div>
                    </form>
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
                                href="{{ route('admin.business-settings.subscriptionackage.subscriberListExport', ['export_type' => 'excel', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin/svg/components/excel.svg') }}"
                                    alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item"
                                href="{{ route('admin.business-settings.subscriptionackage.subscriberListExport', ['export_type' => 'csv', request()->getQueryString()]) }}">
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
                                <th class="border-0">{{ $is_provider ? translate('Provider information') : translate('Store information') }}</th>
                                <th class="border-0">{{ translate('Package') }}</th>
                                <th class="border-0">{{ translate('Expires on') }}</th>
                                <th class="border-0 col--numeric">{{ translate('Transactions') }}</th>
                                <th class="border-0">{{ translate('Status') }}</th>
                                <th class="border-0 text-center">{{ translate('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subscribers as $subscriber)
                                @php($subscription = $subscriber->store_sub_update_application)
                                @php($package = $subscription?->package)
                                @php($expiry = $subscription?->expiry_date ? \Carbon\Carbon::parse($subscription->expiry_date) : null)
                                @php($days_left = $expiry ? (int) \Carbon\Carbon::now()->startOfDay()->diffInDays($expiry->copy()->startOfDay(), false) : null)
                                @php($validity = $subscription?->validity ?: $package?->validity)
                                @php($store_reviews = app(\App\Services\Store\StoreService::class)->calculateRating($subscriber['rating']))
                                @php($store_meta = array_filter([$subscriber->module?->module_name, $subscriber->zone?->name]))
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.store.view', ['store' => $subscriber->id, 'module_id' => $subscriber->module_id]) }}" class="table-rest-info min--200">
                                            <img src="{{ $subscriber->logo_full_url ?? asset('public/assets/admin/img/100x100/1.png') }}" alt="">
                                            <div class="info">
                                                <span class="d-block text-title line--limit-2" title="{{ $subscriber->name }}">{{ $subscriber->name }}</span>
                                                <span class="rating text-star"><i class="tio-star"></i> {{ number_format($store_reviews['rating'], 1) }}</span>
                                                @if (count($store_meta))
                                                    <span class="d-block fs-12 text-muted font-weight-normal">{{ implode(' · ', $store_meta) }}</span>
                                                @endif
                                            </div>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="d-block text-title font-semibold line--limit-2 max-w-220px" @if($package) title="{{ $package->package_name }}" @endif>
                                            {{ $package?->package_name ?? translate('No data found') }}
                                        </span>
                                        @if ($package)
                                            <span class="d-block fs-12 text-muted">
                                                {{ \App\CentralLogics\Helpers::format_currency($package->price) }}@if ($validity) · {{ translate('Validity') }}: {{ \Carbon\CarbonInterval::days($validity)->forHumans() }}@endif
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($expiry)
                                            <span class="table-when{{ $days_left <= $warning_days ? ' table-when--stale' : '' }}">
                                                <span class="table-when__day">{{ \App\CentralLogics\Helpers::date_format($expiry) }}</span>
                                                <span class="table-when__ago">
                                                    @if ($days_left > 1)
                                                        {{ translate('Expires') }} {{ $expiry->copy()->startOfDay()->diffForHumans(\Carbon\Carbon::now()->startOfDay(), \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW) }}
                                                    @elseif ($days_left === 1)
                                                        {{ translate('Expires tomorrow') }}
                                                    @elseif ($days_left === 0)
                                                        {{ translate('Expires today') }}
                                                    @elseif ($days_left === -1)
                                                        {{ translate('Expired yesterday') }}
                                                    @else
                                                        {{ translate('Expired') }} {{ $expiry->copy()->startOfDay()->diffForHumans(\Carbon\Carbon::now()->startOfDay(), \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW) }}
                                                    @endif
                                                </span>
                                            </span>
                                        @else
                                            <span class="text-muted font-size-sm">{{ translate('N/A') }}</span>
                                        @endif
                                    </td>
                                    <td class="col--numeric" data-order="{{ $subscriber->store_all_sub_trans_count }}">
                                        <span class="d-block text-title font-semibold">{{ $subscriber->store_all_sub_trans_count }}</span>
                                        <span class="d-block fs-12 text-muted" title="{{ translate('Paid to date') }}">
                                            {{ \App\CentralLogics\Helpers::format_currency($subscriber->store_sub_paid_total ?? 0) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($subscriber->status == 0 && $subscriber->vendor?->status == 0)
                                            <span class="badge badge-soft-info">{{ translate('Approval pending') }}</span>
                                        @elseif ($subscription?->status == 1)
                                            <span class="badge badge-soft-success">{{ translate('Active') }}</span>
                                        @elseif ($subscription)
                                            <span class="badge badge-soft-danger">{{ translate('Expired') }}</span>
                                        @endif

                                        @if ($subscription?->is_trial || $subscription?->is_canceled)
                                            <span class="cell-chips d-block mt-1">
                                                @if ($subscription->is_trial)
                                                    <span class="cell-chip">{{ translate('Trial') }}</span>
                                                @endif
                                                @if ($subscription->is_canceled)
                                                    <span class="cell-chip" title="{{ translate('Canceled by') . ': ' . ($canceled_by_labels[$subscription->canceled_by ?? 'none'] ?? translate('N/A')) }}">{{ translate('Canceled') }}</span>
                                                @endif
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn action-btn--view" title="{{ translate('View subscription') }}" href="{{ route('admin.business-settings.subscriptionackage.subscriberDetail', ['id' => $subscriber->id, 'module' => $subscriber->module_id]) }}">
                                                <i class="tio-visible-outlined"></i>
                                            </a>
                                            <a class="btn action-btn action-btn--open" title="{{ translate('View transactions') }}" href="{{ route('admin.business-settings.subscriptionackage.subscriberTransactions', ['id' => $subscriber->id]) }}">
                                                <i class="tio-receipt-outlined"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if(count($subscribers) !== 0)
                <hr>
                @endif
                <div class="page-area">
                    {!! $subscribers->withQueryString()->links() !!}
                </div>
                @if(count($subscribers) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>
                        {{translate('No data found')}}
                    </h5>
                </div>
                @endif
            </div>
        </div>
    </div>

@endsection

@push('script_2')

@endpush
