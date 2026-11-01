@php
    $walletTitle = $title === 'Provider'
        ? translate('messages.Provider wallet')
        : translate('messages.Store wallet');
@endphp

@extends('layouts.vendor.app')
@section('title', $walletTitle)

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/withdraw.css') }}">
@endpush

@php
    $type_labels = [
        'manual' => translate('messages.Manual'),
        'disbursement' => translate('messages.Disbursement'),
        'adjustment' => translate('messages.Adjustment'),
    ];
    $adjustment_notes = [
        'Store_wallet_adjustment_partial' => translate('Adjusted amount partially'),
        'Store_wallet_adjustment_full' => translate('Adjusted amount'),
    ];
@endphp

@section('content')
    <div class="content container-fluid wdr">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <img src="{{ asset('public/assets/admin/img/outline/wallet.svg') }}" class="w--26" alt="">
                        </span>
                        <span>
                            {{ $walletTitle }}
                        </span>
                    </h1>
                    <p class="page-header-desc">{{ translate('What you have earned, what has been paid out and what is still to come.') }}</p>
                </div>
            </div>
        </div>
        @include('vendor-views.wallet.partials._balance_data',['wallet'=>$wallet])

        <div class="card-body p-0">
            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('messages.Request ID') }}</th>
                            <th class="col--numeric">{{ translate('Amount') }}</th>
                            <th>{{ translate('messages.Withdraw method') }}</th>
                            <th>{{ translate('messages.Request time') }}</th>
                            <th>{{ translate('messages.Status') }}</th>
                            <th>{{ translate('messages.Note') }}</th>
                            <th class="text-center">{{ translate('messages.Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($withdraw_req as $wr)
                            @php($method_name = $wr->type === 'disbursement' ? $wr->disbursementMethod?->method_name : $wr->method?->method_name)
                            @php($method_fields = json_decode($wr->withdrawal_method_fields ?? '', true) ?: [])
                            @php($account_number = $method_fields['account_number'] ?? null)
                            @php($account_name = $method_fields['account_name'] ?? null)
                            @php($account = is_scalar($account_number) && trim((string) $account_number) !== ''
                                ? '•••• '.substr((string) $account_number, -4)
                                : (is_scalar($account_name) ? $account_name : null))
                            @php($note = $wr->transaction_note ? ($adjustment_notes[$wr->transaction_note] ?? $wr->transaction_note) : null)
                            <tr>
                                <td>
                                    <span class="wdr-id">
                                        <span class="wdr-id__num">#{{ $wr->id }}</span>
                                        <span class="wdr-id__type">{{ $type_labels[$wr->type] ?? $wr->type }}</span>
                                    </span>
                                </td>
                                <td class="col--numeric">
                                    <span class="wdr-amount">{{ \App\CentralLogics\Helpers::format_currency($wr->amount) }}</span>
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
                                        <span class="wdr-method">
                                            <span class="wdr-method__name">{{ translate('Default method') }}</span>
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span class="wdr-when">
                                        {{ \App\CentralLogics\Helpers::time_date_format($wr->created_at) }}
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
                                    @if($note)
                                        {!! Str::limit(e($note), 30, '&hellip; <a href="#" class="showMyModal" data-message="'.e($note).'">'.e(translate('messages.Read more')).'</a>') !!}
                                    @else
                                        <span class="wdr-none">&mdash;</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($wr->approved == 0)
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn action-btn--delete form-alert" href="javascript:" data-id="withdraw-{{ $wr->id }}" data-message="{{ translate('This withdraw request will be canceled and the amount returned to your withdrawable balance.') }}" title="{{ translate('messages.Cancel request') }}"><i class="tio-delete-outlined"></i></a>
                                        </div>
                                        <form action="{{ route('vendor.wallet.close-request', [$wr->id]) }}" method="post" id="withdraw-{{ $wr->id }}">
                                            @csrf @method('delete')
                                        </form>
                                    @else
                                        <span class="wdr-none">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if(count($withdraw_req) === 0)
                    @include('admin-views.withdraw.partials._empty', [
                        'empty_title' => translate('messages.No withdraw request yet'),
                        'empty_body' => translate('messages.Requests appear here as soon as you ask to withdraw your earnings.'),
                    ])
                @endif
            </div>
        </div>
        <div class="card-footer pt-0 border-0">
            {{$withdraw_req->links()}}
        </div>
    </div>

    <div class="modal fade" id="payment_model" tabindex="-1"  role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{translate('Pay via online')}}  </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>

                </div>
                <form action="{{ route('vendor.wallet.wallet_make_payment') }}" method="POST" class="needs-validation">
                    <div class="modal-body">
                        @csrf
                        <input type="hidden" value="{{ $store_id }}" name="store_id"/>
                        <input type="hidden" value="{{ abs($wallet->collected_cash) }}" name="amount"/>
                        <h5 class="mb-5 ">{{ translate('Pay via online') }} &nbsp; <small>({{ translate('Faster & secure way to pay bill') }})</small></h5>
                        <div class="row g-3">
                            @forelse ($data as $item)
                                <div class="col-sm-6">
                                    <div class="d-flex gap-3 align-items-center">
                                        <input type="radio" required id="{{$item['gateway'] }}" name="payment_gateway" value="{{$item['gateway'] }}">
                                        <label for="{{$item['gateway'] }}" class="d-flex align-items-center gap-3 mb-0">
                                            <img height="24" src="{{ \App\CentralLogics\Helpers::get_full_url('payment_modules/gateway_image', $item['gateway_image'], $item['storage'] ?? 'public') }}" alt="">
                                            {{ $item['gateway_title'] }}
                                        </label>
                                    </div>
                                </div>
                            @empty
                                <h2 class="h1">{{ translate('No payment gateway found') }}</h2>
                            @endforelse
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button id="reset_btn" type="reset" data-dismiss="modal" class="btn btn-secondary" ><i class="tio-clear"></i> {{ translate('Close') }} </button>
                        <button type="submit" class="btn btn-primary"><i class="tio-arrow-forward"></i> {{ translate('Proceed') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>


    <div class="modal fade" id="Adjust_wallet" tabindex="-1"  role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{translate('Adjust wallet')}}  </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>

                </div>
                <form action="{{ route('vendor.wallet.make_wallet_adjustment') }}" method="POST" class="needs-validation">
                    <div class="modal-body">
                        @csrf
                        <h5 class="mb-5 ">{{ translate('This will adjust the collected cash on your earning') }} </h5>
                    </div>

                    <div class="modal-footer">
                        <button id="reset_btn" type="reset" data-dismiss="modal" class="btn btn-secondary" ><i class="tio-clear"></i> {{ translate('Close') }} </button>
                        <button type="submit" class="btn btn-primary"><i class="tio-arrow-forward"></i> {{ translate('Proceed') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    </div>
    </div>
@endsection
@push('script_2')
    <script src="{{asset('public/assets/admin')}}/js/view-pages/vendor/wallet-method.js"></script>
    <script>
        "use strict";
$('#withdraw_method').on('change', function () {
    $('#submit_button').attr("disabled","true");
    let method_id = this.value;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    $.ajax({
        url: "{{route('vendor.wallet.method-list')}}" + "?method_id=" + method_id,
        data: {},
        processData: false,
        contentType: false,
        type: 'get',
        success: function (response) {
            $('#submit_button').removeAttr('disabled');
            let method_fields = response.content.method_fields;
            $("#method-filed__div").html("");
            method_fields.forEach((element, index) => {
                $("#method-filed__div").append(`
                    <div class="form-group mt-2">
                        <label for="wr_num" class="fz-16 text-capitalize c1 mb-2">${element.input_name.replaceAll('_', ' ')}</label>
                        <input type="${element.input_type == 'phone' ? 'number' : element.input_type  }" class="form-control" name="${element.input_name}" placeholder="${element.placeholder}" ${element.is_required === 1 ? 'required' : ''}>
                    </div>
                `);
            })

        },
        error: function () {

        }
    });
});

$('.payment-warning').on('click',function (event ){
            event.preventDefault();
            toastr.info(
                "{{ translate('messages.Currently, there are no payment options available. Please contact admin regarding any payment process or queries.') }}", {
                    CloseButton: true,
                    ProgressBar: true
                });
        });
$(document).ready(function() {
    $("#withdraw_form").on("submit", function(event) {
        $('#set_disable').attr('disabled', true);
    });
});
    </script>
@endpush
