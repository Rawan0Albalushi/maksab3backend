@extends('layouts.vendor.app')

@section('title',translate('Campaign list'))

@push('css_or_js')

@endpush

@section('content')
    @php($join_status_labels = [
        'pending' => translate('messages.Not approved'),
        'confirmed' => translate('messages.confirmed'),
        'rejected' => translate('messages.rejected'),
    ])
    @php($join_status_badges = [
        'pending' => 'badge-soft-info',
        'confirmed' => 'badge-soft-success',
        'rejected' => 'badge-soft-danger',
    ])
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/outline/campaign.svg')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('Basic campaign list')}}<span class="badge badge-soft-dark ml-2" id="itemCount">{{$campaigns->total()}}</span>
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Store campaigns you can join, and the ones you are already in.') }}</p>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header py-2 border-0">
                        <div class="search--button-wrapper justify-content-end">
                            @include('partials._table-head', [
                                'subtitle' => translate('Campaigns running now for your module. Join one to promote your store.'),
                            ])
                            <form class="search-form">
                                <div class="input-group input--group">
                                    <input id="datatableSearch_" value="{{request()?->search ?? ''}}" type="search" name="search"
                                           class="form-control min-h-40px"
                                           placeholder="{{translate('messages.Ex') . ' : ' . translate('messages.search title')}}"
                                           aria-label="{{translate('messages.Search')}}">
                                    <button type="submit" class="btn btn--secondary py-2 min-h-40px"><i class="tio-search"></i></button>
                                </div>
                            </form>
                            @if(request()->input('search'))
                                <a class="btn btn--primary ml-2 min-h-40px" href="{{route('vendor.campaign.list')}}">
                                    <i class="tio-refresh"></i> {{translate('messages.Reset')}}
                                </a>
                            @endif
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive datatable-custom">
                            <table id="columnSearchDatatable"
                                    class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table table--wrap-head"
                                    data-hs-datatables-options='{
                                        "order": [],
                                        "orderCellsTop": true,
                                        "paging":false
                                    }'>
                                <thead class="thead-light">
                                    <tr>
                                        <th class="border-0">{{translate('Campaign')}}</th>
                                        <th class="border-0 col--numeric">{{ $store_data?->module_type === 'service' ? translate('messages.Providers') : translate('messages.Stores') }}</th>
                                        <th class="border-0">{{translate('messages.Schedule')}}</th>
                                        <th class="border-0">{{translate('Ends on')}}</th>
                                        <th class="border-0">{{translate('messages.Status')}}</th>
                                        <th class="border-0 text-center">{{translate('messages.Action')}}</th>
                                    </tr>
                                </thead>

                                <tbody id="set-rows">
                                @foreach($campaigns as $campaign)
                                    @php($joined = $campaign->stores->first())
                                    @php($join_status = $joined?->pivot?->campaign_status)
                                    @php($ends = $campaign->end_date)
                                    @php($days_left = $ends ? (int) \Carbon\Carbon::now()->startOfDay()->diffInDays($ends->copy()->startOfDay(), false) : null)
                                    <tr>
                                        <td>
                                            <div class="table-rest-info">
                                                <img class="img--60 rounded onerror-image" data-onerror-image="{{asset('public/assets/admin/img/160x160/img2.jpg')}}"
                                                     src="{{ $campaign->image_full_url ?? asset('public/assets/admin/img/160x160/img2.jpg') }}" alt="{{ $campaign['title'] }}">
                                                <div class="info max-w-200px">
                                                    <div class="text--title line--limit-2" title="{{ $campaign['title'] }}">{{Str::limit($campaign['title'],30,'...')}}</div>
                                                    <div class="font-light">ID:{{$campaign->id}}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="col--numeric" data-order="{{ $campaign->joined_stores_count ?? 0 }}">
                                            @if($campaign->joined_stores_count)
                                                <span class="text-title font-semibold">{{ $campaign->joined_stores_count }}</span>
                                            @else
                                                <span class="text-muted">0</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="d-block text-title">{{$campaign->start_date? \App\CentralLogics\Helpers::date_format($campaign->start_date).' - '.\App\CentralLogics\Helpers::date_format($campaign->end_date): translate('messages.N/A')}}</span>
                                            <span class="d-block fs-12 text-muted text-uppercase">{{$campaign->start_time? \App\CentralLogics\Helpers::time_format($campaign->start_time).' - '.\App\CentralLogics\Helpers::time_format($campaign->end_time): translate('messages.N/A')}}</span>
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
                                            @if(isset($join_status_labels[$join_status]))
                                                <span class="badge {{ $join_status_badges[$join_status] }}">{{ $join_status_labels[$join_status] }}</span>
                                            @else
                                                <span class="text-muted font-size-sm">{{ translate('Not joined') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($join_status === 'rejected')
                                                <span class="text-muted font-size-sm">{{ translate('No action available') }}</span>
                                            @elseif($joined)
                                                <button type="button" class="btn btn-sm btn--danger form-alert"
                                                        data-id="campaign-{{$campaign['id']}}"
                                                        data-message="{{translate('messages.Alert store out from campaign')}}"
                                                        title="{{translate('You are already joined. Click to leave this campaign.')}}">{{translate('Leave')}}</button>
                                                <form action="{{route('vendor.campaign.remove-store',[$campaign['id'],$store_id])}}"
                                                      method="GET" id="campaign-{{$campaign['id']}}">
                                                    @csrf
                                                </form>
                                            @else
                                                <button type="button" class="btn btn-sm btn--primary form-alert"
                                                        data-id="campaign-{{$campaign['id']}}"
                                                        data-message="{{translate('messages.Alert store join campaign')}}"
                                                        title="{{translate('Click to join this campaign')}}">{{translate('Join')}}</button>
                                                <form action="{{route('vendor.campaign.add-store',[$campaign['id'],$store_id])}}"
                                                      method="GET" id="campaign-{{$campaign['id']}}">
                                                    @csrf
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if(count($campaigns) === 0)
                        <div class="empty--data">
                            <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="">
                            @if(request('search'))
                                <h5>{{ translate('No campaign matches your search.') }}</h5>
                                <p class="text-muted font-size-sm">{{ translate('Try a different spelling, or clear the search to see every campaign.') }}</p>
                            @else
                                <h5>{{ translate('No campaign is running right now.') }}</h5>
                                <p class="text-muted font-size-sm">{{ translate('Campaigns set up by the admin appear here while they are running.') }}</p>
                            @endif
                        </div>
                    @endif
                    <div class="card-footer page-area">
                        {!! $campaigns->links() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
