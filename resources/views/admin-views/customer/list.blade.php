@extends('layouts.admin.app')

@section('title', translate('Customer list'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title mr-3">
                <span class="page-header-icon">
                    <img src="{{asset('/public/assets/admin/img/people.png')}}" class="w--26" alt="">
                </span>
                <span>
                     {{ translate('messages.customers') }}
                <span class="badge badge-soft-dark ml-2" id="count">{{ $customers->total() }}</span></span>
            </h1>
            <p class="page-header-desc">{{ translate('Everyone who has signed up in your apps, and how much each has ordered.') }}</p>
        </div>

        @if($builder_published)
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 __gap-12px">
            <div class="js-nav-scroller hs-nav-scroller-horizontal mt-2">
                <ul class="nav nav-tabs border-0 nav--tabs nav--pills">
                    <li class="nav-item">
                        <a class="nav-link {{ $tab === 'main' ? 'active' : '' }}"
                           href="{{ route('admin.users.customer.list', ['tab' => 'main']) }}">
                            {{ translate('messages.Main system customers') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $tab === 'storefront' ? 'active' : '' }}"
                           href="{{ route('admin.users.customer.list', ['tab' => 'storefront']) }}">
                            {{ translate('messages.Vendor storefront customers') }}
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        @endif
        <div class="card mb-3">
            <div class="card-body">
                <form>
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="row g-3">
                        @if($tab === 'storefront')
                        <div class="col-md-4">
                            <label class="form-label">{{translate('messages.storefront')}}</label>
                            <select name="storefront_id" data-placeholder="{{ translate('messages.All storefronts') }}" class="form-control js-select2-custom">
                                <option value="" {{ !$storefront_id ? 'selected' : '' }}>{{ translate('messages.All storefronts') }}</option>
                                @foreach($storefronts as $sf)
                                    <option value="{{ $sf->id }}" {{ (int) $storefront_id === (int) $sf->id ? 'selected' : '' }}>{{ $sf->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="col-md-4">
                            <label class="form-label">{{translate('Order date')}}</label>
                            <div class="position-relative">
                                <span class="tio-calendar icon-absolute-on-right"></span>
                                <input type="text" data-title="{{ translate('Select Order Date Range') }}" data-startDate="09/04/2024"  data-endDate="09/24/2024" readonly name="order_date" value="{{ request()->input('order_date')  ?? null }}" class="date-range-picker form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{translate('Customer Joining Date')}}</label>
                            <div class="position-relative">
                                <span class="tio-calendar icon-absolute-on-right"></span>
                                <input type="text" data-title="{{ translate('Select Customer Joining Date Range') }}" readonly name="join_date" value="{{ request()->input('join_date') ?? null }}" class="date-range-picker form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{translate('Customer status')}}</label>
                            <select name="filter" data-placeholder="{{ translate('Select status') }}" class="form-control js-select2-custom ">
                                <option  value="" selected disabled > {{ translate('Select status') }} </option>
                                <option  {{ request()->input('filter')  == 'all'?'selected':''}} value="all">{{ translate('All customers') }}</option>
                                <option  {{ request()->input('filter')  == 'active'?'selected':''}} value="active">{{ translate('messages.Active Customers') }}</option>
                                <option  {{ request()->input('filter')  == 'blocked'?'selected':''}} value="blocked">{{ translate('messages.Inactive Customers') }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{translate('Sort by')}}</label>
                            <select name="order_wise"  data-placeholder="{{ translate('messages.Select Customer Sorting Order') }}"

                            class="form-control js-select2-custom">
                                <option value="" selected disabled > {{ translate('messages.Select Customer Sorting Order') }} </option>
                                <option  {{ request()->input('order_wise')  == 'top'?'selected':''}}  value="top">{{ translate('messages.Sort by Orders') }}</option>
                                <option {{ request()->input('order_wise')  == 'order_amount'?'selected':''}}  value="order_amount">{{ translate('messages.Sort by order amount') }}</option>
                                <option {{ request()->input('order_wise')  == 'oldest'?'selected':''}}  value="oldest">{{ translate('messages.Sort by First created') }}</option>
                                <option {{ request()->input('order_wise')  == 'latest'?'selected':''}}  value="latest">{{ translate('messages.Sort by newest') }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{translate('Choose First')}}</label>
                            <input type="number" min="1" name="show_limit" class="form-control" value="{{ request()->input('show_limit')}}" placeholder="{{translate('Ex') . ' : 100'}}">
                        </div>
                        <div class="col-12">
                            <div class="btn--container justify-content-end">
                                <button type="submit" class="btn btn--primary"><i class="tio-filter-list"></i> {{translate('Filter')}}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header border-0  py-2">
                <div class="search--button-wrapper justify-content-end">
                    @include('partials._table-head', [
                        'subtitle' => translate('messages.Everyone registered as a customer, with their order and wallet activity.'),
                    ])



                    <form class="search-form">
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        @if($tab === 'storefront' && $storefront_id)
                            <input type="hidden" name="storefront_id" value="{{ $storefront_id }}">
                        @endif
                        <div class="input-group input--group">
                            <input id="datatableSearch_" type="search" name="search" class="form-control min-height-40"
                                value="{{ request()->input('search') }}" placeholder="{{ translate('Ex') . ': ' . translate('name email or phone') }}"
                                aria-label="Search" >
                            <button type="submit" class="btn btn--secondary min-height-40"><i class="tio-search"></i></button>

                        </div>
                    </form>
                    @if(request()->input('search'))
                    <button type="reset" class="btn btn--primary ml-2 location-reload-to-base" data-url="{{url()->full()}}"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                    @endif

                    <div class="hs-unfold mr-2">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40" href="javascript:;"
                            data-hs-unfold-options='{
                                    "target": "#usersExportDropdown",
                                    "type": "css-animation"
                                }'>
                            <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                        </a>

                        <div id="usersExportDropdown"
                            class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                            <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                            <a id="export-excel" class="dropdown-item" href="{{route('admin.users.customer.export', ['type'=>'excel',request()->getQueryString()])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                    alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item" href="{{route('admin.users.customer.export', ['type'=>'csv',request()->getQueryString()])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                    alt="Image Description">
                                CSV
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive datatable-custom">
                    <table id="datatable"
                        class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table table--wrap-head table--sticky-actions" data-hs-datatables-options='{
                            "columnDefs": [{
                                "targets": [-1],
                                "orderable": false
                            }],
                            "order": [],
                            "info": {
                            "totalQty": "#datatableWithPaginationInfoTotalQty"
                            },
                            "search": "#datatableSearch",
                            "entries": "#datatableEntries",
                            "pageLength": 25,
                            "isResponsive": false,
                            "isShowPaging": false,
                            "paging":false
                        }'>
                        <thead class="thead-light">
                            <tr>
                                <th class="border-0">
                                    {{ translate('Customer ID') }}
                                </th>
                                <th class="table-column-pl-0 border-0">{{ translate('Name') }}</th>
                                <th class="border-0">{{ translate('Contact information') }}</th>
                                @if($tab === 'storefront')
                                    <th class="border-0">{{ translate('messages.storefront') }}</th>
                                @endif
                                <th class="border-0 col--numeric">{{ translate('messages.Orders') }}</th>
                                <th class="border-0 col--numeric">{{ translate('Wallet balance') }}</th>
                                <th class="border-0">{{ translate('Joining date') }}</th>
                                <th class="border-0">{{ translate('messages.Status') }}</th>
                                <th class="border-0">{{ translate('messages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody id="set-rows">
                            @foreach ($customers as $customer)

                                <tr class="">
                                    <td class="">
                                        <span class="fw-500">#{{ $customer['id'] }}</span>
                                    </td>



                                        <td class="table-column-pl-0">
                                            <div class="d-flex align-items-center gap-2 min-w-280">
                                                @include('partials._user-avatar', [
                                                    'imageUrl'  => $customer->image_full_url,
                                                    'proStatus' => $customer->pro_status,
                                                    'size'      => 40,
                                                ])

                                                <div>
                                                    <a href="{{ route('admin.users.customer.view', [$customer['id']]) }}"
                                                        class="text-dark fw-500 text-hover-primary max-w-215px min-w-135px text-wrap line--limit-1">
                                                        {{ $customer['f_name'] ? $customer['f_name'] . ' ' . $customer['l_name'] : translate('Incomplete profile') }}
                                                    </a>
                                                    <div>


                                                    </div>
                                                </div>
                                            </div>
                                        </td>



                                    <td>
                                        <div>
                                            <a href="mailto:{{ $customer['email'] }}">
                                                {{ $customer['email'] }}
                                            </a>
                                        </div>
                                        <div>
                                            <a href="tel:{{ $customer['phone'] }}">
                                                {{ $customer['phone'] }}
                                            </a>
                                        </div>
                                    </td>
                                    @if($tab === 'storefront')
                                    <td>
                                        <label class="badge badge-soft-info">
                                            {{ $publishedStoreLookup[$customer->sub_tenant_id] ?? '—' }}
                                        </label>
                                    </td>
                                    @endif
                                    <td class="col--numeric" data-order="{{ $customer->orders_count }}">
                                        <span class="d-block text-title fw-500">{{ $customer->orders_count }}</span>
                                        <span class="d-block fs-12 text-muted">{{ \App\CentralLogics\Helpers::format_currency($customer->total_order_amount ?? 0) }}</span>
                                    </td>
                                    <td class="col--numeric" data-order="{{ $customer->wallet_balance ?? 0 }}">
                                        <span class="d-block text-title fw-500">{{ \App\CentralLogics\Helpers::format_currency($customer->wallet_balance ?? 0) }}</span>
                                        <span class="d-block fs-12 text-muted">
                                            {{ translate('Loyalty point') }}: {{ (float) ($customer->loyalty_point ?? 0) }}
                                        </span>
                                    </td>
                                    <td data-order="{{ $customer->created_at }}">
                                        {{ \App\CentralLogics\Helpers::date_format($customer->created_at) }}
                                    </td>
                                    <td>
                                        @include('admin-views.customer.partials._status-toggle', ['customer' => $customer])
                                    </td>
                                    <td>
                                        <a class="btn action-btn action-btn--view"
                                            href="{{ route('admin.users.customer.view', [$customer['id']]) }}"
                                            title="{{ translate('messages.View customer') }}"><i
                                                class="tio-visible-outlined"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if(count($customers) !== 0)
            <hr>
            @endif
            <div class="page-area">
                {!! $customers->withQueryString()->links() !!}
            </div>
            @if(count($customers) === 0)
            <div class="empty--data">
                <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                <h5>
                    {{translate('No data found')}}
                </h5>
            </div>
            @endif

        </div>
    </div>
@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin')}}/js/view-pages/customer-list.js"></script>
@endpush
