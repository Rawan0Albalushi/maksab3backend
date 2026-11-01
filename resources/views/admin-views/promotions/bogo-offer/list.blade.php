@extends('layouts.admin.app')

@section('title',translate('BOGO offer'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/admin-shared.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/admin-shared.css')) }}">
@endpush

@section('content')
    @php($lifecycle_labels = [
        'running' => translate('messages.Running'),
        'scheduled' => translate('messages.Scheduled'),
        'ended' => translate('messages.Ended'),
    ])
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <i class="tio-gift"></i>
                <span>{{translate('BOGO offer')}}</span>
            </h1>
            <p class="page-header-desc">{{ translate('Every buy-one-get-one offer, with the dates and state of each.') }}</p>
        </div>

        <div class="card">
            @if($offers->total() === 0 && ! request()->filled('search'))
                {{-- The shared `empty-state` block, not hand-rolled utilities. This screen used to
                     size its own icon (145px against the design's 60), use an <h4> where the design
                     asks for 16px/600, and hold the card open with a 440px min-height. The result
                     was a promotion screen that looked nothing like the delivery, area and zone
                     screens beside it. The rules live in admin-shared.css, pushed above. --}}
                <div class="card-body empty-state">
                    <img class="empty-state__icon" src="{{ asset('public/assets/admin/img/empty.png') }}"
                         alt="{{ translate('No BOGO offers yet') }}">
                    <h5 class="empty-state__title">{{ translate('No BOGO offers yet') }}</h5>
                    {{-- The sentence itself is the key, which is this codebase's convention (see the ETA and
                         delivery-rule screens). A key-like `bogo empty state description` was not copy: it
                         self-learned into messages.php as its own humanised name and the page printed
                         "Bogo empty state description" at the customer. --}}
                    <p class="empty-state__text">
                        {{ translate('You haven\'t created any buy one get one offers yet. Start by adding a BOGO deal to boost sales and delight your customers.') }}
                    </p>
                    <a href="{{ route('admin.bogo-offer.add-new') }}" class="btn btn--primary">
                        <i class="tio-add-circle-outlined"></i> {{ translate('Add BOGO offer') }}
                    </a>
                </div>
            @else
                <div class="card-header border-0 py-2 search--button-wrapper">
                    <h5 class="card-title">
                        {{translate('BOGO offer list')}}
                        <span class="badge badge-soft-dark ml-2">{{$offers->total()}}</span>
                    </h5>
                    <form id="search-form" action="{{ url()->current() }}" method="GET">
                        <div class="input--group input-group input-group-merge input-group-flush">
                            <input id="datatableSearch_" type="search" name="search" value="{{ request('search') }}"
                                   class="form-control" placeholder="{{translate('Search')}}" aria-label="Search">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                    <div class="hs-unfold mr-2">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle btn export-btn btn-outline-primary btn--primary font--sm"
                           href="javascript:;"
                           data-hs-unfold-options='{"target": "#bogoExportDropdown", "type": "css-animation"}'>
                            <i class="tio-download-to mr-1"></i> {{ translate('Export') }}
                        </a>
                        <div id="bogoExportDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                            <span class="dropdown-header">{{ translate('Download options') }}</span>
                            <a target="__blank" id="export-excel" class="dropdown-item"
                               href="{{ route('admin.bogo-offer.export', ['type' => 'excel', 'search' => request('search')]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                     src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="excel">
                                Excel
                            </a>
                            <a target="__blank" id="export-csv" class="dropdown-item"
                               href="{{ route('admin.bogo-offer.export', ['type' => 'csv', 'search' => request('search')]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                     src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg" alt="csv">
                                CSV
                            </a>
                        </div>
                    </div>

                    <a href="{{route('admin.bogo-offer.add-new')}}" class="btn btn--primary">
                        <i class="tio-add-circle"></i> {{translate('Add BOGO offer')}}
                    </a>
                </div>

                <div class="table-responsive datatable-custom">
                    <table class="font-size-sm table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                        <tr>
                            <th>{{ translate('Offer name') }}</th>
                            <th>{{ translate('Item quantity') }}</th>
                            <th class="col--numeric">{{ translate('messages.Stores') }}</th>
                            <th class="col--numeric">{{ translate('messages.Total uses') }}</th>
                            <th>{{ translate('messages.Duration') }}</th>
                            <th>{{ translate('Ends on') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th class="text-center">{{ translate('Action') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($offers as $offer)
                            @php($ends = $offer->end_date)
                            @php($days_left = $ends ? (int) \Carbon\Carbon::now()->startOfDay()->diffInDays($ends->copy()->startOfDay(), false) : null)
                            @php($not_started = $offer->start_date && $offer->start_date->copy()->startOfDay()->gt(\Carbon\Carbon::now()->startOfDay()))
                            @php($lifecycle = is_null($days_left) ? null : ($days_left < 0 ? 'ended' : ($not_started ? 'scheduled' : 'running')))
                            @php($pending_count = max($offer->enrollments_count - $offer->approved_count, 0))
                            <tr>
                                <td>
                                    <a href="{{route('admin.bogo-offer.view',$offer->id)}}" class="table-rest-info" title="{{ $offer->title }}">
                                        <img class="img--60 rounded onerror-image" data-onerror-image="{{asset('public/assets/admin/img/160x160/img2.jpg')}}"
                                             src="{{ $offer->image_full_url ?? asset('public/assets/admin/img/160x160/img2.jpg') }}" alt="{{ $offer->title }}">
                                        <div class="info max-w-200px">
                                            <div class="text--title line--limit-2">{{Str::limit($offer->title, 25, '...')}}</div>
                                            <div class="font-light">ID:{{$offer->id}}</div>
                                        </div>
                                    </a>
                                </td>
                                <td>
                                    {{ translate('Buy') }}: {{ $offer->buy_qty }} · {{ translate('Get') }}: {{ $offer->get_qty }}
                                </td>
                                <td class="col--numeric" data-order="{{ $offer->enrollments_count }}">
                                    <span class="d-block text-title font-semibold">{{$offer->enrollments_count}}</span>
                                    @if($offer->enrollments_count)
                                        <span class="d-block fs-12 text-muted">
                                            {{ translate('Approved') }}: {{ $offer->approved_count }}@if($pending_count) · {{ translate('Pending') }}: {{ $pending_count }}@endif
                                        </span>
                                    @else
                                        <span class="d-block fs-12 text-muted">{{ translate('No store joined yet') }}</span>
                                    @endif
                                </td>
                                <td class="col--numeric" data-order="{{ $offer->total_uses }}">
                                    <span class="d-block text-title font-semibold">{{ $offer->total_uses }}</span>
                                    <span class="d-block fs-12 text-muted">
                                        {{ $offer->usage_limit_total ? translate('Usage limit') . ': ' . $offer->usage_limit_total : translate('Unlimited') }}@if($offer->usage_limit_per_customer) · {{ translate('Usage limit per customer') }}: {{ $offer->usage_limit_per_customer }}@endif
                                    </span>
                                </td>
                                <td>
                                    <span class="d-block text-title">{{$offer->start_date ? \App\CentralLogics\Helpers::date_format($offer->start_date).' - '.\App\CentralLogics\Helpers::date_format($offer->end_date) : translate('messages.N/A')}}</span>
                                    <span class="d-block fs-12 text-muted text-uppercase">{{$offer->start_date ? \App\CentralLogics\Helpers::time_format($offer->start_date).' - '.\App\CentralLogics\Helpers::time_format($offer->end_date) : translate('messages.N/A')}}</span>
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
                                    <div class="status-toggle" data-status="{{$offer->status ? 1 : 0}}">
                                        <label class="toggle-switch toggle-switch-sm" for="bogoStatus{{$offer->id}}">
                                            <input type="checkbox" class="toggle-switch-input status_change_alert"
                                                   data-url="{{route('admin.bogo-offer.status',[$offer->id, $offer->status ? 0 : 1])}}"
                                                   data-message="{{ $offer->status
                                                        ? translate('Want to turn off this BOGO offer?')
                                                        : translate('Want to turn on this BOGO offer?') }}"
                                                   id="bogoStatus{{$offer->id}}" {{$offer->status ? 'checked' : ''}}>
                                            <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                        </label>
                                        <span class="status-toggle__text" aria-live="polite">
                                            {{$offer->status ? translate('messages.Active') : translate('messages.Inactive')}}
                                        </span>
                                    </div>
                                    @if($lifecycle)
                                        <span class="cell-chips d-block mt-1">
                                            <span class="cell-chip">{{ $lifecycle_labels[$lifecycle] }}</span>
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <a class="btn btn-sm action-btn action-btn--view"
                                           href="{{route('admin.bogo-offer.view',$offer->id)}}" title="{{translate('View')}}">
                                            <i class="tio-visible-outlined"></i>
                                        </a>
                                        <a class="btn btn-sm action-btn action-btn--edit"
                                           href="{{route('admin.bogo-offer.edit',$offer->id)}}" title="{{translate('Edit')}}">
                                            <i class="tio-edit"></i>
                                        </a>
                                        <a class="btn btn-sm action-btn action-btn--delete form-alert" href="javascript:"
                                           data-id="bogo-offer-{{$offer->id}}"
                                           data-message="{{ $offer->enrollments_count > 0
                                                ? $offer->enrollments_count.' '.translate('messages.stores have joined this offer Deleting it removes the offer from all of them')
                                                    .' '.translate('messages.Any items customers added to their cart from this offer will be removed too')
                                                    .' '.translate('Want to delete this BOGO offer?')
                                                : translate('Want to delete this BOGO offer?') }}"
                                           title="{{translate('Delete')}}">
                                            <i class="tio-delete-outlined"></i>
                                        </a>
                                        <form action="{{route('admin.bogo-offer.delete',$offer->id)}}" method="post" id="bogo-offer-{{$offer->id}}">
                                            @csrf @method('delete')
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">{{ translate('No data found') }}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>

                </div>
                <div class="page-area px-4 pb-3">
                    <div class="d-flex align-items-center justify-content-end">
                        <div>{!! $offers->links() !!}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
