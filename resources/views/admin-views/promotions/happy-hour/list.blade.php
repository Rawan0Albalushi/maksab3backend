@extends('layouts.admin.app')

@section('title',translate('Happy hour'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/admin-shared.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/admin-shared.css')) }}">
@endpush

@section('content')
    @php($duration_labels = [
        'daily' => translate('Daily'),
        'weekly' => translate('Weekly'),
        'custom' => translate('messages.Custom'),
    ])
    @php($lifecycle_labels = [
        'running_now' => translate('messages.Running now'),
        'scheduled' => translate('messages.Scheduled'),
        'ended' => translate('messages.Ended'),
    ])
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <i class="tio-time"></i>
                <span>{{translate('Happy hour')}}</span>
            </h1>
            <p class="page-header-desc">{{ translate('Every happy hour offer, with the hours and discount of each.') }}</p>
        </div>

        <div class="card">
            @if($happyHours->total() === 0 && ! request()->filled('search'))
                {{-- The shared `empty-state` block, not hand-rolled utilities. This screen used to
                     size its own icon (145px against the design's 60), use an <h4> where the design
                     asks for 16px/600, and hold the card open with a 440px min-height. The result
                     was a promotion screen that looked nothing like the delivery, area and zone
                     screens beside it. The rules live in admin-shared.css, pushed above. --}}
                <div class="card-body empty-state">
                    <img class="empty-state__icon" src="{{ asset('public/assets/admin/img/empty.png') }}"
                         alt="{{ translate('No happy hours yet') }}">
                    <h5 class="empty-state__title">{{ translate('No happy hours yet') }}</h5>
                    {{-- The sentence itself is the key, which is this codebase's convention (see the ETA and
                         delivery-rule screens). A key-like `bogo empty state description` was not copy: it
                         self-learned into messages.php as its own humanised name and the page printed
                         "Bogo empty state description" at the customer. --}}
                    <p class="empty-state__text">
                        {{ translate('You haven\'t created any happy hours yet. Start by adding one to drive orders during your quieter hours.') }}
                    </p>
                    <a href="{{ route('admin.happy-hour.add-new') }}" class="btn btn--primary">
                        <i class="tio-add-circle-outlined"></i> {{ translate('Add happy hours') }}
                    </a>
                </div>
            @else
                <div class="card-header border-0 py-2 search--button-wrapper">
                    <h5 class="card-title">
                        {{translate('Happy hour list')}}
                        <span class="badge badge-soft-dark ml-2">{{$happyHours->total()}}</span>
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
                           data-hs-unfold-options='{"target": "#happyHourExportDropdown", "type": "css-animation"}'>
                            <i class="tio-download-to mr-1"></i> {{ translate('Export') }}
                        </a>
                        <div id="happyHourExportDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                            <span class="dropdown-header">{{ translate('Download options') }}</span>
                            <a target="__blank" id="export-excel" class="dropdown-item"
                               href="{{ route('admin.happy-hour.export', ['type' => 'excel', 'search' => request('search')]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                     src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="excel">
                                Excel
                            </a>
                            <a target="__blank" id="export-csv" class="dropdown-item"
                               href="{{ route('admin.happy-hour.export', ['type' => 'csv', 'search' => request('search')]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                     src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg" alt="csv">
                                CSV
                            </a>
                        </div>
                    </div>

                    <a href="{{route('admin.happy-hour.add-new')}}" class="btn btn--primary">
                        <i class="tio-add-circle"></i> {{translate('Create happy hour')}}
                    </a>
                </div>

                <div class="table-responsive datatable-custom">
                    <table class="font-size-sm table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                        <tr>
                            <th>{{ translate('Title') }}</th>
                            <th class="col--numeric">{{ translate('messages.Discount') }}</th>
                            <th>{{ translate('messages.Schedule') }}</th>
                            <th>{{ translate('Runs until') }}</th>
                            <th class="col--numeric">{{ translate('messages.Stores') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th class="text-center">{{ translate('Action') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($happyHours as $happyHour)
                            @php($running = $happyHour->isRunningNow())
                            @php($locked = $running && $happyHour->status)
                            @php($ends = $happyHour->is_permanent ? null : ($happyHour->end_date ?: ($happyHour->last_applicable_date ? \Carbon\Carbon::parse($happyHour->last_applicable_date) : null)))
                            @php($days_left = $ends ? (int) \Carbon\Carbon::now()->startOfDay()->diffInDays($ends->copy()->startOfDay(), false) : null)
                            @php($not_started = $happyHour->start_date && $happyHour->start_date->copy()->startOfDay()->gt(\Carbon\Carbon::now()->startOfDay()))
                            @php($lifecycle = $running ? 'running_now' : (is_null($days_left) ? ($not_started ? 'scheduled' : null) : ($days_left < 0 ? 'ended' : ($not_started ? 'scheduled' : null))))
                            @php($pending_count = max($happyHour->enrollments_count - $happyHour->approved_count, 0))
                            <tr>
                                <td>
                                    <a href="{{route('admin.happy-hour.view',$happyHour->id)}}" class="table-rest-info" title="{{ $happyHour->title }}">
                                        <img class="img--60 rounded onerror-image" data-onerror-image="{{asset('public/assets/admin/img/160x160/img2.jpg')}}"
                                             src="{{ $happyHour->cover_image_full_url ?? $happyHour->icon_full_url ?? asset('public/assets/admin/img/160x160/img2.jpg') }}" alt="{{ $happyHour->title }}">
                                        <div class="info max-w-200px">
                                            <div class="text--title line--limit-2">{{Str::limit($happyHour->title, 25, '...')}}</div>
                                            <div class="font-light">ID:{{$happyHour->id}}</div>
                                        </div>
                                    </a>
                                </td>
                                <td class="col--numeric" data-order="{{ $happyHour->discount }}">
                                    <span class="d-block text-title font-semibold">{{$happyHour->discount}}%</span>
                                    @if($happyHour->min_order_amount)
                                        <span class="d-block fs-12 text-muted">{{ translate('Minimum order amount') }}: {{ \App\CentralLogics\Helpers::format_currency($happyHour->min_order_amount) }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($happyHour->duration_type === 'custom')
                                        <span class="d-block text-title">{{ $duration_labels['custom'] }}</span>
                                        @if(count($happyHour->custom_days ?? []))
                                            <span class="d-block fs-12 text-muted">{{ \Carbon\CarbonInterval::days(count($happyHour->custom_days))->forHumans() }}</span>
                                        @endif
                                    @else
                                        <span class="d-block text-title text-uppercase">
                                            {{ $happyHour->start_time ? \App\CentralLogics\Helpers::time_format($happyHour->start_time) : '' }}
                                            - {{ $happyHour->end_time ? \App\CentralLogics\Helpers::time_format($happyHour->end_time) : '' }}
                                        </span>
                                        <span class="d-block fs-12 text-muted">
                                            {{ $duration_labels[$happyHour->duration_type] ?? $happyHour->duration_type }}@if($happyHour->duration_type === 'weekly' && count($happyHour->weekly_days ?? [])) · {{ implode(', ', array_map(fn($d) => substr($d, 0, 3), $happyHour->weekly_days)) }}@endif
                                        </span>
                                    @endif
                                </td>
                                <td data-order="{{ $ends }}">
                                    @if($happyHour->is_permanent)
                                        <span class="cell-chips"><span class="cell-chip">{{translate('messages.Permanent')}}</span></span>
                                    @elseif($ends)
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
                                <td class="col--numeric" data-order="{{ $happyHour->enrollments_count }}">
                                    <span class="d-block text-title font-semibold">{{$happyHour->enrollments_count}}</span>
                                    @if($happyHour->enrollments_count)
                                        <span class="d-block fs-12 text-muted">
                                            {{ translate('Approved') }}: {{ $happyHour->approved_count }}@if($pending_count) · {{ translate('Pending') }}: {{ $pending_count }}@endif
                                        </span>
                                    @else
                                        <span class="d-block fs-12 text-muted">{{ translate('No store joined yet') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="status-toggle" data-status="{{$happyHour->status ? 1 : 0}}">
                                        <label class="toggle-switch toggle-switch-sm mb-0 {{ $locked ? 'cursor-default' : '' }}"
                                               for="hhStatus{{$happyHour->id}}"
                                               @if($locked) data-toggle="tooltip" data-placement="left"
                                                    title="{{translate('An ongoing happy hour cannot be turned off')}}" @endif>
                                            <input type="checkbox" class="toggle-switch-input {{ $locked ? '' : 'status_change_alert' }}"
                                                   @if(! $locked)
                                                   data-url="{{route('admin.happy-hour.status',[$happyHour->id, $happyHour->status ? 0 : 1])}}"
                                                   data-message="{{ $happyHour->status
                                                        ? translate('Want to turn off this happy hour?')
                                                        : translate('Want to turn on this happy hour?') }}"
                                                   @else disabled @endif
                                                   id="hhStatus{{$happyHour->id}}" {{$happyHour->status ? 'checked' : ''}}>
                                            <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                        </label>
                                        <span class="status-toggle__text" aria-live="polite">
                                            {{$happyHour->status ? translate('messages.Active') : translate('messages.Inactive')}}
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
                                           href="{{route('admin.happy-hour.view',$happyHour->id)}}" title="{{translate('View')}}">
                                            <i class="tio-visible-outlined"></i>
                                        </a>

                                        @if($running)
                                            <span class="btn btn-sm action-btn action-btn--edit disabled"
                                                  data-toggle="tooltip" title="{{translate('An ongoing happy hour cannot be edited')}}"
                                                  style="opacity:.45;cursor:not-allowed">
                                                <i class="tio-edit"></i>
                                            </span>
                                        @else
                                            <a class="btn btn-sm action-btn action-btn--edit"
                                               href="{{route('admin.happy-hour.edit',$happyHour->id)}}" title="{{translate('Edit')}}">
                                                <i class="tio-edit"></i>
                                            </a>
                                        @endif

                                        @if($running)
                                            <span class="btn btn-sm action-btn action-btn--delete disabled"
                                                  data-toggle="tooltip" title="{{translate('An ongoing happy hour cannot be deleted')}}"
                                                  style="opacity:.45;cursor:not-allowed">
                                                <i class="tio-delete-outlined"></i>
                                            </span>
                                        @else
                                            <a class="btn btn-sm action-btn action-btn--delete form-alert" href="javascript:"
                                               data-id="happy-hour-{{$happyHour->id}}"
                                               data-message="{{translate('Want to delete this happy hour?')}}"
                                               title="{{translate('Delete')}}">
                                                <i class="tio-delete-outlined"></i>
                                            </a>
                                            <form action="{{route('admin.happy-hour.delete',$happyHour->id)}}" method="post" id="happy-hour-{{$happyHour->id}}">
                                                @csrf @method('delete')
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">{{ translate('No data found') }}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="page-area px-4 pb-3">
                    <div class="d-flex align-items-center justify-content-end">
                        <div>{!! $happyHours->links() !!}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
