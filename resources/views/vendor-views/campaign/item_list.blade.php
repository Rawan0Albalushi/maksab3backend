@extends('layouts.vendor.app')

@section('title',translate('Campaign list'))

@push('css_or_js')

@endpush

@section('content')
    @php($lifecycle_labels = [
        'running' => translate('messages.Running'),
        'scheduled' => translate('messages.Scheduled'),
        'ended' => translate('messages.Ended'),
    ])
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title"><i class="tio-notice"></i> {{ $module_item_label }} {{ translate('Campaign') }} <span class="badge badge-soft-dark ml-2" id="itemCount">{{$campaigns->total()}}</span></h1>
                    <p class="page-header-desc">{{ translate('Item campaigns you can join, and the ones your items are already in.') }}</p>
                </div>
            </div>
        </div>
        <div class="row gx-2 gx-lg-3">
            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
                <div class="card">
                    <div class="card-header py-2 border-0">
                        <h5 class="card-title"></h5>
                        <form id="search-form">
                            @csrf
                            <div class="input--group input-group input-group-merge input-group-flush">
                                <input id="datatableSearch" type="search" name="search" class="form-control" placeholder=" {{translate('messages.Search by title')}}" aria-label="{{translate('Search')}}">
                                <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                            </div>
                        </form>
                    </div>
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
                                <th >{{translate('Item information')}}</th>
                                <th >{{translate('messages.Schedule')}}</th>
                                <th >{{translate('Ends on')}}</th>
                                <th class="col--numeric">{{translate('messages.price')}}</th>
                                <th class="text-center">{{translate('messages.Status')}}</th>
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
                                        <span class="table-rest-info">
                                            <img class="img--60 rounded onerror-image" data-onerror-image="{{asset('public/assets/admin/img/160x160/img2.jpg')}}"
                                                 src="{{ $campaign->image_full_url ?? asset('public/assets/admin/img/160x160/img2.jpg') }}" alt="{{ $campaign['title'] }}">
                                            <span class="info max-w-200px">
                                                <span class="d-block text--title line--limit-2" title="{{ $campaign['title'] }}">{{Str::limit($campaign['title'],30,'...')}}</span>
                                                <span class="d-block font-light">ID:{{$campaign->id}}</span>
                                            </span>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="d-block text-title">{{$campaign->start_date? $campaign->start_date->format('d M, Y'). ' - ' .$campaign->end_date->format('d M, Y'): translate('messages.N/A')}}</span>
                                        <span class="d-block fs-12 text-muted text-uppercase">{{$campaign->start_time? $campaign->start_time->format(config('timeformat')). ' - ' .$campaign->end_time->format(config('timeformat')): translate('messages.N/A')}}</span>
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
                                    <td class="text-center">
                                        <span class="badge badge-soft-{{ $campaign->status ? 'success' : 'danger' }}">
                                            {{ $campaign->status ? translate('messages.Active') : translate('messages.Inactive') }}
                                        </span>
                                        @if($lifecycle)
                                            <span class="cell-chips d-block mt-1">
                                                <span class="cell-chip">{{ $lifecycle_labels[$lifecycle] }}</span>
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="page-area px-4 pb-3">
                        <div class="d-flex align-items-center justify-content-end">
                            <div>
                                {!! $campaigns->links() !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script>

        "use strict";
        $('#search-form').on('submit', function (event) {
            event.preventDefault();
            let formData = new FormData(this);
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: '{{route('vendor.campaign.searchItem')}}',
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    $('#set-rows').html(data.view);
                    $('#itemCount').html(data.count);
                    $('.page-area').hide();
                },
                complete: function () {
                    $('#loading').hide();
                },
            });
        });
    </script>
@endpush
