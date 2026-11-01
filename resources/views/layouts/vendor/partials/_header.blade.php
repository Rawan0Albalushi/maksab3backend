@if($use_v2_chrome ?? false)
<div id="headerMain" class="d-none"></div>
<button type="button" id="modalOpener" class="d-none" data-toggle="modal" data-target="#staticBackdrop"></button>
@else
<div id="headerMain" class="d-none">
    <header id="header"
            class="navbar navbar-expand-lg navbar-fixed navbar-height navbar-flush navbar-container navbar-bordered">
        <div class="navbar-nav-wrap">
            <div class="navbar-nav-wrap-content-left  d-xl-none">
                <button type="button" class="js-navbar-vertical-aside-toggle-invoker close mr-3">
                    <i class="tio-first-page navbar-vertical-aside-toggle-short-align" data-toggle="tooltip"
                       data-placement="right" title="Collapse"></i>
                    <i class="tio-last-page navbar-vertical-aside-toggle-full-align"
                       data-template='<div class="tooltip d-none d-sm-block" role="tooltip"><div class="arrow"></div><div class="tooltip-inner"></div></div>'
                       data-toggle="tooltip" data-placement="right" title="Expand"></i>
                </button>
            </div>
            @if(\App\CentralLogics\Helpers::check_website_builder_status() && \App\CentralLogics\Helpers::employee_module_permission_check('custom_website'))
            <div class="" id="vendor-dashboard-builder-button">
                <a href="{{ route('vendor.builder.index', ['page' => 'global-settings']) }}"
                   class="website-builder-btn">
                    <span class="website-builder-icon">
                        <img src="{{ asset('public/assets/admin/img/builder.svg') }}" alt="" >
                    </span>
                    <span>{{ translate('Build your custom website') }}</span>
                </a>
            </div>
            @endif
            <div class="navbar-nav-wrap-content-right">
                <ul class="navbar-nav align-items-center flex-row">
                    <li class="nav-item max-sm-m-0 w-xxl-200px ml-auto mr-2 flex-grow-0">
                        <button type="button" id="modalOpener" class="title-color bg--secondary border-0 rounded justify-content-between w-100 align-items-center py-2 px-2 px-md-3 d-flex gap-1" data-toggle="modal" data-target="#staticBackdrop">
                            <div class="align-items-center d-flex flex-grow-1 gap-1 justify-content-between">
                                <span class="align-items-center d-none d-xxl-flex gap-2 text-muted">{{translate('Search or')}}

                                    <span class="bg-E7E6E8 border ctrlplusk d-md-block d-none font-bold fs-12 fw-bold lh-1 ms-1 px-1 rounded text-muted">Ctrl+K</span>

                                </span>
                                <img width="14" src="{{asset('/public/assets/admin/img/new-img/search.svg')}}" class="svg" alt="">
                            </div>
                        </button>
                    </li>
                    <li class="nav-item ml-3 max-sm-m-0">
                        <div class="hs-unfold">
                            <div>
                                
                                @if ($system_language_setting)
                                <div
                                    class="topbar-text dropdown disable-autohide text-capitalize d-flex">
                                    <a class="topbar-link dropdown-toggle d-flex align-items-center title-color"
                                    href="#" data-toggle="dropdown">
                                            @foreach($system_languages as $data)
                                                @if($data['code']==$local)
                                                    <i class="tio-globe"></i> {{$data['code']}}

                                                @elseif(!$local &&  $data['default'] == true)
                                                    <i class="tio-globe"></i> {{$data['code']}}
                                                @endif
                                            @endforeach
                                    </a>
                                    <ul class="dropdown-menu lang-menu">
                                        @foreach($system_languages as $key =>$data)
                                            @if($data['status']==1)
                                                <li>
                                                    <a class="dropdown-item py-1"
                                                        href="{{route('vendor.lang',[$data['code']])}}">
                                                        <span class="text-capitalize">{{$data['code']}}</span>
                                                    </a>
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                </div>
                                @endif
                            </div>
                        </div>
                    </li>
                    @if (\App\CentralLogics\Helpers::employee_module_permission_check('chat'))
                    <li class="nav-item d-none d-sm-inline-block mr-4">
                        <div class="hs-unfold">
                            <a class="js-hs-unfold-invoker btn btn-icon btn-soft-secondary rounded-circle"
                               href="{{route('vendor.message.list')}}">
                                <i class="tio-messages-outlined"></i>
                                
                                @if($unread_message_count!=0)
                                    <span class="btn-status btn-sm-status btn-status-danger"></span>
                                @endif
                            </a>
                        </div>
                    </li>
                    @endif



                    <li class="nav-item">
                        <div class="hs-unfold">
                            <a class="js-hs-unfold-invoker navbar-dropdown-account-wrapper" href="javascript:;"
                               data-hs-unfold-options='{
                                     "target": "#accountNavbarDropdown",
                                     "type": "css-animation"
                                   }'>
                                <div class="cmn--media right-dropdown-icon d-flex align-items-center">
                                    <div class="media-body pl-0 pr-2">
                                        <span class="card-title h5 text-right">
                                            {{\App\CentralLogics\Helpers::get_loggedin_user()->f_name}}
                                            {{\App\CentralLogics\Helpers::get_loggedin_user()->l_name}}
                                        </span>
                                        <span class="card-text">{{\App\CentralLogics\Helpers::get_loggedin_user()->email}}</span>
                                    </div>
                                    <div class="avatar avatar-sm avatar-circle">
                                        <img class="avatar-img  onerror-image aspect-1-1"  data-onerror-image="{{asset('public/assets/admin/img/160x160/img1.jpg')}}"
                                        src="{{ \App\CentralLogics\Helpers::get_loggedin_user()->toArray()['image_full_url'] }}"
                                            alt="Image Description">
                                        <span class="avatar-status avatar-sm-status avatar-status-success"></span>
                                    </div>
                                </div>
                            </a>

                            <div id="accountNavbarDropdown"
                                 class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-right navbar-dropdown-menu navbar-dropdown-account min--240">
                                <div class="dropdown-item-text">
                                    <div class="media align-items-center">
                                        <div class="avatar avatar-sm avatar-circle mr-2">
                                            <img class="avatar-img  onerror-image aspect-1-1 "  data-onerror-image="{{asset('public/assets/admin/img/160x160/img1.jpg')}}"
                                            src="{{ \App\CentralLogics\Helpers::get_loggedin_user()->toArray()['image_full_url'] }}"
                                                 alt="Owner image">
                                        </div>
                                        <div class="media-body">
                                            <span class="card-title h5">{{\App\CentralLogics\Helpers::get_loggedin_user()->f_name}} {{\App\CentralLogics\Helpers::get_loggedin_user()->l_name}}</span>
                                            <span class="card-text">{{\App\CentralLogics\Helpers::get_loggedin_user()->email}}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="dropdown-divider"></div>

                                <a class="dropdown-item" href="{{route('vendor.profile.view')}}">
                                    <span class="text-truncate pr-2" title="Settings">{{translate('Settings')}}</span>
                                </a>

                                <div class="dropdown-divider"></div>

                                <a class="dropdown-item log-out" href="javascript:">
                                    <span class="text-truncate pr-2" title="{{translate('messages.Sign out')}}">{{translate('messages.Sign out')}}</span>
                                </a>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </header>
</div>
@endif
@include('layouts.partials._global_search_modal', ['searchRoute' => route('vendor.search.routing')])
<div id="headerFluid" class="d-none"></div>
<div id="headerDouble" class="d-none"></div>
{{-- View data supplied by App\Services\VendorHeaderService (composed in AppServiceProvider). --}}



@if ($showCashApproachingLimit)
    <div class="alert __alert-2 alert-warning m-0 py-1 px-2" role="alert">
        <img class="rounded mr-1"  width="25" src="{{ asset('/public/assets/admin/img/header_warning.png') }}" alt="">
        <div class="cont">
            <h4 class="m-0">{{ translate('Attention please') }} </h4>
            {{ translate('The cash in hand amount is about to exceed the limit. Please pay the due amount. If the limit exceeds, your account will be suspended.') }}
        </div>
    </div>
@endif

@if ($showCashLimitExceeded)
    <div class="alert __alert-2 alert-warning m-0 py-1 px-2" role="alert">
        <img class="mr-1"  width="25" src="{{ asset('/public/assets/admin/img/header_warning.png') }}" alt="">
        <div class="cont">
            <h4 class="m-0">{{ translate('Attention please') }} </h4>{{ translate('The cash in hand amount limit is exceeded. Your account is now suspended. Please pay the due amount to receive new order requests again.') }}<a href="{{ route('vendor.wallet.index') }}" class="alert-link"> &nbsp; {{ translate('Pay the due') }}</a>
        </div>
    </div>
@endif









@if ($showSubscriptionSection)

        @if ($showSubscriptionBadge)

                <div class="renew-badge mb-20" id="renew-badge">
                    <div class="renew-content d-flex align-items-center">

                        <img src="{{asset('/public/assets/admin/img/timer.svg')}}" alt="">
                        <div class="txt">
                            {{ $subscription_deadline_warning_message != null ?  $subscription_deadline_warning_message : translate('Your subscription ending soon. Please renew to continue access.') }}
                        </div>
                    </div>
                    <div>
                        <a href="{{route('vendor.subscriptionackage.subscriberDetail',['renew_now' => true])}}" class="btn btn--danger"><i class="tio-autorenew"></i> {{ translate('Renew') }}</a>
                    </div>
                </div>



        @elseif ($showSubscriptionBanner)


                <div class="renew-badge mb-20 hide-warning" id="renew-badge">
                    <div class="renew-content d-flex align-items-center">

                        <img src="{{asset('/public/assets/admin/img/timer.svg')}}" alt="">
                        <div class="txt">
                            {{ $subscription_deadline_warning_message != null ?  $subscription_deadline_warning_message : translate('Your subscription ending soon. Please renew to continue access.') }}
                        </div>
                    </div>
                    <div>
                        @if ($subscriptionIsCanceled)
                        <a href="{{route('vendor.subscriptionackage.subscriberDetail',['open_plans' => true])}}" class="btn btn--danger"><i class="tio-sync"></i> {{ translate('Change subscription') }}</a>
                        @else

                        <a href="{{route('vendor.subscriptionackage.subscriberDetail',['renew_now' => true])}}" class="btn btn--danger"><i class="tio-autorenew"></i> {{ translate('Renew') }}</a>

                        @endif
                        <button  data-id="subscription_renew_close_btn" id="hide-warning"  class="btn btn-sm btn-primary add-to-session" ><i class="tio-time"></i> {{ translate('Remind me later') }}</button>
                    </div>
                </div>


        @endif
        @if ($showFreeTrialBanner)

        <div class="free-trial trial success-bg">
            <div class="inner-div">
                <div class="left">
                    <img src="{{asset('/public/assets/admin/img/icon-puck.svg')}}" alt="">
                    <div class="left-content">
                        <h6>{{ translate('Get the best experience of on demand service business') }}</h6>
                        <div>{{ translate('Run your on demand business with the most popular platform') }}</div>
                    </div>
                </div>
                <div class="right">
                    <a href="#" class="btn btn-2">
                        <span class="circle-progress-container">
                            <svg width="40" viewBox="0 0 160 160">
                                <circle r="70" cx="80" cy="80" fill="transparent" stroke="#ffffff20" stroke-width="12px"></circle>
                                <circle r="70" cx="80" cy="80" fill="transparent" stroke="#ffffff" stroke-width="12px" stroke-dasharray="439.6px" stroke-dashoffset="{{ $subscriptionRingOffset }}px"></circle>
                            </svg>
                            {{ Carbon\Carbon::now()->diffInDays($store_data?->store_sub?->expiry_date_parsed->format('Y-m-d'), false) }}
                        </span>
                        {{translate('Days left in free trial')}}
                    </a>
                    <a href="{{route('vendor.subscriptionackage.subscriberDetail' ,['open_plans' => true])}}" class="btn btn-light">{{ translate('Choose subscription plan') }} <i class="tio-arrow-forward"></i></a>
                </div>

                <button type="button" data-id="subscription_free_trial_close_btn" class="trial-close add-to-session ">
                    <i class="tio-clear-circle"></i>
                </button>
            </div>
        </div>
        @elseif ($store_data?->store_sub == null && $store_data?->store_sub_update_application?->is_trial == 1)



        <div class="modal fade show trial-ended-modal" id="free-trial-modal">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-body p-0">
                        <div class="trial-ended-modal-wrapper">
                            <div class="trial-ended-modal-content align-self-center">
                                <h3 class="title">{{ translate('Your free trial has been ended') }}</h3>
                                <p class="mb-4">
                                    {{ translate('Purchase a subscription plan or contact with the admin to settle the payment and unblock the access to service') }}
                                </p>
                                <a href="{{route('vendor.subscriptionackage.subscriberDetail' ,['open_plans' => true])}}" class="btn btn--primary">{{ translate('Choose subscription plan') }} <i class="tio-arrow-forward"></i></a>
                                <div class="blocked-subscription mt-5">
                                    <img src="{{asset('/public/assets/admin/img/WarningOctagon.svg')}}" alt="">
                                    <span>{{ translate('All access to service has been blocked due to no active subscription') }}</span>
                                </div>
                            </div>
                            <div class="trial-ended-modal-img d-none d-md-block">
                                <img src="{{asset('/public/assets/admin/img/trial-ended-bg.png')}}" alt="">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        <div class="free-trial trial danger-bg">
            <div class="inner-div">
                <div class="left">
                    <img src="{{asset('/public/assets/admin/img/timer-2.svg')}}" alt="">
                    <div class="left-content">
                        <h6>{{ translate('Free trial has been ended') }}</h6>
                        <div>{{ translate('Get a subscription plan to continue with your business') }}</div>
                    </div>
                </div>
                <div class="right">
                    <a href="{{route('vendor.subscriptionackage.subscriberDetail' ,['open_plans' => true])}}" class="btn btn-light">{{ translate('Choose subscription plan') }} <i class="tio-arrow-forward"></i></a>
                </div>
            </div>
        </div>
        @elseif ( Session::get('subscription_cancel_close_btn') !== true &&  $store_data?->store_sub  && $store_data?->store_sub?->is_canceled == 1)
        <div class="free-trial trial danger-bg">
            <div class="inner-div">
                <div class="left">
                    <img src="{{asset('/public/assets/admin/img/timer-2.svg')}}" alt="">
                    <div class="left-content">
                        <h6>{{ translate('Your subscription has been cnaceled by') }} {{ $store_data?->store_sub?->canceled_by == 'admin' ? translate($store_data?->store_sub?->canceled_by) : translate('Yourself') }}</h6>
                        <div>{{ translate('You can not consume your subscription after') }} {{ \App\CentralLogics\Helpers::date_format($store_data?->store_sub?->expiry_date_parsed) }}</div>
                    </div>
                </div>
                <div class="right">
                    <a href="" class="btn btn-2">
                        <span class="circle-progress-container">
                            <svg width="40" viewBox="0 0 160 160">
                                <circle r="70" cx="80" cy="80" fill="transparent" stroke="#ffffff20" stroke-width="12px"></circle>
                                <circle r="70" cx="80" cy="80" fill="transparent" stroke="#ffffff" stroke-width="12px" stroke-dasharray="439.6px" stroke-dashoffset="{{ $subscriptionRingOffset }}px"></circle>
                            </svg>
                            {{ (int)Carbon\Carbon::now()->diffInDays($store_data?->store_sub?->expiry_date_parsed->format('Y-m-d'), false) }}
                        </span>
                        {{translate('Days left in this subscription')}}
                    </a>
                    <a href="{{route('vendor.subscriptionackage.subscriberDetail' ,['open_plans' => true])}}" class="btn btn-light">{{ translate('Change subscription plan') }} <i class="tio-arrow-forward"></i></a>
                </div>

                <button type="button" data-id="subscription_cancel_close_btn" class="trial-close add-to-session ">
                    <i class="tio-clear-circle"></i>
                </button>
            </div>
        </div>
        @elseif ( Session::get('subscription_plan_update_close_btn') !== true &&  $store_data?->store_sub  && $store_data?->store_sub?->package?->status != 1)
        <div class="free-trial trial danger-bg">
            <div class="inner-div">
                <div class="left">
                    <img src="{{asset('/public/assets/admin/img/timer-2.svg')}}" alt="">
                    <div class="left-content">
                        <h6>{{ translate('Your current subscription package has been disable by admin.') }} </h6>
                        <div>{{ translate('You can not renew this package after') }} {{ \App\CentralLogics\Helpers::date_format($store_data?->store_sub?->expiry_date_parsed) }}. {{ translate('To continue your subscription please chose another package.')  }}</div>
                    </div>
                </div>
                <div class="right">
                    <a href="" class="btn btn-2">
                        <span class="circle-progress-container">
                            <svg width="40" viewBox="0 0 160 160">
                                <circle r="70" cx="80" cy="80" fill="transparent" stroke="#ffffff20" stroke-width="12px"></circle>
                                <circle r="70" cx="80" cy="80" fill="transparent" stroke="#ffffff" stroke-width="12px" stroke-dasharray="439.6px" stroke-dashoffset="{{ $subscriptionRingOffset }}px"></circle>
                            </svg>
                            {{ (int)Carbon\Carbon::now()->diffInDays($store_data?->store_sub?->expiry_date_parsed->format('Y-m-d'), false) }}
                        </span>
                        {{translate('Days left in this subscription')}}
                    </a>
                    <a href="{{route('vendor.subscriptionackage.subscriberDetail' ,['open_plans' => true])}}" class="btn btn-light">{{ translate('Change subscription plan') }} <i class="tio-arrow-forward"></i></a>
                </div>

                <button type="button" data-id="subscription_plan_update_close_btn" class="trial-close add-to-session ">
                    <i class="tio-clear-circle"></i>
                </button>
            </div>
        </div>

        @elseif ($store_data?->store_sub == null)
        <div class="free-trial trial danger-bg">
            <div class="inner-div">
                <div class="left">
                    <img src="{{asset('/public/assets/admin/img/timer-2.svg')}}" alt="">
                    <div class="left-content">
                        <h6>{{ translate('Your subscription has been expired on') }} {{  \App\CentralLogics\Helpers::date_format($store_data?->store_sub_update_application?->expiry_date_parsed) }} </h6>
                        <div>{{ translate('Purchase a subscription plan or contact with the admin to settle the payment and unblock the access to service') }} </div>
                    </div>
                </div>
                <div class="right">

                    <a href="{{route('vendor.subscriptionackage.subscriberDetail' ,['open_plans' => true])}}" class="btn btn-light">{{ translate('Change/Renew subscription plan') }} <i class="tio-arrow-forward"></i></a>
                </div>
            </div>
        </div>

        @endif

@endif



<script>
    document.addEventListener('DOMContentLoaded', function () {
                $(document).on('click', '.add-to-session', function () {
                    var session_data = $(this).data("id");
                    $.ajax({
                        url: '{{ route('vendor.subscriptionackage.addToSession') }}',
                        method: 'POST',
                        data: {
                            value: session_data,
                            _token: '{{ csrf_token() }}'
                            },
                            success: function(response) {

                            }
                        });
                });
                $(document).on('click', '#hide-warning', function () {
                $('.hide-warning').hide();
                });


    });


</script>
