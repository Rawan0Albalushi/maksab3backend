{{-- Export menu for a tax report.

     @include('admin-views.report.tax-report.partials._export-dropdown', [
         'export_route'  => 'admin.transactions.report.vendorWiseTaxExport',
         'export_extra'  => ['id' => $store->store_id],   // optional
         'export_target' => 'usersExportDropdown',        // optional
     ])

     Every filter on screen is carried into the export as named query
     parameters. All six tax screens used to pass
     `request()->getQueryString()` as a numerically-keyed element of the
     route() array, which Laravel renders as `?0=a%3Db%26c%3Dd` — so a report
     exported with a date range on screen came back unfiltered. --}}

@php
    $txr_target = $export_target ?? 'usersExportDropdown';
    $txr_params = array_merge(request()->except(['page', 'export_type']), $export_extra ?? []);
@endphp

<div class="hs-unfold mr-2 flex-grow-0">
    <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle h--40px" href="javascript:;"
       data-hs-unfold-options='{ "target": "#{{ $txr_target }}", "type": "css-animation" }'>
        <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
    </a>
    <div id="{{ $txr_target }}" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
        <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
        <a id="export-excel" class="dropdown-item" href="{{ route($export_route, array_merge($txr_params, ['export_type' => 'excel'])) }}">
            <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="">
            Excel
        </a>
        <a id="export-csv" class="dropdown-item" href="{{ route($export_route, array_merge($txr_params, ['export_type' => 'csv'])) }}">
            <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg" alt="">
            CSV
        </a>
    </div>
</div>
