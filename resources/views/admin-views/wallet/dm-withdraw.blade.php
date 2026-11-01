@extends('layouts.admin.app')

@section('title', translate('messages.Deliveryman withdraw transaction'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/withdraw.css') }}">
@endpush

@section('content')

@php
    $request_count = function ($n) {
        return translate('Requests') . ': ' . $n;
    };

    $is_filtered = request()->filled('search') || ($status !== 'all');

    $type_labels = [
        'manual' => translate('messages.Manual'),
        'disbursement' => translate('messages.Disbursement'),
        'adjustment' => translate('messages.Adjustment'),
    ];
@endphp

<div class="content container-fluid wdr">
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{ asset('public/assets/admin/img/outline/wallet.svg') }}" class="w--26" alt="">
            </span>
            <span>
                {{ translate('messages.Deliveryman withdraw transaction') }}
                <span class="badge badge-soft-dark ml-2" id="itemCount">{{ $withdraw_req->total() }}</span>
            </span>
        </h1>
        <p class="page-header-desc">{{ translate('Deliverymen asking to take their earnings out, and where each request stands.') }}</p>

        @include('admin-views.withdraw.partials._tabs', [
            'tab_route' => 'admin.transactions.delivery-man.withdraw_list',
        ])
    </div>

    {{-- One grouped query in the controller, not a count per tile. The strip
         describes the whole queue, so it ignores the tab and the search box —
         the badge on the heading tracks those. --}}
    @include('admin-views.withdraw.partials._summary-strip', [
        'lead_label' => translate('messages.Requests in this list'),
    ])

    <div class="card">
        <div class="card-header border-0 py-2">
            <div class="search--button-wrapper">
                @include('partials._table-head', [
                    'subtitle' => translate('Money deliverymen have asked to withdraw from their wallet. open a request to review the account details, then approve or deny it with a note.'),
                    'count' => null,
                ])

                <form class="search-form">
                    <div class="input-group input--group">
                        <input id="datatableSearch" name="search" type="search" value="{{ request('search') }}"
                               class="form-control h--40px"
                               placeholder="{{ translate('Ex') }}: {{ translate('Search deliveryman name') }}"
                               aria-label="{{ translate('Search') }}">
                        <button type="submit" class="btn btn--secondary h--40px"><i class="tio-search"></i></button>
                    </div>
                    {{-- The tabs reload with their own query string, so the search
                         box has to carry the status or searching would drop it. --}}
                    @if($status !== 'all')
                        <input type="hidden" name="status" value="{{ $status }}">
                    @endif
                </form>

                @if(request()->filled('search'))
                    <a href="{{ route('admin.transactions.delivery-man.withdraw_list', ['status' => $status]) }}" class="btn btn--reset ml-2">
                        <i class="tio-refresh"></i> {{ translate('messages.Reset') }}
                    </a>
                @endif

                @include('admin-views.withdraw.partials._export-dropdown', [
                    'export_route' => 'admin.transactions.delivery-man.withdraw_export',
                ])
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive datatable-custom">
                <table id="datatable"
                       class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('messages.Request ID') }}</th>
                            <th>{{ translate('Deliveryman') }}</th>
                            <th>{{ translate('messages.Withdraw method') }}</th>
                            <th class="col--numeric">{{ translate('Amount') }}</th>
                            <th class="col--numeric">{{ translate('Wallet balance') }}</th>
                            <th>{{ translate('messages.Request time') }}</th>
                            <th>{{ translate('messages.Status') }}</th>
                            <th class="text-center">{{ translate('messages.Action') }}</th>
                        </tr>
                    </thead>
                    <tbody id="set-rows">
                        @foreach($withdraw_req as $wr)
                            @php
                                $method_name = $wr->type === 'disbursement'
                                    ? $wr->disbursementMethod?->method_name
                                    : $wr->method?->method_name;
                                $method_fields = json_decode($wr->withdrawal_method_fields ?? '', true) ?: [];
                                $account_number = $method_fields['account_number'] ?? null;
                                $account_name = $method_fields['account_name'] ?? null;
                                $account = is_scalar($account_number) && trim((string) $account_number) !== ''
                                    ? '•••• '.substr((string) $account_number, -4)
                                    : (is_scalar($account_name) ? $account_name : null);
                                $balance = $wr->deliveryman?->wallet?->balance;
                            @endphp
                            <tr>
                                <td>
                                    <span class="wdr-id">
                                        <span class="wdr-id__num">#{{ $wr->id }}</span>
                                        <span class="wdr-id__type">{{ $type_labels[$wr->type] ?? $wr->type }}</span>
                                    </span>
                                </td>
                                <td>
                                    @include('admin-views.withdraw.partials._payee-cell', [
                                        'payee_url' => $wr->deliveryman ? route('admin.users.delivery-man.preview', [$wr->deliveryman->id]) : null,
                                        'payee_avatar' => $wr->deliveryman?->image_full_url,
                                        'payee_name' => trim(($wr->deliveryman?->f_name ?? '').' '.($wr->deliveryman?->l_name ?? '')),
                                        'payee_sub' => $wr->deliveryman?->phone,
                                        'payee_store' => false,
                                        'payee_gone' => translate('messages.Deliveryman deleted'),
                                    ])
                                </td>
                                <td>
                                    @if($method_name || $account)
                                        <span class="wdr-method">
                                            <span class="wdr-method__name">{{ $method_name ?: $account }}</span>
                                            @if($method_name && $account)
                                                <span class="wdr-method__acc">{{ $account }}</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="wdr-none">—</span>
                                    @endif
                                </td>
                                <td class="col--numeric">
                                    <span class="wdr-amount">{{ \App\CentralLogics\Helpers::format_currency($wr['amount']) }}</span>
                                </td>
                                <td class="col--numeric">
                                    @if($balance !== null)
                                        <span class="wdr-balance">{{ \App\CentralLogics\Helpers::format_currency($balance) }}</span>
                                    @else
                                        <span class="wdr-none">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="wdr-when">
                                        {{ \App\CentralLogics\Helpers::time_date_format($wr->created_at) }}
                                        {{-- How long it has been waiting is the thing an
                                             approval queue is actually sorted by. --}}
                                        <span class="wdr-when__ago">{{ $wr->created_at?->diffForHumans() }}</span>
                                    </span>
                                </td>
                                <td>
                                    <span class="wdr-status">
                                        @include('admin-views.withdraw.partials._status-pill', ['approved' => $wr->approved])
                                        @if((int) $wr->approved !== 0 && $wr->updated_at)
                                            <span class="wdr-status__when">{{ translate('messages.Processed') }}: {{ \App\CentralLogics\Helpers::time_date_format($wr->updated_at) }}</span>
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    <div class="btn--container justify-content-center">
                                        @include('admin-views.withdraw.partials._row-action', [
                                            'withdraw_id' => $wr->id,
                                            'payee_present' => (bool) $wr->deliveryman,
                                            'disabled_hint' => translate('This deliveryman has been removed'),
                                        ])
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if(count($withdraw_req) === 0)
                    @include('admin-views.withdraw.partials._empty', [
                        'empty_title' => translate('messages.No withdraw request found'),
                        'empty_body' => $is_filtered
                            ? translate('messages.Nothing matches this filter. Try another status or clear the search.')
                            : translate('Requests appear here as soon as a deliveryman asks to withdraw their earnings.'),
                    ])
                @endif
            </div>
        </div>

        <div class="page-area">
            {!! $withdraw_req->links() !!}
        </div>
    </div>
</div>

@include('admin-views.withdraw.partials._request-panel')

@endsection

@push('script_2')
    <script>
        "use strict";
        $(document).on('click', '.withdraw-info-hide, .withdraw-info-sidebar-overlay', function () {
            $('.withdraw-info-sidebar, .withdraw-info-sidebar-overlay').removeClass('show');
        });

        $(document).on('click', '.withdraw-info-show', function() {
            let id = $(this).data('id');
            fetch_data(id)
        })


        $(document).on('click', '.show-approve-view', function() {
            let id = $(this).data('id');
            let url = "{{ route('admin.transactions.delivery-man.withdraw_status', ['data_id']) }}";
            url = url.replace('data_id', id);
            let htmlContent = `
            <form  class="withdraw_status_form" action="${url}" method="POST">
                    @csrf
                <div class="mt-5">
                    <h5 class="font-semibold text-center mb-3">{{ translate('Approval note') }} </h5>
                    <textarea required name="note" id="" class="form-control" rows="6" maxlength="200" placeholder="{{ translate('Type a note about request approval') }}"></textarea>
                    <input name="approved" value="1" type="hidden">
                    <div class="mt-4 d-flex justify-content-center gap-3">
                        <button type="button"  data-id="${id}" class="btn btn-soft-secondary min-w-100px withdraw-info-show">
                            <i class="tio-arrow-backward"></i>
                            {{ translate('Back') }}
                        </button>
                        <button type="submit" class="btn btn-success set_disable min-w-100px"><i class="tio-checkmark-circle-outlined"></i> {{ translate('Complete') }}</button>
                    </div>
                </div>
              </form>`
            $('#data-view').empty().html(htmlContent);
        });

        $(document).on('click', '.show-deny-view', function() {
            let id = $(this).data('id');
            let url = "{{ route('admin.transactions.delivery-man.withdraw_status', ['data_id']) }}";
            url = url.replace('data_id', id);
            let htmlContent = `
            <form class="withdraw_status_form" action="${url}" method="POST">
                    @csrf
                <div class="mt-5">
                    <h5 class="font-semibold text-center mb-3">{{ translate('Denial note') }} </h5>
                    <textarea required name="note" id="" class="form-control" rows="6" placeholder="{{ translate('Type a note about request denial') }}"></textarea>
                    <input name="approved" value="2" type="hidden">
                    <div class="mt-4 d-flex justify-content-center gap-3">
                        <button type="button"  data-id="${id}" class="btn btn-soft-secondary min-w-100px withdraw-info-show">
                            <i class="tio-arrow-backward"></i>
                            {{ translate('Back') }}
                        </button>
                        <button type="submit" class="btn btn-success set_disable min-w-100px"><i class="tio-checkmark-circle-outlined"></i> {{ translate('Complete') }}</button>
                    </div>
                </div>
              </form>`
            $('#data-view').empty().html(htmlContent);
        });

        function fetch_data(id) {
            $.ajax({
                url: "{{ route('admin.transactions.delivery-man.getWithdrawDetails') }}" + '?withdraw_id=' + id,
                type: "get",
                beforeSend: function() {
                    $('#data-view').empty();
                    $('#loading').show()
                },
                success: function(data) {
                    $('.withdraw-info-sidebar, .withdraw-info-sidebar-overlay').addClass('show');
                    $("#data-view").append(data.view);
                },
                complete: function() {
                    $('#loading').hide()
                }
            })
        }

        $(document).on('submit', '.withdraw_status_form', function(event) {
            $(this).find('button[type="submit"]').attr('disabled', true);
        });
    </script>
@endpush
