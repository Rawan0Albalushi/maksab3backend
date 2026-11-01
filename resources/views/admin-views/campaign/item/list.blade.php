@extends('layouts.admin.app')

@section('title',translate('Campaign list'))


@section('content')
    @php
        $module_type = Config::get('module.current_module_type');
        $has_stock = (bool) config('module.'.$module_type.'.stock');
        $lifecycle_labels = [
            'running' => translate('messages.Running'),
            'scheduled' => translate('messages.Scheduled'),
            'ended' => translate('messages.Ended'),
        ];
    @endphp
    <div class="content container-fluid">
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h1 class="page-header-title">
                    <span class="page-header-icon">
                        <img src="{{asset('public/assets/admin/img/outline/campaign.svg')}}" class="w--26" alt="">
                    </span>
                    <span>
                        {{translate('Campaign')}}
                        <span class="badge badge-soft-dark ml-2" id="itemCount">{{ $campaigns->total() }}</span>
                    </span>
                </h1>
                <p class="page-header-desc">{{ translate('Every item campaign you have set up, with the dates, price and state of each.') }}</p>
            </div>
            <div class="page-header-actions">
                <a class="btn btn--primary" href="{{route('admin.campaign.add-new', 'item')}}">
                    <i class="tio-add-circle"></i> {{translate('messages.Add new campaign')}}
                </a>
            </div>
        </div>
        <div class="card">
            <div class="card-header border-0 py-2">
                <div class="search--button-wrapper">
                    @include('partials._table-head', [
                        'subtitle' => translate('messages.Campaigns that promote selected items for a limited period.'),
                    ])

                    <div class="min--200">
                        <select name="store_id" data-url="{{ url()->full() }}" data-filter="store_id"
                            data-placeholder="{{ config('module.current_module_type') === 'service' ? translate('Select provider') : translate('Select store') }}"
                            data-search-placeholder="{{ config('module.current_module_type') === 'service' ? translate('messages.Search provider') : translate('messages.Search store') }}"
                            class="js-data-example-ajax form-control set-filter">
                            @if (isset($store))
                                <option value="{{ $store->id }}" data-verified="{{ (int) $store->verified_seller }}" selected>{{ $store->name }}</option>
                            @else
                                <option value="all" selected>{{ config('module.current_module_type') === 'service' ? translate('All providers') : translate('All stores') }}</option>
                            @endif
                        </select>
                    </div>
                    <form class="search-form min--270">

                        <div class="input-group input--group">
                            <input id="datatableSearch" type="search" value="{{ request()?->search ?? null }}" name="search" class="form-control" placeholder="{{ translate('messages.Ex') . ': ' . translate('Enter campaign title') }}" aria-label="{{translate('Search')}}">
                            <button type="submit" class="btn btn--secondary">
                                <i class="tio-search"></i>
                            </button>
                        </div>
                    </form>




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
                                {{ route('admin.campaign.item_campaign_export', ['type' => 'excel', request()->getQueryString()]) }}
                                ">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                    alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item" href="
                            {{ route('admin.campaign.item_campaign_export', ['type' => 'csv', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                    alt="Image Description">
                                CSV
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
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
                            <th class="border-0" >{{translate('Item information')}}</th>
                            <th class="border-0" >{{ config('module.current_module_type') === 'service' ? translate('messages.Provider') : translate('messages.Store') }}</th>
                            <th class="border-0" >{{translate('messages.Schedule')}}</th>
                            <th class="border-0" >{{translate('Ends on')}}</th>
                            <th class="border-0 col--numeric" >{{translate('messages.price')}}</th>
                            @if($has_stock)
                                <th class="border-0 col--numeric" >{{translate('messages.stock')}}</th>
                            @endif
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
                            @php($discount_amount = $campaign->discount > 0 ? ($campaign->discount_type == 'percent' ? ($campaign->price * $campaign->discount / 100) : $campaign->discount) : 0)
                            @php($final_price = max($campaign->price - $discount_amount, 0))
                            <tr>
                                <td>
                                    <a href="{{route('admin.campaign.view',['item',$campaign->id])}}" class="table-rest-info" title="{{ $campaign['title'] }}">
                                        <img class="img--60 rounded onerror-image" data-onerror-image="{{asset('public/assets/admin/img/160x160/img2.jpg')}}"
                                                src="{{ $campaign->image_full_url ?? asset('public/assets/admin/img/160x160/img2.jpg') }}" alt="{{ $campaign['title'] }}">
                                        <div class="info max-w-200px">
                                            <div class="text--title line--limit-2">{{Str::limit($campaign['title'],25,'...')}}</div>
                                            @if($campaign->category)
                                                <span class="d-block fs-12 text-muted font-weight-normal">{{Str::limit($campaign->category->name, 20, '...')}}</span>
                                            @endif
                                            <div class="font-light">ID:{{$campaign->id}}</div>
                                        </div>
                                    </a>
                                </td>
                                <td>
                                    @if($campaign->store)
                                        <a class="d-block text-body" href="{{route('admin.store.view', $campaign->store->id)}}" title="{{ $campaign->store->name }}">
                                            {{Str::limit($campaign->store->name, 20, '...')}}
                                        </a>
                                    @else
                                        <span class="text-muted font-size-sm">{{translate('messages.Store deleted')}}</span>
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
                                <td class="col--numeric" data-order="{{ $final_price }}">
                                    <span class="d-block text-title font-semibold">{{\App\CentralLogics\Helpers::format_currency($final_price)}}</span>
                                    @if($discount_amount > 0)
                                        <span class="d-block fs-12 text-muted">
                                            <del>{{\App\CentralLogics\Helpers::format_currency($campaign->price)}}</del>
                                            -{{ $campaign->discount_type == 'percent' ? rtrim(rtrim(number_format($campaign->discount, 2, '.', ''), '0'), '.').'%' : \App\CentralLogics\Helpers::format_currency($discount_amount) }}
                                        </span>
                                    @endif
                                </td>
                                @if($has_stock)
                                    <td class="col--numeric" data-order="{{ $campaign->stock }}">
                                        <span class="badge badge-soft-{{ $campaign->stock > 0 ? 'success' : 'danger' }}">
                                            {{ $campaign->stock > 0 ? $campaign->stock : translate('Out of stock') }}
                                        </span>
                                    </td>
                                @endif
                                <td>
                                    <div class="status-toggle" data-status="{{$campaign->status?1:0}}">
                                        <label class="toggle-switch toggle-switch-sm" for="campaignCheckbox{{$campaign->id}}">
                                            <input type="checkbox"  class="toggle-switch-input  dynamic-checkbox"
                                            data-id="campaignCheckbox{{$campaign->id}}"
                                            data-type="status"
                                            data-image-on="{{ asset('/public/assets/admin/img/modal/basic_campaign_on.png') }}"
                                            data-image-off="{{ asset('/public/assets/admin/img/modal/basic_campaign_off.png') }}"
                                            data-title-on="{{ translate('By Turning ON Campaign!') }}"
                                            data-title-off="{{ translate('By Turning OFF Campaign!') }}"
                                            data-text-on="<p>{{ translate('Turned on to customer website and apps. Are you sure you want to turn on the campaign already inactive.') }}</p>"
                                            data-text-off="<p>{{ translate('Turned off to customer website and apps. Are you sure you want to turn off the campaign already active.') }}</p>"
                                            id="campaignCheckbox{{$campaign->id}}" {{$campaign->status?'checked':''}}>
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

                                <form action="{{route('admin.campaign.status',['item',$campaign['id'],$campaign->status?0:1])}}"
                                    method="get" id="campaignCheckbox{{$campaign->id}}_form">
                                    </form>
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <a class="btn action-btn action-btn--view" href="{{route('admin.campaign.view',['item',$campaign->id])}}" title="{{translate('messages.View')}}">
                                            <i class="tio-visible-outlined"></i>
                                        </a>
                                        <a class="btn action-btn action-btn--edit"
                                            href="{{route('admin.campaign.edit',['item',$campaign['id']])}}" title="{{translate('messages.Edit campaign')}}"><i class="tio-edit"></i>
                                        </a>
                                        <a class="btn action-btn action-btn--delete form-alert" href="javascript:"
                                            data-id="campaign-{{$campaign['id']}}" data-message="{{ config('module.current_module_type') === 'service' ? translate('Want to delete this service?') : translate('Want to delete this item?') }}" title="{{translate('messages.Delete campaign')}}"><i class="tio-delete-outlined"></i>
                                        </a>
                                        <form action="{{route('admin.campaign.delete-item',[$campaign['id']])}}"
                                                    method="post" id="campaign-{{$campaign['id']}}">
                                            @csrf @method('delete')
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
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
    </div>

@endsection

@push('script_2')
    <script>
           $(document).on('ready', function() {
            $('.js-data-example-ajax').select2({
                ajax: {
                    url: '{{ route('admin.store.get-stores') }}',
                    data: function(params) {
                        return {
                            q: params.term,
                            all:true,
                            @if (isset($zone))
                                zone_ids: [{{ $zone->id }}],
                            @endif
                            @if (request('module_id') || Config::get('module.current_module_id'))
                                module_id: {{ request('module_id') ?? Config::get('module.current_module_id') }},
                            @endif
                            page: params.page
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data
                        };
                    },
                    __port: function(params, success, failure) {
                        let $request = $.ajax(params);

                        $request.then(success);
                        $request.fail(failure);

                        return $request;
                    }
                }
            });
        });
    </script>

@endpush
