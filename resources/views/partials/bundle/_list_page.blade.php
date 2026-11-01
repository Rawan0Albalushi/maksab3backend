<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title text-capitalize m-0 d-flex align-items-center">
            <span class="page-header-icon d-flex align-items-center">
                <img src="{{ asset('public/assets/admin/img/items-store.png') }}" alt="" style="height:20px;width:auto">
            </span>
            <span class="d-flex align-items-center">
                {{ translate('Bundle package') }}
            </span>
        </h1>
    </div>

    <div class="card">
        @if ($bundles->total() === 0 && ! request()->filled('search'))
            <div class="d-flex flex-column align-items-center justify-content-center text-center px-3"
                style="min-height: 300px">
                <img src="{{ asset('public/assets/admin/img/fi_2976415.png') }}"
                    alt="{{ translate('No bundle packages yet') }}" style="max-width:60px;height:auto">
                <h4 class="mt-4 mb-2 font-weight-bold">{{ translate('No bundle packages yet') }}</h4>
                <p class="opacity-75 mb-4" style="max-width: 420px">
                    {{ translate('messages.Create your first bundle package by grouping related products and offering them at a special price to attract more customers') }}
                </p>
                <a href="{{ route($routePrefix.'.create') }}" class="btn btn--primary min-w-120">
                    {{ translate('Add bundle') }}
                </a>
            </div>
        @else
            <div class="card-header py-2 border-0">
                <div class="search--button-wrapper">
                    <div class="table-head">
                        <h5 class="card-title">{{ translate('Bundle package list') }} <span class="badge badge-soft-dark ml-2">{{ $bundleCount }}</span> </h5>
                    </div>

                    <form id="search-form" action="{{ url()->current() }}" method="GET">
                        <div class="input--group input-group input-group-merge input-group-flush">
                            <input id="datatableSearch_" type="search" name="search" value="{{ request('search') }}"
                                class="form-control" placeholder="{{ translate('Search') }}" aria-label="Search">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>

                    <div class="hs-unfold">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle btn export-btn btn-outline-primary btn--primary font--sm"
                            href="javascript:;"
                            data-hs-unfold-options='{"target": "#bundleExportDropdown", "type": "css-animation"}'>
                            <i class="tio-download-to mr-1"></i> {{ translate('Export') }}
                        </a>
                        <div id="bundleExportDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                            <span class="dropdown-header">{{ translate('Download options') }}</span>
                            <a target="__blank" class="dropdown-item"
                                href="{{ route($routePrefix.'.export', ['type' => 'excel', 'search' => request('search')]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="excel">
                                Excel
                            </a>
                            <a target="__blank" class="dropdown-item"
                                href="{{ route($routePrefix.'.export', ['type' => 'csv', 'search' => request('search')]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg" alt="csv">
                                CSV
                            </a>
                        </div>
                    </div>

                    <a href="{{ route($routePrefix.'.create') }}" class="btn btn--primary">
                        <i class="tio-add-circle"></i> {{ translate('Add bundle') }}
                    </a>
                </div>
            </div>

            @include('partials.bundle._list_table')
        @endif
    </div>
</div>

<div id="offcanvas__bundle_detail" class="custom-offcanvas d-flex flex-column justify-content-between"
    style="--offcanvas-width: 465px">
    <div id="bundle-detail-body" class="h-100"></div>
</div>
<div id="offcanvasOverlay"></div>

@include('partials.bundle._confirm_modal')
