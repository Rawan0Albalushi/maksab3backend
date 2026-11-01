@extends('layouts.admin.app')

@section('title',translate('messages.Collect cash transaction'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/cash.css') }}">
@endpush

@section('content')

@php
    // When the Rental/Service addon is published, cash can be collected from providers too.
    $isProviderContext = addon_published_status('Rental') || addon_published_status('Service');
    $storeSlashProvider = $isProviderContext ? translate('messages.Store') . '/' . translate('messages.Provider') : translate('messages.Store');
    $ride_on = addon_published_status('RideShare');

    // Literal maps, never translate($at['from_type']) — a variable inside
    // translate() writes whatever it is handed into the language file (§8).
    $source_labels = [
        'store' => translate('messages.vendor'),
        'deliveryman' => translate('Deliveryman'),
        'rider' => translate('Rider'),
    ];
    $source_icons = [
        'store' => 'tio-shop-outlined',
        'deliveryman' => 'tio-user-outlined',
        'rider' => 'tio-bike',
    ];

    // `ref` mixes two things: free text an admin typed, and three constants the
    // app itself writes (see VendorService / DeliveryManWalletService). Map the
    // constants to real sentences; print anything else verbatim, because a
    // variable inside translate() writes it into the language file (§8).
    $creator_labels = [
        'admin' => translate('messages.admin'),
        'store' => translate('messages.vendor'),
        'deliveryman' => translate('Deliveryman'),
        'rider' => translate('Rider'),
    ];

    $ref_labels = [
        'store_collect_cash_payments' => translate('messages.Vendor cash collection'),
        'deliveryman_collect_cash_payments' => translate('Deliveryman cash collection'),
        'rider_collect_cash_payments' => translate('messages.Rider cash collection'),
    ];

    $from_store = $summary['store'] ?? null;
    $from_dm = $summary['deliveryman'] ?? null;
    $from_rider = $summary['rider'] ?? null;

    $tiles = [
        [
            'icon' => 'tio-money', 'tone' => 'in',
            'value' => \App\CentralLogics\Helpers::format_currency((float) $summary->sum('amount')),
            'label' => translate('messages.Collected all time'),
        ],
        [
            'icon' => 'tio-shop-outlined',
            'value' => \App\CentralLogics\Helpers::format_currency((float) ($from_store?->amount ?? 0)),
            'label' => translate('messages.From vendors'),
        ],
        [
            'icon' => 'tio-user-outlined', 'tone' => 'info',
            'value' => \App\CentralLogics\Helpers::format_currency((float) ($from_dm?->amount ?? 0)),
            'label' => translate('From deliverymen'),
        ],
    ];
    // The tile only makes sense while RideShare is published; the strip is
    // auto-fit, so dropping it reflows the rest on its own.
    if ($ride_on) {
        $tiles[] = [
            'icon' => 'tio-bike', 'tone' => 'out',
            'value' => \App\CentralLogics\Helpers::format_currency((float) ($from_rider?->amount ?? 0)),
            'label' => translate('messages.From riders'),
        ];
    }
@endphp

<div class="content container-fluid csh">
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{asset('public/assets/admin/img/outline/collect-cash.svg')}}" class="w--26" alt="">
            </span>
            <span>
                {{ translate('messages.Collect cash transaction') }}
                <span class="badge badge-soft-dark ml-2" id="itemCount">{{ $account_transaction->total() }}</span>
            </span>
        </h1>
        <p class="page-header-desc">{{ translate('Record cash a deliveryman or store has handed over, and see what is still outstanding.') }}</p>
    </div>

    @include('admin-views.cash.partials._summary-strip')

    {{-- The composer. It is the thing this screen exists for, so it says what
         it does rather than being an unlabelled card of inputs. --}}
    <form action="{{ route('admin.transactions.account-transaction.store') }}" method="post" id="add_transaction" class="csh-form">
        @csrf
        <div class="csh-form__head">
            <span class="csh-form__icon"><i class="tio-money"></i></span>
            <span class="csh-form__text">
                <span class="csh-form__title">{{ translate('messages.Record a cash collection') }}</span>
                <span class="csh-form__subtitle">{{ translate('messages.Pick who handed the cash over, then enter what you received. The balance beside the amount shows what they are still holding.') }}</span>
            </span>
        </div>

        <div class="csh-form__body">
            <div class="row g-3">
                <div class="col-lg-4 col-sm-6">
                    <div class="form-group mb-0">
                        <label class="form-label" for="type">{{ translate('messages.Collect from') }}</label>
                        <select name="type" id="type" class="form-control">
                            <option value="deliveryman">{{ translate('Deliveryman') }}</option>
                            @if($ride_on)
                                <option value="rider">{{ translate('Rider') }}</option>
                            @endif
                            <option value="store">{{ $storeSlashProvider }}</option>
                        </select>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="form-group mb-0">
                        <label class="form-label" for="store">{{ $storeSlashProvider }}</label>
                        <select id="store" name="store_id" data-placeholder="{{ $isProviderContext ? translate('Select store') . '/' . translate('Provider') : translate('Select store') }}" class="form-control" disabled></select>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="form-group mb-0">
                        <label class="form-label" for="deliveryman">{{ translate('Deliveryman') }}</label>
                        <select id="deliveryman" name="deliveryman_id" data-placeholder="{{ translate('Select deliveryman') }}" class="form-control"></select>
                    </div>
                </div>
                @if($ride_on)
                    <div class="col-lg-4 col-sm-6">
                        <div class="form-group mb-0">
                            <label class="form-label" for="rider">{{ translate('Rider') }}</label>
                            <select id="rider" name="rider_id" data-placeholder="{{ translate('messages.Select rider') }}" class="form-control" disabled></select>
                        </div>
                    </div>
                @endif
                <div class="col-lg-4 col-sm-6">
                    <div class="form-group mb-0">
                        <label class="form-label" for="method">{{ translate('messages.Payment method') }}</label>
                        <input class="form-control" type="text" name="method" id="method" required maxlength="191" placeholder="{{ translate('messages.Ex') }}: {{ translate('messages.Card') }}">
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="form-group mb-0">
                        <label class="form-label" for="ref">{{ translate('messages.reference') }}</label>
                        <input class="form-control" type="text" name="ref" id="ref" maxlength="191">
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="form-group mb-0">
                        <label class="form-label" for="amount">
                            {{ translate('Amount') }} {{ \App\CentralLogics\Helpers::currency_symbol() }}
                            <span class="input-label-secondary" id="account_info"></span>
                        </label>
                        <input class="form-control" type="number" min=".01" step="0.01" name="amount" id="amount" max="999999999999.99" placeholder="{{ translate('messages.Ex') }}: 1000">
                    </div>
                </div>
            </div>
        </div>

        <div class="csh-form__foot">
            <button class="btn btn--reset" type="reset" id="reset_btn"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
            <button class="btn btn--primary" type="submit"><i class="tio-money"></i> {{ translate('messages.Collect cash') }}</button>
        </div>
    </form>

    <div class="card">
        <div class="card-header py-2 border-0">
            <div class="search--button-wrapper">
                @include('partials._table-head', [
                    'title' => translate('Transaction history'),
                    'subtitle' => translate('messages.Every cash hand-over recorded above, newest first.'),
                    'count' => $account_transaction->total(),
                    'count_id' => 'transactionCount',
                ])

                <form class="search-form theme-style">
                    <div class="input-group input--group">
                        <input id="datatableSearch" name="search" type="search" class="form-control h--40px"
                               placeholder="{{ translate('Ex') }}: {{ translate('messages.reference') }}"
                               value="{{ request('search') }}" aria-label="{{ translate('Search') }}">
                        <button type="submit" class="btn btn--secondary h--40px"><i class="tio-search"></i></button>
                    </div>
                </form>

                @if(request()->filled('search'))
                    <a href="{{ route('admin.transactions.account-transaction.index') }}" class="btn btn--reset ml-2">
                        <i class="tio-refresh"></i> {{ translate('messages.Reset') }}
                    </a>
                @endif

                @include('admin-views.cash.partials._export-dropdown', [
                    'export_route' => 'admin.transactions.account-transaction.export',
                ])
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive datatable-custom">
                <table id="datatable" class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('messages.Transaction ID') }}</th>
                            <th>{{ translate('messages.Collect from') }}</th>
                            <th>{{ translate('Type') }}</th>
                            <th>{{ translate('messages.Payment method') }}</th>
                            <th>{{ translate('Received at') }}</th>
                            <th class="col--numeric">{{ translate('Amount') }}</th>
                            <th>{{ translate('messages.reference') }}</th>
                            <th class="text-center">{{ translate('messages.Action') }}</th>
                        </tr>
                    </thead>
                    <tbody id="set-rows">
                        @foreach($account_transaction as $at)
                            @php
                                // One source per row; `from_type` is the column that says which.
                                $source = $at->store ?: ($at->deliveryman ?: ($ride_on ? $at->rider : null));
                                $is_store = (bool) $at->store;
                                $source_url = $at->store
                                    ? route('admin.store.view', [$at->store->id, 'module_id' => $at->store->module_id])
                                    : ($at->deliveryman
                                        ? route('admin.users.delivery-man.preview', [$at->deliveryman->id])
                                        : ($ride_on && $at->rider ? route('admin.users.rider.preview', [$at->rider->id]) : null));
                                $source_name = $is_store
                                    ? $at->store->name
                                    : trim(($source?->f_name ?? '').' '.($source?->l_name ?? ''));
                            @endphp
                            <tr>
                                <td><span class="csh-id">#{{ $at->id }}</span></td>
                                <td>
                                    @include('admin-views.cash.partials._payee-cell', [
                                        'payee_url' => $source_url,
                                        'payee_avatar' => $is_store ? $at->store->logo_full_url : $source?->image_full_url,
                                        'payee_name' => $source_name,
                                        'payee_sub' => $source?->phone,
                                        'payee_store' => $is_store,
                                        'payee_gone' => translate('No data found'),
                                    ])
                                </td>
                                <td>
                                    <span class="csh-type csh-type--{{ $at->from_type }}">
                                        <i class="{{ $source_icons[$at->from_type] ?? 'tio-user-outlined' }}"></i>
                                        {{ $source_labels[$at->from_type] ?? $at->from_type }}
                                    </span>
                                </td>
                                <td>
                                    <span class="csh-method"><i class="tio-credit-card"></i> {{ $at->method }}</span>
                                </td>
                                <td>
                                    <span class="csh-when">
                                        {{ \App\CentralLogics\Helpers::time_date_format($at->created_at) }}
                                        @if(filled($at->created_by))
                                            <span class="csh-when__by">{{ translate('messages.Recorded by') }}: {{ $creator_labels[$at->created_by] ?? $at->created_by }}</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="col--numeric">
                                    <span class="csh-amount csh-amount--in">{{ \App\CentralLogics\Helpers::format_currency($at['amount']) }}</span>
                                </td>
                                <td>
                                    {{-- A reference is free text an admin typed. It used to go through
                                         translate($at['ref']), which wrote every reference anyone had
                                         ever entered into the language file (§8: it is data — print it). --}}
                                    @if(isset($ref_labels[$at->ref]))
                                        <span class="csh-ref csh-ref--none">{{ $ref_labels[$at->ref] }}</span>
                                    @elseif(filled($at->ref))
                                        <span class="csh-ref" title="{{ $at->ref }}">{{ $at->ref }}</span>
                                    @else
                                        <span class="csh-ref csh-ref--none">{{ translate('messages.No reference') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <a href="javascript:;"
                                           data-payment_method="{{ $at->method }}"
                                           data-ref="{{ $ref_labels[$at->ref] ?? $at->ref }}"
                                           data-amount="{{ \App\CentralLogics\Helpers::format_currency($at['amount']) }}"
                                           data-date="{{ \App\CentralLogics\Helpers::time_date_format($at->created_at) }}"
                                           data-type="{{ $at->from_type == 'deliveryman' ? translate('Deliveryman information') : ($at->from_type == 'rider' ? translate('Rider information') : ($storeSlashProvider . ' ' . translate('Information'))) }}"
                                           data-phone="{{ $source?->phone }}"
                                           data-address="{{ $is_store ? $at->store->address : ($source?->last_location?->location ?? translate('No data found')) }}"
                                           data-latitude="{{ $is_store ? $at->store->latitude : ($source?->last_location?->latitude ?? 0) }}"
                                           data-longitude="{{ $is_store ? $at->store->longitude : ($source?->last_location?->longitude ?? 0) }}"
                                           data-name="{{ $source_name !== '' ? $source_name : translate('No data found') }}"
                                           class="btn btn-sm action-btn action-btn--view withdraw-info-show"
                                           title="{{ translate('View details') }}">
                                            <i class="tio-visible-outlined"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if(count($account_transaction) === 0)
                    @include('admin-views.cash.partials._empty', [
                        'empty_title' => translate('messages.No transaction found'),
                        'empty_body' => request()->filled('search')
                            ? translate('messages.Nothing matches this search. Try another name or reference.')
                            : translate('messages.Cash you collect appears here the moment it is recorded above.'),
                    ])
                @endif
            </div>
        </div>

        <div class="page-area">
            {!! $account_transaction->links() !!}
        </div>
    </div>
</div>

{{-- `.csh` on the wrapper as well: the panel is a sibling of the page
     container, so the page's scope class does not reach it. --}}
<div class="sidebar-wrap csh">
    <div class="withdraw-info-sidebar-overlay"></div>
    <div class="withdraw-info-sidebar">
        <div class="csh-panel__head">
            <span class="csh-panel__title">{{ translate('Account transaction information') }}</span>
            <span class="circle bg-light withdraw-info-hide cursor-pointer" role="button" aria-label="{{ translate('Close') }}">
                <i class="tio-clear"></i>
            </span>
        </div>

        <div class="csh-panel__body">
            <div class="csh-panel__amount">
                <span class="csh-panel__amount-value" id="csh-amount"></span>
                <span class="csh-panel__amount-label">{{ translate('messages.Collected') }}</span>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0 font-medium">{{ translate('messages.Transaction') }}</h6>
                </div>
                <div class="card-body">
                    <div class="key-val-list d-flex flex-column gap-2" style="--min-width: 60px">
                        <div class="key-val-list-item d-flex gap-3">
                            <span>{{ translate('method') }}</span>
                            <span id="csh-method"></span>
                        </div>
                        <div class="key-val-list-item d-flex gap-3">
                            <span>{{ translate('Request time') }}</span>
                            <span id="csh-date"></span>
                        </div>
                        <div class="key-val-list-item d-flex gap-3">
                            <span>{{ translate('reference') }}</span>
                            <span id="csh-ref"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0 font-medium" id="csh-subject"></h6>
                </div>
                <div class="card-body">
                    <div class="key-val-list d-flex flex-column gap-2" style="--min-width: 60px">
                        <div class="key-val-list-item d-flex gap-3">
                            <span>{{ translate('Name') }}</span>
                            <span id="csh-name"></span>
                        </div>
                        <div class="key-val-list-item d-flex gap-3">
                            <span>{{ translate('Phone') }}</span>
                            <a href="tel:" id="csh-phone" class="text-dark"></a>
                        </div>
                        <div class="key-val-list-item d-flex gap-3">
                            <span>{{ translate('Address') }}</span>
                            <a id="csh-address" target="_blank"></a>
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
    $(document).on('click', '.withdraw-info-hide, .withdraw-info-sidebar-overlay', function () {
        $('.withdraw-info-sidebar, .withdraw-info-sidebar-overlay').removeClass('show');
    });

    $(document).on('click', '.withdraw-info-show', function () {
        let data = $(this).data();
        $('#csh-method').text(data.payment_method);
        $('#csh-amount').text(data.amount);
        $('#csh-subject').text(data.type);
        $('#csh-date').text(data.date);
        $('#csh-ref').text(data.ref || '{{ translate('messages.No reference') }}');
        $('#csh-name').text(data.name);
        $('#csh-phone').text(data.phone).attr('href', 'tel:' + data.phone);
        $('#csh-address').text(data.address).attr('href', "https://www.google.com/maps/search/?api=1&query=" + data.latitude + "," + data.longitude);

        $('.withdraw-info-sidebar, .withdraw-info-sidebar-overlay').addClass('show');
    });
</script>

<script src="{{asset('public/assets/admin')}}/js/view-pages/account-index.js"></script>
<script>
    "use strict";

    $('#store').select2({
        ajax: {
            url: '{{ route('admin.store.get-stores') }}',
            data: function (params) {
                return {
                    q: params.term, // search term
                    page: params.page,
                    include_addon_providers: 1 // include rental & service providers on collect cash
                };
            },
            processResults: function (data) {
                return {
                results: data
                };
            },
            __port: function (params, success, failure) {
                var $request = $.ajax(params);

                $request.then(success);
                $request.fail(failure);

                return $request;
            }
        }
    });

    $('#deliveryman').select2({
        ajax: {
            url: '{{url('/')}}/admin/users/delivery-man/get-deliverymen',
            data: function (params) {
                return {
                    q: params.term, // search term
                    page: params.page
                };
            },
            processResults: function (data) {
                return {
                results: data
                };
            },
            __port: function (params, success, failure) {
                var $request = $.ajax(params);

                $request.then(success);
                $request.fail(failure);

                return $request;
            }
        }
    });

    $('#rider').select2({
        ajax: {
            url: '{{url('/')}}/admin/users/rider/get-deliverymen',
            data: function (params) {
                return {
                    q: params.term, // search term
                    page: params.page
                };
            },
            processResults: function (data) {
                return {
                results: data
                };
            },
            __port: function (params, success, failure) {
                var $request = $.ajax(params);

                $request.then(success);
                $request.fail(failure);

                return $request;
            }
        }
    });

    // The chip beside the amount label reports what the selected payee is
    // still holding. No parentheses: `cash.css` gives it a pill of its own.
    function showAccountInfo(data) {
        $('#account_info').html(
            '{{translate('Cash in hand')}}: ' + data.cash_in_hand +
            ' · {{translate('messages.Total earning')}}: ' + data.earning_balance
        );
    }

    $('#store').on('change', function() {
        $.get({ url: '{{url('/')}}/admin/store/get-account-data/'+this.value, dataType: 'json', success: showAccountInfo });
    })

    $('#deliveryman').on('change', function() {
        $.get({ url: '{{url('/')}}/admin/users/delivery-man/get-account-data/'+this.value, dataType: 'json', success: showAccountInfo });
    })

    $('#rider').on('change', function() {
        $.get({ url: '{{url('/')}}/admin/users/rider/get-account-data/'+this.value, dataType: 'json', success: showAccountInfo });
    })

    $('#add_transaction').on('submit', function (e) {
        e.preventDefault();
        var formData = new FormData(this);
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        $.post({
            url: '{{route('admin.transactions.account-transaction.store')}}',
            data: formData,
            cache: false,
            contentType: false,
            processData: false,
            success: function (data) {
                if (data.errors) {
                    for (var i = 0; i < data.errors.length; i++) {
                        toastr.error(data.errors[i].message, {
                            CloseButton: true,
                            ProgressBar: true
                        });
                    }
                } else {
                    toastr.success('{{translate('messages.Transaction saved')}}', {
                        CloseButton: true,
                        ProgressBar: true
                    });
                    setTimeout(function () {
                        location.href = '{{route('admin.transactions.account-transaction.index')}}';
                    }, 2000);
                }
            }
        });
    });
</script>
@endpush
