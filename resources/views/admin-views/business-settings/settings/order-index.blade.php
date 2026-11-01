@extends('layouts.admin.app')

@section('title', translate('Business setup'))


@section('content')
@php use App\CentralLogics\Helpers; @endphp
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title mr-3">
            <span class="page-header-icon">
                <img src="{{ asset('public/assets/admin/img/outline/business.svg') }}" class="w--26" alt="">
            </span>
            <span>
                {{ translate('Business settings') }}
            </span>
        </h1>
        <p class="page-header-desc">{{ translate('How orders behave, from the delivery charge to cancellation and scheduling rules.') }}</p>
        @include('admin-views.business-settings.partials.nav-menu')
    </div>
    <form action="{{ route('admin.business-settings.update-order') }}" method="post" enctype="multipart/form-data"
        id="order-settings-form">
        @csrf

        <div class="row g-3">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="info-notes-bg px-3 py-2 rounded fz-11  gap-2 align-items-center d-flex mb-20">
                            <img src="{{asset('public/assets/admin/img/info-idea.svg')}}" alt="">
                            @php($all_orders_link = '<a href="'.e(route('admin.order.list', ['status' => 'all'])).'" class="fz-12px font-semibold info-dark">'.e(translate('All orders')).'</a>')
                            <span>
                                {{ translate('See and manage all orders') }}: {!! $all_orders_link !!}
                            </span>
                        </div>
                        <div class="p-xxl-20 shadow-xxl bg-white rounded mb-20"id="order_type_section">
                                <div class="mb-20">
                                    <div>
                                        <h4 class="mb-1">
                                            {{ translate('Order type') }}
                                        </h4>
                                        <p class="mb-0 fs-12">
                                            {{ translate('Which way customer order their food') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="bg-light rounded p-xxl-20 p-3">
                                    <div class="bg-white rounded p-3 border">
                                        <div class="row g-3">
                                            <div class="col-md-6 col-lg-4">
                                                @php($home_delivery_status = Helpers::get_business_settings('home_delivery_status'))
                                                <div class="form-group m-0">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input" id="home_delivery_status-id" value="1" name="home_delivery_status" {{ $home_delivery_status ? 'checked' : '' }}>
                                                        <label class="custom-control-label size-checkbox-20" for="home_delivery_status-id">
                                                            <h5 class="mb-1">{{ translate('Home delivery') }}</h5>
                                                            <p class="mb-0 fs-12">
                                                                {{ translate('If enabled, customers can choose Home Delivery option from the customer app and website') }}
                                                            </p>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6 col-lg-4">
                                                @php($takeaway_status = Helpers::get_business_settings('takeaway_status'))
                                                <div class="form-group m-0">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input" id="takeaway_status-id" value="1" name="takeaway_status" {{ $takeaway_status ? 'checked' : '' }}>
                                                        <label class="custom-control-label size-checkbox-20" for="takeaway_status-id">
                                                            <h5 class="mb-1">{{ translate('Takeaway') }}</h5>
                                                            <p class="mb-0 fs-12">
                                                                {{ translate('If enabled, customers can use Takeaway feature during checkout from the Customer App/Website.') }}
                                                            </p>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6 col-lg-4">
                                                @php($schedule_order = Helpers::get_business_settings('schedule_order'))

                                                <div class="form-group m-0">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input schedule_order-in" value="1" id="schedule_order-id" name="schedule_order" {{ $schedule_order ? 'checked' : '' }}>
                                                        <label class="custom-control-label size-checkbox-20" for="schedule_order-id">
                                                            <h5 class="mb-1">{{ translate('Scheduled order') }}</h5>
                                                            <p class="mb-0 fs-12">
                                                                {{ translate('If enabled, customers can choose their preferred order time from the Customer App or Website') }}
                                                            </p>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="order-type-box d-none">
                                        <div class="mt-20">
                                            @php($schedule_order_slot_duration = Helpers::get_business_settings('schedule_order_slot_duration'))
                                            @php($schedule_order_slot_duration_time_format = Helpers::get_business_settings('schedule_order_slot_duration_time_format'))
                                            <div class="form-group mb-0">
                                                <label class="input-label text-capitalize d-flex alig-items-center"
                                                    for="schedule_order_slot_duration">
                                                    <span class="pr-1 d-flex align-items-center switch--label">
                                                        <span class="line--limit-1">
                                                            {{ translate('messages.Time Interval for Scheduled Delivery') }}
                                                        </span>
                                                        <span class="form-label-secondary text-danger"
                                                        data-toggle="tooltip" data-placement="right"
                                                        data-original-title="{{ translate('Customers choose a delivery slot in minute or hour intervals set by admin.') }}"><i class="tio-info text-muted"></i></span>
                                                    </span>
                                                </label>
                                                <div class="d-flex border rounded overflow-hidden">
                                                    <input type="number" name="schedule_order_slot_duration" class="form-control rounded-0 border-0"
                                                    id="schedule_order_slot_duration"
                                                    value="{{ $schedule_order_slot_duration ? $schedule_order_slot_duration_time_format == 'hour' ? $schedule_order_slot_duration / 60 : $schedule_order_slot_duration : 0 }}"
                                                    min="0" required>
                                                    <select   name="schedule_order_slot_duration_time_format" class="custom-select rounded-0 border-0 bg-modal-btn form-control w-90px">
                                                        <option  value="min" {{ $schedule_order_slot_duration_time_format == 'min' ? 'selected' : '' }}>{{ translate('Min') }}</option>
                                                        <option  value="hour" {{ $schedule_order_slot_duration_time_format == 'hour' ? 'selected' : ''}}>{{ translate('Hour') }}</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex p-2 px-3 rounded gap-2 bg-opacity-warning-10 mt-20">
                                        <i class="tio-info text-warning"></i>
                                        <p class="fz-12px mb-0">
                                            {{translate('At least one delivery method must be selected for your business')}}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            @include('admin-views.business-settings.settings.partials._product-bundle')

                            <div class="p-xxl-20 shadow-xxl bg-white rounded mb-20" id="notification_setup_section">
                                <div class="mb-20">
                                    <div>
                                        <h4 class="mb-1">
                                            {{ translate('Notification setup') }}
                                        </h4>
                                        <p class="mb-0 fs-12">
                                            {{ translate('Here you can manage the notification settings for this panel') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="bg-light rounded p-xxl-20 p-3">
                                    <div class="row g-3">
                                        <div class="col-sm-6 col-lg-4 access_product_approval">
                                             @php($admin_order_notification = Helpers::get_business_settings('admin_order_notification'))
                                            <div class="form-group mb-0">
                                                <span class="mb-2 d-flex align-items-center text-title">
                                                    <span class="text-title">
                                                        {{ translate('messages.Order Notification for Admin?') }}
                                                    </span>
                                                    <span class="form-label-secondary text-danger d-flex" data-toggle="tooltip"
                                                        data-placement="right"
                                                        data-original-title="{{ translate('messages.Admin will get a pop-up notification with sounds for any order placed by customers.') }}">
                                                        <i class="tio-info text-muted top01"></i>
                                                    </span>
                                                </span>
                                                <label
                                                    class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                    <span class="pr-1 d-flex align-items-center switch--label">
                                                        <span class="line--limit-1">
                                                            {{ translate('messages.Status') }}
                                                        </span>
                                                    </span>
                                                    <input type="checkbox" data-id="aon1" data-type="toggle"
                                                        data-image-on="{{ asset('/public/assets/admin/img/modal/order-notification-on.png') }}"
                                                        data-image-off="{{ asset('/public/assets/admin/img/modal/order-notification-off.png') }}"
                                                        data-title-on="{{ translate('messages.Want to enable') }} <strong>{{ translate('messages.Order Notification for Admin?') }}</strong>"
                                                        data-title-off="{{ translate('messages.Want to disable') }} <strong>{{ translate('messages.Order Notification for Admin?') }}</strong>"
                                                        data-text-on="<p>{{ translate('messages.If you enable this, the Admin will receive a Notification for every order placed.') }}</p>"
                                                        data-text-off="<p>{{ translate('If you disable this, the admin will NOT receive a notification for every order placed.') }}</p>"
                                                        class="status toggle-switch-input dynamic-checkbox-toggle" value="1"
                                                        name="admin_order_notification" id="aon1" {{ $admin_order_notification == 1 ? 'checked' : '' }}>
                                                    <span class="toggle-switch-label text">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-lg-4 access_product_approval">
                                            @php($order_notification_type = Helpers::get_business_settings('order_notification_type'))
                                            <input type="hidden" id="hidden_notification_type">
                                            <div class="form-group mb-0">
                                                <label class="input-label text-capitalize d-flex alig-items-center"><span
                                                        class="line--limit-1 text-title">{{ translate('Order Notification Type') }}
                                                        <span class="form-label-secondary" data-toggle="tooltip"
                                                            data-placement="right"
                                                            data-original-title="{{ translate('Firebase sends one notification per order. Manual repeats until the order is viewed.') }} {{ translate('Repeat interval') }}: 10 {{ translate('seconds') }}">
                                                            <i class="tio-info text-muted top01"></i>
                                                        </span>
                                                    </span>
                                                </label>
                                                <div class="resturant-type-group bg-white border flex-sm-nowrap gap-1 flex-wrap">
                                                    <label class="form-check form--check w-100">
                                                        <input class="form-check-input" type="radio" value="firebase"
                                                            name="order_notification_type" {{ $order_notification_type ? ($order_notification_type == 'firebase' ? 'checked' : '') : '' }}>
                                                        <span class="form-check-label">
                                                    Firebase
                                                </span>
                                                    </label>
                                                    <label class="form-check form--check w-100">
                                                        <input class="form-check-input" type="radio" value="manual"
                                                            name="order_notification_type" {{ $order_notification_type ? ($order_notification_type == 'manual' ? 'checked' : '') : '' }}>
                                                        <span class="form-check-label">
                                                    {{translate('Manual')}}
                                                </span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="fs-12 text-dark px-3 py-2 rounded bg-warning-10 mt-20">
                                    <div class="d-flex gap-2 mb-1">
                                        <span class="text-warning lh-1 fs-14">
                                            <i class="tio-info"></i>
                                        </span>
                                        <span>
                                            {{ translate('To receive order notifications properly, select the notification type based on your preference') }}:
                                        </span>
                                    </div>
                                    <ul class="mb-0 gap-1 d-flex flex-column">
                                        <li>{{ translate('Manual Notification: You need to send order notifications manually for each order update.') }} </li>
                                        <li>
                                            {{ translate('Firebase Notification: Order notifications will be sent automatically. Ensure') }} <a target="_blank" style="text-decoration: underline; color: #245BD1;" href="{{ route('admin.business-settings.fcm-config') }}" class="font-semibold text-primary">{{ translate('Firebase Configuration') }}</a>  {{ translate('is completed and notification messages are set up in the Notification Message section.') }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            {{-- Free Delivery Setup moved to its own per-(zone, module) screen in
                                 S6 — Delivery Management -> Free Delivery Setup. The three business
                                 settings this block edited are deprecated and nothing reads them, so
                                 the controls are gone rather than left editing dead config. --}}
                            <div class="p-xxl-20 p-3 shadow-sm bg-white rounded mb-20" id="extra_packaging_section">
                                <div class="">
                                    <div class="row g-1 align-items-center">
                                        <div class="col-xxl-9 col-lg-8 col-md-7 col-sm-6">
                                            <div>
                                                <h4 class="mb-1">
                                                    {{ translate('Enable Extra Packaging Charge') }}
                                                </h4>
                                                <p class="mb-0 fs-12">
                                                    {{ translate('Adds an extra fee for orders that need additional protection, such as fragile or bulky items.') }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-xxl-3 col-lg-4 col-md-5 col-sm-6">
                                            <div class="">
                                                @php($extra_packaging_charge_status = Helpers::get_business_settings('extra_packaging_charge_status'))
                                                <div class="form-group mb-0">
                                                    <label class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                        <span class="pr-1 d-flex align-items-center switch--label">
                                                            <span class="line--limit-1">
                                                                {{translate('messages.Status') }}
                                                            </span>
                                                        </span>
                                                        <input type="checkbox" class="status toggle-switch-input" name="extra_packaging_charge_status" value="1" {{ $extra_packaging_charge_status ? 'checked' : '' }} id="extra_packaging_charge_status">
                                                        <span class="toggle-switch-label text">
                                                            <span class="toggle-switch-indicator"></span>
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @php($extra_packaging_data = Helpers::get_business_settings('extra_packaging_data'))

                                <div class="mb-0 mt-20 access_product_approval" id="extra_packaging_charge_options">
                                    <label class="mb-2 input-label text-capitalize d-flex alig-items-center" for="">
                                        {{ translate('Enable Extra Packaging Charge') }}
                                        <span class="text-danger">*</span>
                                        <span class="form-label-secondary text-danger"
                                        data-toggle="tooltip" data-placement="right"
                                        data-original-title="{{ translate('messages.After saving information, sellers will get the option to offer extra packaging charge to the customer') }}"><i class="tio-info text-muted ps--3"></i></span>
                                    </label>
                                    <div class="rounded border py-2 min-h-45px bg-white px-3">
                                        <div class="row g-lg-3 g-1">
                                            @foreach (config('module.module_type') as $key => $value)
                                                @if ($value != 'parcel' && $value != 'rental' && $value != 'ride-share' && $value != 'service')
                                                    <div class="col-lg-3 col-sm-6">
                                                        <div class="custom-control custom-checkbox pt-1">
                                                            <input class="custom-control-input extra-packaging-option" type="checkbox" {{ isset($extra_packaging_data[$value]) && $extra_packaging_data[$value] == 1 ? 'checked' : '' }} id="inlineCheckbox{{$key}}" value="1" name="{{ $value }}">
                                                            <label class="custom-control-label" for="inlineCheckbox{{$key}}">{{ translate($value) }}</label>
                                                        </div>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="p-xxl-20 p-3 shadow-sm bg-white rounded mb-20" id="extra_packaging_section">
                                <div class="">
                                    <div class="row g-1 align-items-center">
                                        <div class="col-xxl-9 col-lg-8 col-md-7 col-sm-6">
                                            <div>
                                                <h4 class="mb-1">
                                                    {{ translate('Monthly Order Setup') }}
                                                </h4>
                                                <p class="mb-0 fs-12">
                                                    {{ translate('Shows the monthly order option on add to cart for pharmacy and grocery only.') }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-xxl-3 col-lg-4 col-md-5 col-sm-6">
                                            <div class="">
                                                <div class="form-group mb-0">
                                                    <label class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                        <span class="pr-1 d-flex align-items-center switch--label">
                                                            <span class="line--limit-1">
                                                                {{translate('messages.Status') }}
                                                            </span>
                                                        </span>
                                                        <input type="checkbox" class="status toggle-switch-input" name="monthly_order_reminder" value="1" {{ Helpers::get_business_settings('monthly_order_reminder') == 1 ? 'checked' : '' }}>
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
                            <div class="p-xxl-20 p-3 shadow-sm bg-white rounded mb-20">
                                <div class="">
                                    <div class="row g-1 align-items-center">
                                        <div class="col-xxl-9 col-lg-8 col-md-7 col-sm-6">
                                            <div>
                                                <h4 class="mb-1">
                                                    {{ translate('Re-order Feature') }}
                                                </h4>
                                                <p class="mb-0 fs-12">
                                                    {{ translate('By turning on customer can easily reorder from their past order List.') }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-xxl-3 col-lg-4 col-md-5 col-sm-6">
                                            <div class="">
                                                <div class="form-group mb-0">
                                                    <label class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                        <span class="pr-1 d-flex align-items-center switch--label">
                                                            <span class="line--limit-1">
                                                                {{translate('messages.Status') }}
                                                            </span>
                                                        </span>
                                                        <input type="checkbox" class="status toggle-switch-input" name="repeat_order_option" value="1" {{ Helpers::get_business_settings('repeat_order_option') == 1 ? 'checked' : '' }}>
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
                            <div class="p-xxl-20 p-3 shadow-sm bg-white rounded mb-20" id="other_setup_section">
                                <div class="mb-20">
                                    <div>
                                        <h4 class="mb-1">
                                            {{ translate('Other Setup') }}
                                        </h4>
                                        <p class="mb-0 fs-12">
                                            {{ translate('Setup your business time zone and format from here') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="bg-light rounded p-xxl-20 p-3">
                                    <div class="row g-3">
                                        <div class="col-sm-6 col-lg-4">

                                            @php($prescription_order_status = Helpers::get_business_settings('prescription_order_status'))
                                            <div class="form-group mb-0">
                                                <span class="mb-2 d-flex align-items-center">
                                                        <span class="text-title">
                                                            {{ translate('messages.Place Order by Prescription?') }}
                                                        </span>
                                                        <span class="form-label-secondary text-danger d-flex"
                                                            data-toggle="tooltip" data-placement="right"
                                                            data-original-title="{{ translate('messages.Customers can order by uploading a prescription. Stores can turn this off in their settings.') }}">
                                                            <i class="tio-info text-muted ps--3"></i>
                                                        </span>
                                                    </span>
                                                <label
                                                    class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                    <span class="pr-1 d-flex align-items-center switch--label">
                                                        <span class="line--limit-1 text-title">
                                                            {{ translate('messages.Status') }}
                                                        </span>

                                                    </span>
                                                    <input type="checkbox"
                                                           data-id="prescription_order_status"
                                                           data-type="toggle"
                                                           data-image-on="{{ asset('/public/assets/admin/img/modal/prescription-on.png') }}"
                                                           data-image-off="{{ asset('/public/assets/admin/img/modal/prescription-off.png') }}"
                                                           data-title-on="{{ translate('messages.Want to enable') }} <strong>{{ translate('messages.Place Order by Prescription?') }}</strong>"
                                                           data-title-off="{{ translate('messages.Want to disable') }} <strong>{{ translate('messages.Place Order by Prescription?') }}</strong>"
                                                           data-text-on="<p>{{ translate('Customers can order by uploading a prescription in the pharmacy module. Stores can turn this off in their settings.') }}</p>"
                                                           data-text-off="<p>{{ translate('messages.If disabled, this feature will be hidden from the Customer App, Website, and Store App & Panel.') }}</p>"
                                                           class="status toggle-switch-input dynamic-checkbox-toggle"
                                                           value="1"
                                                        name="prescription_order_status" id="prescription_order_status"
                                                        {{ $prescription_order_status == 1 ? 'checked' : '' }}>
                                                    <span class="toggle-switch-label text">
                                                        <span class="toggle-switch-indicator"></span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-lg-4">
                                             @php($odc = Helpers::get_business_settings('order_delivery_verification'))
                                            <div class="form-group mb-0">
                                                <span class="d-flex align-items-center mb-2">
                                                    <span class="text-title">
                                                        {{ translate('messages.Order delivery verification') }}
                                                    </span>
                                                    <span class="form-label-secondary text-danger d-flex"
                                                        data-toggle="tooltip" data-placement="right"
                                                        data-original-title="{{ translate('Customers get a verification code in the app and give it to the deliveryman to confirm delivery.') }} {{ translate('Code length') }}: 4">
                                                        <i class="tio-info text-muted ps--3"></i>
                                                    </span>
                                                </span>
                                                <label
                                                    class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                    <span class="pr-1 d-flex align-items-center switch--label">
                                                        <span class="line--limit-1 text-title">
                                                            {{ translate('messages.Status') }}
                                                        </span>
                                                    </span>
                                                    <input type="checkbox"
                                                           data-id="odc1"
                                                           data-type="toggle"
                                                           data-image-on="{{ asset('/public/assets/admin/img/modal/order-delivery-verification-on.png') }}"
                                                           data-image-off="{{ asset('/public/assets/admin/img/modal/order-delivery-verification-off.png') }}"
                                                           data-title-on="{{ translate('messages.Want to enable') }} <strong>{{ translate('messages.Delivery Verification?') }}</strong>"
                                                           data-title-off="{{ translate('messages.Want to disable') }} <strong>{{ translate('messages.Delivery Verification?') }}</strong>"
                                                           data-text-on="<p>{{ translate('If you enable this, the Deliveryman has to verify the order during delivery through a verification code.') }} {{ translate('Code length') }}: 4</p>"
                                                           data-text-off="<p>{{ translate('If you disable this, the deliveryman will deliver the order and update the status. He doesn\'t need to verify the order with any code.') }}</p>"
                                                           class="status toggle-switch-input dynamic-checkbox-toggle"

                                                           value="1"
                                                        name="odc" id="odc1" {{ $odc == 1 ? 'checked' : '' }}>
                                                    <span class="toggle-switch-label text">
                                                        <span class="toggle-switch-indicator"></span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="col-sm-6 col-lg-4 access_product_approval">

                                            @php($order_confirmation_model = Helpers::get_business_settings('order_confirmation_model') ?? 'deliveryman')
                                            <div class="form-group mb-0">
                                                <label class="input-label text-capitalize d-flex alig-items-center">
                                                    <span class="line--limit-1">{{ translate('messages.Who Will Confirm Order?') }}
                                                        <span class="form-label-secondary" data-toggle="tooltip"
                                                              data-placement="right"
                                                              data-original-title="{{ translate('After a customer order placement, admin can define who will confirm the order first- deliveryman or store? for example, if you choose \'deliveryman\', the deliveryman nearby will confirm the order and forward it to the related store to process the order. it works vice-versa if you choose \'store\'.') }}">
                                                            <i class="tio-info text-muted ps--3"></i>
                                                        </span>
                                                    </span>
                                                </label>
                                                <div class="resturant-type-group bg-white border flex-sm-nowrap flex-wrap">
                                                    <label class="form-check form--check w-100">
                                                        <input class="form-check-input" type="radio" value="store"
                                                               name="order_confirmation_model" id="order_confirmation_model" {{ $order_confirmation_model == 'store' ? 'checked' : '' }}>
                                                        <span class="form-check-label">
                                                            {{ translate('messages.Store') }}
                                                        </span>
                                                    </label>
                                                    <label class="form-check form--check w-100">
                                                        <input class="form-check-input" type="radio" value="deliveryman"
                                                               name="order_confirmation_model" id="order_confirmation_model2" {{ $order_confirmation_model == 'deliveryman' ? 'checked' : '' }}>
                                                        <span class="form-check-label">
                                                            {{ translate('Deliveryman') }}
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @include('admin-views.partials._floating-submit-button')
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="mt-4">
            <div class="card" id="order_cancellation_section">
                <div class="card-body">
                    <div class="mb-20">
                        <div>
                            <h4 class="mb-1">
                                {{ translate('Setup Order Cancellation Messages') }}
                            </h4>
                            <p class="mb-0 fs-12">
                                {{ translate('Set up cancellation messages here to allow customers to select a reason when canceling an order') }}
                            </p>
                        </div>
                    </div>
                    <div class="bg-light rounded p-xxl-20 p-3 mb-20">
                        <form action="{{ route('admin.business-settings.order-cancel-reasons.store') }}" method="post">
                            @csrf

                            @if ($language)
                                <div class="js-nav-scroller tabs-slide-wrap tabs-slide-space position-relative hs-nav-scroller-horizontal">
                                    <ul class="nav nav-tabs tabs-inner nav--tabs mb-4 border-bottom">
                                        <li class="nav-item">
                                            <a class="nav-link lang_link active" href="#"
                                                id="default-link">{{ translate('Default') }}</a>
                                        </li>
                                        @foreach ($language as $lang)
                                            <li class="nav-item">
                                                <a class="nav-link lang_link" href="#"
                                                    id="{{ $lang }}-link">{{  Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <div class="arrow-area">
                                        <div class="button-prev align-items-center">
                                            <button type="button"
                                                class="btn btn-click-prev mr-auto border-0 btn-primary rounded-circle fs-12 p-2 d-center">
                                                <i class="tio-chevron-left fs-24"></i>
                                            </button>
                                        </div>
                                        <div class="button-next align-items-center">
                                            <button type="button"
                                                class="btn btn-click-next ml-auto border-0 btn-primary rounded-circle fs-12 p-2 d-center">
                                                <i class="tio-chevron-right fs-24"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <div class="row g-3">
                                <div class="col-sm-6 lang_form default-form">
                                    <label for="order_cancellation" class="form-label">{{ translate('Order cancellation reason') }}
                                        ({{ translate('Default') }})</label>
                                    <input type="text" class="form-control h--45px" name="reason[]"
                                        id="order_cancellation" placeholder="{{ translate('Ex') . ': ' . translate('Item is broken') }}">
                                    <input type="hidden" name="lang[]" value="default">
                                </div>
                                @if ($language)
                                    @foreach ($language as $lang)
                                        <div class="col-sm-6 d-none lang_form" id="{{ $lang }}-form">
                                            <label for="order_cancellation{{$lang}}" class="form-label">{{ translate('Order cancellation reason') }}
                                                ({{ strtoupper($lang) }})</label>
                                            <input type="text" class="form-control h--45px" name="reason[]"
                                                id="order_cancellation{{$lang}}" placeholder="{{ translate('Ex') . ': ' . translate('Item is broken') }}">
                                            <input type="hidden" name="lang[]" value="{{ $lang }}">
                                        </div>
                                    @endforeach
                                @endif
                                <div class="col-sm-6">
                                    <label for="user_type" class="form-label d-flex">
                                        <span class="line--limit-1">{{ translate('User type') }} </span>
                                        <span class="form-label-secondary text-danger d-flex align-items-center" data-toggle="tooltip"
                                            data-placement="right"
                                            data-original-title="{{ translate('When this field is active, user can cancel an order with proper reason.') }}">
                                            <i class="tio-info text-muted ps--3 top-01"></i>
                                        </span>
                                    </label>
                                    <select id="user_type" name="user_type" class="form-control custom-select h--45px" required>
                                        <option value="">{{ translate('messages.Select user type') }}</option>
                                        <option value="admin">{{ translate('messages.admin') }}</option>
                                        <option value="store">{{ translate('messages.Store') }}</option>
                                        <option value="customer">{{ translate('messages.Customer') }}</option>
                                        <option value="deliveryman">{{ translate('Deliveryman') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="btn--container justify-content-end mt-20">
                                <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                                <button type="{{ getEnvMode() != 'demo' ? 'submit' : 'button' }}"
                                    class="btn btn--primary call-demo"><i class="tio-checkmark-circle-outlined"></i> {{ translate('Submit') }}</button>
                            </div>
                        </form>
                    </div>
                    <div class="card border-0">
                        <div class="card-body mb-3">
                            <div class="d-flex gap-2 flex-wrap justify-content-between align-items-center mb-20">
                                <div class="mx-1">
                                    <h4 class="fs-16 text-title mb-0">
                                        {{ translate('messages.Order cancellation reason list') }}
                                    </h4>
                                </div>
                                <div class="d-flex align-items-center gap-lg-3 gap-2 flex-md-nowrap flex-wrap">
                                    <select id="type" name="type" class="form-control custom-select py-1 h-40px set-filter" data-url="{{ url()->full() }}" data-filter="type">
                                        <option value="all" {{ request('type') == 'all' ? 'selected' : '' }}>{{ translate('messages.All user') }}</option>
                                        <option value="admin" {{ request('type') == 'admin' ? 'selected' : '' }}>{{ translate('messages.admin') }}</option>
                                        <option value="store" {{ request('type') == 'store' ? 'selected' : '' }}>{{ translate('messages.Store') }}</option>
                                        <option value="customer" {{ request('type') == 'customer' ? 'selected' : '' }}>{{ translate('messages.Customer') }}</option>
                                        <option value="deliveryman" {{ request('type') == 'deliveryman' ? 'selected' : '' }}>{{ translate('Deliveryman') }}</option>
                                    </select>
                                    <form class="search-form order-search-wrap min--260">
                                        <div class="input-group input--group">
                                            <input id="" type="search" name="search" class="form-control h--40px" placeholder="Search here" value="">
                                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive datatable-custom">
                                    <table id="columnSearchDatatable"
                                        class="table table-borderless table-thead-bordered table-align-middle"
                                        data-hs-datatables-options='{
                                    "isResponsive": false,
                                    "isShowPaging": false,
                                    "paging":false
                                }'>
                                        <thead class="thead-light">
                                            <tr>
                                                <th class="border-0">{{ translate('messages.SL') }}</th>
                                                <th class="border-0">{{ translate('messages.Reason') }}</th>
                                                <th class="border-0">{{ translate('User type') }}</th>
                                                <th class="border-0">{{ translate('messages.Status') }}</th>
                                                <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
                                            </tr>
                                        </thead>

                                        <tbody id="table-div">
                                            @foreach ($reasons as $key => $reason)
                                                <tr>
                                                    <td class="text-dark fs-14">{{ $key + $reasons->firstItem() }}</td>

                                                    <td>
                                                        <span class="d-block font-size-sm text-body min-w-176px line--limit-2 text-dark fs-14" title="{{ $reason->reason }}">
                                                            {{ Str::limit($reason->reason, 25, '...') }}
                                                        </span>
                                                    </td>
                                                    <td class="text-dark fs-14">{{ Str::title($reason->user_type) }}</td>
                                                    <td>
                                                        <label class="toggle-switch toggle-switch-sm"
                                                            for="stocksCheckbox{{ $reason->id }}">
                                                            <input type="checkbox"
                                                                    data-url="{{ route('admin.business-settings.order-cancel-reasons.status', [$reason['id'], $reason->status ? 0 : 1]) }}"
                                                                class="toggle-switch-input redirect-url"
                                                                id="stocksCheckbox{{ $reason->id }}"
                                                                {{ $reason->status ? 'checked' : '' }}>
                                                            <span class="toggle-switch-label">
                                                                <span class="toggle-switch-indicator"></span>
                                                            </span>
                                                        </label>
                                                    </td>

                                                    <td>
                                                        <div class="btn--container justify-content-center">

                                                            <a class="btn btn-sm action-btn action-btn--edit edit-reason offcanvas-trigger data-info-show"
                                                                title="{{ translate('Edit') }}"
                                                                data-url="{{ route('admin.business-settings.order-cancel-reasons.edit', [$reason['id']]) }}"
                                                                data-id="{{ $reason['id'] }}"
                                                                data-target="#offcanvas__customBtn"
                                                                href="javascript:"><i class="tio-edit"></i>
                                                            </a>


                                                            <a class="btn btn-sm action-btn action-btn--delete form-alert"
                                                                href="javascript:"
                                                                data-id="order-cancellation-reason-{{ $reason['id'] }}"
                                                                data-message="{{ translate('messages.If you want to delete this reason, please confirm your decision.') }}"
                                                                title="{{ translate('messages.Delete') }}">
                                                                <i class="tio-delete-outlined"></i>
                                                            </a>
                                                            <form
                                                                action="{{ route('admin.business-settings.order-cancel-reasons.destroy', $reason['id']) }}"
                                                                method="post" id="order-cancellation-reason-{{ $reason['id'] }}">
                                                                @csrf @method('delete')
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach

                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="modal fade" id="confirmation_modal_free_delivery_by_order_amount" tabindex="-1" role="dialog"
         aria-labelledby="modalLabel" aria-hidden="true">
        <div class=" modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-5 pt-0">
                    <div class="max-349 mx-auto mb-20">
                        <div>
                            <div class="text-center">
                                <img src="{{asset('/public/assets/admin/img/subscription-plan/package-status-disable.png')}}"
                                     class="mb-20">

                                <h5 class="modal-title"></h5>
                            </div>
                            <div class="text-center">
                                <h3> {{ translate('Do you want active "set specific criteria"?') }}</h3>
                                <div>
                                    <p>{{ translate('Activate "set specific criteria"? Delivery is free once a customer orders above your "free delivery over" amount.') }}
                                    </p>
                                </div>
                            </div>



                            <div class="btn--container justify-content-center">
                                <button data-dismiss="modal"
                                        class="btn btn-soft-secondary min-w-120"><i class="tio-clear-circle-outlined"></i> {{translate("Cancel")}}</button>
                                <button data-dismiss="modal" type="button" id="confirmBtn_free_delivery_by_order_amount"
                                        class="btn btn--primary min-w-120"><i class="tio-checkmark-circle-outlined"></i> {{translate('Yes')}}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="modal fade" id="confirmation_modal_free_delivery_to_all_store" tabindex="-1" role="dialog"
         aria-labelledby="modalLabel" aria-hidden="true">
        <div class="modal-dialog-centered modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-5 pt-0">
                    <div class="max-349 mx-auto mb-20">
                        <div>
                            <div class="text-center">
                                <img src="{{asset('/public/assets/admin/img/subscription-plan/package-status-disable.png')}}"
                                     class="mb-20">

                                <h5 class="modal-title"></h5>
                            </div>
                            <div class="text-center">
                                <h3> {{ translate('Do you want active "free delivery for all stores"?') }}</h3>
                                <div>
                                    <p>{{ translate('Activate free delivery for all stores? You will bear the delivery cost.') }}
                                    </p>
                                </div>
                            </div>
                            <div class="btn--container justify-content-center">
                                <button data-dismiss="modal"
                                        class="btn btn-soft-secondary min-w-120"><i class="tio-clear-circle-outlined"></i> {{translate("Cancel")}}</button>
                                <button data-dismiss="modal" type="button" id="confirmBtn_free_delivery_to_all_store"
                                        class="btn btn--primary min-w-120"><i class="tio-checkmark-circle-outlined"></i> {{translate('Yes')}}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>
    <div id="offcanvas__customBtn" class="custom-offcanvas d-flex flex-column justify-content-between">
        <div id="data-view" class="h-100">
        </div>
    </div>
    <div id="global_guideline_offcanvas"
        class="custom-offcanvas d-flex flex-column justify-content-between global_guideline_offcanvas">
        <div>
            <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
                <h3 class="mb-0">{{ translate('messages.Order Settings Guideline') }}</h3>
                <button type="button"
                    class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
                    aria-label="Close">&times;</button>
            </div>

            <div class="custom-offcanvas-body offcanvas-height-100 py-3 px-md-4 px-3">
                <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                    <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                        <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed"
                            type="button" data-toggle="collapse" data-target="#order_type_guide"
                            aria-expanded="true">
                            <div
                                class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                                <i class="tio-down-ui"></i>
                            </div>
                            <span
                                class="font-semibold text-left fs-14 text-title">{{ translate('Order type') }}</span>
                        </button>
                        <a href="#order_type_section"
                            class="text-info text-underline fs-12 text-nowrap offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                    </div>
                    <div class="collapse mt-3 show" id="order_type_guide">
                        <div class="card card-body">
                            <div class="">
                                <h5 class="mb-3">{{ translate('Order type') }}</h5>
                                <p class="fs-12 mb-0">
                                    {{ translate('messages.This feature allows customers to place orders based on how they want to receive or consume their items.') }}
                                </p>
                                <ul class="fs-12">
                                    <li><strong>{{ translate('Home delivery') }}:</strong> {{ translate('messages.Customers order to their address, delivered by a deliveryman or third-party service.') }}</li>
                                    <li><strong>{{ translate('messages.Takeaway') }}:</strong> {{ translate('messages.Customers order in advance and collect from the vendor. No delivery charge applies.') }}</li>
                                    <li><strong>{{ translate('Scheduled') }}:</strong> {{ translate('messages.Customers order to their address at a chosen time, delivered with live tracking.') }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                    <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                        <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed"
                            type="button" data-toggle="collapse" data-target="#notification_setup_guide"
                            aria-expanded="true">
                            <div
                                class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                                <i class="tio-down-ui"></i>
                            </div>
                            <span
                                class="font-semibold text-left fs-14 text-title">{{ translate('Notification setup') }}</span>
                        </button>
                        <a href="#notification_setup_section"
                            class="text-info text-underline fs-12 text-nowrap offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                    </div>
                    <div class="collapse mt-3" id="notification_setup_guide">
                        <div class="card card-body">
                            <div class="">
                                <h5 class="mb-3">{{ translate('Notification setup') }}</h5>
                                <p class="fs-12 mb-0">
                                    {{ translate('messages.Choose how admin notifications are delivered — manually or through Firebase.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                    <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                        <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed"
                            type="button" data-toggle="collapse" data-target="#free_delivery_guide"
                            aria-expanded="true">
                            <div
                                class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                                <i class="tio-down-ui"></i>
                            </div>
                            <span
                                class="font-semibold text-left fs-14 text-title">{{ translate('Free delivery setup') }}</span>
                        </button>
                        <a href="#free_delivery_section"
                            class="text-info text-underline fs-12 text-nowrap offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                    </div>
                    <div class="collapse mt-3" id="free_delivery_guide">
                        <div class="card card-body">
                            <div class="">
                                <h5 class="mb-3">{{ translate('Free delivery setup') }}</h5>
                                <ul class="fs-12">
                                    <li><strong>{{ translate('messages.Free delivery over') }}:</strong> {{ translate('messages.Admin can define the minimum order amount for the customer to receive free shipping automatically.') }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                    <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                        <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed"
                            type="button" data-toggle="collapse" data-target="#product_bundle_guide"
                            aria-expanded="true">
                            <div
                                class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                                <i class="tio-down-ui"></i>
                            </div>
                            <span
                                class="font-semibold text-left fs-14 text-title">{{ translate('Product bundle') }}</span>
                        </button>
                        <a href="#product_bundle_section"
                            class="text-info text-underline fs-12 text-nowrap offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                    </div>
                    <div class="collapse mt-3" id="product_bundle_guide">
                        <div class="card card-body">
                            <div class="">
                                <h5 class="mb-3">{{ translate('Product bundle') }}</h5>
                                <p class="fs-12 mb-0">
                                    {{ translate('messages.This option lets you select which modules can sell products together as a bundle, with or without a discount.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                    <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                        <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed"
                            type="button" data-toggle="collapse" data-target="#extra_packaging_guide"
                            aria-expanded="true">
                            <div
                                class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                                <i class="tio-down-ui"></i>
                            </div>
                            <span
                                class="font-semibold text-left fs-14 text-title">{{ translate('Extra Packaging Charge') }}</span>
                        </button>
                        <a href="#extra_packaging_section"
                            class="text-info text-underline fs-12 text-nowrap offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                    </div>
                    <div class="collapse mt-3" id="extra_packaging_guide">
                        <div class="card card-body">
                            <div class="">
                                <h5 class="mb-3">{{ translate('Extra Packaging Charge') }}</h5>
                                <p class="fs-12 mb-0">
                                    {{ translate('messages.This option lets you select which modules require extra packaging fees.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                    <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                        <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed"
                            type="button" data-toggle="collapse" data-target="#other_setup_guide"
                            aria-expanded="true">
                            <div
                                class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                                <i class="tio-down-ui"></i>
                            </div>
                            <span
                                class="font-semibold text-left fs-14 text-title">{{ translate('Other Setup') }}</span>
                        </button>
                        <a href="#other_setup_section"
                            class="text-info text-underline fs-12 text-nowrap offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                    </div>
                    <div class="collapse mt-3" id="other_setup_guide">
                        <div class="card card-body">
                            <div class="">
                                <h5 class="mb-3">{{ translate('Other Setup') }}</h5>
                                <p class="fs-12 mb-0">
                                    {{ translate('messages.Set delivery verification methods and choose how orders are confirmed.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                    <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                        <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed"
                            type="button" data-toggle="collapse" data-target="#order_cancellation_guide"
                            aria-expanded="true">
                            <div
                                class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                                <i class="tio-down-ui"></i>
                            </div>
                            <span
                                class="font-semibold text-left fs-14 text-title">{{ translate('Setup Order Cancellation Messages') }}</span>
                        </button>
                        <a href="#order_cancellation_section"
                            class="text-info text-underline fs-12 text-nowrap offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                    </div>
                    <div class="collapse mt-3" id="order_cancellation_guide">
                        <div class="card card-body">
                            <div class="">
                                <h5 class="mb-3">{{ translate('Setup Order Cancellation Messages') }}</h5>
                                <p class="fs-12 mb-0">
                                    {{ translate('messages.This section allows the admin to manage order cancellation reasons for different user types. You can') }}:
                                </p>
                                <ul class="fs-12">
                                    <li>{{ translate('messages.Create and edit cancellation reasons') }}</li>
                                    <li>{{ translate('messages.Set a reason as active or inactive') }}</li>
                                    <li>{{ translate('messages.Mark a default cancellation reason') }}</li>
                                </ul>
                                <p class="fs-12 mb-0">
                                    {{ translate('messages.These reasons will be shown to users when they try to cancel an order.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>
@endsection
@push('script_2')
    <script>
        $(document).ready(function() {
            $('.offcanvas-close-btn').on('click', function() {
                $('.offcanvas-close').trigger('click');
            });
        });
    </script>
@endpush

@push('script_2')
    <script src="{{asset('public/assets/admin/js/view-pages/business-settings-order-page.js')}}"></script>
    <script src="{{asset('public/assets/admin/js/view-pages/offcanvas-edit.js')}}"></script>

    <script>
        "use strict";
        $(document).ready(function () {
            {{-- The Free Delivery Setup handlers were removed with the block they drove (S6).
                 Everything below is unrelated and stays. --}}

            $('#home_delivery_status-id, #takeaway_status-id').on('change', function() {
                if (!$('#home_delivery_status-id').is(':checked') && !$('#takeaway_status-id').is(':checked')) {
                    toastr.error('{{ translate('At least one delivery method home delivery or takeaway must be selected for your business') }}');
                    $(this).prop('checked', true);
                }
            });

            // Product Bundle Toggle
            $('#product_bundle_status').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#product_bundle_options').slideDown();
                } else {
                    $('#product_bundle_options').slideUp();
                }
            });

            if ($('#product_bundle_status').is(':checked')) {
                $('#product_bundle_options').show();
            } else {
                $('#product_bundle_options').hide();
            }

            // Extra Packaging Charge Toggle
            $('#extra_packaging_charge_status').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#extra_packaging_charge_options').slideDown();
                } else {
                    $('#extra_packaging_charge_options').slideUp();
                }
            });

            // Initialize state on load
            if ($('#extra_packaging_charge_status').is(':checked')) {
                $('#extra_packaging_charge_options').show();
            } else {
                $('#extra_packaging_charge_options').hide();
            }

            $('#order-settings-form').on('submit', function(e) {
                if ($('#extra_packaging_charge_status').is(':checked')) {
                    let checkedOptions = $('.extra-packaging-option:checked').length;
                    if (checkedOptions === 0) {
                        e.preventDefault();
                        toastr.error('{{ translate('Please select at least one module for extra packaging charge') }}');
                    }
                }
            });
        });
    </script>
    <script>
        $(document).ready(function () {

            const toggle = $('#aon1');
            const radios = $('input[name="order_notification_type"]');
            const form = radios.closest('form');

            function toggleNotificationType() {
                if (!toggle.length) return;

                radios.prop('disabled', !toggle.prop('checked'));
            }

            toggleNotificationType();

            toggle.on('change', function () {
                setTimeout(toggleNotificationType, 120);
            });

            $(document).on('click', '.confirm-Toggle', function () {
                let toggle_id = $('#toggle-ok-button').attr('toggle-ok-button');
                if (toggle_id === 'aon1') {
                    setTimeout(toggleNotificationType, 120);
                }
            });

            form.on('submit', function () {

                if (!toggle.prop('checked')) {
                    radios.prop('disabled', false);
                }

            });

        });
    </script>

@endpush
