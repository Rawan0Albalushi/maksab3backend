{{-- Export menu for a withdraw queue.

     @include('admin-views.withdraw.partials._export-dropdown', [
         'export_route' => 'admin.transactions.delivery-man.withdraw_export',
     ])

     The status tab and the search box are carried into the export as named
     query parameters. All three screens used to pass
     `request()->getQueryString()` as a numerically-keyed element of the
     route() array, which Laravel renders as `?0=a%3Db%26c%3Dd` — so the search
     term never reached the export, and the status came from a session key the
     list no longer uses. --}}

@php
    $wdr_export_params = request()->except(['page', 'type']);
@endphp

<div class="hs-unfold mr-2">
    <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40" href="javascript:;"
       data-hs-unfold-options='{
            "target": "#usersExportDropdown",
            "type": "css-animation"
       }'>
        <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
    </a>

    <div id="usersExportDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
        <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
        <a id="export-excel" class="dropdown-item" href="{{ route($export_route, array_merge($wdr_export_params, ['type' => 'excel'])) }}">
            <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="">
            Excel
        </a>
        <a id="export-csv" class="dropdown-item" href="{{ route($export_route, array_merge($wdr_export_params, ['type' => 'csv'])) }}">
            <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg" alt="">
            CSV
        </a>
    </div>
</div>
