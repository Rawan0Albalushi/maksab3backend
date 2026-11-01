{{-- Export menu for a disbursement details table.

     @include('admin-views.disbursement.partials._export-dropdown', [
         'export_route' => 'admin.transactions.dm-disbursement.export',
         'disbursement' => $disbursement,
     ])

     The filters on screen are carried into the export as named query
     parameters. All three screens used to pass `request()->getQueryString()`
     as a numerically-keyed element of the route() array, which Laravel renders
     as `?0=a%3Db%26c%3Dd` — so every export silently ignored them. --}}

@php
    $sdb_export_params = request()->except(['page', 'id', 'type']);
@endphp

<div class="hs-unfold ml-3">
    <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle btn export-btn btn-outline-primary btn--primary font--sm" href="javascript:;"
       data-hs-unfold-options='{
            "target": "#usersExportDropdown",
            "type": "css-animation"
       }'>
        <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
    </a>
    <div id="usersExportDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
        <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
        <a id="export-excel" class="dropdown-item" href="{{ route($export_route, array_merge($sdb_export_params, ['id' => $disbursement->id, 'type' => 'excel'])) }}">
            <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="">
            Excel
        </a>
        <a id="export-csv" class="dropdown-item" href="{{ route($export_route, array_merge($sdb_export_params, ['id' => $disbursement->id, 'type' => 'csv'])) }}">
            <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg" alt="">
            CSV
        </a>
        <a id="export-pdf" class="dropdown-item" href="{{ route($export_route, array_merge($sdb_export_params, ['id' => $disbursement->id, 'type' => 'pdf'])) }}">
            <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin') }}/svg/components/pdf.svg" alt="">
            PDF
        </a>
    </div>
</div>
