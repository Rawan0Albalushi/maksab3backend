@extends('layouts.admin.app')

@section('title',translate('Denied stores'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('new_store_request')
active
@endsection

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title"><i class="tio-filter-list"></i> {{translate('messages.Denied stores')}}
                            <span class="badge badge-soft-dark ml-2" id="itemCount">{{ $stores->total() }}</span>
                        </h1>
            <p class="page-header-desc">{{ translate('Stores you turned down, kept so you can look back at why.') }}</p>
            <div class="row">
                <div class="col-md-12">
                    <div class="js-nav-scroller hs-nav-scroller-horizontal mt-2">
                        <ul class="nav nav-tabs mb-3 border-0 nav--tabs nav--pills">
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.store.pending-requests') }}"   aria-disabled="true">{{translate('messages.Pending stores')}}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link active" href="{{ route('admin.store.deny-requests') }}"  aria-disabled="true">{{translate('messages.Denied stores')}}</a>
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
                        'subtitle' => translate('messages.Denied store list subtitle'),
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
                    <form action="javascript:" id="search-form" class="search-form">
                        @csrf
                        <div class="input-group input--group">
                            <input id="datatableSearch_" type="search" name="search" class="form-control"
                                    placeholder="{{translate('Ex') . ': ' . translate('Search by store name')}}" aria-label="{{translate('Search')}}" value="{{isset($search_by) ? $search_by : ''}}" required>
                            <button type="submit" class="btn btn--primary"><i class="tio-search"></i></button>
                        </div>
                    </form>
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
                        <th class="border-0">{{translate('Denied reason')}}</th>
                        <th class="border-0">{{translate('Applied')}}</th>
                        <th class="border-0 text-center " >{{translate('messages.Action')}}</th>
                    </tr>
                    </thead>

                    <tbody id="set-rows">
                    @foreach($stores as $store)
                        <tr>
                            <td>
                                <a href="{{route('admin.store.view', $store->id)}}" class="table-rest-info" alt="view store">
                                    <img class="img--60 circle onerror-image" data-onerror-image="{{asset('public/assets/admin/img/160x160/img1.jpg')}}"
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
                                @if($store->vendor?->rejection_note)
                                    <span class="d-block text-body" title="{{ $store->vendor->rejection_note }}">
                                        {{Str::limit($store->vendor->rejection_note, 40, '...')}}
                                    </span>
                                @else
                                    <span class="text-muted font-size-sm">{{translate('No reason recorded')}}</span>
                                @endif
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

                                    <span class="table-actions__sep" aria-hidden="true"></span>

                                    <a class="btn action-btn action-btn--edit"
                                    href="{{route('admin.store.edit',[$store['id'],'pending'=>1])}}" title="{{translate('Edit store')}}"><i class="tio-edit"></i>
                                    </a>
                                @endif
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
