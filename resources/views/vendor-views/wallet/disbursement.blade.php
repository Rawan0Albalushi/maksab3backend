@extends('layouts.vendor.app')

@section('title',translate('Disbursement list'))

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
                    <p class="page-header-desc">{{ translate('Payouts on their way to you, and the state each one is in.') }}</p>
                </div>
            </div>
        </div>


        @include('vendor-views.wallet.partials._balance_data',['wallet'=>$wallet])


        <div class="card-header border-0 py-2">
            <div class="search--button-wrapper">
                <h2 class="card-title">
                    {{ translate('Total disbursements') }} <span class="badge badge-soft-secondary ml-2" id="countItems">{{ $disbursements->total() }}</span>
                </h2>
                <form class="search-form">
                    <div class="input--group input-group input-group-merge input-group-flush">
                        <input class="form-control" value="{{ request()?->search  ?? null }}" placeholder="{{ translate('Search by ID') }}" name="search">
                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>
                </form>
                <div class="hs-unfold ml-3">
                    <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle btn export-btn btn-outline-primary btn--primary font--sm" href="javascript:;"
                       data-hs-unfold-options='{
                                    "target": "#usersExportDropdown",
                                    "type": "css-animation"
                                }'>
                        <i class="tio-download-to mr-1"></i> {{translate('messages.Export')}}
                    </a>
                    <div id="usersExportDropdown"
                         class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                        <span class="dropdown-header">{{translate('messages.Download options')}}</span>
                        <a id="export-excel" class="dropdown-item" href="{{route('vendor.wallet.export', ['type'=>'excel',request()->getQueryString()])}}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{asset('public/assets/admin')}}/svg/components/excel.svg" alt="Image Description">
                            Excel
                        </a>
                        <a id="export-csv" class="dropdown-item" href="{{route('vendor.wallet.export', ['type'=>'csv',request()->getQueryString()])}}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{asset('public/assets/admin')}}/svg/components/placeholder-csv-format.svg" alt="Image Description">
                            CSV
                        </a>

                    </div>
                </div>

                <div id="action-section" class="d--none">
                    <button class="btn btn-danger btn-outline-danger" id="cancel"><i class="tio-clear-circle-outlined"></i> {{ translate('Cancel') }}</button>
                    <button class="btn btn-success" id="complete"><i class="tio-checkmark-circle-outlined"></i> {{ translate('Complete') }}</button>
                </div>

            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-thead-bordered table-align-middle card-table">
                    <thead>
                    <tr>
                        <th>{{ translate('SL') }}</th>
                        <th>ID</th>
                        <th>{{ translate('Created at') }}</th>
                        <th>{{ translate('Disburse amount') }}</th>
                        <th>{{ translate('Payment method') }}</th>
                        <th>{{ translate('Payout date') }}</th>
                        <th>{{ translate('Status') }}</th>
                        <th>
                            <div class="text-center">
                                {{ translate('Action') }}
                            </div>
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($disbursements as $key => $store)

                        <tr>
                            <td>
                                <span class="font-weight-bold">{{$key+ $disbursements->firstItem()}}</span>
                            </td>
                            <td>
                                <span class="font-weight-bold">{{$store->disbursement_id}}</span>
                            </td>
                            <td>
                                {{ \App\CentralLogics\Helpers::time_date_format( $store->created_at )  }}

                            </td>

                            <td>
                                {{\App\CentralLogics\Helpers::format_currency($store['disbursement_amount'])}}
                            </td>
                            <td>
                                <div>
                                    {{ $store->withdraw_method?->method_name ?? translate('messages.Payment method removed') }}
                                </div>
                            </td>
                            <td>
                                <div>
                                    {{ $store->created_at->addDays($store_disbursement_waiting_time)->format('d-M-y')  }}
                                    <small>
                                        {{  translate('Estimated') }}
                                    </small>
                                </div>
                            </td>
                            <td>
                                @if($store->status=='pending')
                                    <label class="badge badge-soft-primary">{{ translate('Pending') }}</label>
                                @elseif($store->status=='completed')
                                    <label class="badge badge-soft-success">{{ translate('Completed') }}</label>
                                @else
                                    <label class="badge badge-soft-danger">{{ translate('Canceled') }}</label>
                                @endif
                            </td>


                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="btn btn-sm action-btn action-btn--view" data-toggle="modal" data-target="#payment-info-{{$store->id}}" title="{{ translate('View details') }}">
                                        <i class="tio-visible-outlined"></i>
                                    </a>

                                </div>
                            </td>
                            <div class="modal fade" id="payment-info-{{$store->id}}">
                                <div class="modal-dialog modal-xl">
                                    <div class="modal-content">
                                        <div class="modal-header pb-4">
                                            <button type="button" class="payment-modal-close btn-close border-0 outline-0 bg-transparent" data-dismiss="modal">
                                                <i class="tio-clear"></i>
                                            </button>
                                            <div class="w-100 text-center">
                                                <h2 class="mb-2">{{ translate('Payment information') }}</h2>
                                                <div>
                                                    <span class="mr-2">{{ translate('Disbursement ID') }}</span>
                                                    <strong>#{{$store->disbursement_id}}</strong>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="mr-2">{{ translate('Status') }}</span>
                                                    @if($store->status=='pending')
                                                        <label class="badge badge-soft-primary">{{ translate('Pending') }}</label>
                                                    @elseif($store->status=='completed')
                                                        <label class="badge badge-soft-success">{{ translate('Completed') }}</label>
                                                    @else
                                                        <label class="badge badge-soft-danger">{{ translate('Canceled') }}</label>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-body">
                                            <div class="card shadow--card-2">
                                                <div class="card-body">
                                                    <div class="d-flex flex-wrap payment-info-modal-info p-xl-4">
                                                        <div class="item">
                                                            <h5>{{ $is_provider_module ? translate('Provider information') : translate('Store information') }}</h5>
                                                            <ul class="item-list">
                                                                <li class="d-flex flex-wrap">
                                                                    <span class="name">{{ translate('Name') }}</span>
                                                                    <span>:</span>
                                                                    <strong>{{$store?->store?->name}}</strong>
                                                                </li>
                                                                <li class="d-flex flex-wrap">
                                                                    <span class="name">{{ translate('Contact') }}</span>
                                                                    <span>:</span>
                                                                    <strong>{{$store?->store?->phone}}</strong>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                        <div class="item">
                                                            <h5>{{ translate('Owner information') }}</h5>
                                                            <ul class="item-list">
                                                                <li class="d-flex flex-wrap">
                                                                    <span class="name">{{ translate('Name') }}</span>
                                                                    <span>:</span>
                                                                    <strong>{{$store->store->vendor->f_name}} {{$store->store->vendor->l_name}}</strong>
                                                                </li>
                                                                <li class="d-flex flex-wrap">
                                                                    <span class="name">{{ translate('email') }}</span>
                                                                    <span>:</span>
                                                                    <strong>{{$store->store->vendor->email}}</strong>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                        <div class="item w-100">
                                                            <h5>{{ translate('Account information') }}</h5>
                                                            <ul class="item-list">
                                                                <li class="d-flex flex-wrap">
                                                                    <span class="name">{{ translate('Payment method') }}</span>
                                                                    <span>:</span>
                                                                    <strong>{{ $store->withdraw_method?->method_name ?? translate('messages.Payment method removed') }}</strong>
                                                                </li>
                                                                <li class="d-flex flex-wrap">
                                                                    <span class="name">{{ translate('Amount') }}</span>
                                                                    <span>:</span>
                                                                    <strong>{{\App\CentralLogics\Helpers::format_currency($store['disbursement_amount'])}}</strong>
                                                                </li>
                                                                @forelse((is_array($store->withdraw_method?->method_fields) ? $store->withdraw_method->method_fields : (json_decode($store->withdraw_method?->method_fields ?? '', true) ?: [])) as $key=> $item)
                                                                    <li class="d-flex flex-wrap">
                                                                        <span class="name">{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                                                                        <span>:</span>
                                                                        <strong>{{$item}}</strong>
                                                                    </li>
                                                                @empty

                                                                @endforelse

                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if(count($disbursements) === 0)
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
            {{$disbursements->links()}}
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
