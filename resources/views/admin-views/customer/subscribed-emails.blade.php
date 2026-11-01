@php use App\CentralLogics\Helpers; @endphp
@extends('layouts.admin.app')
@section('title', translate('Subscribed emails'))
@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush
@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title mr-3">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/email.png')}}" class="w--26" alt="">
                </span>
                <span>{{ translate('Subscriber list') }}
                <span class="badge badge-soft-dark ml-2" id="count">{{ $subscribedCustomers->total() }}</span></span>
            </h1>
            <p class="page-header-desc">{{ translate('Everyone who has asked for your newsletter, ready to export to a mailing tool.') }}</p>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-sm-6 col-lg-3">
                <div class="order--card h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-subtitle d-flex m-0 align-items-center">
                            <i class="tio-email-outlined text--primary mr-2 fs-18"></i>
                            <span>{{ translate('messages.Total subscribers') }}</span>
                        </h6>
                        <span class="card-title text--primary">{{ $stats['total'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="order--card h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-subtitle d-flex m-0 align-items-center">
                            <i class="tio-user-outlined text-success mr-2 fs-18"></i>
                            <span>{{ translate('messages.Registered customers') }}</span>
                        </h6>
                        <span class="card-title text-success">{{ $stats['registered'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="order--card h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-subtitle d-flex m-0 align-items-center">
                            <i class="tio-incognito text-muted mr-2 fs-18"></i>
                            <span>{{ translate('messages.Guest subscribers') }}</span>
                        </h6>
                        <span class="card-title text-muted">{{ $stats['guest'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="order--card h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-subtitle d-flex m-0 align-items-center">
                            <i class="tio-chart-bar-1 text-info mr-2 fs-18"></i>
                            <span>{{ translate('messages.New this month') }}</span>
                        </h6>
                        <span class="card-title text-info">{{ $stats['this_month'] }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">{{translate('Subscription Date')}}</label>
                            <div class="position-relative">
                                <span class="tio-calendar icon-absolute-on-right"></span>
                                <input type="text" readonly data-title="{{ translate('Select Subscription Date Range') }}" name="join_date" value="{{ request()->input('join_date')  ?? null }}" class="date-range-picker form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{translate('Sort by')}}</label>
                            <select name="filter" data-placeholder="{{ translate('messages.Select Mail Sorting Order') }}" class="form-control js-select2-custom">
                                <option  value="" selected disabled > {{ translate('messages.Select Mail Sorting Order') }} </option>
                                <option  {{ request()->input('filter')  == 'oldest'?'selected':''}} value="oldest">{{ translate('messages.Sort by First created') }}</option>
                                <option  {{ request()->input('filter')  == 'latest'?'selected':''}} value="latest">{{ translate('messages.Sort by newest') }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{translate('Choose First')}}</label>
                            <input type="number" min="1" name="show_limit" class="form-control" value="{{ request()->input('show_limit')}}" class="form-control" placeholder="{{translate('Ex') . ' : 100'}}">
                        </div>
                    </div>
                    <div class="btn--container justify-content-end mt-20">
                        <button type="submit" class="btn btn--primary"><i class="tio-filter-list"></i> {{translate('Filter')}}</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header border-0 py-2">



                <div class="search--button-wrapper justify-content-end">
                    @include('partials._table-head', [
                        'subtitle' => translate('messages.Newsletter subscribers, matched to their customer account where one exists.'),
                    ])

                    <form class="search-form">
                        <div class="input-group input--group">
                            <input type="search" name="search" class="form-control"
                                   placeholder="{{translate('Ex') . ' : ' . translate('search email')}}"
                                   aria-label="{{translate('messages.Search')}}" value="{{request()?->search}}">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                    @if(request()->input('search'))
                        <button type="reset" class="btn btn--primary ml-2 location-reload-to-base"
                                data-url="{{url()->full()}}"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                    @endif

                    <div class="hs-unfold mr-2">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40"
                           href="javascript:"
                           data-hs-unfold-options='{
                                                        "target": "#usersExportDropdown",
                                                        "type": "css-animation"
                                                    }'>
                            <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                        </a>

                        <div id="usersExportDropdown"
                             class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                            <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                            <a id="export-excel" class="dropdown-item"
                               href="{{route('admin.users.customer.subscriber-export', ['type'=>'excel',request()->getQueryString()])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                     src="{{ asset('public/assets/admin/svg/components/excel.svg') }}"
                                     alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item"
                               href="{{route('admin.users.customer.subscriber-export', ['type'=>'csv',request()->getQueryString()])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                     src="{{ asset('public/assets/admin/svg/components/placeholder-csv-format.svg') }}"
                                     alt="Image Description">
                                CSV
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            @php
            $count= 0;
            @endphp
            <div class="card-body p-0">
                <div class="table-responsive datatable-custom">
                    <table id="datatable"
                        class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table generalData"
                        data-hs-datatables-options='{
                                                                "columnDefs": [{
                                                                    "targets": [0],
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
                                {{ translate('SL') }}
                            </th>
                            <th class="border-0">{{ translate('messages.email') }}</th>
                            <th class="border-0">{{ translate('messages.Customer account') }}</th>
                            <th class="border-0">{{ translate('Phone') }}</th>
                            <th class="border-0 text-center col--numeric">{{ translate('messages.Orders') }}</th>
                            <th class="border-0">{{ translate('messages.Subscribed on') }}</th>
                            <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
                        </tr>
                        </thead>
                        <tbody id="set-rows">
                        @if (count($subscribedCustomers))
                            @foreach ($subscribedCustomers as $key => $customer)
                                @php($linked = ($linkedCustomers ?? collect())->get(strtolower($customer->email)))
                                <tr>
                                    <td>
                                        {{ (request()->input('show_limit') ?  $count++ : $key  )+ $subscribedCustomers->firstItem() }}
                                    </td>

                                    <td>
                                        <div class="d-flex align-items-center gap-2 min-w-220">
                                            @include('partials._user-avatar', [
                                                'imageUrl'    => $linked?->image_full_url,
                                                'proStatus'   => $linked?->pro_status ?? false,
                                                'size'        => 36,
                                                'placeholder' => asset('public/assets/admin/img/placeholder.png'),
                                                'alt'         => $customer->email,
                                            ])
                                            <div class="text-truncate max-w-215px" title="{{ $customer->email }}">
                                                {{ $customer->email }}
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($linked)
                                            <a href="{{ route('admin.users.customer.view', [$linked->id]) }}"
                                               class="text--hover">
                                                {{ trim($linked->f_name . ' ' . $linked->l_name) ?: translate('messages.N/A') }}
                                            </a>
                                            <div class="mt-1">
                                                @if($linked->status)
                                                    <span class="badge badge-soft-success">{{ translate('messages.Active') }}</span>
                                                @else
                                                    <span class="badge badge-soft-danger">{{ translate('messages.Inactive') }}</span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="badge badge-soft-secondary">{{ translate('messages.Guest subscriber') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($linked?->phone)
                                            <a href="tel:{{ $linked->phone }}" class="text--hover">{{ $linked->phone }}</a>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center col--numeric">
                                        @if($linked)
                                            <span class="badge badge-soft-dark">{{ $linked->order_count ?? 0 }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ Helpers::date_format($customer->created_at) }}</div>
                                        <div class="text-muted">
                                            {{ Helpers::time_format($customer->created_at) }} &middot;
                                            {{ \Illuminate\Support\Carbon::parse($customer->created_at)->diffForHumans() }}
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-2">
                                            <a class="btn action-btn action-btn--copy"
                                               href="javascript:" onclick="copy_text(@js($customer->email))"
                                               title="{{ translate('messages.Copy email') }}">
                                                <i class="tio-copy"></i>
                                            </a>
                                            <a class="btn action-btn action-btn--send"
                                               href="mailto:{{ $customer->email }}"
                                               title="{{ translate('messages.Send email') }}">
                                                <i class="tio-send"></i>
                                            </a>
                                            @if($linked)
                                                <a class="btn action-btn action-btn--view"
                                                   href="{{ route('admin.users.customer.view', [$linked->id]) }}"
                                                   title="{{ translate('messages.View customer') }}">
                                                    <i class="tio-visible-outlined"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                        </tbody>

                    </table>
                </div>
                @if(count($subscribedCustomers) !== 0)
                    <hr>
                @endif
                <div class="page-area">
                    {!! $subscribedCustomers->withQueryString()->links() !!}
                </div>
                @if(count($subscribedCustomers) === 0)
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
