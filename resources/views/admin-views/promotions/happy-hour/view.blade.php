@extends('layouts.admin.app')

@section('title',translate('Happy hour details'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Multi-select picker. The theme's .multiple-select2 helper is deliberately not used
         here: it re-initialises select2 itself with tags:true and no dropdownParent, which
         inside a modal drops the list behind the backdrop and lets a typed name become a
         value. Styling the stock widget keeps both. --}}
    <style>
        #addStoreModal .select2-container--default .select2-selection--multiple {
            min-height: 45px;
            /* Roughly three rows, then it scrolls - a module with many stores must not push
               the footer off the modal. */
            max-height: 132px;
            overflow-y: auto;
            padding: 3px 6px;
            border: 1px solid var(--bs-border-color, #e9e9ea);
            border-radius: 5px;
        }

        #addStoreModal .select2-container--default .select2-selection--multiple .select2-selection__rendered {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px;
            padding: 0;
            margin: 0;
        }

        /* select2 puts its remove control first in the DOM, and the design wants it after
           the name. Reversing a flex row does that but leaves the name unable to shrink -
           the chip is a flex container and the label is an anonymous item, so it cannot be
           ellipsised - and a long store name gets sliced. Taking the control out of
           flow instead keeps the order and lets the chip clip cleanly. */
        #addStoreModal .select2-container--default .select2-selection--multiple .select2-selection__choice {
            position: relative;
            display: inline-block;
            max-width: 100%;
            margin: 0;
            padding: 3px 26px 3px 8px;
            border: 0;
            border-radius: 4px;
            background: var(--section-bg1, #F0F2F7);
            color: var(--title-clr);
            font-size: 13px;
            line-height: 1.6;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #addStoreModal .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            position: absolute;
            inset-inline-end: 7px;
            top: 50%;
            transform: translateY(-50%);
            margin: 0;
            color: var(--bs-body-color, #656566);
            font-size: 15px;
            line-height: 1;
        }

        #addStoreModal .select2-container--default .select2-selection--multiple .select2-search--inline {
            margin: 0;
        }

        #addStoreModal .select2-container--default .select2-selection--multiple .select2-search__field {
            margin: 0;
            height: 28px;
        }
    </style>
@endpush

@section('content')
    @php($running = $happyHour->isRunningNow())

    <div class="content container-fluid">
        <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h1 class="page-header-title mb-0">
                    <i class="tio-time"></i>
                    <span>{{translate('Happy hour')}} #{{$happyHour->id}}</span>
                </h1>
                <p class="page-header-desc">{{ translate('What this offer covers, the hours it runs and how often it has been used.') }}</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                {{-- Deleting a live window would take its enrolments with it and drop every
                     store in it back to full price mid session, so it is blocked while it runs -
                     the same rule the list follows, and the controller enforces it too. --}}
                @if($running)
                    <span class="btn btn-soft-danger d-flex align-items-center gap-1 disabled" data-toggle="tooltip"
                          title="{{translate('An ongoing happy hour cannot be deleted')}}"
                          style="cursor:not-allowed">
                        <i class="tio-delete"></i> {{translate('messages.Delete')}}
                    </span>
                @else
                    <a class="btn btn-soft-danger d-flex align-items-center gap-1 form-alert" href="javascript:"
                       data-id="happy-hour-{{$happyHour->id}}"
                       data-message="{{translate('Want to delete this happy hour?')}}">
                        <i class="tio-delete"></i> {{translate('messages.Delete')}}
                    </a>
                    <form action="{{route('admin.happy-hour.delete',$happyHour->id)}}" method="post" id="happy-hour-{{$happyHour->id}}">
                        @csrf @method('delete')
                    </form>
                @endif

                {{-- Status sits in its own bordered pill, per design. --}}
                <div class="d-flex align-items-center gap-2 border rounded bg-white px-3 py-2">
                    <span class="font-weight-bold">{{translate('messages.Status')}}</span>
                    @php($status_locked = $running && $happyHour->status)
                    <label class="toggle-switch toggle-switch-sm mb-0 {{ $status_locked ? 'cursor-default' : '' }}"
                           for="hhStatus{{$happyHour->id}}"
                           @if($status_locked) data-toggle="tooltip" data-placement="bottom"
                                title="{{translate('An ongoing happy hour cannot be turned off')}}" @endif>
                        <input type="checkbox" class="toggle-switch-input {{ $status_locked ? '' : 'status_change_alert' }}"
                               @if(! $status_locked)
                               data-url="{{route('admin.happy-hour.status',[$happyHour->id, $happyHour->status ? 0 : 1])}}"
                               data-message="{{ $happyHour->status
                                    ? translate('Want to turn off this happy hour?')
                                    : translate('Want to turn on this happy hour?') }}"
                               @else onclick="return false;" @endif
                               id="hhStatus{{$happyHour->id}}" {{$happyHour->status ? 'checked' : ''}}>
                        <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                    </label>
                </div>

{{-- A live window is locked in every direction now: it cannot be switched off, edited or deleted. --}}
                @if($running)
                    <span class="btn btn-soft-primary d-flex align-items-center gap-1 disabled" data-toggle="tooltip"
                          title="{{translate('An ongoing happy hour cannot be edited')}}"
                          style="cursor:not-allowed">
                        <i class="tio-edit"></i> {{translate('messages.Edit')}}
                    </span>
                @else
                    <a class="btn btn-soft-primary d-flex align-items-center gap-1"
                       href="{{route('admin.happy-hour.edit',$happyHour->id)}}">
                        <i class="tio-edit"></i> {{translate('messages.Edit')}}
                    </a>
                @endif
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row align-items-md-center">
                    <div class="col-md-3 mb-3 mb-md-0">
                        <img class="rounded w-100 onerror-image" data-onerror-image="{{asset('public/assets/admin/img/900x400/img1.jpg')}}"
                             src="{{ $happyHour->cover_image_full_url }}" alt="happy hour">
                    </div>
                    <div class="col-md-5">
                        <h4 class="mb-1">{{$happyHour->title}}</h4>
                        {{-- Folded past 140 characters, the same threshold and controls the BOGO
                             details page uses, so a long description cannot push the panel apart. --}}
                        @php($description = $happyHour->short_description ?? '')
                        <p class="mb-0 opacity-75">
                            @if(Str::length($description) > 140)
                                <span class="description-short">{{ Str::limit($description, 140, '...') }}</span>
                                <span class="description-full d-none">{{ $description }}</span>
                                <a href="javascript:" class="toggle-description">{{translate('See more')}}</a>
                            @else
                                {{ $description }}
                            @endif
                        </p>
                    </div>
                    <div class="col-md-4">
                        {{-- Plain PHP tags rather than a Blade php block: this file already
                             uses the inline directive form, and mixing the two makes Blade's
                             raw-block regex swallow everything between them. --}}
                        <?php
                            // label : value rows, per design.
                            $durationRows = [];

                            if ($happyHour->duration_type === 'custom') {
                                $durationRows[translate('Offer schedule')] = translate('messages.Custom');
                                $durationRows[translate('messages.days_selected')] = count($happyHour->custom_days ?? []);
                            } else {
                                $durationRows[translate('messages.Time')] =
                                    ($happyHour->start_time ? \App\CentralLogics\Helpers::time_format($happyHour->start_time) : '')
                                    .' - '.($happyHour->end_time ? \App\CentralLogics\Helpers::time_format($happyHour->end_time) : '');

                                if ($happyHour->duration_type === 'weekly') {
                                    $durationRows[translate('messages.Weekly')] = implode(', ', array_map(
                                        fn ($d) => substr($d, 0, 3),
                                        $happyHour->weekly_days ?? []
                                    ));
                                }

                                $durationRows[translate('messages.Date')] = $happyHour->is_permanent
                                    ? translate('messages.Permanent')
                                    : ($happyHour->start_date ? $happyHour->start_date->format('d M, Y') : '')
                                        .' - '.($happyHour->end_date ? $happyHour->end_date->format('d M, Y') : '');
                            }
                        ?>

                        <div class="bg-light rounded-8 p-3 h-100">
                            <h6 class="mb-3 d-flex align-items-center gap-2">
                                <i class="tio-calendar"></i> {{translate('Happy hour duration')}}
                            </h6>
                            @foreach($durationRows as $label => $value)
                                <div class="d-flex align-items-start mb-2">
                                    <span class="opacity-75 flex-shrink-0" style="width:88px">{{ $label }}</span>
                                    <span class="opacity-75 flex-shrink-0 px-2">:</span>
                                    <strong>{{ $value }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <hr>

                @php($active_days = $happyHour->duration_type === 'custom'
                    ? count($happyHour->custom_days ?? [])
                    : ($happyHour->is_permanent ? null : ($happyHour->start_date && $happyHour->end_date
                        ? $happyHour->start_date->diffInDays($happyHour->end_date) + 1
                        : null)))

                {{-- Left aligned within each column, per design - not centred. --}}
                <div class="row">
                    <div class="col-6 col-md-3 mb-2 mb-md-0">
                        <span class="d-block opacity-75 mb-1">{{translate('Created at')}}</span>
                        <strong class="fs-16">{{$happyHour->created_at->format('d M, Y h:i A')}}</strong>
                    </div>
                    <div class="col-6 col-md-3 mb-2 mb-md-0">
                        <span class="d-block opacity-75 mb-1">{{translate('Offer active')}}</span>
                        <strong class="fs-16">
                            {{ $active_days !== null ? $active_days.' '.translate('messages.Days') : translate('messages.Permanent') }}
                        </strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="d-block opacity-75 mb-1">{{translate('messages.Discount')}}(%)</span>
                        <strong class="fs-16">{{$happyHour->discount}}%</strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="d-block opacity-75 mb-1">{{translate('Min order amount')}}</span>
                        <strong class="fs-16">{{ $happyHour->min_order_amount ? \App\CentralLogics\Helpers::format_currency($happyHour->min_order_amount) : 'N/A' }}</strong>
                    </div>
                </div>

                @if($happyHour->duration_type === 'custom' && count($happyHour->custom_days ?? []))
                    <hr>
                    <h6>{{translate('messages.Selected Days List')}}</h6>
                    <div class="row">
                        @foreach($happyHour->custom_days as $i => $day)
                            <div class="col-md-3 mb-2">
                                <div class="border rounded p-2 d-flex justify-content-between">
                                    <strong>{{ \Carbon\Carbon::parse($day)->format('D, M d') }}</strong>
                                    <span>{{ \App\CentralLogics\Helpers::time_format($happyHour->custom_times[$i] ?? '') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header border-0 py-2 search--button-wrapper">
                <h5 class="card-title">
                    {{translate('messages.Store list')}}
                    <span class="badge badge-soft-dark ml-2">{{$enrollments->total()}}</span>
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
                       data-hs-unfold-options='{"target": "#happyHourStoreExportDropdown", "type": "css-animation"}'>
                        <i class="tio-download-to mr-1"></i> {{ translate('Export') }}
                    </a>
                    <div id="happyHourStoreExportDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                        <span class="dropdown-header">{{ translate('Download options') }}</span>
                        <a target="__blank" class="dropdown-item"
                           href="{{ route('admin.happy-hour.store-export', ['id' => $happyHour->id, 'type' => 'excel', 'search' => request('search')]) }}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                 src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="excel">
                            Excel
                        </a>
                        <a target="__blank" class="dropdown-item"
                           href="{{ route('admin.happy-hour.store-export', ['id' => $happyHour->id, 'type' => 'csv', 'search' => request('search')]) }}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                 src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg" alt="csv">
                            CSV
                        </a>
                    </div>
                </div>

                {{-- An expired happy hour cannot be enrolled into: the endpoint refuses it,
                     and a store added to one that is already over would never discount a
                     single order. The button says why rather than collecting an error.

                     Asked of the model, not of end_date: a CUSTOM schedule leaves both date
                     columns null and keeps its days in happy_hour_dates, so reading end_date
                     left every custom happy hour looking live for ever. A permanent one has
                     no end at all and never expires. --}}
                @php($expired = $happyHour->hasEnded())

                @if($expired)
                    <span data-toggle="tooltip" data-placement="top"
                          title="{{ translate('This happy hour has already ended') }}">
                        <button type="button" class="btn btn--primary" disabled>
                            <i class="tio-add-circle"></i> {{translate('messages.Add store')}}
                        </button>
                    </span>
                @else
                    <button type="button" class="btn btn--primary" data-toggle="modal" data-target="#addStoreModal">
                        <i class="tio-add-circle"></i> {{translate('messages.Add store')}}
                    </button>
                @endif
            </div>

            @if($enrollments->total() === 0)
                {{-- Same empty panel either way, but the copy has to say which it is: nobody
                     has joined, or the search simply matched nothing. --}}
                <div class="empty--data py-5">
                    <img src="{{asset('public/assets/admin/img/empty.png')}}" alt="empty">
                    <h5>
                        {{ request()->filled('search')
                            ? translate('No data found')
                            : translate('No stores yet') }}
                    </h5>
                    <p class="opacity-75">
                        {{ request()->filled('search')
                            ? translate('messages.No store matched your search')
                            : translate('messages.No stores have joined the happy hour yet') }}
                    </p>
                </div>
            @else
                <div class="table-responsive datatable-custom">
                    <table class="font-size-sm table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                        <tr>
                            <th>{{ translate('SL') }}</th>
                            <th>{{ translate('messages.Store') }}</th>
                            <th>{{ translate('Joining offer date') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th class="text-center">{{ translate('Action') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @include('admin-views.promotions.happy-hour.partials._store_table', ['enrollments' => $enrollments])
                        </tbody>
                    </table>
                    <div class="page-area px-4 pb-3">
                        <div class="d-flex align-items-center justify-content-end">
                            <div>{!! $enrollments->links() !!}</div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="addStoreModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h4 class="modal-title">{{translate('messages.Add store')}}</h4>
                    <button type="button" class="btn-close w-30px h-30px border rounded-circle d-center bg--secondary p-0"
                            data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="bg-light rounded-8 p-3">
                        <div class="form-group mb-0">
                            <label class="input-label">
                                {{translate('Select stores')}}
                                <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                      data-original-title="{{translate('messages.Only stores in this happy hours module can join')}}">
                                    <i class="tio-info text-gray1 fs-16"></i>
                                </span>
                            </label>
                            {{-- Scoped to this happy hour's module and to stores that have
                                 not joined yet; the controller builds the list. Multi-select:
                                 joining a happy hour needs no per-store setup, so a whole
                                 module can be enrolled in one go. --}}
                            <select id="hh_store_id" class="form-control" multiple>
                                @forelse($availableStores as $store)
                                    <option value="{{$store->id}}">{{$store->name}}</option>
                                @empty
                                    <option value="" disabled>{{translate('messages.No store available in this module')}}</option>
                                @endforelse
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    {{-- Clears the picker in place rather than dismissing the modal, so a wrong
                         choice can be corrected without reopening Add Store. --}}
                    <button type="button" class="btn min-w-120 h--45px btn--reset" id="hh_add_store_reset">{{translate('Reset')}}</button>
                    <button type="button" class="btn min-w-120 h--45px btn--primary" id="hh_add_store">{{translate('Add')}}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <button type="button" class="close ml-auto" data-dismiss="modal">&times;</button>
                    <i class="tio-warning text-danger" style="font-size:48px"></i>
                    <h4 class="mt-3">{{translate('Reject store join request')}}?</h4>
                    <p class="opacity-75">{{translate('messages.If you reject this store it wont be able to join the happy hour')}}</p>
                    <form id="reject-form" method="post">
                        @csrf
                        <div class="form-group text-left">
                            <label class="input-label">{{translate('Rejection reason')}}</label>
                            {{-- Capped and counted like the BOGO form. The validator has always
                                 refused more than 255, but the field let the admin type past it
                                 and only said so on submit, losing what they had written. --}}
                            <textarea name="rejection_reason" class="form-control" rows="3" maxlength="255"
                                      data-counter="count_hh_rejection_reason"
                                      placeholder="{{translate('messages.Type the reason')}}"></textarea>
                            <small class="d-block text-right opacity-75">
                                <span id="count_hh_rejection_reason">0</span>/255
                            </small>
                        </div>
                        <div class="btn--container justify-content-center">
                            <button type="button" class="btn btn--reset" data-dismiss="modal">{{translate('Close')}}</button>
                            <button type="submit" class="btn btn--primary">{{translate('Submit')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    @include('admin-views.partials._status_change_alert')

    {{-- The shared [data-counter] handler lives in _schedule_scripts, which this page does
         not load - the counter would have read 0/255 no matter what was typed. Bound here
         for the one field on this page rather than pulling in the whole form's scripts. --}}
    <script>
        $(document).on('input', '#rejectModal [data-counter]', function () {
            $('#' + $(this).data('counter')).text($(this).val().length);
        });
    </script>
    <script>
        "use strict";
        $.ajaxSetup({headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}});

        // dropdownParent keeps the list inside the modal; without it select2 renders it
        // behind the backdrop. Bound on show so the modal has real dimensions first.
        $('#addStoreModal').on('shown.bs.modal', function () {
            const $select = $('#hh_store_id');

            // Destroy rather than skip when an instance already exists. This used to bail out
            // on `select2-hidden-accessible`, which meant whichever script reached the element
            // first owned it -- and the theme's own helper builds it with tags:true, so a typed
            // search term became a store the moment Enter was pressed. Re-initialising here
            // makes the options below authoritative whatever ran before.
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }

            $select.select2({
                width: '100%',
                placeholder: '{{ translate('Select stores') }}',
                dropdownParent: $('#addStoreModal .modal-content'),
                // The list stays open after a pick, so several stores can be
                // chosen without reopening the dropdown between each one.
                closeOnSelect: false,
                // A store has to exist to be enrolled -- the list is the only source of valid
                // ids. Off explicitly, not by omission, so Enter picks the highlighted store
                // instead of turning whatever was typed into a value the controller cannot map.
                tags: false,
            });
        });

        // closeOnSelect above keeps the list open after a pick, and select2 leaves the typed
        // term sitting in the search box when it does -- so the keyword just searched for
        // stayed behind next to the new chip, reading as though it had been taken as a value.
        // Cleared on each pick, which unfilters the list for the next one too.
        $(document).on('select2:select select2:unselect', '#hh_store_id', function () {
            $(this).siblings('.select2-container')
                .find('.select2-search__field')
                .val('')
                .trigger('input');
        });

        $(document).on('click', '.toggle-description', function () {
            const wrapper = $(this).closest('p');
            wrapper.find('.description-short, .description-full').toggleClass('d-none');
            $(this).text($(this).text().trim() === '{{ translate('See more') }}'
                ? '{{ translate('See less') }}'
                : '{{ translate('See more') }}');
        });

        // Reset clears the picked store and leaves the modal where it is, so a wrong
        // choice can be corrected without reopening Add Store.
        $('#hh_add_store_reset').on('click', function () {
            $('#hh_store_id').val(null).trigger('change.select2');
        });

        // And closing it any other way clears it too, so it never reopens pre-filled.
        $('#addStoreModal').on('hidden.bs.modal', function () {
            $('#hh_store_id').val(null).trigger('change.select2');
        });

        $('#hh_add_store').on('click', function () {
            const storeIds = $('#hh_store_id').val() || [];

            if (!storeIds.length) {
                toastr.error('{{ translate('messages.Please select a store') }}');
                return;
            }

            const $button = $(this).prop('disabled', true);

            $.post({
                url: '{{ route('admin.happy-hour.add-store', $happyHour->id) }}',
                data: {store_ids: storeIds},
                success: function (res) {
                    const added = res?.added ?? storeIds.length;

                    toastr.success('{{ translate('Stores added to happy hour') }}: ' + added);

                    (res?.skipped || []).forEach(row => toastr.warning(row.name + ' - ' + row.message));

                    setTimeout(() => location.reload(), 1500);
                },
                error: function (xhr) {
                    $button.prop('disabled', false);
                    const errors = xhr.responseJSON?.errors || [];
                    errors.length
                        ? errors.forEach(e => toastr.error(e.message))
                        : toastr.error('{{ translate('messages.Something went wrong') }}');
                }
            });
        });

        $(document).on('click', '.approve-enrollment', function () {
            const url = $(this).data('url');
            Swal.fire({
                title: '{{ translate('Are you sure?') }}',
                text: '{{ translate('messages.you want to approve this store') }}',
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#FC6A57',
                cancelButtonText: '{{ translate('No') }}',
                confirmButtonText: '{{ translate('Yes') }}',
                reverseButtons: true
            }).then((result) => {
                if (result.value) {
                    $.post(url, {}, () => location.reload());
                }
            });
        });

        $(document).on('click', '.reject-enrollment', function () {
            $('#reject-form').attr('action', $(this).data('url'));
            $('#rejectModal').modal('show');
        });
    </script>
@endpush
