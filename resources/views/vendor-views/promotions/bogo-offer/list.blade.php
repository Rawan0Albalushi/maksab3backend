@extends('layouts.vendor.app')

@section('title', translate('BOGO offer'))

@push('css_or_js')
    @include('partials.promotion._name_cell_styles')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.promotion._item_picker_styles')
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <i class="tio-gift"></i>
                <span>{{ translate('BOGO offer') }}</span>
            </h1>
            <p class="page-header-desc">{{ translate('Buy-one-get-one offers you can join, and the ones already running on your items.') }}</p>
        </div>

        <div class="card">
            <div class="card-header border-0 py-2 search--button-wrapper">
                <h5 class="card-title">
                    {{ translate('BOGO offer list') }}
                    <span class="badge badge-soft-dark ml-2">{{ $offers->total() }}</span>
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

            @if($offers->total() === 0)
                <div class="empty--data py-5">
                    <img src="{{ asset('public/assets/admin/img/empty.png') }}" alt="empty">
                    <h5>
                        {{ request()->filled('search')
                            ? translate('No data found')
                            : translate('No BOGO offers yet') }}
                    </h5>
                    <p class="opacity-75">
                        {{ request()->filled('search')
                            ? translate('messages.No offer matched your search')
                            : translate('messages.There are no BOGO offers available for your store right now') }}
                    </p>
                </div>
            @else
                <div class="table-responsive datatable-custom">
                    <table class="font-size-sm table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                        <tr>
                            <th>{{ translate('messages.Title') }}</th>
                            <th>{{ translate('messages.Image') }}</th>
                            <th>{{ translate('Item quantity') }}</th>
                            <th>{{ translate('messages.Duration') }}</th>
                            <th>{{ translate('Ends on') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th class="text-center">{{ translate('Action') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($offers as $offer)
                            @php($enrollment = $offer->enrollments->first())
                            @php($ends = $offer->end_date)
                            @php($days_left = $ends ? (int) \Carbon\Carbon::now()->startOfDay()->diffInDays($ends->copy()->startOfDay(), false) : null)
                            <tr>
                                <td class="promo-name-cell">
                                    {{-- Truncated by the cell rather than by Str::limit: the column keeps
                                         whatever width it has, the full title is on its own tooltip, and the
                                         warning beside it cannot be pushed out of the row by a long name. --}}
                                    <div class="promo-name">
                                        {{-- Grouped in their own block, matching the Happy Hour list beside it:
                                             .promo-name is a flex row, so title and id must be direct children of a
                                             plain wrapper rather than direct children of the flex container itself,
                                             or they lay out side by side instead of stacked. --}}
                                        <div>
                                            <span class="promo-name__text d-block" title="{{ $offer->title }}">{{ $offer->title }}</span>
                                            <span class="d-block fs-12 text-muted">ID:{{ $offer->id }}</span>
                                        </div>

                                        {{-- Approved and still not reaching customers. Moved here from the action
                                             column: it reports a state rather than offering an action, and a third
                                             control in that column was what pushed it off the right edge. --}}
                                        @include('partials.promotion._visibility_warning', [
                                            'reasons' => $offer->hidden_reasons,
                                            'class' => 'promo-name-warning',
                                        ])
                                    </div>
                                </td>
                                <td>
                                    <img class="rounded onerror-image" style="width:90px;height:36px;object-fit:cover"
                                         data-onerror-image="{{ asset('public/assets/admin/img/900x400/img1.jpg') }}"
                                         src="{{ $offer->image_full_url }}" alt="{{ translate('BOGO offer') }}">
                                </td>
                                <td>
                                    {{ translate('Buy') }}: {{ $offer->buy_qty }}, {{ translate('Get') }}: {{ $offer->get_qty }}
                                </td>
                                <td>
                                    <span class="d-block text-title">{{ $offer->start_date ? \App\CentralLogics\Helpers::date_format($offer->start_date).' - '.\App\CentralLogics\Helpers::date_format($offer->end_date) : translate('messages.N/A') }}</span>
                                    <span class="d-block fs-12 text-muted text-uppercase">{{ $offer->start_date ? \App\CentralLogics\Helpers::time_format($offer->start_date).' - '.\App\CentralLogics\Helpers::time_format($offer->end_date) : translate('messages.N/A') }}</span>
                                </td>
                                <td data-order="{{ $ends }}">
                                    @if($ends)
                                        <span class="table-when{{ $days_left < 0 ? ' table-when--stale' : '' }}">
                                            <span class="table-when__day">{{ \App\CentralLogics\Helpers::date_format($ends) }}</span>
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
                                        <span class="text-muted font-size-sm">{{ translate('messages.N/A') }}</span>
                                    @endif
                                </td>
                                {{-- Left, like its own <th> and like the admin list's status cell.
                                     Centred here it sat off to the right of the "Status" heading,
                                     the only column in the table whose cell and header disagreed. --}}
                                <td>
                                    @include('vendor-views.promotions.partials._state_badge', [
                                        'enrollment' => $enrollment,
                                        'label' => $offer->state_label,
                                        'note' => $offer->rejection_note,
                                        'ended' => $offer->has_ended,
                                    ])
                                    {{-- What a customer sees, which "Approved" does not answer. --}}
                                    <div class="mt-1">
                                        @include('vendor-views.promotions.partials._live_state', [
                                            'visibility' => $offer->visibility,
                                        ])
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        {{-- View stays its own control beside the menu: it is the one thing every
                                             row offers, so hiding it behind a menu costs a click. --}}
                                        <a class="btn btn-sm action-btn action-btn--view promotion-detail"
                                           href="javascript:" data-url="{{ route('vendor.bogo-offer.detail', $offer->id) }}"
                                           title="{{ translate('View') }}">
                                            <i class="tio-visible-outlined"></i>
                                        </a>

                                        {{-- The rest behind one control. The handlers are delegated on these
                                             classes, so the markup around them may change but the classes may not. --}}
                                        <div class="dropdown dropdown-2">
                                            <button type="button" class="btn btn-sm action-btn action-btn--menu"
                                                    data-toggle="dropdown" aria-expanded="false"
                                                    title="{{ translate('Action') }}">
                                                <i class="tio-more-vertical"></i>
                                            </button>
                                            {{-- Right aligned: the action column is the last one, so a menu
                                                 hanging to the left of the button stays on the screen. --}}
                                            <ul class="dropdown-menu dropdown-menu-right" dir="ltr">
                                                {{-- Asked one flag at a time rather than as a chain: a denial from
                                                     the admin allows a rework and nothing else, so a chain that
                                                     reached Leave first left that row with no action at all. --}}
                                                @if($offer->actions['can_respond'])
                                                    <a class="dropdown-item d-flex gap-2 align-items-center respond-enrollment"
                                                       href="javascript:" data-url="{{ route('vendor.bogo-offer.respond', [$offer->id, 'approved']) }}">
                                                        <i class="tio-done"></i>
                                                        {{ translate('messages.Approve') }}
                                                    </a>
                                                    <a class="dropdown-item d-flex gap-2 align-items-center text--danger reject-enrollment"
                                                       href="javascript:" data-url="{{ route('vendor.bogo-offer.respond', [$offer->id, 'rejected']) }}">
                                                        <i class="tio-clear"></i>
                                                        {{ translate('messages.Deny') }}
                                                    </a>
                                                @else
                                                    @if($offer->actions['can_join'])
                                                        <a class="dropdown-item d-flex gap-2 align-items-center join-offer"
                                                           href="javascript:" data-id="{{ $offer->id }}"
                                                           data-buy="{{ $offer->buy_qty }}" data-get="{{ $offer->get_qty }}">
                                                            <i class="tio-add-circle-outlined"></i>
                                                            {{ translate('messages.Join') }}
                                                        </a>
                                                    @endif

                                                    @if($offer->actions['can_resubmit'])
                                                        <a class="dropdown-item d-flex gap-2 align-items-center edit-items"
                                                           href="javascript:" data-id="{{ $offer->id }}"
                                                           data-buy="{{ $offer->buy_qty }}" data-get="{{ $offer->get_qty }}"
                                                           data-payload="{{ json_encode($enrollment->pickerPayload()) }}">
                                                            <i class="tio-edit"></i>
                                                            {{ translate('Edit items') }}
                                                        </a>
                                                    @endif

                                                    @if($offer->actions['can_leave'] || $offer->actions['can_cancel'])
                                                        <a class="dropdown-item d-flex gap-2 align-items-center text--danger form-alert"
                                                           href="javascript:" data-id="leave-bogo-{{ $offer->id }}"
                                                           data-message="{{ $offer->actions['can_leave']
                                                               ? translate('Want to leave this BOGO offer?')
                                                               : translate('Want to cancel this request?') }}">
                                                            <i class="tio-delete-outlined"></i>
                                                            {{ $offer->actions['can_leave']
                                                                ? translate('messages.Leave')
                                                                : translate('Cancel request') }}
                                                        </a>
                                                    @endif

                                                    {{-- Nothing actionable left, and why. Shown as a dead row rather
                                                         than an empty menu, which reads as a broken control. --}}
                                                    @if($offer->locked_reason && ! array_filter($offer->actions))
                                                        <span class="dropdown-item d-flex gap-2 align-items-center disabled opacity-75">
                                                            <i class="tio-info-outined"></i>
                                                            {{ $offer->locked_reason }}
                                                        </span>
                                                    @endif
                                                @endif
                                            </ul>
                                        </div>

                                        {{-- Outside the menu: a form is not valid markup inside a <ul>. --}}
                                        @if($offer->actions['can_leave'] || $offer->actions['can_cancel'])
                                            <form action="{{ route('vendor.bogo-offer.leave', $offer->id) }}" method="post"
                                                  id="leave-bogo-{{ $offer->id }}">
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
                        <div>{!! $offers->links() !!}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Join drawer: the same picker the admin uses, so one selection engine serves both panels. --}}
    <div id="offcanvas__join_offer" class="custom-offcanvas d-flex flex-column justify-content-between"
         style="--offcanvas-width: 620px">
        <div class="h-100 d-flex flex-column">
            <div class="custom-offcanvas-header d-flex justify-content-between align-items-center px-3 py-3">
                <h2 class="mb-0 fs-18">{{ translate('BOGO offer') }}</h2>
                <button type="button" class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
                        aria-label="{{ translate('Close') }}">&times;</button>
            </div>

            <div class="custom-offcanvas-body p-20 flex-grow-1">
                <h5 class="mb-3">{{ translate('messages.Select items for BOGO offer') }}</h5>

                @foreach(['buy', 'get'] as $type)
                    <div class="bg-light rounded p-3 mb-3">
                        <h6 class="mb-1">{{ $type === 'buy' ? translate('Buy item') : translate('Get item') }}</h6>
                        <p class="font-size-sm opacity-75">
                            {{ $type === 'buy'
                                ? translate('messages.Customers must buy selected items in specific quantities for the BOGO offer')
                                : translate('messages.Customers receive the item in the specified quantity with this BOGO offer') }}
                        </p>

                        <div class="alert alert-soft-warning alert--note d-flex align-items-start gap-2 py-2">
                            <i class="tio-error lh-base"></i>
                            <span>
                                {{ translate('messages.You must add') }}
                                <strong class="{{ $type }}-required">0</strong>
                                {{ $type === 'buy'
                                    ? translate('messages.quantities of the item as the buy quantity for this BOGO offer')
                                    : translate('messages.quantity of the item as the Get quantity for this BOGO offer') }}
                                <span class="{{ $type }}-total-wrap d-none">
                                    (<strong class="{{ $type }}-total">0</strong>/<span class="{{ $type }}-required">0</span>)
                                </span>
                            </span>
                        </div>

                        <div class="form-group mb-2">
                            <label class="input-label">
                                {{ translate('messages.Select Item') }} <span class="text-danger">*</span>
                                <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                      data-original-title="{{ translate('messages.Search and pick an item from your menu') }}">
                                    <i class="tio-info text-gray1 fs-16"></i>
                                </span>
                            </label>
                            <select class="form-control h--45px food-picker" data-type="{{ $type }}">
                                <option value="">{{ translate('messages.Select Item') }}</option>
                            </select>
                        </div>

                        <div class="selected-items" data-type="{{ $type }}"></div>
                    </div>
                @endforeach
            </div>

            <div class="offcanvas-footer bg-white p-3 d-flex align-items-center gap-3">
                <button type="button" class="btn btn--reset h--45px flex-fill" id="bogo_reset">{{ translate('messages.Reset') }}</button>
                <button type="button" class="btn btn--primary h--45px flex-fill" id="bogo_submit">{{ translate('messages.Join') }}</button>
            </div>
        </div>
    </div>

    {{-- Detail drawer, filled on demand by the eye action. --}}
    <div id="offcanvas__promotion_detail" class="custom-offcanvas d-flex flex-column justify-content-between"
         style="--offcanvas-width: 620px">
        <div id="promotion-detail-body" class="h-100"></div>
    </div>

    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>

    @include('partials.promotion._visibility_modal', ['promotionLabel' => translate('messages.BOGO offer')])
    @include('partials.promotion._item_options_modal')

    {{-- Raised before the edit drawer opens. Reworking a selection has a consequence the vendor
         cannot see from this screen: the frozen combination is replaced, so every customer cart
         holding the old bundle is emptied, and an approved offer drops back to pending and stops
         applying until the admin approves it again. --}}
    <div class="modal fade" id="editItemsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content border-0 rounded-8">
                <div class="modal-body px-4 pb-4 pt-3">
                    <button type="button" class="close ml-auto" data-dismiss="modal"
                            aria-label="{{ translate('Close') }}">&times;</button>

                    <div class="d-flex justify-content-center my-3">
                        <span class="d-center rounded-circle bg--danger text-white"
                              style="width:64px;height:64px;font-size:36px;line-height:1">!</span>
                    </div>

                    <h4 class="font-bold text-center mb-3">{{ translate('messages.Edit the items of this BOGO offer') }}?</h4>

                    <div class="alert-soft-warning alert--note rounded-8 p-3 mb-0 fs-14">
                        <ul class="mb-0 pl-3">
                            <li>{{ translate("messages.Customers who already added this BOGO offer to their cart won't be able to place an order with it until they remove it and add it again") }}</li>
                            <li>{{ translate('messages.The offer goes back to pending and stops applying until the admin approves the new selection') }}</li>
                        </ul>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0 px-4 pb-4 d-flex gap-3">
                    <button type="button" class="btn btn--reset h--45px flex-fill m-0" data-dismiss="modal">
                        {{ translate('messages.Cancel') }}
                    </button>
                    <button type="button" class="btn btn--primary h--45px flex-fill m-0" id="edit_items_confirm">
                        {{ translate('Edit items') }}
                    </button>
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
                    <h4 class="mt-3">{{ translate('messages.Decline this BOGO offer') }}?</h4>
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

        // Set per offer when the join drawer opens: each offer has its own buy/get quantities.
        const requiredQty = {buy: 0, get: 0};
        const selected = {buy: [], get: []};
        let foodCatalog = [];

        $.ajaxSetup({headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}});

        const drawer = $('#offcanvas__join_offer');

        // The store's own menu, so the endpoint takes no store id at all.
        const foodsEndpoint = '{{ route('vendor.bogo-offer.store-items') }}';

        function initSelect2($el, placeholder) {
            $el.select2({width: '100%', placeholder: placeholder, dropdownParent: drawer});
        }
    </script>

    {{-- The selection engine shared with the admin panel. Needs requiredQty / selected /
         foodCatalog / drawer, defined just above. --}}
    @include('partials.bogo._item_picker_scripts')

    <script>
        "use strict";

        let joiningOfferId = null;
        let submitUrl = null;

        function loadMenuInto(callback) {
            $.get(foodsEndpoint, function (items) {
                foodCatalog = items;

                const options = items.map(i =>
                    `<option value="${i.id}" ${i.is_available ? '' : 'disabled'}>${escapeHtml(i.name)}</option>`
                ).join('');

                $('.food-picker').each(function () {
                    const $picker = $(this);
                    if ($picker.hasClass('select2-hidden-accessible')) $picker.select2('destroy');
                    $picker.prop('disabled', false)
                        .html(`<option value="">{{ translate('messages.Select Item') }}</option>` + options);
                    initFoodSelect2($picker, '{{ translate('messages.Select Item') }}');
                });

                if (callback) callback();
            });
        }

        function openJoinDrawer(offerId, buy, get, url, label, payload) {
            joiningOfferId = offerId;
            submitUrl = url;
            requiredQty.buy = Number(buy);
            requiredQty.get = Number(get);

            $('.buy-required').text(requiredQty.buy);
            $('.get-required').text(requiredQty.get);
            $('#bogo_submit').text(label).prop('disabled', false);

            // Cleared first either way, so the drawer never opens holding the last offer's picks
            // while the menu it is about to seed from is still in flight.
            resetSelections();
            loadMenuInto(payload ? () => seedSelections(payload) : null);

            // "Edit Items" inside the detail drawer (#offcanvas__promotion_detail) calls here too,
            // and that drawer never closes itself first. Both panels are the same fixed, full-height
            // offcanvas anchored at the same edge, so leaving it `.open` didn't block this drawer
            // from opening underneath -- it just covered it completely, which read as the button
            // doing nothing. Every other offcanvas is closed here, the one time this can actually
            // stack, rather than requiring each trigger to remember to close its own first.
            $('.custom-offcanvas').not(drawer).removeClass('open');

            drawer.addClass('open');
            $('#offcanvasOverlay').addClass('show');
        }

        $(document).on('click', '.join-offer', function () {
            openJoinDrawer(
                $(this).data('id'), $(this).data('buy'), $(this).data('get'),
                '{{ url('vendor-panel/bogo-offer/join') }}/' + $(this).data('id'),
                '{{ translate('messages.Join') }}'
            );
        });

        // Reworking an approved or denied selection posts to resubmit rather than join; the
        // drawer is otherwise identical, so only the endpoint, the button word and the selection
        // it opens on change. Editing means changing what is there, so what is there is loaded -
        // an empty drawer would have made a one-item change a full rebuild, against an equality
        // rule that refuses anything short of the offer's exact quantities.
        //
        // Asked first, because saving has a consequence off this screen: the new selection
        // replaces the frozen one, so every customer cart holding the old bundle is emptied.
        let pendingEdit = null;

        $(document).on('click', '.edit-items', function () {
            pendingEdit = {
                id: $(this).data('id'),
                buy: $(this).data('buy'),
                get: $(this).data('get'),
                payload: $(this).data('payload')
            };

            $('#editItemsModal').modal('show');
        });

        $('#edit_items_confirm').on('click', function () {
            if (!pendingEdit) return;

            $('#editItemsModal').modal('hide');

            openJoinDrawer(
                pendingEdit.id, pendingEdit.buy, pendingEdit.get,
                '{{ url('vendor-panel/bogo-offer/resubmit') }}/' + pendingEdit.id,
                '{{ translate('messages.Submit') }}',
                pendingEdit.payload
            );
        });

        $('#bogo_reset').on('click', resetSelections);

        $('#bogo_submit').on('click', function () {
            const $button = $(this);
            if ($button.prop('disabled')) return;

            const payload = type => selected[type].map(i => ({
                item_id: i.item_id,
                quantity: i.quantity,
                variations: i.selected_variations,
                add_on_ids: i.add_on_ids,
                add_on_qtys: i.add_on_qtys
            }));

            $button.prop('disabled', true);

            $.post({
                url: submitUrl,
                data: {buy_items: payload('buy'), get_items: payload('get')},
                success: function () {
                    toastr.success('{{ translate('Your request has been sent to the admin') }}');
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

        $(document).on('click', '.respond-enrollment', function () {
            const url = $(this).data('url');
            Swal.fire({
                title: '{{ translate('Are you sure?') }}',
                text: '{{ translate('messages.you want to join this BOGO offer') }}',
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#FC6A57',
                cancelButtonText: '{{ translate('No') }}',
                confirmButtonText: '{{ translate('Yes') }}',
                reverseButtons: true
            }).then((result) => {
                if (result.value) $.post(url, {}, () => location.reload());
            });
        });

        $(document).on('click', '.reject-enrollment', function () {
            $('#reject-form').attr('action', $(this).data('url'));
            // One modal serves every row, so the reason typed for the last offer has to be
            // cleared before it opens for the next.
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
