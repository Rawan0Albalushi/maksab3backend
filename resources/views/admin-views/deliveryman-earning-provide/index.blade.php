@extends('layouts.admin.app')

@section('title',translate('messages.Provide deliverymen earning'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/cash.css') }}">
@endpush

@section('content')

@php
    // Literal maps, never translate($at['ref']) — a variable inside translate()
    // writes whatever it is handed into the language file (§8). These two are
    // the only refs the app itself writes; everything else is admin free text.
    $ref_labels = [
        'delivery_man_wallet_adjustment_full' => translate('messages.Wallet adjusted'),
        'delivery_man_wallet_adjustment_partial' => translate('messages.Wallet adjusted partially'),
    ];

    $all = $summary['all'];
    $month = $summary['month'];

    $tiles = [
        [
            'icon' => 'tio-money-vs', 'tone' => 'out',
            'value' => \App\CentralLogics\Helpers::format_currency((float) ($all?->amount ?? 0)),
            'label' => translate('messages.Paid out all time'),
        ],
        [
            'icon' => 'tio-calendar', 'tone' => 'info',
            'value' => \App\CentralLogics\Helpers::format_currency((float) ($month?->amount ?? 0)),
            'label' => translate('messages.Paid this month'),
        ],
        [
            'icon' => 'tio-receipt-outlined',
            'value' => (int) ($all?->payments ?? 0),
            'label' => translate('messages.Payments recorded'),
        ],
        [
            'icon' => 'tio-user-outlined', 'tone' => 'off',
            'value' => (int) ($all?->recipients ?? 0),
            'label' => translate('Deliverymen paid'),
        ],
    ];
@endphp

<div class="content container-fluid csh">
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{asset('public/assets/admin/img/outline/report.svg')}}" class="w--26" alt="">
            </span>
            <span>
                {{ translate('messages.Provide deliverymen earning') }}
                <span class="badge badge-soft-dark ml-2" id="itemCount">{{ $provide_dm_earning->total() }}</span>
            </span>
        </h1>
        <p class="page-header-desc">{{ translate('Hand over the cash a deliveryman has earned, and record that you have done it.') }}</p>
    </div>

    @include('admin-views.cash.partials._summary-strip')

    <form action="{{ route('admin.transactions.provide-deliveryman-earnings.store') }}" method="post" id="add_transaction" class="csh-form">
        @csrf
        <div class="csh-form__head">
            <span class="csh-form__icon"><i class="tio-money-vs"></i></span>
            <span class="csh-form__text">
                <span class="csh-form__title">{{ translate('Pay a deliveryman') }}</span>
                <span class="csh-form__subtitle">{{ translate('messages.Hand earnings over outside the disbursement schedule. The balance beside the amount shows what they are owed and still holding.') }}</span>
            </span>
        </div>

        <div class="csh-form__body">
            <div class="row g-3">
                <div class="col-sm-6">
                    <div class="form-group mb-0">
                        <label class="form-label" for="deliveryman">{{ translate('Deliveryman') }}</label>
                        <select id="deliveryman" name="deliveryman_id" data-placeholder="{{ translate('Select deliveryman') }}"
                                data-url="{{ url('/') }}/admin/users/delivery-man/get-account-data/" data-type="deliveryman"
                                class="form-control account-data"></select>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group mb-0">
                        <label class="form-label" for="amount">
                            {{ translate('Amount') }}
                            <span class="input-label-secondary" id="account_info"></span>
                        </label>
                        <input class="form-control" type="number" min="1" step="0.01" name="amount" id="amount" max="999999999999.99" placeholder="{{ translate('Ex') }}: 100">
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group mb-0">
                        <label class="form-label" for="method">{{ translate('messages.method') }}</label>
                        <input class="form-control" type="text" name="method" id="method" required maxlength="191" placeholder="{{ translate('Ex cash') }}">
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group mb-0">
                        <label class="form-label" for="ref">{{ translate('messages.reference') }}</label>
                        <input class="form-control" type="text" name="ref" id="ref" maxlength="191" placeholder="{{ translate('Ex collect cash') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="csh-form__foot">
            <button class="btn btn--reset" type="reset" id="reset_btn"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
            <button class="btn btn--primary" type="submit"><i class="tio-save"></i> {{ translate('messages.Save') }}</button>
        </div>
    </form>

    <div class="card">
        <div class="card-header py-2 border-0">
            <div class="search--button-wrapper">
                @include('partials._table-head', [
                    'title' => translate('messages.Payment history'),
                    'subtitle' => translate('messages.Every earning payment recorded above, newest first.'),
                    'count' => $provide_dm_earning->total(),
                    'count_id' => 'paymentCount',
                ])

                <form class="search-form">
                    <div class="input-group input--group">
                        <input id="datatableSearch" name="search" type="search" class="form-control h--40px"
                               placeholder="{{ translate('Ex') }}: {{ translate('Search by deliveryman') }}"
                               value="{{ request('search') }}" aria-label="{{ translate('Search') }}">
                        <button type="submit" class="btn btn--secondary h--40px"><i class="tio-search"></i></button>
                    </div>
                </form>

                @if(request()->filled('search'))
                    <a href="{{ route('admin.transactions.provide-deliveryman-earnings.index') }}" class="btn btn--reset ml-2">
                        <i class="tio-refresh"></i> {{ translate('messages.Reset') }}
                    </a>
                @endif

                @include('admin-views.cash.partials._export-dropdown', [
                    'export_route' => 'admin.transactions.export-deliveryman-earning',
                ])
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive datatable-custom">
                <table id="datatable" class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('messages.Payment ID') }}</th>
                            <th>{{ translate('Name') }}</th>
                            <th>{{ translate('messages.Zone') }}</th>
                            <th>{{ translate('Received at') }}</th>
                            <th class="col--numeric">{{ translate('Amount') }}</th>
                            <th>{{ translate('messages.method') }}</th>
                            <th>{{ translate('messages.reference') }}</th>
                        </tr>
                    </thead>
                    <tbody id="set-rows">
                        @foreach($provide_dm_earning as $at)
                            <tr>
                                <td><span class="csh-id">#{{ $at->id }}</span></td>
                                <td>
                                    @include('admin-views.cash.partials._payee-cell', [
                                        'payee_url' => $at->delivery_man ? route('admin.users.delivery-man.preview', $at->delivery_man_id) : null,
                                        'payee_avatar' => $at->delivery_man?->image_full_url,
                                        'payee_name' => trim(($at->delivery_man?->f_name ?? '').' '.($at->delivery_man?->l_name ?? '')),
                                        'payee_sub' => $at->delivery_man?->phone,
                                        'payee_store' => false,
                                        'payee_gone' => translate('messages.Deliveryman deleted'),
                                    ])
                                </td>
                                <td>
                                    @if($at->delivery_man?->zone?->name)
                                        <span class="csh-zone">{{ $at->delivery_man?->zone?->name }}</span>
                                    @else
                                        <span class="csh-none">—</span>
                                    @endif
                                </td>
                                <td>{{ \App\CentralLogics\Helpers::time_date_format($at->created_at) }}</td>
                                <td class="col--numeric">
                                    <span class="csh-amount csh-amount--out">{{ \App\CentralLogics\Helpers::format_currency($at['amount']) }}</span>
                                </td>
                                <td>
                                    <span class="csh-method"><i class="tio-credit-card"></i> {{ $at['method'] }}</span>
                                </td>
                                <td>
                                    @if(isset($ref_labels[$at['ref']]))
                                        <span class="csh-ref csh-ref--none">{{ $ref_labels[$at['ref']] }}</span>
                                    @elseif(filled($at['ref']))
                                        <span class="csh-ref" title="{{ $at['ref'] }}">{{ $at['ref'] }}</span>
                                    @else
                                        <span class="csh-ref csh-ref--none">{{ translate('messages.No reference') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if(count($provide_dm_earning) === 0)
                    @include('admin-views.cash.partials._empty', [
                        'empty_title' => translate('messages.No payment found'),
                        'empty_body' => request()->filled('search')
                            ? translate('messages.Nothing matches this search. Try another name.')
                            : translate('messages.Payments you record above appear here straight away.'),
                    ])
                @endif
            </div>
        </div>

        <div class="page-area">
            {!! $provide_dm_earning->links() !!}
        </div>
    </div>
</div>

@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin')}}/js/view-pages/deliveryman-earning-provide.js"></script>
<script>
    "use strict";

    $('#deliveryman').select2({
        ajax: {
            url: '{{url('/')}}/admin/users/delivery-man/get-deliverymen',
            data: function (params) {
                return {
                    q: params.term, // search term
                    earning: true,
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

    $('#add_transaction').on('submit', function (e) {
        e.preventDefault();
        var formData = new FormData(this);
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        $.post({
            url: '{{route('admin.transactions.provide-deliveryman-earnings.store')}}',
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
                        location.href = '{{route('admin.transactions.provide-deliveryman-earnings.index')}}';
                    }, 2000);
                }
            }
        });
    });

    // No parentheses: `cash.css` gives this chip a pill of its own.
    function getAccountData(route, data_id, type)
    {
        $.get({
            url: route+data_id,
            dataType: 'json',
            success: function (data) {
                $('#account_info').html(
                    '{{translate('Cash in hand')}}: '+data.cash_in_hand+
                    ' · {{translate('messages.Earning balance')}}: '+data.earning_balance
                );
            },
        });
    }
</script>
@endpush
