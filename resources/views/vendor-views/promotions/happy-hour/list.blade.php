@extends('layouts.vendor.app')

@section('title', translate('Happy hour'))

@push('css_or_js')
    @include('partials.promotion._name_cell_styles')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@php($duration_labels = [
    'daily' => translate('Daily'),
    'weekly' => translate('Weekly'),
    'custom' => translate('messages.Custom'),
])

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <i class="tio-time"></i>
                <span>{{ translate('Happy hour') }}</span>
            </h1>
            <p class="page-header-desc">{{ translate('Happy hour offers you can join, and the hours each one runs.') }}</p>
        </div>

        <div class="card">
            <div class="card-header border-0 py-2 search--button-wrapper">
                <h5 class="card-title">
                    {{ translate('Happy hour list') }}
                    <span class="badge badge-soft-dark ml-2">{{ $happyHours->total() }}</span>
                </h5>
                <form id="search-form" action="{{ url()->current() }}" method="GET">
                    <div class="input--group input-group input-group-merge input-group-flush">
                        <input id="datatableSearch_" type="search" name="search" value="{{ request('search') }}"
                               class="form-control" placeholder="{{ translate('Search') }}"
                               aria-label="{{ translate('Search') }}">
                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>
                </form>
            </div>

            @if($happyHours->total() === 0)
                <div class="empty--data py-5">
                    <img src="{{ asset('public/assets/admin/img/empty.png') }}" alt="empty">
                    <h5>
                        {{ request()->filled('search')
                            ? translate('No data found')
                            : translate('No happy hours yet') }}
                    </h5>
                    <p class="opacity-75">
                        {{ request()->filled('search')
                            ? translate('messages.No happy hour matched your search')
                            : translate('messages.There are no happy hours available for your store right now') }}
                    </p>
                </div>
            @else
                <div class="table-responsive datatable-custom">
                    <table class="font-size-sm table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                        <tr>
                            <th>{{ translate('Information') }}</th>
                            <th class="col--numeric">{{ translate('messages.Discount') }}</th>
                            <th>{{ translate('messages.Schedule') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th class="text-center">{{ translate('Action') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($happyHours as $happyHour)
                            @php($enrollment = $happyHour->enrollments->first())
                            <tr>
                                <td class="promo-name-cell">
                                    <div class="d-flex align-items-center gap-2 promo-name">
                                        <img class="rounded onerror-image flex-shrink-0"
                                             style="width:90px;height:36px;object-fit:cover"
                                             data-onerror-image="{{ asset('public/assets/admin/img/900x400/img1.jpg') }}"
                                             src="{{ $happyHour->cover_image_full_url }}" alt="{{ translate('Happy hour') }}">
                                        {{-- Truncated by the cell, not by Str::limit, so the column keeps
                                             whatever width it has and the full title stays on the tooltip. --}}
                                        <div>
                                            <span class="promo-name__text d-block" title="{{ $happyHour->title }}">{{ $happyHour->title }}</span>
                                            <span class="d-block fs-12 text-muted">ID:{{ $happyHour->id }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="col--numeric" data-order="{{ $happyHour->discount }}">
                                    <span class="d-block text-title font-semibold">{{ $happyHour->discount }}%</span>
                                    @if($happyHour->min_order_amount)
                                        <span class="d-block fs-12 text-muted">{{ translate('Minimum order amount') }}: {{ \App\CentralLogics\Helpers::format_currency($happyHour->min_order_amount) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>
                                        {{ $happyHour->start_time ? \App\CentralLogics\Helpers::time_format($happyHour->start_time) : '' }}
                                        -
                                        {{ $happyHour->end_time ? \App\CentralLogics\Helpers::time_format($happyHour->end_time) : '' }}
                                    </strong>
                                    <div class="opacity-75">
                                        {{-- A permanent weekly rule has no dates at all; a custom one carries its
                                             own list rather than a range. --}}
                                        @if($happyHour->is_permanent)
                                            {{ translate('messages.Permanent') }}
                                        @elseif($happyHour->duration_type === \App\Models\HappyHour::DURATION_CUSTOM)
                                            {{ count($happyHour->custom_days ?? []) }} {{ translate('messages.Days') }}
                                        @else
                                            {{ $happyHour->start_date ? $happyHour->start_date->format('d M Y') : translate('N/A') }}
                                            -
                                            {{ $happyHour->end_date ? $happyHour->end_date->format('d M Y') : translate('N/A') }}
                                        @endif
                                    </div>
                                    <div class="opacity-75">
                                        {{ $duration_labels[$happyHour->duration_type] ?? $happyHour->duration_type }}@if($happyHour->duration_type === \App\Models\HappyHour::DURATION_WEEKLY && $happyHour->weekly_days) · {{ implode(', ', array_map(fn($d) => substr($d, 0, 3), $happyHour->weekly_days)) }}@endif
                                    </div>
                                </td>
                                <td class="text-center">
                                    @include('vendor-views.promotions.partials._state_badge', [
                                        'enrollment' => $enrollment,
                                        'label' => $happyHour->state_label,
                                        'note' => $happyHour->rejection_note,
                                        'ended' => $happyHour->has_ended,
                                    ])
                                    {{-- What a customer sees, which "Approved" does not answer. --}}
                                    <div class="mt-1">
                                        @include('vendor-views.promotions.partials._live_state', [
                                            'visibility' => $happyHour->visibility,
                                        ])
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        {{-- View stays its own control beside the menu: it is the one thing every
                                             row offers, so hiding it behind a menu costs a click. --}}
                                        <a class="btn btn-sm action-btn action-btn--view promotion-detail"
                                           href="javascript:" data-url="{{ route('vendor.happy-hour.detail', $happyHour->id) }}"
                                           title="{{ translate('View') }}">
                                            <i class="tio-visible-outlined"></i>
                                        </a>

                                        {{-- The rest behind one control, matching the BOGO list beside it. The
                                             handlers are delegated on these classes, so the markup around them may
                                             change but the classes may not. --}}
                                        <div class="dropdown dropdown-2">
                                            <button type="button" class="btn btn-sm action-btn action-btn--menu"
                                                    data-toggle="dropdown" aria-expanded="false"
                                                    title="{{ translate('Action') }}">
                                                <i class="tio-more-vertical"></i>
                                            </button>
                                            {{-- Right aligned: the action column is the last one, so a menu
                                                 hanging to the left of the button stays on the screen. --}}
                                            <ul class="dropdown-menu dropdown-menu-right" dir="ltr">
                                                @if($happyHour->actions['can_join'])
                                                    {{-- Not the generic confirm: joining replaces the store's own
                                                         discount and the store carries the whole cost, neither of
                                                         which is visible on this screen. --}}
                                                    <a class="dropdown-item d-flex gap-2 align-items-center happy-hour-join"
                                                       href="javascript:" data-form="join-hh-{{ $happyHour->id }}">
                                                        <i class="tio-add-circle-outlined"></i>
                                                        {{ translate('messages.Join') }}
                                                    </a>
                                                @elseif($happyHour->actions['can_respond'])
                                                    <a class="dropdown-item d-flex gap-2 align-items-center happy-hour-join"
                                                       href="javascript:" data-url="{{ route('vendor.happy-hour.respond', [$happyHour->id, 'approved']) }}">
                                                        <i class="tio-done"></i>
                                                        {{ translate('messages.Approve') }}
                                                    </a>
                                                    <a class="dropdown-item d-flex gap-2 align-items-center text--danger reject-enrollment"
                                                       href="javascript:" data-url="{{ route('vendor.happy-hour.respond', [$happyHour->id, 'rejected']) }}">
                                                        <i class="tio-clear"></i>
                                                        {{ translate('messages.Deny') }}
                                                    </a>
                                                @elseif($happyHour->actions['can_leave'] || $happyHour->actions['can_cancel'])
                                                    <a class="dropdown-item d-flex gap-2 align-items-center text--danger form-alert"
                                                       href="javascript:" data-id="leave-hh-{{ $happyHour->id }}"
                                                       data-message="{{ $happyHour->actions['can_leave']
                                                           ? translate('Want to leave this happy hour?')
                                                           : translate('Want to cancel this request?') }}">
                                                        <i class="tio-delete-outlined"></i>
                                                        {{ $happyHour->actions['can_leave']
                                                            ? translate('messages.Leave')
                                                            : translate('Cancel request') }}
                                                    </a>
                                                @else
                                                    {{-- An empty menu reads as a broken control, so the row says so. --}}
                                                    <span class="dropdown-item d-flex gap-2 align-items-center disabled opacity-75">
                                                        <i class="tio-info-outined"></i>
                                                        {{ translate('messages.No action available') }}
                                                    </span>
                                                @endif
                                            </ul>
                                        </div>

                                        {{-- Outside the menu: a form is not valid markup inside a <ul>. --}}
                                        @if($happyHour->actions['can_join'])
                                            <form action="{{ route('vendor.happy-hour.join', $happyHour->id) }}" method="post"
                                                  id="join-hh-{{ $happyHour->id }}">
                                                @csrf
                                            </form>
                                        @elseif($happyHour->actions['can_leave'] || $happyHour->actions['can_cancel'])
                                            <form action="{{ route('vendor.happy-hour.leave', $happyHour->id) }}" method="post"
                                                  id="leave-hh-{{ $happyHour->id }}">
                                                @csrf @method('delete')
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
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

    <div id="offcanvas__promotion_detail" class="custom-offcanvas d-flex flex-column justify-content-between"
         style="--offcanvas-width: 620px">
        <div id="promotion-detail-body" class="h-100"></div>
    </div>

    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>

    @include('vendor-views.promotions.happy-hour.partials._join_confirm_modal')

    <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <button type="button" class="close ml-auto" data-dismiss="modal">&times;</button>
                    <i class="tio-warning text-danger" style="font-size:48px"></i>
                    <h4 class="mt-3">{{ translate('messages.Decline this happy hour') }}?</h4>
                    <p class="opacity-75">{{ translate('messages.The admin will be told you are not taking part') }}</p>
                    <form id="reject-form" method="post">
                        @csrf
                        <div class="form-group text-left">
                            <label class="input-label">{{ translate('Rejection reason') }}</label>
                            <textarea name="rejection_reason" class="form-control" rows="3" maxlength="255"
                                      data-counter="count_rejection_reason"
                                      placeholder="{{ translate('messages.Type the reason') }}"></textarea>
                            <small class="d-block text-right opacity-75">
                                <span id="count_rejection_reason">0</span>/255
                            </small>
                        </div>
                        <div class="btn--container justify-content-center">
                            <button type="button" class="btn btn--reset" data-dismiss="modal">{{ translate('Close') }}</button>
                            <button type="submit" class="btn btn--primary">{{ translate('Submit') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/offcanvas.js') }}"></script>
    <script>
        "use strict";

        $.ajaxSetup({headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}});

        // offcanvas.js binds .offcanvas-close on ready, so it never sees the detail drawer's
        // button -- that markup arrives over ajax. Closed here instead, delegated.
        $(document).on('click', '.offcanvas-close, #offcanvasOverlay', function () {
            $('.custom-offcanvas').removeClass('open');
            $('#offcanvasOverlay').removeClass('show');
        });

        $(document).on('click', '.promotion-detail', function () {
            const target = $('#offcanvas__promotion_detail');

            $('#promotion-detail-body').html(
                '<div class="d-flex align-items-center justify-content-center h-100">' +
                '<div class="spinner-border text--primary" role="status"></div></div>'
            );
            target.addClass('open');
            $('#offcanvasOverlay').addClass('show');

            $.get($(this).data('url'), function (html) {
                $('#promotion-detail-body').html(html);
            }).fail(function () {
                toastr.error('{{ translate('messages.Something went wrong') }}');
                target.removeClass('open');
                $('#offcanvasOverlay').removeClass('show');
            });
        });

        // Joining and accepting the admin's invitation are the same commitment, so both go
        // through the one modal that spells out what it costs. Whichever the button carries -
        // a form to submit, or a url to post - is held until the store confirms.
        let joinTarget = null;

        $(document).on('click', '.happy-hour-join', function () {
            joinTarget = {form: $(this).data('form'), url: $(this).data('url')};
            $('#happyHourJoinModal').modal('show');
        });

        $('#happy_hour_join_confirm').on('click', function () {
            if (!joinTarget) return;

            $('#happyHourJoinModal').modal('hide');

            joinTarget.form
                ? $('#' + joinTarget.form).submit()
                : $.post(joinTarget.url, {}, () => location.reload());
        });

        $(document).on('click', '.reject-enrollment', function () {
            $('#reject-form').attr('action', $(this).data('url'));
            $('#reject-form')[0].reset();
            $('#count_rejection_reason').text('0');
            $('#rejectModal').modal('show');
        });

        $(document).on('input', '[data-counter]', function () {
            $('#' + $(this).data('counter')).text($(this).val().length);
        });

        $(document).on('click', '.toggle-description', function () {
            const wrapper = $(this).closest('p');
            wrapper.find('.description-short, .description-full').toggleClass('d-none');
            $(this).text($(this).text().trim() === '{{ translate('See more') }}'
                ? '{{ translate('See less') }}'
                : '{{ translate('See more') }}');
        });
    </script>
@endpush
