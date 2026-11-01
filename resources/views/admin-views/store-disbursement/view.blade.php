@extends('layouts.admin.app')

@section('title',translate('Disbursement details'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/disbursement.css') }}">
@endpush

@section('content')

@php
    $isProviderContext = addon_published_status('Rental') || addon_published_status('Service');
    $storeSlashProvider = $isProviderContext ? translate('messages.Store') . '/' . translate('messages.Provider') : translate('messages.Store');

    $payout_count = function ($n) {
        return translate('Vendor payouts') . ': ' . $n;
    };

    $total_payouts = $payout_summary->sum('payouts');

    $is_filtered = request()->filled('search')
        || is_numeric(request('store_id'))
        || is_numeric(request('module_id'))
        || is_numeric(request('payment_method_id'));
@endphp

<div class="content container-fluid sdb">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('/public/assets/admin/img/report/new/disburstment.png')}}" class="w--22" alt="">
                </span>
                <span>{{ translate('Disbursement details') }}</span>
            </h1>
            <p class="page-header-desc">{{ translate('The orders that make up this payout, and where the money is on its way to.') }}</p>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('admin.transactions.store-disbursement.list', ['status' => 'all']) }}" class="btn btn--reset">
                <i class="tio-arrow-backward"></i> {{ translate('Back') }}
            </a>
        </div>
    </div>

    @include('admin-views.disbursement.partials._run-header')

    {{-- One grouped query in the controller. It breaks down the whole run, so
         it deliberately ignores the pickers below — the count badge on the
         table card tracks those. --}}
    @include('admin-views.disbursement.partials._summary-strip', [
        'lead_value' => $total_payouts,
        'lead_label' => translate('messages.Vendor payouts in this run'),
        'released_label' => translate('messages.Released to vendors'),
    ])

    <div class="card">
        {{-- Scoping controls for the whole table, so they get their own row
             rather than crowding the title/search/export line. --}}
        <div class="sdb-filters">
            <span class="sdb-filters__label"><i class="tio-filter-list"></i> {{ translate('Filter by') }}</span>

            <div class="sdb-filters__field">
                <select name="module_id" class="form-control js-select2-custom set-filter"
                        data-url="{{ request()->fullUrlWithQuery(['store_id' => null]) }}" data-filter="module_id"
                        title="{{ translate('messages.Select modules') }}">
                    <option value="" {{ !request('module_id') ? 'selected' : '' }}>{{ translate('All modules') }}</option>
                    @php
                        $excludedModuleTypes = ['ride-share'];
                        if (! addon_published_status('Rental')) {
                            $excludedModuleTypes[] = 'rental';
                        }
                        if (! addon_published_status('Service')) {
                            $excludedModuleTypes[] = 'service';
                        }
                    @endphp
                    @foreach (\App\CentralLogics\Helpers::modules_list()->where('module_type', '!=', 'parcel')->whereNotIn('module_type', $excludedModuleTypes) as $module)
                        <option value="{{ $module->id }}" {{ request('module_id') == $module->id ? 'selected' : '' }}>
                            {{ $module['module_name'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="sdb-filters__field">
                <select name="store_id"
                        data-placeholder="{{ $isProviderContext ? translate('Select store') . '/' . translate('Provider') : translate('Select store') }}"
                        class="js-data-example-ajax form-control store-filter" data-url="{{ url()->full() }}">
                    @if (isset($store))
                        <option value="{{ $store->id }}" data-verified="{{ (int) $store->verified_seller }}" selected>{{ $store->name }}</option>
                    @else
                        <option value="all" selected>{{ $isProviderContext ? translate('All stores') . '/' . translate('Provider') : translate('All stores') }}</option>
                    @endif
                </select>
            </div>

            <div class="sdb-filters__field">
                <select name="payment_method_id" data-url="{{ url()->current() }}"
                        data-placeholder="{{ translate('Select payment method') }}"
                        class="js-select2-custom form-control payment-method-filter">
                    <option value="all">{{ translate('messages.All payment methods') }}</option>
                    @foreach (\App\CentralLogics\Helpers::cached_list(\App\Models\WithdrawalMethod::class, ['is_active' => 1]) as $method)
                        <option value="{{ $method['id'] }}"
                            {{ isset($payment_method_id) && is_numeric($payment_method_id) && ($payment_method_id == $method['id']) ? 'selected' : '' }}>
                            {{ $method['method_name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="card-header border-0 py-2">
            <div class="search--button-wrapper">
                @include('partials._table-head', [
                    'title' => translate('messages.Vendor payouts'),
                    'subtitle' => translate('messages.Every vendor paid in this run. Release or cancel a payout individually, or tick several and act on them at once.'),
                    'count' => $disbursement_stores->total(),
                    'count_id' => 'countItems',
                ])

                <form class="search-form">
                    <div class="input--group input-group input-group-merge input-group-flush">
                        <input class="form-control" type="search" name="search" value="{{ request('search') }}"
                               placeholder="{{ translate('Search by store information') }}">
                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>
                </form>

                @include('admin-views.disbursement.partials._export-dropdown', [
                    'export_route' => 'admin.transactions.store-disbursement.export',
                ])

                @include('admin-views.disbursement.partials._bulk-tray')
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th class="w--30px">
                                <label class="form-check form--check mb-0">
                                    <input type="checkbox" id="select-all" class="form-check-input" aria-label="{{ translate('Select all') }}">
                                </label>
                            </th>
                            <th>{{ translate('Payout ID') }}</th>
                            <th>{{ $storeSlashProvider }} {{ translate('Information') }}</th>
                            <th>{{ translate('Module') }}</th>
                            <th class="col--numeric">{{ translate('Disburse amount') }}</th>
                            <th>{{ translate('Payment method') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th class="text-center">{{ translate('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($disbursement_stores as $detail)
                            @php
                                $method_fields = json_decode($detail->withdraw_method?->method_fields ?? '', true) ?: [];
                                $account_number = $method_fields['account_number'] ?? null;
                                $account_name = $method_fields['account_name'] ?? null;
                                $account = is_scalar($account_number) && trim((string) $account_number) !== ''
                                    ? '•••• '.substr((string) $account_number, -4)
                                    : (is_scalar($account_name) ? $account_name : null);
                            @endphp
                            <tr>
                                <td>
                                    <label class="form-check form--check mb-0">
                                        <input type="checkbox" name="store_ids[]" class="form-check-input rest-check" value="{{ $detail->store_id }}"
                                               aria-label="{{ translate('Select') }}">
                                    </label>
                                </td>
                                <td><span class="sdb-id">#{{ $detail->id }}</span></td>
                                <td>
                                    @include('admin-views.disbursement.partials._payee-cell', [
                                        'payee_url' => $detail->store ? route('admin.store.view', $detail->store->id) : null,
                                        'payee_avatar' => $detail->store?->logo_full_url,
                                        'payee_name' => $detail->store?->name,
                                        'payee_sub' => $detail->store?->phone ?: $detail->store?->vendor?->email,
                                        'payee_person' => false,
                                    ])
                                </td>
                                <td>
                                    @if($detail->store?->module)
                                        <span class="sdb-module">{{ $detail->store->module->module_name }}</span>
                                    @else
                                        <span class="sdb-none">—</span>
                                    @endif
                                </td>
                                <td class="col--numeric">
                                    <span class="sdb-amount">{{ \App\CentralLogics\Helpers::format_currency($detail['disbursement_amount']) }}</span>
                                </td>
                                <td>
                                    <span class="sdb-method">
                                        <i class="tio-credit-card"></i>
                                        <span class="sdb-method__text">
                                            <span class="sdb-method__name">{{ $detail->withdraw_method?->method_name ?? translate('messages.N/A') }}</span>
                                            @if($account)
                                                <span class="sdb-method__acc">{{ $account }}</span>
                                            @endif
                                        </span>
                                    </span>
                                </td>
                                <td>
                                    <span class="sdb-status">
                                        @include('admin-views.disbursement.partials._status-pill', ['status' => $detail->status])
                                        @if($detail->status !== 'pending' && $detail->updated_at)
                                            <span class="sdb-status__when">{{ translate('Processed') }}: {{ \App\CentralLogics\Helpers::time_date_format($detail->updated_at) }}</span>
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    @include('admin-views.disbursement.partials._row-actions', [
                                        'status_route' => 'admin.transactions.store-disbursement.change-status',
                                    ])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if(count($disbursement_stores) === 0)
                    @include('admin-views.disbursement.partials._empty', [
                        'empty_title' => translate('messages.No payout found'),
                        'empty_body' => $is_filtered
                            ? translate('messages.Nothing matches this filter. Try another store, module or payment method.')
                            : translate('messages.This run holds no vendor payouts.'),
                    ])
                @endif
            </div>
        </div>

        <div class="page-area">
            {!! $disbursement_stores->links() !!}
        </div>
    </div>
</div>

{{-- Modals live outside the table. Inside a `<tr>` the parser foster-parents
     them out of the table anyway, which is what used to leave them stranded
     between the rows. --}}
@foreach($disbursement_stores as $detail)
    @include('admin-views.disbursement.partials._payment-modal', [
        'subject_label' => $storeSlashProvider . ' ' . translate('Information'),
        'subject_name' => $detail->store?->name,
        'subject_phone' => $detail->store?->phone,
        'owner_name' => trim(($detail->store?->vendor?->f_name ?? '') . ' ' . ($detail->store?->vendor?->l_name ?? '')),
        'owner_email' => $detail->store?->vendor?->email,
        'status_route' => 'admin.transactions.store-disbursement.change-status',
    ])
@endforeach

@endsection

@push('script_2')
    <script>
        "use strict";
        $(document).ready(function() {
            let disbursement_id = sessionStorage.getItem('disbursement_id');
            if(disbursement_id && (disbursement_id != {{$disbursement->id}})){
                sessionStorage.removeItem('selectedValues');
                sessionStorage.removeItem('selectedDmValues');
                sessionStorage.removeItem('disbursement_id');
            }
            let storedValues = sessionStorage.getItem('selectedValues');
            // Initialize as an array
            let checkedValues = storedValues ? JSON.parse(storedValues) : [];

            let storeIds = {{ $store_ids }};

            function renderSelection() {
                if (checkedValues.length > 0) {
                    $('#action-section').show();
                    $('.action-btn-section').hide();
                } else {
                    $('#action-section').hide();
                    $('.action-btn-section').show();
                }
                let $count = $('#selected-count');
                $count.text(String($count.data('label')) + ': ' + checkedValues.length);
            }

            renderSelection();

            if ((checkedValues.length > 0) && (checkedValues.length == storeIds.length)) {
                $('#select-all').prop('checked', true);
            }

            $('.rest-check').each(function() {
                let checkboxValue = parseInt($(this).val());
                if (checkedValues.includes(checkboxValue)) {
                    $(this).prop('checked', true);
                }
            });


            $('#select-all').on('click', function() {
                if (this.checked) {
                    $('.rest-check').each(function() {
                        this.checked = true;
                    });
                    checkedValues = storeIds;
                } else {
                    $('.rest-check').each(function() {
                        this.checked = false;
                    });
                    checkedValues = [];
                }
                saveSelectedValues();
            });

            $('.rest-check').on('click', function() {
                if ($('.rest-check:checked').length == $('.rest-check').length) {
                    $('#select-all').prop('checked', true);
                } else {
                    $('#select-all').prop('checked', false);
                }
                let value = parseInt($(this).val());
                if (this.checked) {
                    // Add the value to the array when the checkbox is checked
                    checkedValues.push(value);
                } else {
                    // Remove the value from the array when the checkbox is unchecked
                    let index = checkedValues.indexOf(value);
                    if (index !== -1) {
                        checkedValues.splice(index, 1);
                    }
                }
                saveSelectedValues();
            });

            function saveSelectedValues() {
                renderSelection();
                // Store the selected values in sessionStorage as a JSON string
                sessionStorage.setItem('selectedValues', JSON.stringify(checkedValues));
                sessionStorage.setItem('disbursement_id', {{$disbursement->id}});
            }

            $('#complete').on('click', function() {
                $.get({
                    url: '{{ route('admin.transactions.store-disbursement.status') }}',
                    dataType: 'json',
                    data: {
                        disbursement_id: {{$disbursement->id}},
                        store_ids: checkedValues,
                        status: 'completed'
                    },
                    beforeSend: function() {
                        $('#loading').show();
                    },
                    success: function(response) {
                        checkedValues = [];
                        saveSelectedValues();
                        if (response.status == 'error') {
                            toastr.error(response.message, {
                                CloseButton: true,
                                ProgressBar: true
                            });
                        }else if(response.status == 'success'){
                            toastr.success(response.message, {
                                CloseButton: true,
                                ProgressBar: true
                            });
                            location.reload();
                        }

                    },
                    complete: function() {
                        $('#loading').hide();
                    },
                });
            });
            $('#cancel').on('click', function() {
                $.get({
                    url: '{{ route('admin.transactions.store-disbursement.status') }}',
                    dataType: 'json',
                    data: {
                        disbursement_id: {{$disbursement->id}},
                        store_ids: checkedValues,
                        status: 'canceled'
                    },
                    beforeSend: function() {
                        $('#loading').show();
                    },
                    success: function(response) {
                        if (response.status == 'error') {
                            toastr.error(response.message, {
                                CloseButton: true,
                                ProgressBar: true
                            });
                        }else if(response.status == 'success'){
                            checkedValues = [];
                            saveSelectedValues();
                            toastr.success(response.message, {
                                CloseButton: true,
                                ProgressBar: true
                            });
                            location.reload();
                        }

                    },
                    complete: function() {
                        $('#loading').hide();
                    },
                });
            });




            $('.js-data-example-ajax').select2({
                ajax: {
                    url: '{{ route('admin.store.get-stores') }}',
                    data: function(params) {
                        return {
                            q: params.term, // search term
                            all:true,
                            include_addon_providers: 1, // rental/service providers are disbursed too
                            @if (isset($zone))
                                zone_ids: [{{ $zone->id }}],
                            @endif
                            @if (request('module_id'))
                            module_id: {{ request('module_id') }},
                            @endif

                            page: params.page
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data
                        };
                    },
                    __port: function(params, success, failure) {
                        let $request = $.ajax(params);

                        $request.then(success);
                        $request.fail(failure);

                        return $request;
                    }
                }
            });

        });

    </script>
@endpush
