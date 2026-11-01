@extends('layouts.admin.app')

@section('title', translate('Admin tax report details'))

@section('tax_report')
    active
@endsection

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/tax.css') }}">
@endpush

@section('content')
    <div class="content container-fluid txr">


        <h2 class="mb-20">{{ translate('Admin tax report') }}</h2>
            <h2 class="mb-20 mt-5">{{ translate('Tax details') }}</h2>
            <div class="bg--secondary rounded p-20">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-15">
                    <div>
                        <h5 class="mb-1">{{ translate($taxSource) }} {{ translate('Taxes') }}</h5>
                        <p class="fz-12px mb-0">{{ translate('Date') }}: {{ $startDate }} - {{ $endDate }}</p>
                    </div>
                    <div class="hs-unfold mr-2 hungar-export">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-primary dropdown-toggle h--40px" href="javascript:;"
                            data-hs-unfold-options='{
                        "target": "#usersExportDropdown4", "type": "css-animation" }'>
                            <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                        </a>
                        <div id="usersExportDropdown4"
                            class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                            <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                            <a id="export-excel" class="dropdown-item" href="{{ route('admin.transactions.report.getTaxDetailsExport',array_merge(request()->except(['page', 'export_type']), ['source' => $taxSource, 'export_type' => 'excel'])) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                    alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item" href="{{ route('admin.transactions.report.getTaxDetailsExport',array_merge(request()->except(['page', 'export_type']), ['source' => $taxSource, 'export_type' => 'csv'])) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                    alt="Image Description">
                                CSV
                            </a>
                        </div>
                    </div>
                </div>
                <div class="row g-3">
                    @if ($taxSource =='admin_commission' )
                    <div class="col-md-6">
                        <div class="bg-white p-12 w-100 rounded d-flex align-items-center justify-content-between">
                            {{ translate('Total orders') }} <h4 class="theme-clr fw-bold mb-0">{{ $total_count }}
                            </h4>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-white p-12 w-100 rounded d-flex align-items-center justify-content-between">
                            {{ translate('Total order amount') }} <h4 class="theme-clr fw-bold mb-0">
                                {{ \App\CentralLogics\Helpers::format_currency($total_order_amount) }}</h4>
                        </div>
                    </div>
                    @endif
                    <div class="col-md-6">
                        <div class="bg-white p-12 w-100 rounded d-flex align-items-center justify-content-between">
                            {{ translate('Total commission') }} <h4 class="cus-warning-light-clr fw-bold mb-0">
                                {{  \App\CentralLogics\Helpers::format_currency($total_amount) }}</h4>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-white p-12 w-100 rounded d-flex align-items-center justify-content-between">
                            {{ translate('Total tax amount') }} <h4 class="cus-warning-clr fw-bold mb-0">
                                {{ \App\CentralLogics\Helpers::format_currency($total_tax_amount) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card p-20 mt-5">
                <div class="table-responsive datatable-custom">
                    <table id="datatable"
                        class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table fz--14px">
                        <thead class="thead-light">
                            <tr>
                                @php
                                if( $taxSource =='admin_commission'){
                                    $col= 'Commission';
                                } elseif($taxSource =='delivery_commission'){
                                    $col= 'Delivery_Commission';
                                } else{
                                    $col= 'Additional_Charge';
                                }

                                @endphp
                                <th class="border-0">{{ translate('SL') }}</th>
                                <th class="border-0">{{ translate('Order') }}</th>
                                <th class="border-0">{{ translate($col) }}</th>
                                <th class="border-0">{{ translate('Tax amount') }}</th>
                            </tr>
                        </thead>

                        <tbody>

                            @foreach ($taxData as $key => $item)
                                <tr>
                                    <td>
                                        {{ $key + $taxData->firstItem() }}
                                    </td>
                                    <td>
                                        <h6>{{ \App\CentralLogics\Helpers::format_currency($item->order_amount) }}</h6>
                                        <a href="{{ route($item->order->order_type == 'parcel' ? 'admin.parcel.order.details' : 'admin.order.details', ['id' => $item->order_id]) }}">
                                            <small>#{{ $item->order_id }} </small>
                                        </a>
                                    </td>
                                    <td>
                                        @if ($taxSource =='admin_commission' )
                                        {{ \App\CentralLogics\Helpers::format_currency($item->admin_commission+$item->admin_expense-$item->delivery_fee_comission-$item->additional_charge) }}
                                        @elseif ($taxSource == 'delivery_commission')
                                        {{ \App\CentralLogics\Helpers::format_currency( $item->delivery_fee_comission ) }}
                                        @else
                                        {{ \App\CentralLogics\Helpers::format_currency( $item->additional_charge ) }}
                                        @endif
                                    </td>

                                    <td>
                                        @php
                                            $taxSummary = collect($item['calculated_taxes']);
                                            $totalTaxRate = $taxSummary->sum('tax_rate');
                                            $totalTaxAmount = $taxSummary->sum('tax_amount');
                                        @endphp

                                        <div class="d-flex flex-column gap-1">
                                            <div class="d-flex fz-14 gap-3 align-items-center title-clr">
                                                {{ translate('Total') }} ({{ $totalTaxRate }}%):
                                                <span>
                                                    {{ \App\CentralLogics\Helpers::format_currency($totalTaxAmount) }}</span>
                                            </div>

                                            @foreach ($item['calculated_taxes'] as $taxItems)
                                                <div class="d-flex fz-11 gap-3 align-items-center">
                                                    {{ $taxItems['tax_name'] }} ({{ $taxItems['tax_rate'] }}%) :
                                                    <span>{{ \App\CentralLogics\Helpers::format_currency($taxItems['tax_amount']) }}</span>
                                                </div>
                                            @endforeach

                                        </div>
                                    </td>
                                </tr>
                            @endforeach

                        </tbody>
                    </table>
                </div>

                @if (count($taxData) !== 0)
                    <hr>
                @endif
                <div class="page-area">
                    {!! $taxData->withQueryString()->links() !!}
                </div>
                @if (count($taxData) === 0)
                    <div class="empty--data">
                        <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
                        <h5>
                            {{ translate('No data found') }}
                        </h5>
                    </div>
                @endif
            </div>
    </div>



@endsection

@push('script_2')
@endpush
