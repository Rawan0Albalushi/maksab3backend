@extends('layouts.admin.app')

@section('title',translate('Campaign list'))


@section('content')
    @php
        $lifecycle_labels = [
            'running' => translate('messages.Running'),
            'scheduled' => translate('messages.Scheduled'),
            'ended' => translate('messages.Ended'),
        ];
    @endphp
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/outline/campaign.svg')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('Campaign')}}
                    <span class="badge badge-soft-dark ml-2" id="itemCount">{{ $campaigns->total() }}</span>
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Every store campaign you have set up, with the stores, dates and state of each.') }}</p>
        </div>

        <div class="fs-12 px-3 py-2 rounded bg-info bg-opacity-10 mb-20">
            <div class="d-flex align-items-center gap-2 mb-0">
                <span class="text-info lh-1 fs-14">
                    <img src="{{asset('public/assets/admin/img/svg/bulb.svg')}}" class="svg" alt="">
                </span>
                <p class="mb-0">
                    {{ translate('messages.Vendors can join any campaign directly from the Basic Campaign List in the vendor panel.') }}
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-2 border-0">
                <div class="search--button-wrapper">
                    @include('partials._table-head', [
                        'subtitle' => translate('messages.Store-wide campaigns that run between a set start and end date.'),
                    ])
                    <form class="search-form">

                        <div class="input-group input--group">
                            <input id="datatableSearch" type="search" name="search"  value="{{ request()?->search ?? null }}" class="form-control" placeholder="{{ translate('messages.Ex') . ': ' . translate('Search by title') }}" aria-label="Search here">
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
                    <a class="btn btn--primary py-10px px-3 fs-12" href="{{route('admin.campaign.add-new', 'basic')}}">
                        <i class="tio-add-circle"></i> {{translate('messages.Add new campaign')}}
                    </a>
                </div>
            </div>
            <div class="card-body pt-0 pb-0">
                <div class="table-responsive datatable-custom">
                    <table id="columnSearchDatatable"
                            class="font-size-sm table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                            data-hs-datatables-options='{
                                "order": [],
                                "orderCellsTop": true,
                                "paging":false
                            }'>
                        <thead class="thead-light">
                        <tr>
                            <th class="border-0" >{{translate('Campaign')}}</th>
                            <th class="border-0 col--numeric" >{{ config('module.current_module_type') === 'service' ? translate('messages.Providers') : translate('messages.Stores') }}</th>
                            <th class="border-0" >{{translate('messages.Schedule')}}</th>
                            <th class="border-0" >{{translate('Ends on')}}</th>
                            <th class="border-0">{{translate('messages.Status')}}</th>
                            <th class="border-0 text-center">{{translate('messages.Action')}}</th>
                        </tr>
                        </thead>

                        <tbody id="set-rows">
                        @foreach($campaigns as $campaign)
                            @php($ends = $campaign->end_date)
                            @php($days_left = $ends ? (int) \Carbon\Carbon::now()->startOfDay()->diffInDays($ends->copy()->startOfDay(), false) : null)
                            @php($not_started = $campaign->start_date && $campaign->start_date->copy()->startOfDay()->gt(\Carbon\Carbon::now()->startOfDay()))
                            @php($lifecycle = is_null($days_left) ? null : ($days_left < 0 ? 'ended' : ($not_started ? 'scheduled' : 'running')))
                            <tr>
                                <td>
                                    <a href="{{route('admin.campaign.view',['basic',$campaign->id])}}" class="table-rest-info" title="{{ $campaign['title'] }}">
                                        <img class="img--60 rounded onerror-image" data-onerror-image="{{asset('public/assets/admin/img/160x160/img2.jpg')}}"
                                                src="{{ $campaign->image_full_url ?? asset('public/assets/admin/img/160x160/img2.jpg') }}" alt="{{ $campaign['title'] }}">
                                        <div class="info max-w-200px">
                                            <div class="text--title line--limit-2">{{Str::limit($campaign['title'],25, '...')}}</div>
                                            <div class="font-light">ID:{{$campaign->id}}</div>
                                        </div>
                                    </a>
                                </td>
                                <td class="col--numeric" data-order="{{ $campaign->stores_count ?? 0 }}">
                                    <span class="d-block text-title font-semibold">{{ $campaign->stores_count ?? 0 }}</span>
                                    @if($campaign->stores_count)
                                        <span class="d-block fs-12 text-muted">
                                            {{ translate('Joined') }}: {{ $campaign->joined_stores_count ?? 0 }}@if($campaign->pending_stores_count) · {{ translate('Pending') }}: {{ $campaign->pending_stores_count }}@endif
                                        </span>
                                    @else
                                        <span class="d-block fs-12 text-muted">{{ translate('No store joined yet') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="d-block text-title">{{$campaign->start_date? \App\CentralLogics\Helpers::date_format($campaign?->start_date).' - '.  \App\CentralLogics\Helpers::date_format($campaign?->end_date): translate('messages.N/A')}}</span>
                                    <span class="d-block fs-12 text-muted text-uppercase">{{$campaign->start_time? \App\CentralLogics\Helpers::time_format($campaign?->start_time).' - '.  \App\CentralLogics\Helpers::time_format($campaign?->end_time): translate('messages.N/A')}}</span>
                                </td>
                                <td data-order="{{ $ends }}">
                                    @if($ends)
                                        <span class="table-when{{ $days_left < 0 ? ' table-when--stale' : '' }}">
                                            <span class="table-when__day">{{\App\CentralLogics\Helpers::date_format($ends)}}</span>
                                            <span class="table-when__ago">
                                                @if($days_left > 1)
                                                    {{ translate('Ends') }} {{ $ends->copy()->startOfDay()->diffForHumans(\Carbon\Carbon::now()->startOfDay(), \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW) }}
                                                @elseif($days_left === 1)
                                                    {{ translate('Ends tomorrow') }}
                                                @elseif($days_left === 0)
                                                    {{ translate('Ends today') }}
                                                @elseif($days_left === -1)
                                                    {{ translate('Ended yesterday') }}
                                                @else
                                                    {{ translate('Ended') }} {{ $ends->copy()->startOfDay()->diffForHumans(\Carbon\Carbon::now()->startOfDay(), \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW) }}
                                                @endif
                                            </span>
                                        </span>
                                    @else
                                        <span class="text-muted font-size-sm">{{translate('messages.N/A')}}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="status-toggle" data-status="{{$campaign->status?1:0}}">
                                        <label class="toggle-switch toggle-switch-sm" for="stocksCheckbox{{$campaign->id}}">
                                            <input type="checkbox" data-url=""
                                            data-id="stocksCheckbox{{$campaign->id}}"
                                            data-type="status"
                                            data-image-on="{{ asset('/public/assets/admin/img/modal/basic_campaign_on.png') }}"
                                            data-image-off="{{ asset('/public/assets/admin/img/modal/basic_campaign_off.png') }}"
                                            data-title-on="{{ translate('By Turning ON Campaign!') }}"
                                            data-title-off="{{ translate('By Turning OFF Campaign!') }}"
                                            data-text-on="<p>{{ translate('If you turn on this status, it will show on user website and app.') }}</p>"
                                            data-text-off="<p>{{ translate('If you turn off this status, it won\'t show on user website and app') }}</p>"
                                            class="toggle-switch-input dynamic-checkbox" id="stocksCheckbox{{$campaign->id}}" {{$campaign->status?'checked':''}}>
                                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                        <span class="status-toggle__text" aria-live="polite">
                                            {{$campaign->status ? translate('messages.Active') : translate('messages.Inactive')}}
                                        </span>
                                    </div>
                                    @if($lifecycle)
                                        <span class="cell-chips d-block mt-1">
                                            <span class="cell-chip">{{ $lifecycle_labels[$lifecycle] }}</span>
                                        </span>
                                    @endif
                                </td>
                                <form action="{{route('admin.campaign.status',['basic',$campaign['id'],$campaign->status?0:1])}}"
                                method="get" id="stocksCheckbox{{$campaign->id}}_form">
                                </form>
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <a class="btn action-btn action-btn--view" href="{{route('admin.campaign.view',['basic',$campaign->id])}}" title="{{translate('messages.View')}}">
                                            <i class="tio-visible-outlined"></i>
                                        </a>
                                        <a class="btn action-btn action-btn--edit"
                                            href="{{route('admin.campaign.edit',['basic',$campaign['id']])}}" title="{{translate('messages.Edit campaign')}}"><i class="tio-edit"></i>
                                        </a>
                                        <a class="btn action-btn action-btn--delete" data-toggle="modal"
                                                data-target="#confirmation-deletes-{{$campaign['id']}}" data-id="campaign-{{$campaign['id']}}"
                                                data-message="{{translate('Want to delete this item?')}}"
                                                title="{{translate('messages.Delete campaign')}}"><i class="tio-delete-outlined"></i>
                                        </a>

                                        <div class="modal shedule-modal fade" id="confirmation-deletes-{{$campaign['id']}}" tabindex="-1" aria-labelledby="exampleModalLabel"
                                                    aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                         <form action="{{route('admin.campaign.delete',[$campaign['id']])}}"  method="post" id="campaign-{{$campaign['id']}}">
                                                            @csrf @method('delete')
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
                                                                        <img src="{{asset('public/assets/admin/img/delete.png')}}" alt="icon" class="mb-20">
                                                                        <h3 class="mb-2 fs-18">{{ translate('Want to delete this campaign?') }}</h3>
                                                                        @if ( $campaign->stores_count > 0)
                                                                        <p class="mb-2 px-3 text-wrap">{{ translate('This campaign is already running, and') }}  {{ $campaign->stores_count }} {{ translate('of your stores have joined. If you delete it, those stores will be removed from the campaign.') }}</p>
                                                                        @else
                                                                        <p class="mb-2 px-3 text-wrap">{{ translate('Please confirm before deleting this campaign. This will permanently remove this from the campaign list.') }}</p>
                                                                        @endif

                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer justify-content-center border-0 pt-0 mb-1 gap-2">
                                                                    <button type="submit" class="btn min-w-120px btn-danger min-h-45px"><i class="tio-delete-outlined"></i> {{ translate('Yes, delete') }}</button>
                                                                    <button type="button" class="btn min-w-120px btn--reset min-h-45px" data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{ translate('messages.Cancel') }}</button>
                                                                </div>
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
            </div>
                @if(count($campaigns) !== 0)
                <hr>
                @endif
                <div class="page-area">
                    {!! $campaigns->links() !!}
                </div>
                @if(count($campaigns) === 0)
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

@endpush
