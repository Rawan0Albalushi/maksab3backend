@extends('layouts.admin.app')

@section('title', translate('messages.Delivery man settings'))


@section('content')
@php use App\CentralLogics\Helpers; @endphp
    <div class="content container-fluid">
        <div class="page-header">
            <div class="d-flex align-items-center justify-content-between gap-1 w-100">
                <h1 class="page-header-title mr-3">
                    <span class="page-header-icon">
                        <img src="{{ asset('public/assets/admin/img/outline/business.svg') }}" class="w--26" alt="">
                    </span>
                    <span>
                        {{translate('Business setup')}}
                    </span>
                </h1>
                <p class="page-header-desc">{{ translate('How deliverymen are assigned orders, what they may see and how they are paid.') }}</p>
                @if (!(Request::is('admin/business-settings/language') || Request::is('admin/business-settings/business-setup/refund-settings') || Request::is('admin/business-settings/business-setup/automated-message')))
                <div class="d-flex flex-wrap justify-content-end align-items-center flex-grow-1">
                    <div class="blinkings active">
                        <i class="tio-info-outined"></i>
                        <div class="business-notes">
                            <h6><img src="{{asset('/public/assets/admin/img/notes.png')}}" alt=""> {{translate('Note')}}</h6>
                            <div>
                                @if (Request::is('admin/business-settings/business-setup/refund-settings'))
                                *{{ translate('messages.If the Admin enables the \'Refund Request Mode\', customers can request a refund.') }}
                                @else
                                {{translate('messages.don\'t forget to click the \'Save Information\' button below to save changes.')}}
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
            @include('admin-views.business-settings.partials.nav-menu')
        </div>

        <form action="{{ route('admin.business-settings.update-dm') }}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="row g-2">
                <div class="col-lg-12">
                    <div class="card mb-20">
                        <div class="card-body">
                            <div class="rounded p-xxl-20 p-3 bg-light">
                                <div class="row g-3">
                                    <div class="col-sm-6 col-lg-4">
                                        @php($toggle_dm_registration =   Helpers::get_business_settings('toggle_dm_registration') )
                                        <div class="form-group mb-0">
                                            <span class="d-flex align-items-center mb-2">
                                                <span class="text-dark pr-1">
                                                    {{ translate('messages.Deliveryman Self Registration?') }}
                                                </span>
                                                <span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('messages.Deliverymen can register themselves from any app or the website. You get an email to accept or reject.') }}">
                                                    <i class="tio-info text-light-gray"></i>
                                                </span>
                                            </span>
                                            <label
                                                class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                <span class="pr-1 d-flex align-items-center switch--label">
                                                    <span class="line--limit-1">
                                                        {{ translate('messages.Status') }}
                                                    </span>
                                                </span>
                                                <input type="checkbox"
                                                       data-id="dm_self_registration1"
                                                       data-type="toggle"
                                                       data-image-on="{{ asset('/public/assets/admin/img/modal/dm-self-reg-on.png') }}"
                                                       data-image-off="{{ asset('/public/assets/admin/img/modal/dm-self-reg-off.png') }}"
                                                       data-title-on="{{ translate('messages.Want to enable') }} <strong>{{ translate('messages.Deliveryman Self Registration?') }}</strong>"
                                                       data-title-off="{{ translate('messages.Want to disable') }} <strong>{{ translate('messages.Deliveryman Self Registration?') }}</strong>"
                                                       data-text-on="<p>{{ translate('messages.Users can register as deliverymen from any app or the website.') }}</p>"
                                                       data-text-off="<p>{{ translate('messages.The feature is hidden from the deliveryman apps and the website.') }}</p>"
                                                       class="status toggle-switch-input dynamic-checkbox-toggle"

                                                       value="1"
                                                    name="toggle_dm_registration" id="dm_self_registration1"
                                                    {{ $toggle_dm_registration == 1 ? 'checked' : '' }}>
                                                <span class="toggle-switch-label text">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-4">
                                        @php($dm_maximum_orders =  Helpers::get_business_settings('dm_maximum_orders')   )
                                        <div class="form-group mb-0">
                                            <label class="form-label text-capitalize"
                                                for="dm_maximum_orders">
                                                <div class="d-flex align-items-center">
                                                    <span class="line--limit-1 flex-grow pr-1">{{ translate('Maximum Assigned Order Limit') }} </span>
                                                    <span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('messages.Set the maximum order limit a Deliveryman can take at a time.') }}">
                                                        <i class="tio-info text-light-gray"></i>
                                                    </span>
                                                </div>
                                            </label>
                                            <input type="number" name="dm_maximum_orders" class="form-control"
                                                id="dm_maximum_orders" min="1"
                                                value="{{ $dm_maximum_orders ?? 1 }}" required>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-4">
                                        <div class="form-group mb-0">
                                            <label class="input-label text-capitalize d-flex align-items-center"><span
                                                    class="line--limit-1 pr-1">{{ translate('messages.Can A Deliveryman Cancel Order?') }}</span>
                                                <span class="form-label-secondary"
                                                data-toggle="tooltip" data-placement="right"
                                                data-original-title="{{ translate('messages.Admin can enable/disable Deliveryman\'s order cancellation option in the respective app.') }}"><i class="tio-info text-light-gray"></i></span></label>

                                            <label
                                                class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                <span class="pr-1 d-flex align-items-center switch--label">
                                                    <span class="line--limit-1">
                                                        {{ translate('messages.Can cancel') }}
                                                    </span>
                                                </span>
                                                <input type="checkbox" class="status toggle-switch-input" value="1"
                                                    name="canceled_by_deliveryman" id="canceled_by_deliveryman" checked>
                                                <span class="toggle-switch-label text">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="fs-12 text-dark px-3 py-2 bg-opacity-10 rounded bg-info mt-20">
                                <div class="d-flex align-items-center gap-2 mb-0">
                                    <span class="text-info fs-16">
                                        <i class="tio-light-on"></i>
                                    </span>
                                    <span>
                                        {{ translate('You may setup') }} <strong> {{ translate('Registration Form') }} </strong> {{ translate('from') }} <strong class="text-primary">{{ translate('Deliveryman Registration Form') }}</strong> {{ translate('Page to work properly.') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-20">
                        <div class="card-body">
                            <div class="row g-3 align-items-center">
                                <div class="col-xxl-9 col-lg-8 col-md-7 col-sm-6">
                                    <div>
                                        <h3 class="mb-1">
                                            {{ translate('Tips For Deliveryman') }}
                                        </h3>
                                        <p class="mb-0 fs-12">
                                            {{ translate('Customers can give tips to deliverymen during checkout from the Customer App & Website.') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="col-xxl-3 col-lg-4 col-md-5 col-sm-6">
                                    @php($dm_tips_status = Helpers::get_business_settings('dm_tips_status'))
                                    <div class="form-group mb-0">
                                        <label class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                            <span class="line--limit-1 switch--label">
                                                {{ translate('messages.Status') }}
                                            </span>
                                            <input type="checkbox"
                                                    data-id="dm_tips_status"
                                                    data-type="toggle"
                                                    data-image-on="{{ asset('/public/assets/admin/img/modal/dm-tips-on.png') }}"
                                                    data-image-off="{{ asset('/public/assets/admin/img/modal/dm-tips-off.png') }}"
                                                    data-title-on="{{ translate('messages.Want to enable') }} <strong>{{ translate('messages.Tips for Deliveryman feature?') }}</strong>"
                                                    data-title-off="{{ translate('messages.Want to disable') }} <strong>{{ translate('messages.Tips for Deliveryman feature?') }}</strong>"
                                                    data-text-on="<p>{{ translate('messages.If you enable this, Customers can give tips to a deliveryman during checkout.') }}</p>"
                                                    data-text-off="<p>{{ translate('If you disable this, the tips for deliveryman feature will be hidden from the customer app and website.') }}</p>"
                                                    class="status toggle-switch-input dynamic-checkbox-toggle"
                                                    value="1"
                                                name="dm_tips_status" id="dm_tips_status"
                                                {{ $dm_tips_status == '1' ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="fs-12 text-dark px-3 py-2 rounded bg-warning-10 mt-20">
                                <div class="d-flex align-items-center gap-2 mb-0">
                                    <span class="text-warning fs-14">
                                        <i class="tio-info"></i>
                                    </span>
                                    <span class="color-656566">
                                        {{ translate('Admins do not receive any commission from tips given to deliverymen; these go entirely to the deliveryman earning.') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-20">
                        <div class="card-body">
                            <div class="mb-20">
                                <h3 class="mb-1">
                                    {{ translate('Deliveryman App Setup') }}
                                </h3>
                                <p class="mb-0 fs-12">
                                    {{ translate('Set up all necessary app configurations from here') }}
                                </p>
                            </div>
                            <div class="rounded p-xxl-20 p-3 bg-light">
                                <div class="row g-3">
                                    <div class="col-sm-6 col-lg-4">
                                        @php($show_dm_earning = Helpers::get_business_settings('show_dm_earning')  )
                                        <div class="form-group mb-0">
                                            <span class="d-flex align-items-center mb-2">
                                                <span class="text-dark pr-1">
                                                    {{ translate('messages.Show Earnings in App?') }}
                                                </span>
                                                <span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('messages.With this feature, Deliverymen can see their earnings on a specific order while accepting it.') }}">
                                                    <i class="tio-info text-light-gray"></i>
                                                </span>
                                            </span>
                                            <label
                                                class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                <span class="pr-1 d-flex align-items-center switch--label">
                                                    <span class="line--limit-1">
                                                        {{ translate('Status') }}
                                                    </span>
                                                </span>
                                                <input type="checkbox"
                                                        data-id="show_dm_earning"
                                                        data-type="toggle"
                                                        data-image-on="{{ asset('/public/assets/admin/img/modal/show-earning-in-apps-on.png') }}"
                                                        data-image-off="{{ asset('/public/assets/admin/img/modal/show-earning-in-apps-off.png') }}"
                                                        data-title-on="{{ translate('messages.Want to enable') }} <strong>{{ translate('messages.Show Earnings in App?') }}</strong>"
                                                        data-title-off="{{ translate('messages.Want to disable') }} <strong>{{ translate('messages.Show Earnings in App?') }}</strong>"
                                                        data-text-on="<p>{{ translate('messages.Deliverymen can see their earning per order on the Order Details page.') }}</p>"
                                                        data-text-off="<p>{{ translate('If you disable this, the feature will be hidden from the deliveryman app.') }}</p>"
                                                        class="status toggle-switch-input dynamic-checkbox-toggle"
    
                                                        value="1"
                                                    name="show_dm_earning" id="show_dm_earning"
                                                    {{ $show_dm_earning == 1 ? 'checked' : '' }}>
                                                <span class="toggle-switch-label text">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>                           
                                    <div class="col-sm-6 col-lg-4">
                                        @php($dm_picture_upload_status = Helpers::get_business_settings('dm_picture_upload_status'))
                                        <div class="form-group mb-0">
                                            <span class="d-flex align-items-center mb-2">
                                                <span class="text-dark pr-1">
                                                    {{ translate('messages.Take Picture for Delivery Completing') }}
                                                </span>
                                                <span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('messages.Deliverymen can photograph delivered products when swiping to confirm delivery.') }}">
                                                    <i class="tio-info text-light-gray"></i>
                                                </span>
                                            </span>
                                            <label
                                                class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                <span class="pr-1 d-flex align-items-center switch--label">
                                                    <span class="line--limit-1">
                                                        {{ translate('messages.Status') }}
                                                    </span>
                                                </span>
                                                <input type="checkbox"
                                                        data-id="dm_picture_upload_status"
                                                        data-type="toggle"
                                                        data-image-on="{{ asset('/public/assets/admin/img/modal/dm-self-reg-on.png') }}"
                                                        data-image-off="{{ asset('/public/assets/admin/img/modal/dm-self-reg-off.png') }}"
                                                        data-title-on="{{ translate('messages.Want to enable') }} <strong>{{ translate('messages.Picture upload before complete?') }}</strong>"
                                                        data-title-off="{{ translate('messages.Want to disable') }} <strong>{{ translate('messages.Picture upload before complete?') }}</strong>"
                                                        data-text-on="<p>{{ translate('messages.If you enable this, delivery man can upload order proof before order delivery.') }}</p>"
                                                        data-text-off="<p>{{ translate('If you disable this, this feature will be hidden from the deliveryman app.') }}</p>"
                                                        class="status toggle-switch-input dynamic-checkbox-toggle"
                                                        value="1"
                                                    name="dm_picture_upload_status" id="dm_picture_upload_status"
                                                    {{ $dm_picture_upload_status == 1 ? 'checked' : '' }}>
                                                <span class="toggle-switch-label text">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div class="mb-20">
                                <h3 class="mb-1">
                                    {{ translate('Cash in Hand Controls') }}
                                </h3>
                                <p class="mb-0 fs-12">
                                    {{ translate('Configure the necessary settings for cash-in-hand management for deliverymen.') }}
                                </p>
                            </div>
                            <div class="rounded p-xxl-20 p-3 bg-light2">        
                                <div class="row g-3">
                                    <div class="col-sm-6 col-lg-4">
                                        @php($cash_in_hand_overflow = Helpers::get_business_settings('cash_in_hand_overflow_delivery_man'))
                                        <div class="form-label  mb-0 ">
                                            <span class="d-flex align-items-center mb-2">
                                                <span class="text-dark pr-1">
                                                    {{ translate('messages.Suspend on Cash In Hand Overflow') }}
                                                </span>
                                                <span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('messages.If enabled, delivery men will be automatically suspended by the system when their \'Cash in Hand\' limit is exceeded.') }}">
                                                    <i class="tio-info text-light-gray"></i>
                                                </span>
                                            </span>
                                            <label
                                                class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                <span class="pr-1 d-flex align-items-center switch--label">
                                                    <span class="line--limit-1">
                                                        {{ translate('messages.Status') }}
                                                    </span>
                                                </span>
                                                <input type="checkbox"
                                                       data-id="cash_in_hand_overflow"
                                                       data-type="toggle"
                                                       data-image-on="{{ asset('/public/assets/admin/img/modal/show-earning-in-apps-on.png') }}"
                                                       data-image-off="{{ asset('/public/assets/admin/img/modal/show-earning-in-apps-off.png') }}"
                                                       data-title-on="{{ translate('Want to enable') }} <strong>{{ translate('Cash in hand overflow') }}</strong>?"
                                                       data-title-off="{{ translate('Want to disable') }} <strong>{{ translate('Cash in hand overflow') }}</strong>?"
                                                       data-text-on="<p>{{ translate('If enabled, delivery men have to provide collected cash by themselves.') }}</p>"
                                                       data-text-off="<p>{{ translate('If disabled, delivery men do not have to provide collected cash by themselves.') }}</p>"
                                                       class="status toggle-switch-input dynamic-checkbox-toggle"
                                                       value="1"
                                                       name="cash_in_hand_overflow_delivery_man" id="cash_in_hand_overflow"
                                                    {{ $cash_in_hand_overflow == 1 ? 'checked' : '' }}>
                                                <span class="toggle-switch-label text">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-4">
                                        @php($dm_max_cash_in_hand =  Helpers::get_business_settings('dm_max_cash_in_hand') )
                                        <div class="form-label mb-0">
                                            <label class="d-flex text-capitalize"
                                                   for="dm_max_cash_in_hand">
                                                <span class="line--limit-1">
                                                    {{translate('Cash In hand Max Amount')}} ({{ \App\CentralLogics\Helpers::currency_symbol() }})
                                                </span>
                                                <span data-toggle="tooltip" data-placement="right" data-original-title="{{translate('Over the cash-in-hand limit, a deliveryman must deposit with the admin before accepting orders.')}}" class="input-label-secondary"><i class="tio-info text-light-gray"></i></span>
                                            </label>
                                            <input type="number" name="dm_max_cash_in_hand" class="form-control"
                                                   id="dm_max_cash_in_hand" min="0" step=".001"
                                                   value="{{ $dm_max_cash_in_hand ?? '' }}" {{ $cash_in_hand_overflow  == 1 ? 'required' : 'readonly' }} >
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-4">
                                        @php($min_amount_to_pay_dm = Helpers::get_business_settings('min_amount_to_pay_dm')  )
                                        <div class="form-label mb-0">
                                            <label class="text-capitalize"
                                                   for="min_amount_to_pay_dm">
                                                <span>
                                                    {{ translate('Minimum Payable Amount') }} ({{ \App\CentralLogics\Helpers::currency_symbol() }})

                                                </span>

                                                <span class="form-label-secondary"
                                                      data-toggle="tooltip" data-placement="right"
                                                      data-original-title="{{ translate('Enter the minimum cash amount delivery men can pay') }}"><i class="tio-info text-light-gray"></i></span>
                                            </label>
                                            <input type="number" name="min_amount_to_pay_dm" class="form-control"
                                                   id="min_amount_to_pay_dm" min="0" step=".001"
                                                   value="{{ $min_amount_to_pay_dm ?? '' }}"  {{ $cash_in_hand_overflow  == 1 ? 'required' : 'readonly' }} >
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="fs-12 text-dark px-3 py-2 bg-opacity-10 rounded bg-info mt-20">
                                <div class="d-flex align-items-center gap-2 mb-0">
                                    <span class="text-info fs-16">
                                        <i class="tio-light-on"></i>
                                    </span>
                                    <span>
                                        {{ translate('Configure the maximum cash amount a delivery person can hold and set a minimum payment threshold for better financial oversight.') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    @php($dm_loyality_point_status = Helpers::get_business_settings('dm_loyality_point_status')  )
                    @php($dm_loyality_point_per_order = Helpers::get_business_settings('dm_loyality_point_per_order')  )
                    @php($dm_loyality_point_conversion_rate = Helpers::get_business_settings('dm_loyality_point_conversion_rate')  )
                    @php($dm_min_loyality_point_to_convert = Helpers::get_business_settings('dm_min_loyality_point_to_convert')  )

                    <div class="card mt-20 card-container">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between gap-2 flex-sm-nowrap flex-wrap">
                                <div>
                                    <h4 class="mb-1">{{translate('Loyalty point')}}</h4>
                                    <p class="fs-12 m-0">{{translate('If enabled, deliverymen will earn a certain number of points for each successful delivery.')}}</p>
                                </div>
                                <div class="d-flex flex-sm-nowrap flex-wrap justify-content-end justify-content-end align-items-center gap-3">
                                    <div class="view_toggle_btn fz--14px info-dark cursor-pointer text-decoration-underline font-semibold d-flex align-items-center gap-1">
                                        {{ translate('messages.View') }}
                                        <i class="tio-chevron-down fs-22"></i>
                                    </div>
                                    <div class="mb-0">
                                        <label class="toggle-switch toggle-switch-sm mb-0">
                                            <input type="checkbox" data-type="toggle" class="status toggle-switch-input" name="dm_loyality_point_status" id="dm_loyality_point_status" value="1" {{ $dm_loyality_point_status == 1 ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text mb-0">
                                                <span
                                                    class="toggle-switch-indicator">
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="card-details-body {{ !$dm_loyality_point_status ? 'd-none' : '' }} ">
                                <div class="bg-light2  rounded p-xxl-20 p-3 mt-20">
                                    <div class="row g-3">
                                        <div class="col-sm-6 col-lg-4">
                                            <div class="form-group mb-0">
                                                <label class="form-label text-capitalize" for="dm_loyality_point_per_order">
                                                    <div class="d-flex align-items-center">
                                                        <span class="line--limit-1 flex-grow pr-1">{{ translate('Loyalty Point Earn Per Order') }} </span>
                                                    </div>
                                                </label>
                                                <input type="number" name="dm_loyality_point_per_order" class="form-control" min="0"   max="9999999999"  id="dm_loyality_point_per_order" placeholder="1" value="{{ $dm_loyality_point_per_order ?? ''}}" {{ $dm_loyality_point_status == 1 ? 'required':'readonly' }}>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-lg-4">
                                            <div class="form-group mb-0">
                                                <label class="form-label text-capitalize" for="dm_loyality_point_conversion_rate">
                                                    <div class="d-flex align-items-center">
                                                        <span class="line--limit-1 flex-grow pr-1">{{ \App\CentralLogics\Helpers::currency_symbol() }} 1.00 {{ translate('Equivalent To Points') }} </span>
                                                    </div>
                                                </label>
                                                <input type="number" name="dm_loyality_point_conversion_rate"  min="0" max="999999999"  class="form-control" id="dm_loyality_point_conversion_rate" placeholder="100" value="{{ $dm_loyality_point_conversion_rate ?? ''}}" {{ $dm_loyality_point_status == 1 ? 'required':'readonly' }}>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-lg-4">
                                            <div class="form-group mb-0">
                                                <label class="form-label text-capitalize" for="dm_min_loyality_point_to_convert">
                                                    <div class="d-flex align-items-center">
                                                        <span class="line--limit-1 flex-grow pr-1">{{ translate('Minimum Point Required To Convert') }} </span>
                                                    </div>
                                                </label>
                                                <input type="number" name="dm_min_loyality_point_to_convert" min="0" max="999999999"  class="form-control" id="dm_min_loyality_point_to_convert" placeholder="200" value="{{ $dm_min_loyality_point_to_convert ?? '' }}" {{ $dm_loyality_point_status == 1 ? 'required':'readonly' }}>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    @php($dm_referal_status = Helpers::get_business_settings('dm_referal_status')  )
                    @php($dm_referal_amount = Helpers::get_business_settings('dm_referal_amount')  )
                    @php($dm_referal_bonus = Helpers::get_business_settings('dm_referal_bonus')  )

                    <div class="card mt-20 card-container">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between gap-2 flex-sm-nowrap flex-wrap">
                                <div>
                                    <h4 class="mb-1">{{translate('Deliveryman Referral Earning Settings')}}</h4>
                                    <p class="fs-12 m-0">{{translate('Allow Drivers to refer your app to friends and family using a unique code and earn rewards.')}}</p>
                                </div>
                                <div class="d-flex flex-sm-nowrap flex-wrap justify-content-end justify-content-end align-items-center gap-3">
                                    <div class="view_toggle_btn fz--14px info-dark cursor-pointer text-decoration-underline font-semibold d-flex align-items-center gap-1">
                                        {{ translate('messages.View') }}
                                        <i class="tio-chevron-down fs-22"></i>
                                    </div>
                                    <div class="mb-0">
                                        <label class="toggle-switch toggle-switch-sm mb-0">
                                            <input type="checkbox" data-type="toggle" class="status toggle-switch-input" name="dm_referal_status" id="dm_referal_status" value="1" {{ $dm_referal_status == 1 ? 'checked' : '' }} >
                                            <span class="toggle-switch-label text mb-0">
                                                <span
                                                    class="toggle-switch-indicator">
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="card-details-body {{ !$dm_referal_status ? 'd-none' : '' }}">
                                <div class="bg-light2 d-flex flex-column gap-4 rounded p-xxl-20 p-3 mt-20">
                                    <div class="row g-3">
                                        <div class="col-md-6 col-lg-4">
                                            <div>
                                                <h4 class="mb-1">{{translate('Who Share the Code')}}</h4>
                                                <p class="fs-12 m-0">{{translate('Reward for the person who signs up with a rider referral code and completes their first order.')}}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-lg-8">
                                            <div class="bg-white rounded p-xxl-20 p-2">
                                                <div class="form-group mb-0">
                                                    <label class="form-label text-capitalize" for="dm_referal_amount">
                                                        <div class="d-flex align-items-center">
                                                            <span class="line--limit-1 flex-grow pr-1">{{ translate('Earning Per Referral') }} ({{ \App\CentralLogics\Helpers::currency_symbol() }})  <span class="text-danger">*</span> </span>
                                                        </div>
                                                    </label>
                                                    <input type="number" name="dm_referal_amount"   min="0" max="999999999" step="0.001" class="form-control " id="dm_referal_amount" placeholder="100" value="{{ $dm_referal_amount??'' }}" {{ $dm_referal_status ? 'required' : 'readonly' }}>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6 col-lg-4">
                                            <div>
                                                <h4 class="mb-1">{{translate('Who Use the Code')}}</h4>
                                                <p class="fs-12 m-0">{{translate('Set the reward amount that riders receive when signing up with a referral code & completes first order')}}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-lg-8">
                                            <div class="bg-white rounded p-xxl-20 p-2">
                                                <div class="form-group mb-0">
                                                    <label class="form-label text-capitalize" for="dm_referal_bonus">
                                                        <div class="d-flex align-items-center">
                                                            <span class="line--limit-1 flex-grow pr-1">{{ translate('Bonus In Wallet') }} ({{ \App\CentralLogics\Helpers::currency_symbol() }}) <span class="text-danger">*</span> </span>
                                                        </div>
                                                    </label>
                                                    <input type="number" name="dm_referal_bonus" min="0" max="999999999" step="0.001" class="form-control " id="dm_referal_bonus" placeholder="100" value="{{ $dm_referal_bonus  ?? ''}}" {{ $dm_referal_status ? 'required' : 'readonly' }}>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="btn--container justify-content-end mt-4">
                        <button type="reset" id="reset_btn" class="btn min-w-120px btn--reset location-reload"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                        <button type="submit" id="submit" class="btn min-w-120px btn--primary"><i class="tio-save"></i> {{ translate('Save information') }}</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('script_2')

    <script>
        "use strict";
        $(document).on('ready', function () {

            function toggleFields(checkbox, fields) {
                if ($(checkbox).is(':checked')) {
                    $(fields).attr('required', true).removeAttr('readonly');
                } else {
                    $(fields).attr('required', false).attr('readonly', true);
                }
            }

            $('#dm_referal_status').on('change', function () {
                toggleFields(this, '#dm_referal_amount, #dm_referal_bonus');
            }).trigger('change');

            $('#dm_loyality_point_status').on('change', function () {
                toggleFields(this, '#dm_loyality_point_per_order, #dm_loyality_point_conversion_rate, #dm_min_loyality_point_to_convert');
            }).trigger('change');

        });

    </script>
@endpush
