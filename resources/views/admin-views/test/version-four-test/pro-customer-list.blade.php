@extends('layouts.admin.app')

@section('title', translate('Pro customer'))

@section('content')


<div class="content container-fluid">
    <div class="page-header pb-2 mb-0">
        <div class="d-flex flex-wrap justify-content-between align-items-start">
            <h1 class="page-header-title text-capitalize">
                <span>
                    {{ translate('Pro customer') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Customers on a Pro membership, and when each one renews.') }}</p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body p-10px">
            <div class="row g-2">
                <div class="col-lg-3 col-sm-6">
                    <a class="shadow-effect-hover d-flex align-items-center gap-3 p-3 rounded bg-soft-info w-100 position-relative" href="#0">
                        <div class="thumb min-w-45px w-45px h-45px rounded-circle d-center bg-white">
                            <img class="resturant-icon" width="20"
                                src="{{asset('public/assets/admin/img/customer-g.png') }}" alt="img">
                        </div>
                        <div>
                            <h4 class="title fs-18 font-weight-bold text-dark mb-1">10</h4>
                            <span
                                class="subtitle fs-14 text-dark fw-normal">{{ translate('Total subscriber') }}</span>
                        </div>
                        <button type="button" class="btn text-info text-left position-absolute right-0 top-0 m-2 p-0" data-toggle="tooltip"
                            data-placement="top" data-html="true" data-title="
                            <div class='text-start text-left'>
                                <span class='font-weight-medium'>Plan Wise Subscribers</span> </br>
                                <span class='d-flex align-items-center gap-1'>
                                    Monthly :
                                    <span class='fw-500'>
                                        300
                                    </span>
                                </span>
                                <span class='d-flex align-items-center gap-1'>
                                    Quarterly :
                                    <span class='fw-500'>
                                        190
                                    </span>
                                </span>
                                <span class='d-flex align-items-center gap-1'>
                                    yearly :
                                    <span class='fw-500'>
                                        110
                                    </span>
                                </span>
                                <span class='d-flex align-items-center gap-1'>
                                    free trail :
                                    <span class='fw-500'>
                                        502
                                    </span>
                                </span>
                            </div>">
                            <i class="tio-info fs-14"></i>
                        </button>
                    </a>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <a class="shadow-effect-hover d-flex align-items-center gap-3 p-3 rounded bg-soft-success w-100 position-relative" href="#0">
                        <div class="thumb min-w-45px w-45px h-45px rounded-circle d-center bg-white">
                            <img class="resturant-icon" width="20"
                                src="{{asset('public/assets/admin/img/customer-a.png') }}" alt="img">
                        </div>
                        <div>
                            <h4 class="title fs-18 font-weight-bold text-dark mb-1">20</h4>
                            <span
                                class="subtitle fs-14 text-dark fw-normal">{{ translate('Active subscriber') }}</span>
                        </div>
                        <button type="button" class="btn text-info text-left position-absolute right-0 top-0 m-2 p-0" data-toggle="tooltip"
                            data-placement="top" data-html="true" data-title="
                            <div class='text-start text-left'>
                               <span class='font-weight-medium'>Plan Wise Subscribers</span> </br>
                                <span class='d-flex align-items-center gap-1'>
                                    Monthly :
                                    <span class='fw-500'>
                                        300
                                    </span>
                                </span>
                                <span class='d-flex align-items-center gap-1'>
                                    Quarterly :
                                    <span class='fw-500'>
                                        190
                                    </span>
                                </span>
                                <span class='d-flex align-items-center gap-1'>
                                    yearly :
                                    <span class='fw-500'>
                                        110
                                    </span>
                                </span>
                                <span class='d-flex align-items-center gap-1'>
                                    free trail :
                                    <span class='fw-500'>
                                        502
                                    </span>
                                </span>
                            </div>">
                            <i class="tio-info fs-14"></i>
                        </button>
                    </a>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <a class="shadow-effect-hover d-flex align-items-center gap-3 p-3 rounded bg-soft-danger w-100 position-relative" href="#0">
                        <div class="thumb min-w-45px w-45px h-45px rounded-circle d-center bg-white">
                            <img class="resturant-icon" width="20"
                                src="{{asset('public/assets/admin/img/customer-inactive.png') }}"
                                alt="img">
                        </div>
                        <div>
                            <h4 class="title fs-18 font-weight-bold text-dark mb-1">02</h4>
                            <span
                                class="subtitle fs-14 text-dark fw-normal">{{ translate('Inactive subscriber') }}</span>
                        </div>
                        <button type="button" class="btn text-info text-left position-absolute right-0 top-0 m-2 p-0" data-toggle="tooltip"
                            data-placement="top" data-html="true" data-title="
                            <div class='text-start text-left'>
                               <span class='font-weight-medium'>Plan Wise Subscribers</span> </br>
                                <span class='d-flex align-items-center gap-1'>
                                    Monthly :
                                    <span class='fw-500'>
                                        300
                                    </span>
                                </span>
                                <span class='d-flex align-items-center gap-1'>
                                    Quarterly :
                                    <span class='fw-500'>
                                        190
                                    </span>
                                </span>
                                <span class='d-flex align-items-center gap-1'>
                                    yearly :
                                    <span class='fw-500'>
                                        110
                                    </span>
                                </span>
                                <span class='d-flex align-items-center gap-1'>
                                    free trail :
                                    <span class='fw-500'>
                                        502
                                    </span>
                                </span>
                            </div>">
                            <i class="tio-info fs-14"></i>
                        </button>
                    </a>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <a class="shadow-effect-hover d-flex align-items-center gap-3 p-3 rounded bg-soft-warning w-100 position-relative"
                        href="#0">
                        <div class="thumb min-w-45px w-45px h-45px rounded-circle d-center bg-white">
                            <img class="resturant-icon" width="20"
                                src="{{asset('public/assets/admin/img/customer-add.png') }}"
                                alt="img">
                        </div>
                        <div>
                            <h4 class="title fs-18 font-weight-bold text-dark mb-1">14</h4>
                            <span
                                class="subtitle fs-14 text-dark fw-normal">{{ translate('messages.New Subscriber') }} </span>
                        </div>
                        <span class="text-info text-left position-absolute right-0 top-0 m-2" data-toggle="tooltip"
                            data-placement="right"
                            data-original-title="{{ translate('messages.Customers who joined recently are considered new subscribers.') }} {{ translate('messages.Joined within') }}: {{ \Carbon\CarbonInterval::months(2)->forHumans() }}">
                            <i class="tio-info fs-14"></i>
                        </span>

                    </a>
                </div>
                <div class="col-lg-6 col-sm-6">
                    <div class="bg--F6F6F6 shadow-effect-hover rounded d-flex align-items-center justify-content-between gap-2 flex-wrap px-3 py-2">
                        <a class=" d-flex align-items-center gap-3"
                            href="#0">
                            <div class="thumb min-w-45px w-45px h-45px rounded-circle d-center bg-white">
                                <img class="resturant-icon" width="20"
                                    src="{{asset('public/assets/admin/img/bank-hand.png') }}"
                                    alt="img">
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <h4 class="title fs-14 fw-500 text-dark mb-0">
                                    {{ translate('messages.New Subscriber') }} 
                                </h4>
                                <button type="button" class="btn text-info text-left p-0" data-toggle="tooltip"
                                    data-placement="top" data-html="true" data-title="
                                    <div class='text-start text-left'>
                                       <span class='font-weight-medium'>Plan Wise total earned</span> </br>
                                        <span class='d-flex align-items-center gap-1'>
                                            Monthly :
                                            <span class='fw-500'>
                                                 $ 15607.00
                                            </span>
                                        </span>
                                        <span class='d-flex align-items-center gap-1'>
                                            Quarterly :
                                            <span class='fw-500'>
                                                $ 9689.00
                                            </span>
                                        </span>
                                        <span class='d-flex align-items-center gap-1'>
                                            yearly :
                                            <span class='fw-500'>
                                                $ 7301.00
                                            </span>
                                        </span>
                                    </div>">
                                    <i class="tio-info fs-14"></i>
                                </button>
                            </div>
                        </a>
                        <div class="d-flex align-items-center gap-20px">
                            <h4 class="mb-0">$ 35600.00</h4>
                            <img class="m-0" width="20"
                                    src="{{asset('public/assets/admin/img/arrow-trend-up.png') }}"
                                    alt="img">
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-sm-6">
                    <div class="bg--F6F6F6 shadow-effect-hover rounded d-flex align-items-center justify-content-between gap-2 flex-wrap px-3 py-2">
                        <a class=" d-flex align-items-center gap-3"
                            href="#0">
                            <div class="thumb min-w-45px w-45px h-45px rounded-circle d-center bg-white">
                                <img class="resturant-icon" width="20"
                                    src="{{asset('public/assets/admin/img/days-calender.png') }}"
                                    alt="img">
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <h4 class="title fs-14 fw-500 text-dark mb-0">
                                    {{ translate('messages.Earned in the last') }} {{ \Carbon\CarbonInterval::days(30)->forHumans() }} 
                                </h4>
                                <button type="button" class="btn text-info text-left p-0" data-toggle="tooltip"
                                    data-placement="top" data-html="true" data-title="
                                    <div class='text-start text-left'>
                                        <span class='font-weight-medium'>Plan Wise earned last 30 days</span> </br>
                                        <span class='d-flex align-items-center gap-1'>
                                            Monthly :
                                            <span class='fw-500'>
                                                 $ 15607.00
                                            </span>
                                        </span>
                                        <span class='d-flex align-items-center gap-1'>
                                            Quarterly :
                                            <span class='fw-500'>
                                                $ 9689.00
                                            </span>
                                        </span>
                                        <span class='d-flex align-items-center gap-1'>
                                            yearly :
                                            <span class='fw-500'>
                                                $ 7301.00
                                            </span>
                                        </span>
                                    </div>">
                                    <i class="tio-info fs-14"></i>
                                </button>
                            </div>
                        </a>
                        <div class="d-flex align-items-center gap-20px">
                            <h4 class="mb-0">$ 500.00</h4>
                            <img class="m-0" width="20"
                                    src="{{asset('public/assets/admin/img/arrow-trend-up.png') }}"
                                    alt="img">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header w-100 gap-2 justify-content-between flex-wrap pt-4 border-0">
            <div class="js-nav-scroller hs-nav-scroller-horizontal mb-0">
                <ul class="nav nav-tabs border-0 nav--tabs nav--pills nav--theme-version">
                    <li class="nav-item">
                        <a class="nav-link active" href="">{{ translate('All') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link " href="">{{ translate('Active') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link " href="">{{ translate('Expired') }}</a>
                    </li>
                </ul>
            </div>
            <div class="search--button-wrapper gap-2 justify-content-lg-end">
                <form class="search-form">

                    <div class="input-group input--group">
                        <input id="datatableSearch" type="search" name="search"  value="{{ request()?->search ?? null }}" class="form-control" placeholder="{{ translate('messages.Ex') . ': ' . translate('Search by title') }}" aria-label="Search here">
                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>
                </form>
                <div class="hs-unfold mr-2">
                    <a class="btn btn-outline-primary btn-white filter-button-show h--40px px-4 w-max-content offcanvas-trigger"
                                data-target="#customer_list_offcanvas" href="javascript:;">
                        <i class="tio-tune-horizontal mr-1"></i>
                        {{ translate('messages.Filter') }}
                    </a>
                </div>

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
                        <a id="export-excel" class="dropdown-item" href="
                            {{ route('admin.campaign.basic_campaign_export', ['type' => 'excel', request()->getQueryString()]) }}
                            ">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                alt="Image Description">
                            Excel
                        </a>
                        <a id="export-csv" class="dropdown-item" href="
                        {{ route('admin.campaign.basic_campaign_export', ['type' => 'csv', request()->getQueryString()]) }}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                alt="Image Description">
                            CSV
                        </a>
                    </div>
                </div>                                
            </div>
        </div>

        <div class="px-xxl-20 px-3">
            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th class="border-0">
                                {{ translate('SL') }}
                            </th>
                            <th class="table-column-pl-0 border-0">{{ translate('Customer information') }}</th>
                            <th class="border-0">{{ translate('Plan validity') }}</th>
                            <th class="border-0">{{ translate('Plan name') }}</th>
                            <th class="border-0">{{ translate('Plan price') }}</th>
                            <th class="border-0 text-center">{{ translate('Subscription status') }}</th>
                            <th class="border-0 text-center">{{ translate('auto-renew') }}</th>
                            <th class="border-0 text-center">
                                <div class="d-flex align-items-center gap-1">
                                    {{ translate('Total orders') }}
                                    <span class="gray-text text-left" data-toggle="tooltip"
                                        data-placement="right"
                                        data-original-title="{{ translate('Enter content') }}">
                                        <i class="tio-info fs-14 text-8797AB"></i>
                                    </span>
                                </div>
                            </th>
                            <th class="border-0 text-center">{{ translate('actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="set-rows">
                            <tr class="">
                                <td class="">
                                    1
                                </td>
                                <td class="table-column-pl-0">
                                    <div class="d-flex align-items-center gap-2 min-w-220 max-w--220px">
                                        <img class="rounded-circle aspect-1-1 object-cover" width="40"
                                            src="{{asset('public/assets/admin/img/placeholder.png') }}" alt="Image Description">

                                        <div>
                                            <a href=""
                                                class="text-dark fw-500 text-hover-primary max-w-215px min-w-135px text-wrap line--limit-1">
                                                Robert Henri
                                            </a>
                                            <div>
                                                <div>
                                                    <a href="" class="text-light-gray fs-12 text-hover-primary max-w-215px min-w-170px text-wrap line--limit-1">
                                                        Abcd@gmail.com
                                                    </a>
                                                </div>
                                                <div>
                                                    <a href=""
                                                        class="text-light-gray fs-12 text-hover-primary">
                                                        +888 5421 0254 255411
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class=" ">
                                    <div class="d-flex text-dark align-items-center gap-2">
                                        <div>
                                            <div>
                                                28 Nov 2024 11:00 pm - 
                                            </div>
                                            <div>
                                                28 Dec 2024 11:00 pm 
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-dark">
                                    Monthly
                                </td>
                                <td class="text-dark">
                                    $ 20.00
                                </td>
                                <td>
                                    <span  class="fs-14 text-dark text-center">
                                        active
                                    </span>
                                </td>
                                <td>
                                    <span class="fs-14 text-dark text-center d-block">
                                        on
                                    </span>
                                </td>
                                <td>
                                   <span class="fs-14 text-dark text-center d-block">
                                        10
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-3 justify-content-center">
                                        <a class="btn action-btn btn-danger btn-outline-danger"
                                            href="" title="{{ translate('Cancel subscription') }}" data-toggle="tooltip"
                                            data-placement="right" >
                                            <i class="tio-clear"></i>
                                        </a>
                                        <a class="btn action-btn action-btn--view"
                                            href=""
                                            title="{{ translate('messages.View subscriber') }}"><i
                                                class="tio-visible-outlined"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="empty--data">
            <img src="{{asset('public/assets/admin/img/empty.png') }}" alt="public">
            <h5>
                {{ translate('No data found') }}
            </h5>
        </div>

    </div>

</div>

    <div id="customer_list_offcanvas" class="custom-offcanvas d-flex flex-column justify-content-between"
        style="--offcanvas-width: 500px">
            <div>
                <form id="filterForm" action="{{ route('admin.users.customer.list') }}" method="GET">
                <div class="custom-offcanvas-header bg-light d-flex justify-content-between align-items-center">
                    <div class="px-3 py-3 d-flex justify-content-between w-100">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <h2 class="mb-0 fs-18 text-title font-medium">{{ translate('Filter - Subscriber') }}</h2>

                        </div>
                        <button type="button"
                            class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
                            aria-label="Close">&times;
                        </button>
                    </div>
                </div>
                <div class="custom-offcanvas-body p-20">
                    <div class="d-flex flex-column gap-20px">
                        <div class="bg-light p-xxl-20 p-3 rounded">
                            <div class="mb-20">
                                <div class="d-flex align-items-center text-dark gap-1 fs-14 mb-2">
                                    {{ translate('Validity Date Range') }}
                                </div>
                                <div class="position-relative bg-white rounded">
                                    <i class="tio-calendar-month icon-absolute-on-right"></i>
                                    <input type="text" class="form-control h-45 position-relative bg-transparent"
                                            name="dates" placeholder="{{ translate('Select date') }}">
                                </div>
                            </div>
                            <div>
                                <div class="d-flex align-items-center text-dark gap-1 fs-14 mb-2">
                                    {{ translate('Validity Date Range') }}
                                    <span class="text-danger">*</span>
                                </div>
                                <input type="text" class="form-control" placeholder="Ex: data">
                            </div>
                        </div>
                        <div class="bg-light p-xxl-20 p-3 rounded">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label fw-400">{{ translate('Plan') }}</label>
                                    <select name="filter" data-placeholder="{{ translate('Select status') }}"
                                        class="form-control js-select2-custom ">
                                        <option value="" selected disabled>
                                            {{ translate('Select status') }}
                                        </option>
                                        <option {{ request()->input('filter') == 'all' ? 'selected' : '' }} value="all">
                                            {{ translate('All customers') }}</option>
                                        <option {{ request()->input('filter') == 'active' ? 'selected' : '' }}
                                            value="active">
                                            {{ translate('messages.Active Customers') }}</option>
                                        <option {{ request()->input('filter') == 'blocked' ? 'selected' : '' }}
                                            value="blocked">
                                            {{ translate('messages.Inactive Customers') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-400">{{ translate('Subscription status') }}</label>
                                    <select name="filter" data-placeholder="{{ translate('Select status') }}"
                                        class="form-control js-select2-custom ">
                                        <option value="" selected disabled>
                                            {{ translate('Select status') }}
                                        </option>
                                        <option {{ request()->input('filter') == 'all' ? 'selected' : '' }} value="all">
                                            {{ translate('All customers') }}</option>
                                        <option {{ request()->input('filter') == 'active' ? 'selected' : '' }}
                                            value="active">
                                            {{ translate('messages.Active Customers') }}</option>
                                        <option {{ request()->input('filter') == 'blocked' ? 'selected' : '' }}
                                            value="blocked">
                                            {{ translate('messages.Inactive Customers') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-400">{{ translate('auto-renew') }}</label>
                                    <select name="filter" data-placeholder="{{ translate('Select status') }}"
                                        class="form-control js-select2-custom ">
                                        <option value="" selected disabled>
                                            {{ translate('Select status') }}
                                        </option>
                                        <option {{ request()->input('filter') == 'all' ? 'selected' : '' }} value="all">
                                            {{ translate('All customers') }}</option>
                                        <option {{ request()->input('filter') == 'active' ? 'selected' : '' }}
                                            value="active">
                                            {{ translate('messages.Active Customers') }}</option>
                                        <option {{ request()->input('filter') == 'blocked' ? 'selected' : '' }}
                                            value="blocked">
                                            {{ translate('messages.Inactive Customers') }}</option>
                                    </select>
                                </div>                                
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div  class="align-items-center bg-white bottom-0 d-flex gap-3 justify-content-center offcanvas-footer p-3 position-sticky">
                <a href="{{ route('admin.users.customer.list') }}"
                    class="btn w-100 btn--reset offcanvas-close"><i class="tio-refresh"></i> {{ translate('Reset') }}</a>
                <button type="submit" id="apply_filter" class="btn w-100 btn--primary"><i class="tio-filter-list"></i> {{ translate('Apply') }}</button>
            </form>
            </div>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>

@endsection

@push('script_2')

@endpush