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
                <th class="border-0">{{ translate('Business zone name') }}</th>
                <th class="border-0">{{ translate('messages.Modules') }}</th>
                <th class="border-0 col--numeric">{{ translate('messages.vendors') }}</th>
                <th class="border-0 col--numeric">{{ translate('messages.Deliverymen') }}</th>
                <th class="border-0">{{ translate('messages.Created at') }}</th>
                <th class="border-0">{{ translate('messages.Status') }}</th>
                <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
            </tr>
        </thead>

        <tbody id="set-rows">
            @include('admin-views.zone.partials._table_rows', ['zones' => $zones, 'readiness' => $readiness ?? []])
        </tbody>
    </table>
</div>
@if (count($zones) !== 0)
    <hr>
@endif
<div class="page-area">
    {!! $zones->withQueryString()->links() !!}
</div>
@if (count($zones) === 0)
    <div class="empty--data">
        <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
        <h5>
            {{ translate('No data found') }}
        </h5>
    </div>
@endif
