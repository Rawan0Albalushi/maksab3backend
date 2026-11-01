@extends('layouts.admin.app')

@section('title',$store->name."'s ".translate('messages.Subscription'))

@push('css_or_js')
    <link href="{{asset('public/assets/admin/css/croppie.css')}}" rel="stylesheet">

@endpush

@section('content')
<div class="content container-fluid">
    @include('admin-views.vendor.view.partials._header',['store'=>$store])

    @if ($store->store_business_model == 'commission' &&  \App\CentralLogics\Helpers::commission_check())

    <div class="card mb-3">
        <div class="card-header flex-wrap gap-2 border-0 align-items-center">
            <div>
                <h3 class="card-title mb-1 align-items-center gap-2">
                    <span class="text-title">{{ translate('Package overview') }}</span>
                </h3>
                <span class="fs-12 d-block color-334257B2">Here you see your active business plan.</span>
            </div>
            <div class="btn--container justify-content-end m-0">
                <button type="button" data-toggle="modal" data-target="#plan-modal" class="btn btn--primary"><i class="tio-sync"></i> {{ translate('Change business plan') }}</button>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="bg-F7F8F9 p--20 rounded mb-20">
                <div class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <div class="max-w-595">
                            <h3 class="name">{{ translate('Commission base plan') }}</h3>
                            <div class="info-text fs-14">
                                {{ translate('Store will pay') }} {{ $store->comission ?? $admin_commission }}% {{ translate('commission to') }} <strong>{{ $business_name }}</strong> {{ translate('from each order. You will get access of all the features and options  in store panel , app and interaction with user.') }}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-white d-flex align-items-center justify-content-between gap-2 flex-wrap rounded py-3 px-xxl-4 px-3">
                            <h4 class="title mt-2">
                                <span class="text-180 fs-32 theme-clr">
                                 {{ $store->comission ?? $admin_commission }}%
                                </span>
                                <span class="fs-14 font-semibold d-block">{{ translate('messages.Commission per order') }}</span>
                            </h4>
                            <img width="40" src="{{asset('public/assets/admin/img/money-percentage.png')}}" alt="">
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-F7F8F9 p--20 rounded">
                <div class="d-flex flex-wrap flex-md-nowrap justify-content-between __plan-details-top">
                    <div class="w-100">
                        <div class="">
                            <form action="{{route('admin.store.update-settings',[$store['id'] , 'tab' => 'business_plan'])}}" method="post">
                                @csrf
                                @method("post")
                                <div class="row align-items-center g-3">
                                    <div class="col-md-6">
                                        <div class="max-w-595">
                                            <h3 class="name">{{ translate('Change commission rate') }}</h3>
                                            <div class="info-text fs-14">
                                                {{ translate('When enabled admin will only receive the certain commission percentage set for this store. Otherwise the system default commission will be applied.') }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-20">
                                            <label class="d-flex mb-1 justify-content-between switch toggle-switch-sm text-dark bg-white rounded border py-2 px-3 text-capitalize" for="comission_status">
                                                <span class="fs-14 lh-1">{{translate('messages.Status')}}</span>
                                                <input type="checkbox" class="toggle-switch-input" name="comission_status" id="comission_status" value="1" {{isset($store->comission)?'checked':''}}>
                                                <span class="toggle-switch-label">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                        </div>
                                        <div>
                                            <label class="d-flex mb-2 justify-content-between text-dark text-capitalize">
                                                <span>{{translate('Change commission rate')}}(%)                                                 
                                            </label>
                                            <div class="d-flex flex-wrap gap-3">
                                                <input type="number" id="comission" min="0" max="10000" step="0.01" name="comission" class="form-control w-200px flex-grow-1 bg-white" required value="{{$store->comission??'0'}}" {{isset($store->comission)?'':'readonly'}}>                                                
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-end gap-3 mt-4">
                                    <button type="submit" class="btn min-w-120px btn--reset h--45px"><i class="tio-refresh"></i> {{ translate('Reset') }}</button>
                                    <button type="submit" class="btn min-w-120px btn--primary h--45px"><i class="tio-sync"></i> {{ translate('Change') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @elseif (in_array($store->store_business_model,[ 'subscription' ,'unsubscribed']) && $store?->store_sub_update_application)

                <div class="card mb-20">
                    <div class="card-header flex-wrap gap-2 border-0 align-items-center">
                        <h3 class="card-title align-items-center gap-2">
                            <span class="card-header-icon">
                                <img src="{{asset('public/assets/admin/img/billing.png')}}" alt="">
                            </span>
                            <span class="text-title">{{ translate('Billing') }}</span>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6 col-lg-4">
                                <a class="__card-2 __bg-1 flex-row align-items-center gap-4" href="#">
                                    <img src="{{asset('public/assets/admin/img/expiring.png')}}" alt="report/new" class="w-60px">
                                    <div class="w-0 flex-grow-1 py-md-3">
                                        <span class="text-body">{{ translate('Expire date') }}</span>
                                        <h4 class="title m-0">{{  \App\CentralLogics\Helpers::date_format($store?->store_sub_update_application?->expiry_date_parsed) }}</h4>
                                    </div>
                                </a>
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <a class="__card-2 __bg-8 flex-row align-items-center gap-4" href="#">
                                    <img src="{{asset('public/assets/admin/img/total-bill.png')}}" alt="report/new" class="w-60px">
                                    <div class="w-0 flex-grow-1 py-md-3">
                                        <span class="text-body">{{ translate('Total bill') }}</span>
                                        <h4 class="title m-0">{{  \App\CentralLogics\Helpers::format_currency($store?->store_sub_update_application?->package?->price * ($store?->store_sub_update_application?->total_package_renewed + 1) ) }}</h4>
                                    </div>
                                </a>
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <a class="__card-2 __bg-4 flex-row align-items-center gap-4" href="#">
                                    <img src="{{asset('public/assets/admin/img/number.png')}}" alt="report/new" class="w-60px">
                                    <div class="w-0 flex-grow-1 py-md-3">
                                        <span class="text-body">{{ translate('Number of uses') }}</span>
                                        <h4 class="title m-0">{{ $store?->store_sub_update_application?->total_package_renewed + 1 }}</h4>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header flex-wrap gap-2 border-0 align-items-center">
                        <div>
                            <h3 class="card-title align-items-center gap-2">
                                <span class="text-title">{{ translate('Package overview') }}
                                    @if($store?->status == 0 &&  $store?->vendor?->status == 0)
                                    <span class=" badge badge-pill badge-info">  &nbsp; {{ translate('Approval pending') }}  &nbsp; </span>
                                    @elseif($store?->store_sub_update_application?->status == 0)
                                    <span class=" badge badge-pill badge-danger">  &nbsp; {{ translate('Expired') }}  &nbsp; </span>
                                    @elseif ($store?->store_sub_update_application?->is_canceled == 1)
                                    <span class=" badge badge-pill badge-warning">  &nbsp; {{ translate('Canceled') }}  &nbsp; </span>
                                    @elseif($store?->store_sub_update_application?->status == 1)
                                    <span class=" badge badge-pill badge-success">  &nbsp; {{ translate('Active') }}  &nbsp; </span>
                                    @endif
                                </span>
                            </h3>
                            <span class="fs-12 d-block color-334257B2">Here you see your active business plan.</span>
                        </div>
                        <div class="btn--container justify-content-end mt-20">
                            @if ( $store?->store_sub_update_application?->is_canceled == 0 && $store?->store_sub_update_application?->status == 1  )
                            <button type="button"  data-url="{{route('admin.business-settings.subscriptionackage.cancelSubscription',$store?->id)}}" data-message="{{translate('If you cancel the subscription, after')}} {{  Carbon\Carbon::now()->diffInDays($store?->store_sub_update_application?->expiry_date_parsed->format('Y-m-d'), false) }} {{ translate('Days the vendor will no longer be able to run the business before subscribe a new plan.') }}"
                                class="btn btn--danger text-white status_change_alert"><i class="tio-clear-circle-outlined"></i> {{ translate('Cancel subscription') }}</button>
                            @endif
                            <button type="button" data-toggle="modal" data-target="#plan-modal" class="btn text-wrap btn--primary"><i class="tio-autorenew"></i> {{ translate('Change / renew subscription plan') }}</button>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="bg-F7F8F9 p--20 rounded mb-20">
                             <div class="d-flex flex-wrap flex-md-nowrap justify-content-between __plan-details-top">
                                <div class="left">
                                    <h3 class="name">{{ $store?->store_sub_update_application?->package?->package_name }}</h3>
                                    <div class="font-medium text--title">{{ $store?->store_sub_update_application?->package?->text }}</div>
                                </div>
                                <h3 class="right">{{ \App\CentralLogics\Helpers::format_currency($store?->store_sub_update_application?->last_transcations?->price) }} /<small class="font-medium text--title">{{ $store?->store_sub_update_application?->last_transcations?->validity }} {{ translate('messages.Days') }}</small></h3>
                            </div>
                        </div>
                        <div class="bg-F7F8F9 p--20 rounded">
                            <div class="check--grid-wrapper mt-3 max-w-850px">
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check.png')}}" alt="">
                                        @if ( $store?->store_sub_update_application?->max_order == 'unlimited' )
                                        <span class="form-check-label text-dark">{{ translate('messages.Unlimited orders') }}</span>
                                        @else
                                        <span class="form-check-label text-dark"> {{ $store?->store_sub_update_application?->package?->max_order }} {{
                                            translate('messages.Orders') }} <small>({{ $store?->store_sub_update_application?->max_order }} {{ translate('left') }}) </small> </span>
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        @if ( $store?->store_sub_update_application?->pos == 1 )
                                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check.png')}}" alt="">
                                        @else
                                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check-1.png')}}" alt="">
                                        @endif
                                        <span class="form-check-label text-dark">{{ translate('messages.POS') }}</span>
                                    </div>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        @if ( $store?->store_sub_update_application?->mobile_app == 1 )
                                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check.png')}}" alt="">
                                        @else
                                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check-1.png')}}" alt="">
                                        @endif
                                        <span class="form-check-label text-dark">{{ translate('Mobile app') }}</span>
                                    </div>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        @if ( $store?->store_sub_update_application?->self_delivery == 1 )
                                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check.png')}}" alt="">
                                        @else
                                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check-1.png')}}" alt="">
                                        @endif
                                        <span class="form-check-label text-dark">{{ translate('Self delivery') }}</span>
                                    </div>
                                </div>

                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check.png')}}" alt="">
                                        @if ( $store?->store_sub_update_application?->max_product == 'unlimited' )
                                        <span class="form-check-label text-dark">{{ translate('messages.Unlimited item Upload')
                                            }}</span>
                                        @else
                                        <span class="form-check-label text-dark"> {{ $store?->store_sub_update_application?->max_product }} {{
                                            translate('messages.Product Upload') }} <small>({{ $store?->store_sub_update_application?->max_product  - $store->items_count > 0 ? $store?->store_sub_update_application?->max_product  - $store->items_count : 0 }} {{ translate('left') }}) </small></span>
                                        @endif
                                    </div>
                                </div>

                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        @if ( $store?->store_sub_update_application?->review == 1 )
                                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check.png')}}" alt="">
                                        @else
                                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check-1.png')}}" alt="">
                                        @endif
                                        <span class="form-check-label text-dark">{{ translate('messages.review') }}</span>
                                    </div>
                                </div>

                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        @if ( $store?->store_sub_update_application?->chat == 1 )
                                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check.png')}}" alt="">
                                        @else
                                        <img src="{{asset('/public/assets/admin/img/subscription-plan/check-1.png')}}" alt="">
                                        @endif
                                        <span class="form-check-label text-dark">{{ translate('messages.Chat') }}</span>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

        @else


        <div class="card">
            <div class="card-body text-center py-5">
                <div class="max-w-542 mx-auto py-sm-5 py-4">
                    <img class="mb-4" src="{{asset('/public/assets/admin/img/empty-subscription.svg')}}" alt="img">
                    <h4 class="mb-3">{{translate('Chose Subscription Plan')}}</h4>
                    <p class="mb-4">
                        {{translate('Chose a subscription packages from the list. So that Stores get more options to join the business for the growth and success.')}}<br>
                    </p>
                    <button type="button" data-toggle="modal" data-target="#plan-modal" class="btn btn--primary"><i class="tio-sync"></i> {{ translate('Chose Subscription Plan') }}</button>
                </div>
            </div>
        </div>
    @endif



    <div class="modal fade show" id="plan-modal">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header px-3 pt-3">
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true" class="tio-clear"></span>
                    </button>
                </div>
                <div class="modal-body px-4 pt-0">
                    <div>
                        <div class="text-center">
                            <h2 class="modal-title">{{ translate('Change plan') }}</h2>
                        </div>
                        <div class="text-center text-14 mb-4 pb-3">
                           {{ translate('Renew or shift your plan to get better experience!') }}
                        </div>
                        <div class="plan-slider owl-theme owl-carousel owl-refresh">
                            @if (\App\CentralLogics\Helpers::commission_check())
                            <div class="__plan-item hover {{ $store->store_business_model == 'commission'  ? 'active' : ''}} ">
                                <div class="inner-div">
                                    <div class="text-center">
                                        <h3 class="title">{{ translate('Commission base') }}</h3>
                                        <h2 class="price">{{  $store->comission ?? $admin_commission }}%</h2>
                                    </div>
                                    <div class="py-5 mt-4">
                                        <div class="info-text text-center">
                                            {{ translate('Store will pay') }} {{  $store->comission ?? $admin_commission }}% {{ translate('commission to') }} {{ $business_name }} {{ translate('from each order. You will get access of all the features and options  in store panel , app and interaction with user.') }}
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        @if ($store->store_business_model == 'commission')
                                        <button type="button" class="btn btn--secondary"><i class="tio-checkmark-circle-outlined"></i> {{ translate('Current plan') }}</button>
                                        @else
                                        @php
                                        $cash_backs= \App\CentralLogics\Helpers::calculateSubscriptionRefundAmount(store:$store ,return_data:true);
                                        @endphp

                                        <button type="button" data-url="{{route('admin.business-settings.subscriptionackage.switchToCommission',$store->id)}}" data-message="{{translate('You want to migrate to commission.')}} {{ data_get($cash_backs,'back_amount') > 0  ?  translate('You will get').' '. \App\CentralLogics\Helpers::format_currency(data_get($cash_backs,'back_amount')) .' '.translate('To your wallet for remaining') .' '.data_get($cash_backs,'days').' '.translate('messages.Days subscription plan') : '' }}"  class="btn btn--primary shift_to_commission"><i class="tio-sync"></i> {{ translate('Shift in this plan') }}</button>
                                        @endif

                                    </div>
                                </div>
                            </div>
                            @endif

                            @forelse ($packages as $package)

                            <div class="__plan-item hover {{ $store?->store_sub_update_application?->package_id == $package->id  && $store->store_business_model != 'commission'  ? 'active' : ''}}">
                                <div class="inner-div">
                                    <div class="text-center">
                                        <h3 class="title">{{ $package->package_name }}</h3>
                                        <h2 class="price">{{ \App\CentralLogics\Helpers::format_currency($package->price)}}</h2>
                                        <div class="day-count">{{ $package->validity }} {{ translate('messages.days') }}</div>
                                    </div>
                                    <ul class="info">

                                        @if ($package->pos)
                                        <li>
                                            <i class="tio-checkmark-circle"></i> <span>  {{ translate('messages.POS') }} </span>
                                        </li>
                                        @endif
                                        @if ($package->mobile_app)
                                        <li>
                                            <i class="tio-checkmark-circle"></i> <span>  {{ translate('Mobile app') }} </span>
                                        </li>
                                        @endif
                                        @if ($package->chat)
                                        <li>
                                            <i class="tio-checkmark-circle"></i> <span>  {{ translate('messages.Chatting options') }} </span>
                                        </li>
                                        @endif
                                        @if ($package->review)
                                        <li>
                                            <i class="tio-checkmark-circle"></i> <span>  {{ translate('Review section') }} </span>
                                        </li>
                                        @endif
                                        @if ($package->self_delivery)
                                        <li>
                                            <i class="tio-checkmark-circle"></i> <span>  {{ translate('Self delivery') }} </span>
                                        </li>
                                        @endif
                                        @if ($package->max_order == 'unlimited')
                                        <li>
                                            <i class="tio-checkmark-circle"></i> <span>  {{ translate('Unlimited orders') }} </span>
                                        </li>
                                        @else
                                        <li>
                                            <i class="tio-checkmark-circle"></i> <span>  {{ $package->max_order }} {{ translate('messages.Orders') }} </span>
                                        </li>
                                        @endif
                                        @if ($package->max_product == 'unlimited')
                                        <li>
                                            <i class="tio-checkmark-circle"></i> <span>  {{ translate('messages.Unlimited uploads') }} </span>
                                        </li>
                                        @else
                                        <li>
                                            <i class="tio-checkmark-circle"></i> <span>  {{ $package->max_product }} {{ translate('messages.uploads') }} </span>
                                        </li>
                                        @endif

                                    </ul>
                                    <div class="text-center">

                                        @if ( $store?->store_business_model != 'commission'  && $store?->store_sub_update_application?->package_id == $package->id)
                                        <button data-id="{{ $package->id }}"  data-url="{{route('admin.business-settings.subscriptionackage.packageView',[$package->id,$store->id ])}}"
                                            data-target="#package_detail" id="package_detail" type="button" class="btn btn--warning text-white renew-btn package_detail"><i class="tio-autorenew"></i> {{ translate('messages.Renew') }}</button>
                                        @else
                                        <button data-id="{{ $package->id }}" data-url="{{route('admin.business-settings.subscriptionackage.packageView',[$package->id,$store->id ])}}"
                                            data-target="#package_detail" id="package_detail" type="button" class="btn btn--primary shift-btn package_detail"><i class="tio-sync"></i> {{ translate('messages.Shift in this plan') }}</button>
                                        @endif


                                    </div>
                                </div>
                            </div>
                            @empty

                            @endforelse
                        </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade __modal" id="subscription-renew-modal">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body px-4 pt-0">
                    <div class="data_package" id="data_package">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="product_warning">
        <div class="modal-dialog modal-dialog-centered status-warning-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true" class="tio-clear"></span>
                    </button>
                </div>
                <div class="modal-body pb-5 pt-0">
                    <div class="max-349 mx-auto mb-20">
                        <div>
                            <div class="text-center">
                                <img src="{{asset('/public/assets/admin/img/subscription-plan/package-status-disable.png')}}" class="mb-20">
                                <h5 class="modal-title" ></h5>
                            </div>
                            <div class="text-center">
                                <h3>{{ translate('Are you sure you want to switch to this plan?') }}</h3>
                                <p>{{ translate('You are about to downgrade your plan.After subscribing to this plan your oldest') }} <span id="disable_item_count"></span> {{ translate('Items will be inactivated.') }} </p>
                            </div>
                        </div>
                        <div class="btn--container justify-content-center">
                            <button  id="continue_btn" class="btn btn-outline-primary min-w-120" data-dismiss="modal" >
                                <i class="tio-arrow-forward"></i> {{translate("Continue")}}
                            </button>
                            <button  class="btn btn--primary min-w-120  shift_package"  id="back_to_planes" data-dismiss="modal" ><i class="tio-arrow-backward"></i> {{translate('Go back')}}</button>
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
        $('.plan-slider').owlCarousel({
            loop: false,
            margin: 30,
            responsiveClass:true,
            nav:false,
            dots:false,
            items: 3,
            center: true,
            startPosition: '{{ $index }}',

            responsive:{
                0: {
                    items:1.1,
                    margin: 10,
                },
                375: {
                    items:1.3,
                    margin: 30,
                },
                576: {
                    items:1.7,
                },
                768: {
                    items:2.2,
                    margin: 40,
                },
                992: {
                    items: 3,
                    margin: 40,
                },
                1200: {
                    items: 4,
                    margin: 40,
                }
            }
        })

        "use strict";
            $('.status_change_alert').on('click', function (event) {
            let url = $(this).data('url');
            let message = $(this).data('message');
            status_change_alert(url, message, event)
        })

        function status_change_alert(url, message, e) {
            e.preventDefault();
            Swal.fire({
                title: '{{ translate('Are you sure?') }}',
                text: message,
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#FC6A57',
                cancelButtonText: '{{ translate('No') }}',
                confirmButtonText: '{{ translate('Yes') }}',
                reverseButtons: true
            }).then((result) => {
                if (result.value) {
                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    });
                    $.post({
                        url: url,
                        data: {
                            id: '{{ $store->id }}',
                            subscription_id:'{{ $store?->store_sub_update_application?->id }}',
                        },
                        beforeSend: function () {
                            $('#loading').show()
                        },
                        success: function (data) {
                            toastr.success('{{ translate('Successfully canceled the subscription') }}!');
                        },
                        complete: function () {
                            $('#loading').hide();
                            location.reload();
                        }
                    });
                }
            })
        }

        $('.shift_to_commission').on('click', function (event) {
            let url = $(this).data('url');
            let message = $(this).data('message');
            shift_to_commission(url, message, event)
        })

        function shift_to_commission(url, message, e) {
            e.preventDefault();
            Swal.fire({
                title: '{{ translate('Are you sure?') }}',
                text: message,
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#FC6A57',
                cancelButtonText: '{{ translate('No') }}',
                confirmButtonText: '{{ translate('Yes') }}',
                reverseButtons: true
            }).then((result) => {
                if (result.value) {
                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    });
                    $.post({
                        url: url,
                        data: {
                            id: '{{ $store->id }}',
                        },
                        beforeSend: function () {
                            $('#loading').show()
                        },
                        success: function (data) {
                            toastr.success('{{ translate('Successfully switched to commission') }}!');
                        },
                        complete: function () {
                            $('#loading').hide();
                            location.reload();
                        }
                    });
                }
            })
        }

        $(document).on('click', '.package_detail', function () {
            var url = $(this).attr('data-url');
            $.ajax({
                url: url,
                method: 'get',
                beforeSend: function() {
                            $('#loading').show();
                            $('#plan-modal').modal('hide')
                            },
                success: function(data){
                    $('#data_package').html(data.view);
                    if(data.disable_item_count !== null && data.disable_item_count > 0){
                        $('#product_warning').modal('show')
                        $('#disable_item_count').text(data.disable_item_count)
                    } else{
                        $('#subscription-renew-modal').modal('show')
                    }
                },
                complete: function() {
                        $('#loading').hide();
                    },

            });
        });
        $(document).on('click', '#continue_btn', function () {
            $('#subscription-renew-modal').modal('show')
        });

        $(document).on('click', '#back_to_planes', function () {
            $('#plan-modal').modal('show')
        });

        $("#comission_status").on('change', function(){
                if($("#comission_status").is(':checked')){
                    $('#comission').removeAttr('readonly');
                } else {
                    $('#comission').attr('readonly', true);
                    $('#comission').val('0');
                }
            });
    </script>
@endpush
