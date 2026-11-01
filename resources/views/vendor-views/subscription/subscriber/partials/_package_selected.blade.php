<div class="">
    <div>
        <div class="text-center mb-4 pb-2">

            @if ($store_subscription?->package_id ==  $package->id)
            <h2 class="modal-title">{{translate('Renew subscription plan')}}</h2>
            @else
            <h2 class="modal-title">{{translate('Shift to new subscription plan')}}</h2>
            @endif

        </div>
        <div class="change-plan-wrapper align-items-center">
            @if ($store_business_model == 'commission'  )
            <div class="__plan-item">
                <div class="inner-div">
                    <div class="text-center">
                        <h3 class="title">{{ translate('Commission')  }}</h3>
                        <h2 class="price">{{  $admin_commission }} %</h2>
                    </div>
                </div>
            </div>
            <div class="plan-seperator-arrow mx-auto">
                <img src="{{asset('public/assets/admin/img/exchange.svg')}}" alt="" class="w-100">
            </div>

            @elseif(!in_array($store_business_model,['commission','none']))

            <div class="__plan-item {{ !$store_subscription  || $store_subscription?->package_id ==  $package->id ?  'active' : '' }}">
                <div class="inner-div">
                    <div class="text-center">
                        <h3 class="title">{{ $store_subscription?->package?->package_name  }}</h3>
                        <h2 class="price">{{  \App\CentralLogics\Helpers::format_currency($store_subscription?->package?->price) }}</h2>
                        <div class="day-count">{{ $store_subscription?->package?->validity }} {{ translate('days') }}</div>
                    </div>
                </div>
            </div>
                @if ( $store_subscription?->package_id !=  $package->id )
                <div class="plan-seperator-arrow mx-auto">
                <img src="{{asset('public/assets/admin/img/exchange.svg')}}" alt="" class="w-100">
                </div>
                @endif
            @endif


            @if ($store_subscription?->package_id !==  $package->id || $store_business_model == 'commission' )

            <div class="__plan-item active">
                <div class="inner-div">
                    <div class="text-center">
                        <h3 class="title">{{$package->package_name }}</h3>
                        <h2 class="price">{{ \App\CentralLogics\Helpers::format_currency($package?->price) }}</h2>
                        <div class="day-count">{{ $package?->validity }} {{ translate('days') }}</div>
                    </div>
                </div>
            </div>

            @endif
        </div>


        <div class="mb-2 mb-lg-3 subscription__plan-info-wrapper bg-ECEEF1 rounded-20">
            <div class="row g-3">
                <div class="col-md-{{ $pending_bill > 0 ? '3' :'4' }}">
                    <div class="subscription__plan-info">
                        <div class="info">
                            {{ translate('Validity') }}
                        </div>
                        <h4 class="subtitle">{{ $package?->validity }} {{ translate('days') }}</h4>
                    </div>
                </div>
                <div class="col-md-{{ $pending_bill > 0 ? '3' :'4' }}">
                    <div class="subscription__plan-info">
                        <div class="info">
                            {{ translate('price') }}
                        </div>
                        <h4 class="subtitle">{{ \App\CentralLogics\Helpers::format_currency($package?->price) }}</h4>
                    </div>
                </div>
                @if ($pending_bill)
                <div class="col-md-3">
                    <div class="subscription__plan-info">
                        <div class="info">
                            {{ translate('Pending bill') }}
                        </div>
                        <h4 class="subtitle">{{ \App\CentralLogics\Helpers::format_currency($pending_bill) }}</h4>
                    </div>
                </div>

                @endif
                <div class="col-md-{{ $pending_bill > 0 ? '3' :'4' }}">
                    <div class="subscription__plan-info">
                        <div class="info">
                            {{ translate('Bill status') }}
                        </div> <h4 class="subtitle">  {{  $store_business_model != 'commission' &&  $store_subscription?->package_id ==  $package->id ? translate('Renew') :  translate('Migrate to new plan') }}  </h4> </div>
                </div>
            </div>
        </div>
        @if (data_get($cash_backs,'back_amount') > 0 )
        <div class="mb-2 mb-lg-3 subscription__plan-info-wrapper bg--10 rounded-20 py-2">
            <div class="row g-3">
            <div class="col-auto">
                <i class="tio-notice"></i>
                    {{ translate('You will get') }}  {{ \App\CentralLogics\Helpers::format_currency(data_get($cash_backs,'back_amount')) }} {{ translate('To your wallet for remaining') }}  {{ data_get($cash_backs,'days') }} {{ translate('messages.Days subscription plan') }}
                </div>
            </div>
        </div>
        @endif
        <form action="{{ route('vendor.subscriptionackage.packageBuy') }}" method="post">
            @csrf
            @method('POST')
                <input type="hidden" value="{{ $package->id }}" name="package_id">
                <input type="hidden" value="{{ $store_id }}" name="store_id">
                <input type="hidden" value="{{ $store_subscription?->package_id ==  $package->id ? 'renew' : 'payment' }}" name="type">




        <h4 class="mb-4">{{ translate('Pay via online') }} <span class="font-regular text-body">({{ translate('Faster & secure way to pay bill') }})</span></h4>
        <div class="row g-3">
            @if ($balance > 0)

            <div class="col-md-6">
                <label class="payment-item">
                    <input type="radio" {{ $balance >= $package?->price ? '' :'disabled'  }} value="wallet"  class="d-none" name="payment_gateway">
                    <div  data-toggle="tooltip" data-placement="bottom" title="{{$balance >= $package?->price ? translate('Pay the amount via wallet') : translate('You have not sufficient balance on your wallet! Please add money to your wallet to purchase the packages.') }}"  class="payment-item-inner">
                        <div class="check">
                            <img src="{{asset('/public/assets/admin/img/check-1.png')}}" class="uncheck" alt="">
                            <img src="{{asset('/public/assets/admin/img/check-2.png')}}" class="check" alt="">
                        </div>
                        <span>{{ translate('Wallet') }}</span>
                        <span class="ml-auto" >{{ \App\CentralLogics\Helpers::format_currency($balance) }} </span>
                    </div>
                </label>
            </div>
            @endif


            @foreach ($payment_methods as $item)

            <div class="col-md-6">
                <label class="payment-item">
                    <input type="radio" class="d-none" value="{{ $item['gateway'] }}" name="payment_gateway">
                    <div class="payment-item-inner">
                        <div class="check">
                            <img src="{{asset('/public/assets/admin/img/check-1.png')}}" class="uncheck" alt="">
                            <img src="{{asset('/public/assets/admin/img/check-2.png')}}" class="check" alt="">
                        </div>
                        <span>{{ $item['gateway_title'] }}</span>
                        <img class="ml-auto"
                            src="{{ \App\CentralLogics\Helpers::get_full_url('payment_modules/gateway_image',$item['gateway_image'],$item['storage'] ?? 'public') }}"
                        width="30" alt="">
                    </div>
                </label>
            </div>

            @endforeach

        </div>
        <div class="btn--container justify-content-end mt-20">
            <button type="reset" data-dismiss="modal" class="btn btn--reset"><i class="tio-clear-circle-outlined"></i> {{ translate('Cancel') }}</button>
            @if ( $store_business_model != 'commission' && $store_subscription?->package_id ==  $package->id)
            <button type="submit" class="btn btn--primary"><i class="tio-autorenew"></i> {{ translate('Renew subscription plan') }}</button>
            @else
            <button type="submit" class="btn btn--primary"><i class="tio-sync"></i> {{ translate('Change plan') }}</button>
            @endif
        </div>
    </div>
</form>
</div>
