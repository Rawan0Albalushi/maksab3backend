@extends('layouts.admin.app')

@section('title',translate('messages.New joining requests'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    @php
        $admin_commission = \App\CentralLogics\Helpers::get_business_settings('admin_commission', false);
        $business_model_labels = [
            'commission' => translate('Commission'),
            'subscription' => translate('Subscription'),
            'unsubscribed' => translate('messages.unsubscribed'),
        ];
    @endphp
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title"><i class="tio-filter-list"></i> {{translate('messages.New joining requests')}}
                            <span class="badge badge-soft-dark ml-2" id="itemCount">{{ $stores->total() }}</span>
                        </h1>
            <p class="page-header-desc">{{ translate('Stores that have applied to trade with you and are waiting on your decision.') }}</p>
            <div class="row">
                <div class="col-md-12">
                    <div class="js-nav-scroller hs-nav-scroller-horizontal mt-2">
                        <ul class="nav nav-tabs mb-3 border-0 nav--tabs nav--pills">
                            <li class="nav-item">
                                <a class="nav-link active" href="{{ route('admin.store.pending-requests') }}"   aria-disabled="true">{{translate('messages.Pending stores')}}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.store.deny-requests') }}"  aria-disabled="true">{{translate('messages.Denied stores')}}</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-2">
                <div class="search--button-wrapper">
                    @include('partials._table-head', [
                        'subtitle' => translate('messages.Pending store list subtitle'),
                    ])

                    <div class="d-flex align-items-center gap-3 flex-sm-nowrap flex-wrap">
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
                        <form action="javascript:" id="search-form" class="search-form w-100">
                            @csrf
                            <div class="input-group input--group">
                                <input id="datatableSearch_" type="search" name="search" class="form-control"
                                        placeholder="{{translate('Ex') . ': ' . translate('Search by store name')}}" value="{{isset($search_by) ? $search_by : ''}}" aria-label="{{translate('Search')}}" required>
                                <button type="submit" class="btn btn--primary"><i class="tio-search"></i></button>
                            </div>
                        </form>
                        <div>
                            <div class="hs-unfold mr-2">
                                <a class="js-hs-unfold-invoker btn btn-sm btn-white d-inline-flex text-title font-medium dropdown-toggle min-height-40" href="javascript:;"
                                    data-hs-unfold-options='{
                                            "target": "#usersExportDropdown",
                                            "type": "css-animation"
                                        }'>
                                    <i class="tio-download-to mr-1 text-title"></i> {{ translate('messages.Export') }}
                                </a>
                                <div id="usersExportDropdown"
                                    class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                                    <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                                    <a id="export-excel" class="dropdown-item" href="{{route('admin.business-settings.module.export', ['type'=>'excel',request()->getQueryString()])}}">
                                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                                            src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                            alt="Image Description">
                                        Excel
                                    </a>
                                    <a id="export-csv" class="dropdown-item" href="{{route('admin.business-settings.module.export', ['type'=>'csv',request()->getQueryString()])}}">
                                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                                            src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                            alt="Image Description">
                                        CSV
                                    </a>
                                </div>
                            </div>
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
                    <thead class="bg-table-head">
                    <tr>
                        <th class="border-0">{{translate('Store information')}}</th>
                        <th class="border-0">{{translate('Owner information')}}</th>
                        <th class="border-0">{{translate('messages.Zone')}}</th>
                        <th class="border-0">{{translate('Business plan')}}</th>
                        <th class="border-0">{{translate('Applied')}}</th>
                        <th class="border-0 text-center">{{translate('messages.Action')}}</th>
                    </tr>
                    </thead>

                    <tbody id="set-rows">
                    @foreach($stores as $store)
                        @php($waiting_days = $store->created_at ? $store->created_at->diffInDays(now()) : 0)
                        @php($subscription = $store->store_sub_update_application)
                        @php($package_name = $subscription?->package?->package_name ?? $store->package?->package_name)
                        <tr>
                            <td>
                                <a href="{{route('admin.store.view', $store->id)}}" class="table-rest-info" alt="view store">
                                    <img class="img--60 rounded broder onerror-image" data-onerror-image="{{asset('public/assets/admin/img/160x160/img1.jpg')}}"
                                    src="{{ $store['logo_full_url'] ?? asset('public/assets/admin/img/160x160/img1.jpg') }}" >
                                    <div class="info max-w-200px">
                                        <div title="{{ $store?->name }}" class="text--title">
                                            {{Str::limit($store->name,20,'...')}}
                                        </div>
                                        <span class="d-block fs-12 text-muted font-weight-normal">{{$store->address ? Str::limit($store->address, 28, '...') : translate('messages.N/A')}}</span>
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
                                {{$store->zone?$store->zone->name:translate('messages.Zone deleted')}}
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
                                @else
                                    <span class="text-muted font-size-sm">{{translate('messages.N/A')}}</span>
                                @endif
                            </td>
                            <td data-order="{{$store->created_at}}">
                                <span class="table-when{{ $waiting_days >= 3 ? ' table-when--stale' : '' }}">
                                    <span class="table-when__day">{{\App\CentralLogics\Helpers::date_format($store->created_at)}}</span>
                                    <span class="table-when__ago" title="{{\App\CentralLogics\Helpers::time_date_format($store->created_at)}}">
                                        {{$store->created_at?->diffForHumans()}}
                                    </span>
                                </span>
                            </td>
                            <td>
                                <div class="table-actions justify-content-center">
                                    @if($store->vendor->status == 0)
                                        <a class="btn action-pill action-pill--approve swal_fire_alert"
                                       data-title="{{translate('messages.Are you sure?')}}"
                                       data-image_url="{{ asset('public/assets/admin/img/off-danger.png') }}"
                                       data-confirm_button_text="{{ translate('messages.Yes') }}"
                                       data-cancel_button_text="{{ translate('messages.No') }}"
                                       data-message="{{translate('messages.You want to approve the vendor joining request.')}}"
                                        data-url="{{route('admin.store.application',[$store['id'],1])}}"
                                            href="javascript:">
                                            <i class="tio-checkmark-circle-outlined"></i>
                                            <span>{{ translate('Approve') }}</span>
                                        </a>
                                    @endif
                                    @if (!isset($store->vendor->status))
                                        <button class="btn action-pill action-pill--deny" type="button"
                                        data-toggle="modal" data-target="#confirmation-reason-btn{{ $store->id }}">
                                            <i class="tio-clear-circle-outlined"></i>
                                            <span>{{ translate('Reject') }}</span>
                                        </button>
                                    @endif

                                    <span class="table-actions__sep" aria-hidden="true"></span>

                                    <a class="btn action-btn action-btn--edit"
                                    href="{{route('admin.store.edit',[$store['id'],'pending'=>1])}}" title="{{translate('Edit store')}}"><i class="tio-edit"></i>
                                    </a>
                                </div>

    <div class="modal shedule-modal fade" id="confirmation-reason-btn{{ $store->id }}" tabindex="-1"
        aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content pb-2 max-w-500">
                <form action="{{ route('admin.store.application', [$store['id'], 0]) }}" method="get">
                <div class="modal-header">
                    <button type="button"
                        class="close bg-modal-btn w-30px h-30 rounded-circle position-absolute right-0 top-0 m-2 z-2"
                        data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <img src="{{ asset('public/assets/admin/img/delete-confirmation.png') }}" alt="icon"
                            class="mb-3">
                        <h3 class="mb-2">{{ translate('messages.Are you sure?') }}</h3>
                        <p class="mb-0">{{ translate('You want to deny this joining application?') }}</p>
                    </div>
                    <div class="px-3 mt-4">
                        <h5 class="mb-2">{{ translate('messages.Reason') }}</h5>
                        <textarea name="rejection_note" id="" class="form-control" rows="2" required
                            placeholder="{{ translate('Enter the reason for denial') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-0 gap-2">
                    <button type="button" class="btn min-w-120px btn--reset" data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{ translate('messages.No') }}</button>
                    <button type="submit" class="btn min-w-120px btn--primary"><i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Yes') }}</button>
                </div>
            </form>
            </div>
        </div>
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

@endsection

@push('script_2')
    <script>
        "use strict";
        $(document).on('ready', function () {
            // INITIALIZATION OF DATATABLES
            // =======================================================
            let datatable = $.HSCore.components.HSDatatables.init($('#columnSearchDatatable'));

            $('#column1_search').on('keyup', function () {
                datatable
                    .columns(1)
                    .search(this.value)
                    .draw();
            });

            $('#column2_search').on('keyup', function () {
                datatable
                    .columns(2)
                    .search(this.value)
                    .draw();
            });

            $('#column3_search').on('keyup', function () {
                datatable
                    .columns(3)
                    .search(this.value)
                    .draw();
            });

            $('#column4_search').on('keyup', function () {
                datatable
                    .columns(4)
                    .search(this.value)
                    .draw();
            });


            // INITIALIZATION OF SELECT2
            // =======================================================
            $('.js-select2-custom').each(function () {
                let select2 = $.HSCore.components.HSSelect2.init($(this));
            });
        });
         $('.swal_fire_alert').on('click', function (event) {
            let url = $(this).data('url');
            let message = $(this).data('message');
            let title = $(this).data('title');
            let imageUrl = $(this).data('image_url');
            let cancelButtonText = $(this).data('cancel_button_text');
            let confirmButtonText = $(this).data('confirm_button_text');
            swalFire(url,title, message, imageUrl,cancelButtonText, confirmButtonText)
        })

        $('#search-form').on('submit', function () {
            let formData = new FormData(this);
            set_filter('{!! url()->full() !!}',formData.get('search'),'search_by')
        });
    </script>
@endpush
