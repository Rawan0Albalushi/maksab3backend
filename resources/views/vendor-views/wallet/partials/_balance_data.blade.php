@php
    $wallet_earning = round($wallet->total_earning - ($wallet->total_withdrawn + $wallet->pending_withdraw), 8);
    $adjust_able = ($wallet->balance > 0 && $wallet->collected_cash > 0)
        || ($wallet->collected_cash != 0 && $wallet_earning != 0);
    $digital_payment = $digital_payment['status'];
    $col_size = $adjust_able
        || ($disbursement_type == 'manual' && $wallet->balance > 0)
        || $wallet->balance < 0
        || ($wallet->collected_cash > 0 && $min_amount_to_pay_store <= $wallet->collected_cash);
    $balance_card_merged = $wallet->balance > 0 && $wallet->balance == $wallet_earning;
@endphp

<div class="row g-3">

    <div class="col-md-12">
        <div class="row g-3">
            <div class="col-sm-{{ $balance_card_merged ? '4' : ($col_size ? '3' : '4') }}">
                <div class="resturant-card shadow--card-2" >
                    <h4 class="title">{{\App\CentralLogics\Helpers::format_currency($wallet->collected_cash)}}</h4>

                    <div class="d-flex gap-1 align-items-center">
                                    <span class="subtitle">{{translate('Cash in hand')}}
                                    </span>

                        <span class="form-label-secondary text-danger d-flex"
                              data-toggle="tooltip" data-placement="right"
                              data-original-title="{{ translate('The total amount you\'ve received from the customer in cash (cash on delivery)')}}"><img
                                src="{{ asset('/public/assets/admin/img/info-circle.svg') }}"
                                alt="{{ translate('The total amount you\'ve received from the customer in cash (cash on delivery)') }}"> </span>
                        <img class="resturant-icon" src="{{asset('/public/assets/admin/img/transactions/image_total89.png')}}" alt="public">

                    </div>
                </div>
            </div>

            @if(!$balance_card_merged)
                <div class="col-sm-{{ $col_size ? '3' : '4' }}">
                    <div class="resturant-card shadow--card-2">
                        <h4 class="title">{{\App\CentralLogics\Helpers::format_currency($wallet->balance > 0 ? $wallet->balance: 0 )}}</h4>
                        <span class="subtitle">{{translate('Withdrawable balance')}}</span>
                        <img class="resturant-icon" src="{{asset('/public/assets/admin/img/transactions/image_w_balance.png')}}" alt="public">
                    </div>
                </div>
            @endif
            <div class="col-sm-{{ $balance_card_merged ? '8' : ($col_size ? '6' : '4') }}">
                <div class="resturant-card shadow--card-2">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>

                            @if ($wallet->balance > 0)
                                <h4 class="title">{{\App\CentralLogics\Helpers::format_currency(abs($wallet_earning))}}</h4>


                                @if( $wallet->balance ==  $wallet_earning )

                                    <span class="subtitle">{{ translate('Withdrawable balance') }}</span>
                                @else
                                    <span class="subtitle">{{ translate('messages.balance') }}
                                            <small>{{translate('Unadjusted')}} </small>
                                        </span>
                                @endif

                            @else
                                <h4 class="title">{{\App\CentralLogics\Helpers::format_currency(abs($wallet->collected_cash))}}</h4>
                                <span class="subtitle">{{  translate('Payable balance')}}</span>

                            @endif


                        </div>

                        @if($wallet->balance > 0  &&  $wallet->balance > $wallet->collected_cash  )
                            <div class="d-flex gap-2 flex-wrap">
                                @if ($adjust_able)
                                    <a class="btn btn--primary d-flex gap-1 align-items-center text-nowrap"  href="javascript:" data-toggle="modal" data-target="#Adjust_wallet">{{translate('messages.Adjust with wallet')}}

                                        <span class="form-label-secondary d-flex"
                                              data-toggle="tooltip" data-placement="right"
                                              data-original-title="{{ translate('Adjust the withdrawable balance & unadjusted balance with your wallet (cash in hand) or click \'request withdraw\'')}}">
                                        <i class="tio-info-outined"> </i>

                                        </span>

                                    </a>
                                @endif

                                @if ($disbursement_type ==  'manual'  )
                                    <a  href="javascript:"

                                       @if(count($withdrawal_methods) !== 0 )
                                           class="btn btn--primary d-flex gap-1 align-items-center text-nowrap"
                                       data-toggle="modal" data-target="#balance-modal"
                                        @else
                                            class="btn btn--primary d-flex gap-1 align-items-center text-nowrap withdrawal-methods-disable"
                                        data-message="{{translate('Withdraw methods are not available')}}"
                                       @endif
                                    >{{translate('messages.Request withdraw')}}

                                        <span class="form-label-secondary  d-flex"
                                              data-toggle="tooltip" data-placement="right"
                                              data-original-title="{{ translate('As you have more \'withdrawable balance\' than \'cash in hand\', you need to request for withdrawal from admin')}}">
                                            <i class="tio-info-outined"> </i> </span>
                                    </a>
                                @endif
                            </div>
                        @elseif($wallet->balance < 0 ||  ($wallet->collected_cash > 0 && $wallet->collected_cash  > $wallet->balance )     )
                            <div class="d-flex gap-2 flex-wrap">

                                @if ($adjust_able)
                                    <a class="btn btn--primary d-flex gap-1 align-items-center text-nowrap"  href="javascript:" data-toggle="modal" data-target="#Adjust_wallet">{{translate('messages.Adjust with wallet')}}

                                        <span class="form-label-secondary  d-flex"
                                              data-toggle="tooltip" data-placement="right"
                                              data-original-title="{{ translate('As you have more \'cash in hand\' than \'withdrawable balance,\' you need to pay the admin')}}"> <i class="tio-info-outined"> </i> </span> </span>
                                    </a>
                                @endif

                                @if ($min_amount_to_pay_store <= $wallet->collected_cash )
                                    <a
                                    @if ( $digital_payment != 1)
                                    class="btn btn--secondary d-flex gap-1 align-items-center text-nowrap payment-warning"  href="javascript:"

                                    @else

                                    class="btn btn--primary d-flex gap-1 align-items-center text-nowrap"  href="javascript:"
                                    data-toggle="modal" data-target="#payment_model"
                                    @endif

                                    >{{translate('messages.Pay now')}}

                                        <span class="form-label-secondary  d-flex"
                                              data-toggle="tooltip" data-placement="right"
                                              data-original-title="{{ translate('Adjust the payable & withdrawable balance with your wallet (cash in hand) or click \'pay now\'.')}}"> <i class="tio-info-outined"> </i> </span> </span></a>
                                @endif
                            </div>
                        @endif

                    </div>
                </div>
            </div>
        </div>
    </div>



    <div class="col-md-12">
        <div class="row g-3">
            <div class="col-sm-4">
                <div class="resturant-card  bg--3" >
                    <h4 class="title">{{\App\CentralLogics\Helpers::format_currency($wallet->pending_withdraw)}}</h4>
                    <span class="subtitle">{{translate('messages.Pending withdraw')}}</span>
                    <img class="resturant-icon" src="{{asset('/public/assets/admin/img/transactions/image_pending.png')}}" alt="public">
                </div>
            </div>

            <div class="col-sm-4">
                <div class="resturant-card  bg--2">
                    <h4 class="title">{{\App\CentralLogics\Helpers::format_currency($wallet->total_withdrawn)}}</h4>
                    <span class="subtitle">{{translate('Total withdrawn')}}</span>
                    <img class="resturant-icon" src="{{asset('/public/assets/admin/img/transactions/image_withdaw.png')}}" alt="public">
                </div>
            </div>


            <div class="col-sm-4">
                <div class="resturant-card  bg--1">
                    <h4 class="title">{{\App\CentralLogics\Helpers::format_currency($wallet->total_earning)}}</h4>
                    <span class="subtitle">{{translate('messages.Total earning')}}</span>
                    <img class="resturant-icon" src="{{asset('/public/assets/admin/img/transactions/image_total89.png')}}" alt="public">
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="balance-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">
                    {{translate('Withdraw request')}}
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true" class="btn btn--circle btn-soft-danger text-danger"><i class="tio-clear"></i></span>
                </button>
            </div>

            <form id="withdraw_form" action="{{route('vendor.wallet.withdraw-request')}}" method="post">
                <div class="modal-body">
                    @csrf
                    <div class="">
                        <select class="form-control" id="withdraw_method" name="withdraw_method" required>
                            <option value="" selected disabled>{{translate('Select withdraw method')}}</option>
                            @foreach($withdrawal_methods as $item)
                                <option value="{{$item['id']}}">{{$item['method_name']}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="" id="method-filed__div">
                    </div>
                    <div class="form-group">
                        <label for="recipient-name" class="form-label">{{translate('Amount')}}:</label>
                        <input type="number" name="amount"  step="0.01"
                               value="{{abs($wallet->balance)}}"
                               class="form-control h--45px" id="" min="1" max="{{abs($wallet->balance)}}">
                    </div>
                </div>
                <div class="modal-footer pt-0 border-0">
                    <button type="button" class="btn btn--reset" data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{translate('messages.Cancel')}}</button>
                    <button type="submit"  id="set_disable" id="submit_button" class="btn btn--primary"><i class="tio-checkmark-circle-outlined"></i> {{translate('messages.Submit')}}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"  aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">{{translate('messages.Note')}}:  </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>

            </div>
            <div class="modal-body">

                <div class="form-group">
                    <p  id="hiddenValue"> </p>
                </div>
            </div>
            <div class="modal-footer">
                <button id="reset_btn" type="reset" data-dismiss="modal" class="btn btn-secondary" ><i class="tio-clear"></i> {{ translate('Close') }} </button>
            </div>
        </div>
    </div>
</div>
<div class="row mt-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">

                <ul class="nav nav-tabs page-header-tabs pb-2">
                    <li class="nav-item">
                        <a class="nav-link {{ $is_wallet_index ?'active':''}}"  href="{{ route('vendor.wallet.index') }}">{{translate('Withdraw request')}}</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link  {{$is_wallet_payment_list ?'active':''}}" href="{{route('vendor.wallet.wallet_payment_list')}}"  aria-disabled="true">{{translate('messages.Payment history')}}</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link  {{$is_wallet_disbursement ?'active':''}}" href="{{route('vendor.wallet.getDisbursementList')}}"  aria-disabled="true">{{translate('Next payouts')}}</a>
                    </li>
                </ul>

            </div>
