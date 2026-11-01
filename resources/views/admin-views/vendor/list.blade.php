@extends('layouts.admin.app')

@section('title',translate('Store list'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        @php
            $verified_seller_badge = \App\CentralLogics\Helpers::get_business_settings('verified_seller_badge');
            $recommended_store_list = $verified_seller_badge ? \App\CentralLogics\Helpers::get_verified_seller_eligible_stores(countOnly: false , moduleId: config('module.current_module_id')) : [];
            $recommended_stores = count($recommended_store_list) ;
            $store_service = app(\App\Services\Store\StoreService::class);
            $admin_commission = \App\CentralLogics\Helpers::get_business_settings('admin_commission', false);
            $business_model_labels = [
                'commission' => translate('Commission'),
                'subscription' => translate('Subscription'),
                'unsubscribed' => translate('messages.unsubscribed'),
            ];
        @endphp
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h1 class="page-header-title"><i class="tio-filter-list"></i> {{translate('messages.Stores')}} <span class="badge badge-soft-dark ml-2" id="itemCount">{{$stores->total()}}</span></h1>
                <p class="page-header-desc">{{ translate('Every store trading with you, its zone and whether it is open.') }}</p>
            </div>
            @if ($recommended_stores??0 > 0)
            <div class="d-flex align-items-center gap-2 bg-success bg-opacity-10 flex-wrap rounded py-1 px-2">
                    <div class="fs-12 mb-0 d-flex align-items-center gap-2">
                        <img src="{{ asset('public/assets/admin/img/badge-rounded-circle.svg') }}" alt="" class="rounded-0 w-auto h-auto object-contain">
                        {{ translate('Recommended') }} <strong class="title-clr">{{$recommended_stores}}</strong> {{ translate('stores for verification') }}
                    </div>

                <button class="btn btn--primary bg-theme2 border-0 py-1 px-3 fs-12 fw-500 mb-0 offcanvas-trigger  " data-target="#offcanvas__customBtn3" data-id="0"  data-url="" type="button">
                    <i class="tio-visible-outlined"></i> {{ translate('messages.View') }}
                </button>
            </div>
            @endif
        </div>


        <div class="row g-3 mb-3">
            <div class="col-xl-3 col-sm-6">
                <div class="resturant-card card--bg-1">
                    <h4 class="title">{{$total_store}}</h4>
                    <span class="subtitle">{{translate('Total stores')}}</span>
                    <img class="resturant-icon" src="{{asset('/public/assets/admin/img/total-store.png')}}" alt="store">
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="resturant-card card--bg-2">
                    <h4 class="title">{{$active_stores}}</h4>
                    <span class="subtitle">{{translate('messages.Active stores')}}</span>
                    <img class="resturant-icon" src="{{asset('/public/assets/admin/img/active-store.png')}}" alt="store">
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="resturant-card card--bg-3">
                    <h4 class="title">{{$inactive_stores}}</h4>
                    <span class="subtitle">{{translate('messages.inactive_stores')}}</span>
                    <img class="resturant-icon" src="{{asset('/public/assets/admin/img/close-store.png')}}" alt="store">
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="resturant-card card--bg-4">
                    <h4 class="title">{{$recent_stores}}</h4>
                    <span class="subtitle">{{translate('messages.Newly joined stores')}}</span>
                    <img class="resturant-icon" src="{{asset('/public/assets/admin/img/add-store.png')}}" alt="store">
                </div>
            </div>
        </div>
        <ul class="transaction--information text-uppercase">
            <li class="text--info">
                <i class="tio-document-text-outlined"></i>
                <div>
                    <span>{{translate('Total transactions')}}</span> <strong>{{$total_transaction}}</strong>
                </div>
            </li>

            @if (auth('admin')->user()->role_id == 1)
                <li class="seperator"></li>
                <li class="text--success">
                    <i class="tio-checkmark-circle-outlined success--icon"></i>
                    <div>
                        <span>{{translate('messages.Commission earned')}}</span> <strong>{{\App\CentralLogics\Helpers::format_currency($comission_earned)}}</strong>
                    </div>
                </li>
            @endif

            <li class="seperator"></li>
            <li class="text--danger">
                <i class="tio-atm"></i>
                <div>
                    <span>{{translate('messages.Total store withdraws')}}</span> <strong>{{\App\CentralLogics\Helpers::format_currency($store_withdraws)}}</strong>
                </div>
            </li>
        </ul>

        <div class="card">
            <div class="card-header py-2">
                <div class="search--button-wrapper">
                    @include('partials._table-head', [
                        'subtitle' => translate('messages.Store list subtitle'),
                    ])

                @if(!auth('admin')?->user()?->zone_id)
                <div class="select-item min--280">
                    <select name="zone_id" class="form-control js-select2-custom set-filter" data-url="{{url()->full()}}" data-filter="zone_id">
                        <option value="" {{!request('zone_id')?'selected':''}}>{{ translate('All zones') }}</option>
                        @foreach(\App\CentralLogics\Helpers::zones_dropdown() as $z)
                            <option
                                value="{{$z['id']}}" {{isset($zone) && $zone->id == $z['id']?'selected':''}}>
                                {{$z['name']}}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif
                    <form class="search-form">
                        <div class="input-group input--group">
                            <input id="datatableSearch_" type="search" value="{{ request()?->search ?? null }}" name="search" class="form-control"
                                    placeholder="{{translate('Ex') . ': ' . translate('Search by store name')}}" aria-label="{{translate('Search')}}" >
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>

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
                            <a id="export-excel" class="dropdown-item" href="{{route('admin.store.export', ['type'=>'excel',request()->getQueryString()])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                    alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item" href="{{route('admin.store.export', ['type'=>'csv',request()->getQueryString()])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                    alt="Image Description">
                                CSV
                            </a>

                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive datatable-custom">
                <table id="columnSearchDatatable"
                        class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                        data-hs-datatables-options='{
                            "order": [],
                            "orderCellsTop": true,
                            "paging":false

                        }'>
                    <thead class="thead-light">
                    <tr>
                        <th class="border-0">{{translate('Store information')}}</th>
                        <th class="border-0">{{translate('Owner information')}}</th>
                        <th class="border-0">{{translate('messages.Zone')}}</th>
                        <th class="border-0">{{translate('Business plan')}}</th>
                        <th class="border-0 col--numeric">{{translate('messages.Orders')}}</th>
                        <th class="border-0">{{translate('Joined')}}</th>
                        <th class="text-uppercase border-0">{{translate('messages.featured')}}</th>
                        <th class="text-uppercase border-0">{{translate('Status')}}</th>
                        <th class="text-center border-0">{{translate('messages.Action')}}</th>
                    </tr>
                    </thead>

                    <tbody id="set-rows">
                    @foreach($stores as $store)
                        @php($store_reviews = $store_service->calculateRating($store['rating']))
                        @php($subscription = $store->store_sub_update_application)
                        @php($package_name = $subscription?->package?->package_name ?? $store->package?->package_name)
                        <tr>
                            <td>
                                <a href="{{route('admin.store.view', $store->id)}}" class="table-rest-info" alt="view store">
                                    <img class="img--60 circle onerror-image" data-onerror-image="{{asset('public/assets/admin/img/160x160/img1.jpg')}}"
                                            src="{{ $store['logo_full_url'] ?? asset('public/assets/admin/img/160x160/img1.jpg') }}">
                                    <div class="info max-w-200px">
                                        <div title="{{ $store?->name }}" class="text--title ">
                                            {{Str::limit($store->name,20,'...')}}
                                            @include('partials._verified_store_badge', ['store' => $store])
                                        </div>
                                        @if($store_reviews['total'])
                                            <span class="rating text-star" title="{{ translate('messages.Ratings') . ': ' . $store_reviews['total'] }}">
                                                <i class="tio-star"></i> {{number_format($store_reviews['rating'], 1)}} ({{$store_reviews['total']}})
                                            </span>
                                        @else
                                            <span class="d-block fs-12 text-muted font-weight-normal">{{translate('messages.Not rated yet')}}</span>
                                        @endif
                                        <div class="font-light">
                                            ID:{{$store->id}}
                                        </div>
                                    </div>
                                </a>
                            </td>

                            <td>
                                <span title="{{ $store?->vendor?->f_name.' '.$store?->vendor?->l_name }}" class="d-block font-size-sm text-body">
                                    {{Str::limit($store->vendor->f_name.' '.$store->vendor->l_name,20,'...')}}
                                </span>
                                <div>
                                    <a href="tel:{{ $store['phone'] }}">
                                        {{$store['phone']}}
                                    </a>
                                </div>
                                @if($store->vendor?->email)
                                    <a class="d-block fs-12 text-muted" href="mailto:{{ $store->vendor->email }}" title="{{ $store->vendor->email }}">
                                        {{Str::limit($store->vendor->email,30,'...')}}
                                    </a>
                                @endif
                            </td>
                            <td>
                                <span title="{{ $store->zone?->name }}" class="d-block max-w-176px">
                                    {{$store->zone ? Str::limit($store->zone->name, 22, '...') : translate('messages.Zone deleted')}}
                                </span>
                                <span class="d-block fs-12 text-muted">
                                    {{$store->self_delivery_system ? translate('Self delivery') : translate('Platform delivery')}}
                                </span>
                            </td>
                            <td>
                                @if($store->store_business_model == 'commission')
                                    <span class="d-block text-title font-semibold">{{$business_model_labels['commission']}}</span>
                                    <span class="d-block fs-12 text-muted" title="{{ is_null($store->comission) ? translate('Platform default commission') : translate('Store specific commission') }}">{{$store->comission ?? $admin_commission}}%</span>
                                @elseif(in_array($store->store_business_model, ['subscription', 'unsubscribed']))
                                    <span class="d-block text-title font-semibold">{{$business_model_labels[$store->store_business_model]}}</span>
                                    <span class="d-block fs-12 text-muted" title="{{ $package_name }}">
                                        {{$package_name ? Str::limit($package_name, 18, '...') : translate('No data found')}}
                                    </span>
                                    @if($subscription?->is_trial)
                                        <span class="cell-chips d-block mt-1"><span class="cell-chip">{{translate('messages.Free trial')}}</span></span>
                                    @endif
                                @else
                                    <span class="text-muted font-size-sm">{{translate('messages.N/A')}}</span>
                                @endif
                            </td>
                            <td class="col--numeric" data-order="{{$store->total_order}}">
                                <span class="badge badge-soft-{{$store->total_order ? 'success' : 'secondary'}}"
                                        title="{{ $store->total_order ? translate('messages.Total orders') . ': ' . $store->total_order : translate('messages.Never ordered yet') }}">
                                    {{$store->total_order}}
                                </span>
                            </td>
                            <td data-order="{{$store->created_at}}">
                                <span class="table-when">
                                    <span class="table-when__day">{{\App\CentralLogics\Helpers::date_format($store->created_at)}}</span>
                                    <span class="table-when__ago" title="{{\App\CentralLogics\Helpers::time_date_format($store->created_at)}}">
                                        {{$store->created_at?->diffForHumans()}}
                                    </span>
                                </span>
                            </td>
                            <td>
                                <label class="toggle-switch toggle-switch-sm" for="featuredCheckbox{{$store->id}}">
                                    <input type="checkbox" data-url="{{route('admin.store.featured',[$store->id,$store->featured?0:1])}}" class="toggle-switch-input redirect-url" id="featuredCheckbox{{$store->id}}" {{$store->featured?'checked':''}}>
                                    <span class="toggle-switch-label">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </td>

                            <td>
                                @if(isset($store->vendor->status))
                                    @if($store->vendor->status)
                                    <div class="status-toggle" data-status="{{$store->status?1:0}}">
                                        <label class="toggle-switch toggle-switch-sm" for="stocksCheckbox{{$store->id}}">
                                            <input type="checkbox" data-url="{{route('admin.store.status',[$store->id,$store->status?0:1])}}" data-message="{{translate('messages.You want to change this store status')}}" class="toggle-switch-input status_change_alert" id="stocksCheckbox{{$store->id}}" {{$store->status?'checked':''}}>
                                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                        <span class="status-toggle__text" aria-live="polite">
                                            {{$store->status ? translate('messages.Active') : translate('messages.Inactive')}}
                                        </span>
                                    </div>
                                    @else
                                    <span class="badge badge-soft-danger">{{translate('Denied')}}</span>
                                    @endif
                                @else
                                    <span class="badge badge-soft-danger">{{translate('Pending')}}</span>
                                @endif
                                <span class="cell-chips d-block mt-1">
                                    <span class="cell-chip">{{$store->active ? translate('messages.Open') : translate('Temporarily closed')}}</span>
                                </span>
                            </td>

                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="btn action-btn action-btn--view"
                                            href="{{route('admin.store.view', $store->id)}}"
                                            title="{{ translate('messages.View') }}"><i
                                                class="tio-visible-outlined"></i>
                                        </a>
                                    <a class="btn action-btn action-btn--edit"
                                    href="{{route('admin.store.edit',[$store['id']])}}" title="{{translate('Edit store')}}"><i class="tio-edit"></i>
                                    </a>
                                    <a class="btn action-btn action-btn--delete form-alert" href="javascript:"
                                    data-id="vendor-{{$store['id']}}" data-message="{{translate('You want to remove this store')}}" title="{{translate('messages.Delete store')}}"><i class="tio-delete-outlined"></i>
                                    </a>
                                    <form action="{{route('admin.store.delete',[$store['id']])}}" method="post" id="vendor-{{$store['id']}}">
                                        @csrf @method('delete')
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

            </div>
                @if(count($stores) !== 0)
                <hr>
                @endif
                <div class="page-area">
                    {!! $stores->withQueryString()->links() !!}
                </div>
                @if(count($stores) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>
                        {{translate('No data found')}}
                    </h5>
                </div>
                @endif
        </div>
    </div>

    <div id="offcanvas__customBtn3" class="custom-offcanvas d-flex flex-column justify-content-between">
        <div class="d-flex flex-column flex-grow-1">
            <div class="custom-offcanvas-header bg-white d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                <h3 class="mb-0 fs-18 text-title fw-semibold">{{ translate('Verification recommendations') }}</h3>
                <button type="button"
                    class="btn-close w-25px h-25px border rounded-circle d-center bg-white text-dark offcanvas-close fz-15px p-0"
                    aria-label="Close">&times;</button>
            </div>
            <div class="custom-offcanvas-body p-4 d-flex flex-column gap-3">
                <p class="fs-14 lh-base color-5d6167 mb-0">
                    {{ translate('We have detected that') }} <strong>{{ number_format($recommended_stores) }}</strong>
                    {{ translate('These stores perform well overall. Give them a Verified badge, shown beside the store name, to build customer trust.') }}
                </p>

                <div class="bg--secondary rounded p-4">
                    <h4 class="mb-3 fs-18 text-title fw-semibold">{{ translate('They qualified the criteria') }}</h4>

                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex align-items-start gap-2">
                            <span class="d-center flex-shrink-0 mt-1 w-18px h-18px rounded-circle bg-success">
                                <i class="tio-done text-white fz-10px"></i>
                            </span>
                            <span class="fs-14 color-5d6167">
                                <strong class="text-title">{{ config('verified_seller.stores.minimum_total_orders', 10) }}+</strong> {{ translate('Orders completed') }}
                            </span>
                        </div>
                        <div class="d-flex align-items-start gap-2">
                            <span class="d-center flex-shrink-0 mt-1 w-18px h-18px rounded-circle bg-success">
                                <i class="tio-done text-white fz-10px"></i>
                            </span>
                            <span class="fs-14 color-5d6167">
                                <strong class="text-title">{{ config('verified_seller.stores.minimum_success_rate', 40) }}%+</strong> {{ translate('Order completion rate') }}
                            </span>
                        </div>
                        <div class="d-flex align-items-start gap-2">
                            <span class="d-center flex-shrink-0 mt-1 w-18px h-18px rounded-circle bg-success">
                                <i class="tio-done text-white fz-10px"></i>
                            </span>
                            <span class="fs-14 color-5d6167">
                                <strong class="text-title">{{ config('verified_seller.stores.minimum_account_age_months', 3) }}+</strong> {{ translate('months since account creation') }}
                            </span>
                        </div>
                        <div class="d-flex align-items-start gap-2">
                            <span class="d-center flex-shrink-0 mt-1 w-18px h-18px rounded-circle bg-success">
                                <i class="tio-done text-white fz-10px"></i>
                            </span>
                            <span class="fs-14 color-5d6167">
                                <strong class="text-title">{{ translate('Positive') }}</strong> {{ translate('Customer feedback trend') }}
                            </span>
                        </div>
                        <div class="d-flex align-items-start gap-2">
                            <span class="d-center flex-shrink-0 mt-1 w-18px h-18px rounded-circle bg-success">
                                <i class="tio-done text-white fz-10px"></i>
                            </span>
                            <span class="fs-14 color-5d6167">
                                <strong class="text-title">{{ config('verified_seller.stores.minimum_avg_rating', 2) }}+</strong> {{ translate('Rating') }}/5.00
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="align-items-center bg-white bottom-0 d-flex gap-3 justify-content-center mt-auto offcanvas-footer p-3 position-sticky border-top">
            <button type="button" id="open-eligible-store-list" class="btn w-100 btn--reset h--40px"><i class="tio-visible-outlined"></i> {{ translate('View list') }}</button>
            <button type="button" id="verify-all-summary" class="btn w-100 btn--primary h--40px"><i class="tio-verified-outlined"></i> {{ translate('Verify all') }}</button>
        </div>
    </div>
    <div id="offcanvas__eligibleStores" class="custom-offcanvas d-flex flex-column justify-content-between">
        <div class="d-flex flex-column flex-grow-1">
            <div class="custom-offcanvas-header bg-white d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <h3 class="mb-0 fs-18 text-title fw-semibold d-flex align-items-center gap-2">
                        <span id="back-to-verification" class="d-inline-flex align-items-center justify-content-center w-20px h-20px rounded-circle bg-light">
                            <i class="tio-arrow-backward fs-12"></i>
                        </span>
                        {{ translate('Stores list') }}
                    </h3>
                    <span class="badge badge-soft-dark">{{ $recommended_stores }}</span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <label class="d-flex align-items-center gap-2 mb-0 cursor-pointer">
                        <input type="checkbox" id="select-all-eligible-stores" class="form-check-input mt-0">
                        <span class="fs-14 text-title">{{ translate('Select all') }}</span>
                    </label>
                    <button type="button" class="btn-close w-25px h-25px border rounded-circle d-center bg-white text-dark offcanvas-close fz-15px p-0" aria-label="Close">&times;</button>
                </div>
            </div>
            <div class="custom-offcanvas-body p-3 p-md-4 d-flex flex-column gap-3" id="eligible-store-list">
                @foreach ($recommended_store_list as $store)
                    <div class="bg-white rounded-10 p-3 eligible-store-item">
                        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                            <div class="d-flex align-items-center gap-3">
                                <div class="flex-shrink-0">
                                    <img class="w-50px h-50px rounded-circle border object-fit-cover onerror-image"
                                         data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}"
                                         src="{{$store['logo_full_url']}}">
                                </div>
                                <div>
                                    <h4 class="mb-1 fs-16 text-title fw-semibold">{{ $store['name'] }}</h4>
                                    <div class="d-flex flex-wrap gap-2 fs-13 color-6c757d">
                                        <span>{{ translate('messages.Rating') }} {{ number_format($store['avg_rating'], 1) }}/5</span>
                                        <span>{{ translate('messages.Order') }} {{ number_format($store['total_orders']) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="eligible-action-wrapper d-flex align-items-center justify-content-end min-w-110px">
                                <a href="{{ route('admin.store.verified-seller', [$store['id']]) }}" class="btn btn--primary btn-sm min-w-110px eligible-give-btn">
                                    <i class="tio-done mr-1"></i>{{ translate('Give badge') }}
                                </a>
                                <label class="form-check m-0 d-none eligible-store-check">
                                    <input type="checkbox" class="form-check-input mt-0" checked disabled>
                                </label>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div id="eligible-store-footer" class=" d-none">
            <div class="align-items-center bg-white bottom-0 d-flex gap-3 justify-content-center mt-auto offcanvas-footer p-3 position-sticky border-top">
                <div class="d-flex gap-3 w-100 justify-content-center">
                    <button type="button" class="btn w-100 btn--reset offcanvas-close h--40px"><i class="tio-clear-circle-outlined"></i> {{ translate('Cancel') }}</button>
                    <button type="button" id="verify-all-stores" class="btn w-100 btn--primary h--40px"><i class="tio-verified-outlined"></i> {{ translate('Verify all') }}</button>
                </div>
            </div>
        </div>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>
    <div class="modal shedule-modal fade" id="verify-all-modal" tabindex="-1" aria-labelledby="verifyAllModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content pb-2 max-w-500">
                <div class="modal-header">
                    <button type="button"
                        class="close bg-modal-btn w-30px h-30 rounded-circle position-absolute right-0 top-0 m-2 z-2"
                        data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <img src="{{ asset('public/assets/admin/img/badge-big.png') }}" alt="icon" class="mb-3">
                        <h3 class="mb-2">{{ translate('Verify all qualified stores?') }}</h3>
                        <p class="mb-0">{{ translate('This will give a verified badge to every store that matches the verification criteria.') }}</p>
                    </div>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-0 gap-2">
                    <button type="button" class="btn min-w-120px btn--reset" data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{ translate('messages.Cancel') }}</button>
                    <a href="{{ route('admin.store.verified-seller-all') }}" class="btn min-w-120px btn--primary"><i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Yes') }}</a>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script>
        "use strict";
        function resetEligibleStoreSelection() {
            $('#select-all-eligible-stores').prop('checked', false);
            $('#eligible-store-footer').addClass('d-none');
            $('#eligible-store-list .eligible-store-item').removeClass('border border-success bg-success bg-opacity-10');
            $('#eligible-store-list .eligible-give-btn').removeClass('d-none');
            $('#eligible-store-list .eligible-store-check').addClass('d-none');
        }

        $(document).on('click', '#open-eligible-store-list', function (e) {
            e.preventDefault();
            $('#offcanvas__customBtn3').removeClass('open');
            $('#offcanvas__eligibleStores').addClass('open');
            $('#offcanvasOverlay').addClass('show');
            resetEligibleStoreSelection();
        });

        $(document).on('click', '#back-to-verification', function (e) {
            e.preventDefault();
            $('#offcanvas__eligibleStores').removeClass('open');
            $('#offcanvas__customBtn3').addClass('open');
            $('#offcanvasOverlay').addClass('show');
            resetEligibleStoreSelection();
        });

        $(document).on('change', '#select-all-eligible-stores', function () {
            const checked = $(this).is(':checked');
            $('#eligible-store-list .eligible-store-item').toggleClass('border border-success bg-success bg-opacity-10', checked);
            $('#eligible-store-list .eligible-give-btn').toggleClass('d-none', checked);
            $('#eligible-store-list .eligible-store-check').toggleClass('d-none', !checked);
            $('#eligible-store-footer').toggleClass('d-none', !checked);
        });

        $(document).on('click', '#verify-all-stores', function (e) {
            e.preventDefault();
            $('#offcanvas__eligibleStores').removeClass('open');
            $('#offcanvas__customBtn3').removeClass('open');
            $('#offcanvasOverlay').removeClass('show');
            $('#verify-all-modal').modal('show');
        });

        $(document).on('click', '#verify-all-summary', function (e) {
            e.preventDefault();
            $('#offcanvas__customBtn3').removeClass('open');
            $('#offcanvas__eligibleStores').removeClass('open');
            $('#offcanvasOverlay').removeClass('show');
            resetEligibleStoreSelection();
            $('#verify-all-modal').modal('show');
        });

        $(document).on('click', '.offcanvas-close, #offcanvasOverlay', function () {
            resetEligibleStoreSelection();
        });


    </script>

@endpush
