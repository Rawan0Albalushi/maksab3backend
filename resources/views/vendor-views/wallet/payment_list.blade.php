@extends('layouts.vendor.app')

@section('title',translate('Wallet payment list'))

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <img src="{{asset('/public/assets/admin/img/image_90.png')}}" alt="">
                        </span>
                        <span>
                            {{translate('messages.' . $wallet_title_key)}}
                        </span>
                    </h1>
                    <p class="page-header-desc">{{ translate('Every payment that has reached you, and what it was for.') }}</p>
                </div>
            </div>
        </div>
        @include('vendor-views.wallet.partials._balance_data',['wallet'=>$wallet])
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="datatable"
                       class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                       data-hs-datatables-options='{
                                    "order": [],
                                    "orderCellsTop": true,
                                    "paging":false
                                }' >
                    <thead class="thead-light">
                    <tr>
                        <th>{{ translate('messages.SL') }}</th>
                        <th>{{translate('Amount')}}</th>
                        <th>{{translate('Payment time')}}</th>
                        <th>{{translate('messages.Payment method')}}</th>
                        <th>{{translate('messages.Status')}}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($account_transaction as $k=>$wr)

                        <tr>
                            <td scope="row">{{$k+$account_transaction->firstItem()}}</td>
                            <td> {{ \App\CentralLogics\Helpers::format_currency($wr['amount'])}}</td>

                            <td>
                                <span class="d-block">{{ \App\CentralLogics\Helpers::time_date_format($wr['created_at'])}}</span>
                            </td>
                            <td>
                                @if($wr->method)
                                    {{ payment_method_label($wr->method) }}
                                @else
                                    {{ translate('Default method') }}
                                @endif
                            </td>
                            <td>
                                <label class="badge badge-soft-success">{{translate('Approved')}}</label>
                            </td>

                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if(count($account_transaction) === 0)
                    <div class="empty--data">
                        <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                        <h5>
                            {{translate('No data found')}}
                        </h5>
                    </div>
                @endif
            </div>
        </div>
        <div class="card-footer pt-0 border-0">
            {{$account_transaction->links()}}
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
                        <input type="hidden" value="{{  abs($wallet->collected_cash) }}" name="amount"/>
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
