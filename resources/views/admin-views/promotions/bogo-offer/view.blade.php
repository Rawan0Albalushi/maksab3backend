@extends('layouts.admin.app')

@section('title',translate('BOGO offer details'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('partials.promotion._item_picker_styles')
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h1 class="page-header-title mb-0">
                    <i class="tio-gift"></i>
                    <span>{{translate('BOGO offer')}} #{{$offer->id}}</span>
                </h1>
                <p class="page-header-desc">{{ translate('What this offer covers, how long it runs and how often it has been used.') }}</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                {{-- What the delete actually costs, said before it happens: the offer is taken
                     off every store on it and the bundle disappears from any cart holding
                     it. A plain "delete this offer?" made that look like deleting a draft. --}}
                @php($joined = (int) ($offer->joined_count ?? 0))
                <a class="btn btn-soft-danger d-flex align-items-center gap-1 form-alert" href="javascript:"
                   data-id="bogo-offer-{{$offer->id}}"
                   data-message="{{ $joined > 0
                        ? $joined.' '.translate('messages.stores have joined this offer Deleting it removes the offer from all of them')
                            .' '.translate('messages.Any items customers added to their cart from this offer will be removed too')
                            .' '.translate('Want to delete this BOGO offer?')
                        : translate('Want to delete this BOGO offer?') }}">
                    <i class="tio-delete"></i> {{translate('messages.Delete')}}
                </a>
                <form action="{{route('admin.bogo-offer.delete',$offer->id)}}" method="post" id="bogo-offer-{{$offer->id}}">
                    @csrf @method('delete')
                </form>

                {{-- Status sits in its own bordered pill, per design. --}}
                <div class="d-flex align-items-center gap-2 border rounded bg-white px-3 py-2">
                    <span class="font-weight-bold">{{translate('messages.Status')}}</span>
                    <label class="toggle-switch toggle-switch-sm mb-0" for="bogoStatus{{$offer->id}}">
                        <input type="checkbox" class="toggle-switch-input status_change_alert"
                               data-url="{{route('admin.bogo-offer.status',[$offer->id, $offer->status ? 0 : 1])}}"
                               data-message="{{ $offer->status
                                    ? translate('Want to turn off this BOGO offer?')
                                    : translate('Want to turn on this BOGO offer?') }}"
                               id="bogoStatus{{$offer->id}}" {{$offer->status ? 'checked' : ''}}>
                        <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                    </label>
                </div>

                <a class="btn btn-soft-primary d-flex align-items-center gap-1" href="{{route('admin.bogo-offer.edit',$offer->id)}}">
                    <i class="tio-edit"></i> {{translate('messages.Edit')}}
                </a>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row align-items-md-center">
                    <div class="col-md-3 mb-3 mb-md-0">
                        <img class="rounded w-100 onerror-image" data-onerror-image="{{asset('public/assets/admin/img/900x400/img1.jpg')}}"
                             src="{{ $offer->image_full_url }}" alt="bogo offer">
                    </div>
                    <div class="col-md-5">
                        <h4 class="mb-1">{{$offer->title}}</h4>
                        @php($description = $offer->description ?? '')
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
                        <div class="bg-light rounded p-3">
                            <h6 class="d-flex align-items-center gap-2 mb-3">
                                <i class="tio-calendar text-gray1"></i> {{translate('BOGO offer duration')}}
                            </h6>
                            <div class="d-flex align-items-center mb-2">
                                <span class="opacity-75" style="min-width:130px">{{translate('Start date & time')}}</span>
                                <span class="mr-2">:</span>
                                <strong>{{$offer->start_date ? $offer->start_date->format('d M, Y h:i A') : 'N/A'}}</strong>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="opacity-75" style="min-width:130px">{{translate('End date & time')}}</span>
                                <span class="mr-2">:</span>
                                <strong>{{$offer->end_date ? $offer->end_date->format('d M, Y h:i A') : 'N/A'}}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                @php($offer_active = \App\CentralLogics\Helpers::bogo_offer_duration($offer))
                @php($stats = [
                    translate('Created at') => $offer->created_at->format('d M, Y h:i A'),
                    translate('Offer active') => $offer_active,
                    translate('Buy item quantity') => $offer->buy_qty,
                    translate('Get item quantity') => $offer->get_qty,
                    translate('Usage limit total') => $offer->usage_limit_total
                        ? $offer->usage_limit_total.' '.translate('messages.Order')
                        : 'N/A',
                    translate('Usage limit') => $offer->usage_limit_per_customer
                        ? translate('messages.Per person').' '.$offer->usage_limit_per_customer.' '.translate('messages.Order')
                        : 'N/A',
                ])

                <div class="row">
                    @foreach($stats as $label => $value)
                        <div class="col-6 col-md-2 mb-3 mb-md-0">
                            <span class="d-block opacity-75 font-size-sm mb-1">{{ $label }}</span>
                            <span class="d-block fs-16 font-weight-bold text-dark">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
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
                       data-hs-unfold-options='{"target": "#bogoStoreExportDropdown", "type": "css-animation"}'>
                        <i class="tio-download-to mr-1"></i> {{ translate('Export') }}
                    </a>
                    <div id="bogoStoreExportDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                        <span class="dropdown-header">{{ translate('Download options') }}</span>
                        <a target="__blank" class="dropdown-item"
                           href="{{ route('admin.bogo-offer.store-export', ['id' => $offer->id, 'type' => 'excel', 'search' => request('search')]) }}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                 src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="excel">
                            Excel
                        </a>
                        <a target="__blank" class="dropdown-item"
                           href="{{ route('admin.bogo-offer.store-export', ['id' => $offer->id, 'type' => 'csv', 'search' => request('search')]) }}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                 src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg" alt="csv">
                            CSV
                        </a>
                    </div>
                </div>

                {{-- An expired offer cannot be enrolled into: the endpoint refuses it, and a
                     store added to an offer that is already over would never serve the
                     bundle once. The button says why rather than collecting an error. --}}
                @php($expired = (bool) ($offer->end_date && $offer->end_date->isPast()))

                @if($expired)
                    <span data-toggle="tooltip" data-placement="top"
                          title="{{ translate('This offer has already ended') }}">
                        <button type="button" class="btn btn--primary" disabled>
                            <i class="tio-add-circle"></i> {{translate('messages.Add store')}}
                        </button>
                    </span>
                @else
                    <button type="button" class="btn btn--primary offcanvas-trigger" data-target="#offcanvas__add_store">
                        <i class="tio-add-circle"></i> {{translate('messages.Add store')}}
                    </button>
                @endif
            </div>

            @if($enrollments->total() === 0)
                {{-- Same empty panel either way, but the copy has to say which it is: nobody
                     has joined, or the search simply matched nothing. --}}
                {{-- Mart's own empty panel. .empty--data owns the centring and sizes the image
                     to 145px, so a hand-rolled div with its own width is a second convention
                     that drifts from every other list on the panel. --}}
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
                            : translate('messages.No stores have joined the BOGO offer yet') }}
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
                            <th>{{ translate('Buy item') }}</th>
                            <th>{{ translate('Get item') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th class="text-center">{{ translate('Action') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @include('admin-views.promotions.bogo-offer.partials._store_table', [
                            'enrollments' => $enrollments,
                            'addOnNames' => $addOnNames,
                            'hiddenReasons' => $hiddenReasons,
                        ])
                        </tbody>
                    </table>
                </div>
                <div class="page-area px-4 pb-3">
                    <div class="d-flex align-items-center justify-content-end">
                        <div>{!! $enrollments->links() !!}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Add Store : right-hand offcanvas, per design -->
    <div id="offcanvas__add_store" class="custom-offcanvas d-flex flex-column justify-content-between" style="--offcanvas-width: 620px">
        <div class="h-100 d-flex flex-column">
            <div class="custom-offcanvas-header d-flex justify-content-between align-items-center px-3 py-3">
                <h2 class="mb-0 fs-18">{{translate('messages.Add store')}}</h2>
                <button type="button"
                        class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
                        aria-label="Close">&times;</button>
            </div>

            <div class="custom-offcanvas-body p-20 flex-grow-1">
                <div class="bg-light rounded p-3 mb-4">
                    <div class="form-group mb-0">
                        <label class="input-label">
                            {{translate('messages.Store')}} <span class="text-danger">*</span>
                            <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                  data-original-title="{{translate('messages.Pick the store that will run this offer')}}">
                                <i class="tio-info text-gray1 fs-16"></i>
                            </span>
                        </label>
                        <select id="bogo_store_id" class="form-control h--45px">
                            <option value="">{{ translate('Select store') }}</option>
                            @forelse($availableStores as $store)
                                <option value="{{$store->id}}">{{$store->name}}</option>
                            @empty
                                <option value="" disabled>{{ translate('messages.All stores have already joined this offer') }}</option>
                            @endforelse
                        </select>
                    </div>
                </div>

                <h5 class="mb-3">{{translate('messages.Select items for BOGO offer')}}</h5>

                @foreach([['buy', $offer->buy_qty], ['get', $offer->get_qty]] as [$type, $required])
                    <div class="bg-light rounded p-3 mb-3">
                        <h6 class="mb-1">{{ $type === 'buy' ? translate('Buy item') : translate('Get item') }}</h6>
                        <p class="font-size-sm opacity-75">
                            {{ $type === 'buy'
                                ? translate('Customers must buy the selected items with the specific quantities to qualify')
                                : translate('Customers must buy all selected items in the specified quantities to qualify') }}
                        </p>

                        {{-- .alert--note carries the contrast, so every note bar on the
                             promotion screens reads the same way. --}}
                        <div class="alert alert-soft-warning alert--note d-flex align-items-start gap-2 py-2">
                            <i class="tio-error lh-base"></i>
                            <span>
                                {{ translate('messages.You must add') }} <strong>{{$required}}</strong>
                                {{ $type === 'buy'
                                    ? translate('messages.items as the buy quantity for this BOGO offer')
                                    : translate('messages.items as the get quantity for this BOGO offer') }}
                                <span class="{{$type}}-total-wrap d-none">
                                    (<strong class="{{$type}}-total">0</strong>/{{$required}})
                                </span>
                            </span>
                        </div>

                        <div class="form-group mb-2">
                            <label class="input-label">
                                {{translate('messages.Select Item')}} <span class="text-danger">*</span>
                                <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                      data-original-title="{{translate('messages.Search and pick an item from this store')}}">
                                    <i class="tio-info text-gray1 fs-16"></i>
                                </span>
                            </label>
                            <select class="form-control h--45px food-picker" data-type="{{$type}}" disabled>
                                <option value="">{{ translate('messages.Select Item') }}</option>
                            </select>
                        </div>

                        <div class="selected-items" data-type="{{$type}}"></div>
                    </div>
                @endforeach
            </div>

            <div class="offcanvas-footer bg-white p-3 d-flex align-items-center gap-3">
                <button type="button" class="btn btn--reset h--45px flex-fill" id="bogo_reset">{{translate('messages.Reset')}}</button>
                <button type="button" class="btn btn--primary h--45px flex-fill" id="bogo_add_store">{{translate('messages.Add')}}</button>
            </div>
        </div>
    </div>
    {{-- Enrolment drawer, filled on demand by the eye action and the "N Foods" link. --}}
    <div id="offcanvas__enrollment_detail" class="custom-offcanvas d-flex flex-column justify-content-between" style="--offcanvas-width: 620px">
        <div id="enrollment-detail-body" class="h-100"></div>
    </div>

    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>

    {{-- Opened by the warning icon on any row whose bundle is approved but not reaching
         customers. --}}
    @include('partials.promotion._visibility_modal', ['promotionLabel' => translate('messages.BOGO offer')])

    <!-- Reject -->
    @include('partials.promotion._item_options_modal')

    <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <button type="button" class="close ml-auto" data-dismiss="modal">&times;</button>
                    <i class="tio-warning text-danger" style="font-size:48px"></i>
                    <h4 class="mt-3">{{translate('Reject store join request')}}?</h4>
                    <p class="opacity-75">{{translate('messages.If you reject this store it wont be able to join the BOGO offer campaign')}}</p>
                    <form id="reject-form" method="post">
                        @csrf
                        <div class="form-group text-left">
                            <label class="input-label">{{translate('Rejection reason')}}</label>
                            <textarea name="rejection_reason" class="form-control" rows="3" maxlength="255"
                                      data-counter="count_rejection_reason"
                                      placeholder="{{translate('messages.Type the reason')}}"></textarea>
                            <small class="d-block text-right opacity-75">
                                <span id="count_rejection_reason">0</span>/255
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
    <script src="{{ asset('public/assets/admin/js/offcanvas.js') }}"></script>
    @include('admin-views.partials._status_change_alert')
    <script>
        "use strict";

        const requiredQty = {buy: {{ $offer->buy_qty }}, get: {{ $offer->get_qty }}};
        const selected = {buy: [], get: []};
        let foodCatalog = [];

        $.ajaxSetup({headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}});

        const drawer = $('#offcanvas__add_store');

        // Used by the picker to reopen an enrolled line read-only.
        const foodsEndpoint = '{{ route('admin.bogo-offer.store-items') }}';

        // dropdownParent keeps the select2 dropdown inside the offcanvas; without it the
        // list renders behind the panel.
        function initSelect2($el, placeholder) {
            $el.select2({
                width: '100%',
                placeholder: placeholder,
                dropdownParent: drawer,
            });
        }

        initSelect2($('#bogo_store_id'), '{{ translate('Select store') }}');

    </script>

    {{-- Food picker engine (select2 rows, config modal, selection list). Needs
         requiredQty / selected / foodCatalog / drawer, defined just above. --}}
    @include('partials.bogo._item_picker_scripts')

    <script>
        "use strict";

        $('#bogo_store_id').on('change', function () {
            const storeId = $(this).val();
            resetSelections();

            // Clearing the store has to clear its menu too, else the pickers keep
            // offering foods that belong to whoever was selected before.
            if (!storeId) {
                foodCatalog = [];

                $('.food-picker').each(function () {
                    const $picker = $(this);
                    if ($picker.hasClass('select2-hidden-accessible')) $picker.select2('destroy');
                    $picker.prop('disabled', true)
                        .html('<option value="">{{ translate('messages.Select Item') }}</option>');
                    initFoodSelect2($picker, '{{ translate('messages.Select Item') }}');
                });

                return;
            }

            $.get('{{ route('admin.bogo-offer.store-items') }}', {store_id: storeId}, function (foods) {
                foodCatalog = foods;
                // Plain options: the row markup comes from templateResult, and unavailable
                // is shown as a band on the thumbnail rather than a suffix on the name.
                const options = foods.map(f =>
                    `<option value="${f.id}" ${f.is_available ? '' : 'disabled'}>${escapeHtml(f.name)}</option>`
                ).join('');

                $('.food-picker').each(function () {
                    const $picker = $(this);
                    if ($picker.hasClass('select2-hidden-accessible')) {
                        $picker.select2('destroy');
                    }
                    $picker.prop('disabled', false)
                        .html(`<option value="">{{ translate('messages.Select Item') }}</option>` + options);
                    initFoodSelect2($picker, '{{ translate('messages.Select Item') }}');
                });
            });
        });


        $('#bogo_reset').on('click', resetSelections);

        // Null when adding; set to the update URL while editing an existing enrolment.
        let editingUrl = null;
        // True while reworking a denied enrolment, which goes back out as an admin request
        // rather than going live - so it needs a different word on success.
        let editingResubmit = false;

        $(document).on('click', '.edit-and-approve', function () {
            const payload = $(this).data('payload');

            $('#offcanvas__enrollment_detail').removeClass('open');
            editingUrl = payload.url;
            editingResubmit = !!payload.is_resubmit;

            // The store is fixed for an existing enrolment. It is absent from the
            // dropdown (that list is only the ones yet to join), so add it as a temporary
            // option. Disable before change.select2 or the widget redraws still enabled.
            const $store = $('#bogo_store_id');

            if (!$store.find('option[value="' + payload.store_id + '"]').length) {
                $store.append(
                    $('<option>', {value: payload.store_id, text: payload.store_name, 'data-temp': 1})
                );
            }

            $store.val(payload.store_id).prop('disabled', true).trigger('change.select2');
            resetSelections();

            $.get('{{ route('admin.bogo-offer.store-items') }}', {store_id: payload.store_id}, function (foods) {
                foodCatalog = foods;

                seedSelections(payload);

                $('.food-picker').each(function () {
                    const $picker = $(this);
                    if ($picker.hasClass('select2-hidden-accessible')) $picker.select2('destroy');
                    $picker.prop('disabled', false).html(
                        `<option value="">{{ translate('messages.Select Item') }}</option>` +
                        foods.map(f => `<option value="${f.id}" ${f.is_available ? '' : 'disabled'}>${escapeHtml(f.name)}</option>`).join('')
                    );
                    initFoodSelect2($picker, '{{ translate('messages.Select Item') }}');
                });

                // The label comes with the payload: the same drawer approves a pending
                // request and resubmits a denied one, and the enrolment's state decides which.
                $('#bogo_add_store').text(payload.submit_label || '{{ translate('Edit & approve') }}');
                $('#offcanvas__add_store').addClass('open');
                $('#offcanvasOverlay').addClass('show');
            });
        });

        // offcanvas.js binds .offcanvas-close directly on ready, so it never sees the
        // enrolment drawer's button - that markup arrives later over ajax. Close here
        // instead, delegated, and drop edit mode so the next open is a clean add.
        $(document).on('click', '.offcanvas-close, #offcanvasOverlay', function () {
            $('.custom-offcanvas').removeClass('open');
            $('#offcanvasOverlay').removeClass('show');

            leaveEditMode();
        });

        // Drops the enrolment being edited so the drawer reopens as a clean add: the
        // injected option has to go too, or an already-joined store stays offerable.
        // A plain add is left untouched - closing it should not discard the user's picks.
        function leaveEditMode() {
            if (!editingUrl) return;

            editingUrl = null;
            editingResubmit = false;

            $('#bogo_store_id')
                .prop('disabled', false)
                .find('option[data-temp]').remove();

            $('#bogo_store_id').val('').trigger('change');
            $('#bogo_add_store').text('{{ translate('messages.Add') }}');
        }

        $('#bogo_add_store').on('click', function () {
            const storeId = $('#bogo_store_id').val();

            if (!storeId) {
                toastr.error('{{ translate('messages.Please select a store') }}');
                return;
            }

            const payload = type => selected[type].map(i => ({
                item_id: i.item_id,
                quantity: i.quantity,
                variations: i.selected_variations,
                variation_options: [],
                add_on_ids: i.add_on_ids,
                add_on_qtys: i.add_on_qtys
            }));

            $.post({
                url: editingUrl || '{{ route('admin.bogo-offer.add-store', $offer->id) }}',
                data: {store_id: storeId, buy_items: payload('buy'), get_items: payload('get')},
                success: function () {
                    toastr.success(editingResubmit
                        ? '{{ translate('The request has been resent to the store') }}'
                        : '{{ translate('messages.store added to bogo offer') }}');
                    setTimeout(() => location.reload(), 1500);
                },
                error: function (xhr) {
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

        // The same counter the offer form uses. Bound here because this page does not
        // include _form_scripts, where that handler lives.
        $(document).on('input', '[data-counter]', function () {
            $('#' + $(this).data('counter')).text($(this).val().length);
        });

        $(document).on('click', '.reject-enrollment', function () {
            $('#reject-form').attr('action', $(this).data('url'));
            // One modal serves every row, so the reason typed for the last store - and
            // its count - has to be cleared before it opens for the next one.
            $('#reject-form')[0].reset();
            $('#count_rejection_reason').text('0');
            $('#rejectModal').modal('show');
        });

        // Load the enrolment drawer on demand so the table stays light.
        $(document).on('click', '.enrollment-detail', function () {
            const target = $('#offcanvas__enrollment_detail');

            $('#enrollment-detail-body').html(
                '<div class="d-flex align-items-center justify-content-center h-100">' +
                '<div class="spinner-border text--primary" role="status"></div></div>'
            );
            target.addClass('open');
            $('#offcanvasOverlay').addClass('show');

            $.get($(this).data('url'), function (html) {
                $('#enrollment-detail-body').html(html);
            }).fail(function () {
                toastr.error('{{ translate('messages.Something went wrong') }}');
                target.removeClass('open');
                $('#offcanvasOverlay').removeClass('show');
            });
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
