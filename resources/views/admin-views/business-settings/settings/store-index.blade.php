@extends('layouts.admin.app')

@section('title', translate('Store setup'))


@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title mr-3">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/outline/business.svg') }}" class="w--26" alt="">
                </span>
                <span>
                    {{ translate('Business setup') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('What stores may do on their own, and what still needs your approval.') }}</p>
            @include('admin-views.business-settings.partials.nav-menu')
        </div>
        <form action="{{ route('admin.business-settings.update-store') }}" method="post" enctype="multipart/form-data">
            @csrf

            <div class="row g-3">
                <div class="col-lg-12">
                    <div class="card mb-20" id="general_setup_section">
                        <div class="card-body">
                            <div class="mb-20">
                                <div class="row g-1 align-items-center">
                                    <div class="col-xxl-9 col-lg-8 col-md-7 col-sm-6">
                                        <div>
                                            <h4 class="mb-1">
                                                {{ translate('General setup') }}
                                            </h4>
                                            <p class="mb-0 fs-12">
                                                {{ translate('Manage the basic settings that control how vendors operate in your platform.') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-light rounded p-xxl-20 p-3">
                                <div class="row g-3 align-items-end">
                                    <div class="col-lg-4 col-sm-6">
                                        @php
                                            $canceled_by_store = $data['canceled_by_store'] ?? 0;
                                        @endphp
                                        <div class="form-group mb-0">
                                            <label class="input-label text-capitalize d-flex alig-items-center"><span
                                                    class="line--limit-1 text-title">{{ translate('Can a Vendor Cancel Order?') }}
                                                </span><span class="input-label-secondary text--title" data-toggle="tooltip"
                                                    data-placement="right"
                                                    data-original-title="{{ translate('Admin can enable/disable Vendor\'s order cancellation option.') }}">
                                                    <i class="tio-info text-muted"></i>
                                                </span>
                                            </label>
                                            <div class="form-group mb-0">
                                                <label class="toggle-switch h--45px align-items-center toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                    <span class="pr-1 d-flex align-items-center switch--label">
                                                        <span class="line--limit-1 text-title">
                                                            {{translate('Can cancel') }}
                                                        </span>
                                                    </span>
                                                    <input type="checkbox" data-id="canceled_by_store" data-type="toggle"
                                                    data-image-on="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-image-off="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-title-on="<strong>{{ translate('Are you sure to allow vendor to cancel orders?') }}</strong>"
                                                    data-title-off="<strong>{{ translate('Are you sure to not allow vendor to cancel orders?') }}</strong>"
                                                    data-text-on="{{ translate('Vendors can cancel orders directly from their panel if they cannot fulfill them.') }}"
                                                    data-text-off="{{ translate('Vendors can no longer cancel and must ask the admin instead.') }}"
                                                    data-footer-text-on="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    data-footer-text-off="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    class="status toggle-switch-input dynamic-checkbox-toggle"
                                                    name="canceled_by_store" id="canceled_by_store" value="1"
                                                    {{ $canceled_by_store ? 'checked' : '' }}>

                                                    <span class="toggle-switch-label text">
                                                        <span class="toggle-switch-indicator"></span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-sm-6">
                                        @php
                                            $store_self_registration = $data['toggle_store_registration'] ?? 0;
                                        @endphp
                                        <div class="form-group mb-0">
                                            <span class="mb-2 d-flex align-items-center">
                                                <span class="text-title fs-14">
                                                    {{ translate('Vendor self registration') }}
                                                </span>
                                                <span class="form-label-secondary text-danger d-flex align-items-center gap-1"
                                                    data-toggle="tooltip" data-placement="right"
                                                    data-original-title="{{ translate('A vendor can send a registration request from the vendor or customer app.') }}"><i class="tio-info text-muted ps--3"></i>
                                                </span>
                                            </span>
                                            <label
                                                class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                <span class="pr-1 d-flex align-items-center switch--label">
                                                    <span class="line--limit-1 text-title">
                                                        {{ translate('Self Registration') }}
                                                    </span>
                                                </span>

                                                <input type="checkbox" data-id="store_self_registration" data-type="toggle"
                                                    data-image-on="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-image-off="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-title-on="<strong>{{ translate('Are you sure to enable vendor self registration?') }}</strong>"
                                                    data-title-off="<strong>{{ translate('Are you sure to disable vendor self registration?') }}</strong>"
                                                    data-text-on="{{ translate('This allows new business owners to sign up and apply to sell on your platform by themselves.') }}"
                                                    data-text-off="{{ translate('The self-registration link is hidden and you add every vendor manually.') }}"
                                                    data-footer-text-on="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    data-footer-text-off="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    class="status toggle-switch-input dynamic-checkbox-toggle"
                                                    name="store_self_registration" id="store_self_registration" value="1"
                                                    {{ $store_self_registration ? 'checked' : '' }}>
                                                <span class="toggle-switch-label text">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-sm-6 col-lg-4">
                                        @php
                                            $product_gallery = $data['product_gallery'] ?? 0;
                                        @endphp
                                        <div class="form-group mb-0">
                                            <span class="mb-2 d-flex align-items-center">
                                                <span class="text-title">
                                                    {{translate('Product gallery') }}
                                                </span>
                                                <span class="form-label-secondary text-danger d-flex align-items-center gap-1"
                                                    data-toggle="tooltip" data-placement="right"
                                                    data-original-title="{{ translate('If you enable this, any vendor can duplicate product and create a new product by using this.')}}"><i class="tio-info text-muted ps--3"></i>
                                                </span>
                                            </span>
                                            <label
                                                class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                <span class="pr-1 d-flex align-items-center switch--label">
                                                    <span class="line--limit-1 text-title">
                                                        {{translate('gallery') }}
                                                    </span>
                                                </span>


                                                <input type="checkbox" data-id="product_gallery" data-type="toggle"
                                                    data-image-on="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-image-off="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-title-on="<strong>{{ translate('Are you sure to enable product gallery?') }}</strong>"
                                                    data-title-off="<strong>{{ translate('Are you sure to disable product gallery?') }}</strong>"
                                                    data-text-on="{{ translate('This allows vendors to duplicate products and create new products using the gallery.') }}"
                                                    data-text-off="{{ translate('If disabled, vendors cannot duplicate products or create new products using the gallery.') }}"
                                                    data-footer-text-on="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    data-footer-text-off="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    class="status toggle-switch-input dynamic-checkbox-toggle"
                                                    name="product_gallery" id="product_gallery" value="1"
                                                    {{ $product_gallery ? 'checked' : '' }}>
                                                <span class="toggle-switch-label text">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-sm-6 col-lg-4 {{ $product_gallery == 1 ? ' ' : 'd-none' }}  access_all_products">
                                        @php
                                            $access_all_products = $data['access_all_products'] ?? 0;
                                        @endphp
                                        <div class="form-group mb-0">
                                            <span class="mb-2 d-flex align-items-center">
                                                <span class="text-title">
                                                    {{translate('Access all products') }}
                                                </span>
                                                <span class="form-label-secondary text-danger d-flex align-items-center gap-1"
                                                    data-toggle="tooltip" data-placement="right"
                                                    data-original-title="{{ translate('If you enable this vendors can access all products of other vendors.')}}"><i class="tio-info text-muted ps--3"></i>
                                                </span>
                                            </span>
                                            <label
                                                class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                <span class="pr-1 d-flex align-items-center switch--label">
                                                    <span class="line--limit-1 text-title">
                                                        {{translate('Can Edit') }}
                                                    </span>
                                                </span>
                                                <input type="checkbox" data-id="access_all_products" data-type="toggle"
                                                    data-image-on="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-image-off="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-title-on="<strong>{{ translate('Are you sure to enable access all products?') }}</strong>"
                                                    data-title-off="<strong>{{ translate('Are you sure to disable access all products?') }}</strong>"
                                                    data-text-on="{{ translate('If you enable this, vendors can access all products of other available vendors') }}"
                                                    data-text-off="{{ translate('If you disable this, vendors cannot access all products of other available vendors.') }}"
                                                    data-footer-text-on="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    data-footer-text-off="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    class="status toggle-switch-input dynamic-checkbox-toggle"
                                                    name="access_all_products" id="access_all_products" value="1"
                                                    {{ $access_all_products ? 'checked' : '' }}>
                                                <span class="toggle-switch-label text">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-sm-6">
                                        @php
                                            $store_review_reply = $data['store_review_reply'] ?? 0;
                                        @endphp
                                        <div class="form-group mb-0">
                                            <span class="mb-2 d-flex align-items-center">
                                                <span class="text-title">
                                                    {{ translate('Vendor Can Reply Review') }}
                                                </span>
                                                <span class="form-label-secondary text-danger d-flex align-items-center gap-1"
                                                        data-toggle="tooltip" data-placement="right"
                                                        data-original-title="{{ translate('If enabled, vendors can actively engage with the customers by responding to the reviews left for their orders') }}"><i class="tio-info text-muted ps--3"></i>
                                                </span>
                                            </span>
                                            <label
                                                class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                <span class="pr-1 d-flex align-items-center switch--label">
                                                    <span class="line--limit-1 text-title">
                                                        {{ translate('Can Reply') }}
                                                    </span>
                                                </span>

                                                <input type="checkbox" data-id="store_review_reply" data-type="toggle"
                                                    data-image-on="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-image-off="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-title-on="<strong>{{ translate('Are you sure to enable vendor can reply review?') }}</strong>"
                                                    data-title-off="<strong>{{ translate('Are you sure to disable vendor can reply review?') }}</strong>"
                                                    data-text-on="{{ translate('If enabled, vendors can actively engage with the customers by responding to the reviews left for their orders') }}"
                                                    data-text-off="{{ translate('If disabled, vendors cannot reply to reviews left for their orders.') }}"
                                                    data-footer-text-on="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    data-footer-text-off="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    class="status toggle-switch-input dynamic-checkbox-toggle"
                                                    name="store_review_reply" id="store_review_reply" value="1"
                                                    {{ $store_review_reply ? 'checked' : '' }}>
                                                <span class="toggle-switch-label text">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-sm-6">
                                        @php
                                            $review_section = $data['review_section'] ?? 0;
                                        @endphp
                                        <div class="form-group mb-0">
                                            <span class="mb-2 d-flex align-items-center">
                                                <span class="text-title">
                                                    {{ translate('Review section') }}
                                                </span>
                                                <span class="form-label-secondary text-danger d-flex align-items-center gap-1"
                                                        data-toggle="tooltip" data-placement="right"
                                                        data-original-title="{{ translate('If enabled, the Reviews menu is shown in the vendor panel for non-service modules.') }}"><i class="tio-info text-muted ps--3"></i>
                                                </span>
                                            </span>
                                            <label
                                                class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                <span class="pr-1 d-flex align-items-center switch--label">
                                                    <span class="line--limit-1 text-title">
                                                        {{ translate('Status') }}
                                                    </span>
                                                </span>
                                                <input type="checkbox" data-id="review_section" data-type="toggle"
                                                    data-image-on="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-image-off="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-title-on="<strong>{{ translate('Are you sure to enable the Review Section?') }}</strong>"
                                                    data-title-off="<strong>{{ translate('Are you sure to disable the Review Section?') }}</strong>"
                                                    data-text-on="{{ translate('If enabled, the Reviews menu is shown in the vendor panel for non-service modules.') }}"
                                                    data-text-off="{{ translate('If disabled, the Reviews menu is hidden in the vendor panel for non-service modules.') }}"
                                                    data-footer-text-on="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    data-footer-text-off="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    class="status toggle-switch-input dynamic-checkbox-toggle"
                                                    name="review_section" id="review_section" value="1"
                                                    {{ $review_section ? 'checked' : '' }}>
                                                <span class="toggle-switch-label text">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-sm-6">
                                        @php
                                            $verified_seller_badge = $data['verified_seller_badge'] ?? 0;
                                        @endphp
                                        <div class="form-group mb-0">
                                            <span class="mb-2 d-flex align-items-center">
                                                <span class="text-title">
                                                    {{ translate('Show Verified Badge') }}
                                                </span>
                                                <span class="form-label-secondary text-danger d-flex align-items-center gap-1"
                                                        data-toggle="tooltip" data-placement="top"
                                                        data-original-title="{{ translate('This feature enables the admin to grant a verified badge to vendors who fulfill the required criteria.') }}"><i class="tio-info text-muted ps--3"></i>
                                                </span>
                                            </span>
                                            <label
                                                class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                <span class="pr-1 d-flex align-items-center switch--label">
                                                    <span class="line--limit-1 text-title">
                                                        {{ translate('Status') }}
                                                    </span>
                                                </span>

                                                <input type="checkbox" data-id="verified_seller_badge" data-type="toggle"
                                                    data-image-on="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-image-off="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-title-on="<strong>{{ translate('Are you sure to enable verified seller badge?') }}</strong>"
                                                    data-title-off="<strong>{{ translate('Are you sure to disable verified seller badge?') }}</strong>"
                                                    data-text-on="{{ translate('This feature enables the admin to grant a verified badge to vendors who fulfill the required criteria.') }}"
                                                    data-text-off="{{ translate('This feature enables the admin to grant a verified badge to vendors who fulfill the required criteria.') }}"
                                                    data-footer-text-on="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    data-footer-text-off="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    class="status toggle-switch-input dynamic-checkbox-toggle"
                                                    name="verified_seller_badge" id="verified_seller_badge" value="1"
                                                    {{ $verified_seller_badge ? 'checked' : '' }}>
                                                <span class="toggle-switch-label text">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-sm-6">
                                        @php
                                            $vendor_can_set_low_stock = $data['vendor_can_set_low_stock'] ?? 0;
                                        @endphp
                                        <div class="form-group mb-0">
                                            <span class="mb-2 d-flex align-items-center">
                                                <span class="text-title">
                                                    {{ translate('Vendor can set Low Stock') }}
                                                </span>
                                                <span class="form-label-secondary text-danger d-flex align-items-center gap-1"
                                                        data-toggle="tooltip" data-placement="top"
                                                        data-original-title="{{ translate('If enabled, vendors can manage their own low stock limit for products from their panel.') }}"><i class="tio-info text-muted ps--3"></i>
                                                </span>
                                            </span>
                                            <label
                                                class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                <span class="pr-1 d-flex align-items-center switch--label">
                                                    <span class="line--limit-1 text-title">
                                                        {{ translate('Status') }}
                                                    </span>
                                                </span>

                                                <input type="checkbox" data-id="vendor_can_set_low_stock" data-type="toggle"
                                                    data-image-on="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-image-off="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-title-on="<strong>{{ translate('Are you sure to enable vendor can set low stock?') }}</strong>"
                                                    data-title-off="<strong>{{ translate('Are you sure to disable vendor can set low stock?') }}</strong>"
                                                    data-text-on="{{ translate('If enabled, vendors can set their own low stock quantity for products from their panel.') }}"
                                                    data-text-off="{{ translate('If disabled, vendors cannot manage low stock quantity from their panel.') }}"
                                                    data-footer-text-on="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    data-footer-text-off="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    class="status toggle-switch-input dynamic-checkbox-toggle"
                                                    name="vendor_can_set_low_stock" id="vendor_can_set_low_stock" value="1"
                                                    {{ $vendor_can_set_low_stock ? 'checked' : '' }}>
                                                <span class="toggle-switch-label text">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-sm-6">
                                        @php
                                            $store_category_status = $data['store_category_status'] ?? 0;
                                        @endphp
                                        <div class="form-group mb-0">
                                            <span class="mb-2 d-flex align-items-center">
                                                <span class="text-title">
                                                    {{ translate('Vendor can Create Category') }}
                                                </span>
                                                <span class="form-label-secondary text-danger d-flex align-items-center gap-1"
                                                    data-toggle="tooltip" data-placement="right"
                                                    data-original-title="{{ translate('If enabled, vendors can create and manage their own store categories separately from the global categories.') }}"><i class="tio-info text-muted ps--3"></i>
                                                </span>
                                            </span>
                                            <label
                                                class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                <span class="pr-1 d-flex align-items-center switch--label">
                                                    <span class="line--limit-1 text-title">
                                                        {{ translate('Can Create') }}
                                                    </span>
                                                </span>

                                                <input type="checkbox" data-id="store_category_status" data-type="toggle"
                                                    data-image-on="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-image-off="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-title-on="<strong>{{ translate('Are you sure to enable Vendor Store Categories?') }}</strong>"
                                                    data-title-off="<strong>{{ translate('Are you sure to disable Vendor Store Categories?') }}</strong>"
                                                    data-text-on="{{ translate('If enabled, vendors can create and manage their own store categories from their panel.') }}"
                                                    data-text-off="{{ translate('Vendors can no longer manage store categories. Existing item assignments stay, but the menu is hidden.') }}"
                                                    data-footer-text-on="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    data-footer-text-off="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    class="status toggle-switch-input dynamic-checkbox-toggle"
                                                    name="store_category_status" id="store_category_status" value="1"
                                                    {{ $store_category_status ? 'checked' : '' }}>
                                                <span class="toggle-switch-label text">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-sm-6">
                                        @php
                                            $can_vendor_edit_order = $data['can_vendor_edit_order'] ?? 0;
                                        @endphp
                                        <div class="form-group mb-0">
                                            <span class="mb-2 d-flex align-items-center">
                                                <span class="text-title">
                                                    {{ translate('Vendor can Edit Order') }}
                                                </span>
                                                <span class="form-label-secondary text-danger d-flex align-items-center gap-1"
                                                    data-toggle="tooltip" data-placement="right"
                                                    data-original-title="{{ translate('If enabled, vendors can edit orders placed by customers.') }} {{ translate('The admin must also enable this feature from the individual vendors settings for it to take effect.') }}"><i class="tio-info text-muted ps--3"></i>
                                                </span>
                                            </span>
                                            <label
                                                class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                <span class="pr-1 d-flex align-items-center switch--label">
                                                    <span class="line--limit-1 text-title">
                                                        {{ translate('Can Edit') }}
                                                    </span>
                                                </span>

                                                <input type="checkbox" data-id="can_vendor_edit_order" data-type="toggle"
                                                    data-image-on="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-image-off="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-title-on="<strong>{{ translate('Are you sure to enable Vendor Can Edit Order?') }}</strong>"
                                                    data-title-off="<strong>{{ translate('Are you sure to disable Vendor Can Edit Order?') }}</strong>"
                                                    data-text-on="{{ translate('If enabled, vendors can edit orders placed by customers.') }} {{ translate('The vendor must also turn it on from their vendor panel for it to take effect.') }}"
                                                    data-text-off="{{ translate('If disabled, vendors cannot edit orders placed by customers.') }}"
                                                    data-footer-text-on="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    data-footer-text-off="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    class="status toggle-switch-input dynamic-checkbox-toggle"
                                                    name="can_vendor_edit_order" id="can_vendor_edit_order" value="1"
                                                    {{ $can_vendor_edit_order ? 'checked' : '' }}>
                                                <span class="toggle-switch-label text">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="info-notes-bg px-3 py-2 rounded fz-11  gap-2 align-items-center d-flex mt-20">
                                <img src="{{asset('public/assets/admin/img/info-idea.svg')}}" alt="">
                                <span>
                                    {{translate('To Verify store visit module wise')}}
                                    <span class="fz-12px font-semibold info-dark"><a style="color: #245BD1;" href="#0">{{translate('Store list')}}</a></span>
                                    {{translate('Page')}}
                                </span>
                            </div>
                        </div>
                    </div>

                    @if (addon_published_status('Builder'))
                    <div class="card mb-20" id="admin_website_builder_section">
                        <div class="card-body">
                            <div class="mb-20">
                                <div class="row g-1 align-items-center">
                                    <div class="col-xxl-9 col-lg-8 col-md-7 col-sm-6">
                                        <div>
                                            <h4 class="mb-1">
                                                {{ translate('Vendor website builder') }}
                                            </h4>
                                            <p class="mb-0 fs-12">
                                                {{ translate('Enable this option to allow vendors to set up and manage their own website.') }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-xxl-3 col-lg-4 col-md-5 col-sm-6">
                                        <div class="">
                                            @php
                                                $admin_website_builder_status = $data['admin_website_builder_status'] ?? 0;
                                            @endphp
                                            <div class="form-group mb-0">
                                                <label
                                                    class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                    <span class="pr-1 d-flex align-items-center switch--label">
                                                        <span class="line--limit-1">
                                                            {{translate('Status') }}
                                                        </span>
                                                    </span>
                                                    <input type="checkbox"
                                                        data-id="admin_website_builder_status"
                                                        data-type="toggle"
                                                        data-image-on="{{ asset('/public/assets/admin/img/modal/store-reg-on.png') }}"
                                                        data-image-off="{{ asset('/public/assets/admin/img/modal/store-reg-off.png') }}"
                                                        data-title-on="<strong>{{translate('Are you sure to enable vendor website setup?')}}</strong>"
                                                        data-title-off="<strong>{{translate('Are you sure to disable vendor website setup?')}}</strong>"
                                                        data-text-on="<p>{{ translate('If enabled, vendors will have the freedom to create, edit, and manage their own websites independently.') }}</p>"
                                                        data-text-off="<p>{{ translate('If disabled, vendors cannot create or manage their own websites.') }}</p>"
                                                        class="status toggle-switch-input dynamic-checkbox-toggle"
                                                        value="1"
                                                        name="admin_website_builder_status" id="admin_website_builder_status"
                                                        {{ $admin_website_builder_status == 1 ? 'checked' : '' }}>
                                                    <span class="toggle-switch-label text">
                                                        <span class="toggle-switch-indicator"></span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="info-notes-bg px-3 py-2 rounded fz-11 gap-2 align-items-center d-flex">
                                <img src="{{asset('public/assets/admin/img/info-idea.svg')}}" alt="">
                                <span>
                                    {{ translate('This only enables the feature. Each vendor must also switch it on in their own panel.') }}
                                </span>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="card mb-20" id="product_approval_section">
                        <div class="card-body">
                            <div class="mb-20">
                                <div class="row g-1 align-items-center">
                                    <div class="col-xxl-9 col-lg-8 col-md-7 col-sm-6">
                                        <div>
                                            <h4 class="mb-1">
                                                {{ translate('Need approval for') }}
                                            </h4>
                                            <p class="mb-0 fs-12">
                                                {{ translate('If enabled, this option to require admin approval for products to be displayed on the user side.') }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-xxl-3 col-lg-4 col-md-5 col-sm-6">
                                        <div class="">
                                            @php
                                                $product_approval = $data['product_approval'] ?? 0;
                                                $product_approval_datas = $data['product_approval_datas'] ?? null;
                                            @endphp
                                            <div class="form-group mb-0">
                                                <label
                                                    class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                    <span class="pr-1 d-flex align-items-center switch--label">
                                                        <span class="line--limit-1">
                                                            {{translate('Status') }}
                                                        </span>
                                                    </span>
                                                    <input type="checkbox"
                                                        data-id="product_approval"
                                                        data-type="toggle"
                                                        data-image-on="{{ asset('/public/assets/admin/img/modal/store-reg-on.png') }}"
                                                        data-image-off="{{ asset('/public/assets/admin/img/modal/store-reg-off.png') }}"
                                                        data-title-on="<strong>{{translate('Want to enable product approval?')}}</strong>"
                                                        data-title-off="<strong>{{translate('Want to disable product approval?')}}</strong>"
                                                        data-text-on="<p>{{ translate('If you enable this, option to require admin approval for products to be displayed on the user side') }}</p>"
                                                        data-text-off="<p>{{ translate('If you disable this, products will be displayed on the user side without admin approval.') }}</p>"
                                                        class="status toggle-switch-input dynamic-checkbox-toggle"
                                                        value="1"
                                                        name="product_approval" id="product_approval"
                                                        {{ $product_approval == 1 ? 'checked' : '' }}>
                                                    <span class="toggle-switch-label text">
                                                        <span class="toggle-switch-indicator"></span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-light2 rounded p-xxl-20 p-3 mb-20 {{ $product_approval == 1 ? '' : 'd-none' }}" id="hide_show_approval_box">
                                <div class="bg-white rounded p-3 border">
                                    <div class="row g-3">
                                        <div class="col-md-6 col-lg-6">
                                            <div class="form-group m-0">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input" id="inlineCheckbox1" value="1" name="Add_new_product" {{  data_get($product_approval_datas,'Add_new_product',null) == 1 ? 'checked' :'' }}>
                                                    <label class="custom-control-label size-checkbox-20" for="inlineCheckbox1">
                                                        <h5 class="mb-1">{{ translate('Add New Product') }}</h5>
                                                        <p class="mb-0 fs-12">
                                                            {{ translate('If enabled, admin approval is required each time a vendor submits a new product.') }} 
                                                        </p>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-lg-6">
                                            <div class="form-group m-0">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input update_exinting_check" id="exinting_product" name="update_existing_products"
                                                    {{ (data_get($product_approval_datas,'Update_product_price',null) == 1 || data_get($product_approval_datas,'Update_product_variation',null) == 1 || data_get($product_approval_datas,'Update_anything_in_product_details',null) == 1) ? 'checked' : '' }}>
                                                    <label class="custom-control-label size-checkbox-20" for="exinting_product">
                                                        <h5 class="mb-1">{{ translate('Update Existing Product') }}</h5>
                                                        <p class="mb-0 fs-12">
                                                            {{ translate('If enabled, admin approval is required each time a vendor updates an existing product.') }}
                                                        </p>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="update-exinting-product-box d-none">
                                    <div class="mt-20 access_product_approval">
                                        <div class="mb-2">
                                            <span data-toggle="tooltip" data-placement="right"
                                                data-original-title="Specify which updates need approval.">
                                                {{ translate('Available Option for Update Existing Product') }} <span class="text-danger">*</span>
                                                <i class="tio-info text-muted"></i>
                                            </span>
                                        </div>
                                        <div class="bg-white rounded py-2 px-3 min-h-45px border">
                                            <div class="row g-1">
                                                <div class="col-xl-3 col-lg-4 col-sm-6">
                                                    <div class="custom-control custom-checkbox pt-2px">
                                                        <input class="mx-2 custom-control-input" type="checkbox"  {{  data_get($product_approval_datas,'Update_product_price',null) == 1 ? 'checked' :'' }} id="inlineCheckbox2" value="1" name="Update_product_price">
                                                        <label class=" custom-control-label" for="inlineCheckbox2">{{ translate('Update product price') }}</label>
                                                    </div>
                                                </div>
                                                <div class="col-xl-3 col-lg-4 col-sm-6">
                                                    <div class="custom-control custom-checkbox pt-2px">
                                                        <input class="mx-2 custom-control-input" type="checkbox" {{  data_get($product_approval_datas,'Update_product_variation',null) == 1 ? 'checked' :'' }}  id="inlineCheckbox3" value="1" name="Update_product_variation">
                                                        <label class=" custom-control-label" for="inlineCheckbox3">{{ translate('Update product variation') }}</label>
                                                    </div>
                                                </div>
                                                <div class="col-xl-3 col-lg-4 col-sm-6">
                                                    <div class="custom-control custom-checkbox pt-2px">
                                                        <input class="mx-2 custom-control-input" type="checkbox"  {{  data_get($product_approval_datas,'Update_anything_in_product_details',null) == 1 ? 'checked' :'' }} id="inlineCheckbox4" value="1" name="Update_anything_in_product_details">
                                                        <label class=" custom-control-label" for="inlineCheckbox4">{{ translate('Update anything in product details') }}</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @if (addon_published_status('ReelsModule'))
                        @includeIf('reelsmodule::admin.business-settings.partials._reels-settings')
                    @endif

                    <div class="card mb-20" id="cash_in_hand_section">
                        <div class="card-body">
                            <div class="mb-20">
                                <div>
                                    <h4 class="mb-1 d-flex align-items-center gap-1">
                                        {{ translate('Cash in Hand Controls') }}
                                        <i class="tio-info text-muted fs-14" data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('Control how much cash vendors can hold from collections before the system automatically suspends them.') }}"></i>
                                    </h4>
                                    <p class="mb-0 fs-12">
                                        {{ translate('Setup your cash collection from here') }}
                                    </p>
                                </div>
                            </div>
                            <div class="bg-light rounded p-xxl-20 p-3">
                                <div class="row g-3">
                                    <div class="col-lg-4 col-sm-6">
                                        @php
                                            // On a validation-failure redisplay (old input is flashed) reflect the
                                            // submitted toggle state; otherwise fall back to the stored setting. This
                                            // keeps the toggle and the amount fields' required/readonly state coherent.
                                            $cash_in_hand_overflow_store = old()
                                                ? (old('cash_in_hand_overflow_store') ? 1 : 0)
                                                : ($data['cash_in_hand_overflow_store'] ?? 0);
                                        @endphp
                                        <div class="form-group mb-0">
                                            <span class="mb-2 d-flex align-items-center">
                                                <span class="text-title">
                                                    {{ translate('Cash in hand overflow') }}
                                                </span>
                                                <span class="form-label-secondary text-danger d-flex align-items-center gap-1"
                                                        data-toggle="tooltip" data-placement="right"
                                                        data-original-title="{{ translate('If enabled, vendors will be automatically suspended by the system when their \'Cash in Hand\' limit is exceeded.') }}"><i class="tio-info text-muted ps--3"></i>
                                                </span>
                                            </span>
                                            <label
                                                class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                                    <span class="pr-1 d-flex align-items-center switch--label">
                                                        <span class="line--limit-1 text-title">
                                                            {{ translate('Cash in hand overflow') }}
                                                        </span>
                                                    </span>

                                                    <input type="checkbox" data-id="cash_in_hand_overflow_store" data-type="toggle"
                                                    data-image-on="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-image-off="{{ asset('/public/assets/admin/img/modal/info-warning.png') }}"
                                                    data-title-on="<strong>{{ translate('Are you sure to enable cash in hand overflow suspension?') }}</strong>"
                                                    data-title-off="<strong>{{ translate('Are you sure to disable cash in hand overflow suspension?') }}</strong>"
                                                    data-text-on="{{ translate('When enabled, vendors will be automatically suspended when their cash in hand exceeds the allowed limit.') }}"
                                                    data-text-off="{{ translate('When disabled, vendors will not be suspended even if their cash in hand exceeds the set limit.') }}"
                                                    data-footer-text-on="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    data-footer-text-off="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    data-footer-text-off="<div class='text-center text-info mt-5'>{{ translate('Note : Don\'t forget to save the information before leaving this page') }} </div>"
                                                    class="status toggle-switch-input"
                                                    name="cash_in_hand_overflow_store" id="cash_in_hand_overflow_store" value="1"
                                                    {{ $cash_in_hand_overflow_store ? 'checked' : '' }}>
                                                <span class="toggle-switch-label text">
                                                        <span class="toggle-switch-indicator"></span>
                                                    </span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-sm-6">
                                        @php
                                            $cash_in_hand_overflow_store_amount = $data['cash_in_hand_overflow_store_amount'] ?? '';
                                        @endphp
                                        <div class="form-group mb-0">
                                            <label class=" input-label text-capitalize"
                                                   for="cash_in_hand_overflow_store_amount">
                                                    <span class="text-title">
                                                        {{ translate('Maximum Amount to Hold Cash in Hand') }} ({{ \App\CentralLogics\Helpers::currency_symbol() }})
                                                    </span>

                                                <span class="form-label-secondary"
                                                      data-toggle="tooltip" data-placement="right"
                                                      data-original-title="{{ translate('Maximum cash a vendor can hold. Going over suspends them from receiving orders.') }}"><i class="tio-info text-muted ps--3"></i></span>
                                            </label>
                                            <input type="number" name="cash_in_hand_overflow_store_amount" class="form-control" data-toggle="tooltip"
                                                data-placement="top" data-original-title="{{ $cash_in_hand_overflow_store == 1 ? '' : translate('This field is disabled as Cash-in-Hand Overflow suspension is turned OFF') }}"
                                                   id="cash_in_hand_overflow_store_amount" min="0" step="{{ App\CentralLogics\Helpers::getDecimalPlaces() }}"
                                                   value="{{ old('cash_in_hand_overflow_store_amount', $cash_in_hand_overflow_store_amount) }}"  {{ $cash_in_hand_overflow_store  == 1 ? 'required' : 'readonly' }} >
                                            <span class="fs-12 text-info mt-1 d-none" id="amount_warning">{{ translate('Amount must be greater than the minimum payable amount') }}</span>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-sm-6">
                                        @php
                                            $min_amount_to_pay_store = $data['min_amount_to_pay_store'] ?? '';
                                        @endphp
                                        <div class="form-group mb-0">
                                            <label class=" input-label text-capitalize"
                                                   for="min_amount_to_pay_store">
                                                    <span class="text-title">
                                                        {{ translate('Minimum amount to pay') }} ({{ \App\CentralLogics\Helpers::currency_symbol() }})

                                                    </span>

                                                <span class="form-label-secondary"
                                                      data-toggle="tooltip" data-placement="right"
                                                      data-original-title="{{ translate('Enter the minimum cash amount vendors can pay') }}"><i class="tio-info text-muted ps--3"></i></span>
                                            </label>
                                            <input type="number" name="min_amount_to_pay_store" class="form-control"
                                                   id="min_amount_to_pay_store" min="0" step="{{ App\CentralLogics\Helpers::getDecimalPlaces() }}"
                                                   value="{{ old('min_amount_to_pay_store', $min_amount_to_pay_store) }}"  {{ $cash_in_hand_overflow_store  == 1 ? 'required' : 'readonly' }} >
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="info-notes-bg px-3 py-2 rounded fz-11  gap-2 align-items-center d-flex mt-20">
                                <img src="{{asset('public/assets/admin/img/info-idea.svg')}}" alt="">
                                <span>
                                    {{translate('To setup vendor cash withdraw method visit')}}
                                    <span class="fz-12px font-semibold info-dark"><a style="color: #245BD1;" href={{ route('admin.transactions.withdraw-method.list') }} target="_blank" rel="noopener noreferrer">{{translate('Withdraw method list')}}</a></span>
                                    {{translate('Page')}}
                                </span>
                            </div>
                        </div>
                    </div>

                    @includeIf('admin-views.partials._floating-submit-button')
                </div>
            </div>
        </form>
    </div>

    <div id="global_guideline_offcanvas"
        class="custom-offcanvas d-flex flex-column justify-content-between global_guideline_offcanvas">
        <div>
            <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
                <h3 class="mb-0">{{ translate('messages.Store Setup Guideline') }}</h3>
                <button type="button"
                    class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
                    aria-label="Close">&times;</button>
            </div>

            <div class="custom-offcanvas-body offcanvas-height-100 py-3 px-md-4 px-3">

                <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                    <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                        <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed"
                            type="button" data-toggle="collapse" data-target="#general_setup"
                            aria-expanded="true">
                            <div
                                class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                                <i class="tio-down-ui"></i>
                            </div>
                            <span
                                class="font-semibold text-left fs-14 text-title">{{ translate('General setup') }}</span>
                        </button>
                        <a href="#general_setup_section"
                            class="text-info text-underline fs-12 text-nowrap offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                    </div>
                    <div class="collapse mt-3 show" id="general_setup">
                        <div class="card card-body">
                            <div class="">
                                <h5 class="mb-3">{{ translate('General setup') }}</h5>
                                <p class="fs-12 mb-0">
                                    {{ translate('messages.Control vendor-related settings such as') }}:
                                </p>
                                <ul class="fs-12">
                                    <li>{{ translate('messages.Vendor registration availability') }}</li>
                                    <li>{{ translate('messages.Order cancellation permission') }}</li>
                                    <li>{{ translate('messages.Replying to customer reviews') }}</li>
                                    <li>{{ translate('messages.Access to the product gallery') }}</li>
                                    <li>{{ translate('messages.Access to all products') }}</li>
                                </ul>
                                <p class="fs-12 mb-0">
                                    {{ translate('messages.These settings are managed at the vendor level.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                    <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                        <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed"
                            type="button" data-toggle="collapse" data-target="#product_approval_guide"
                            aria-expanded="true">
                            <div
                                class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                                <i class="tio-down-ui"></i>
                            </div>
                            <span
                                class="font-semibold text-left fs-14 text-title">{{ translate('Product Approval') }}</span>
                        </button>
                        <a href="#product_approval_section"
                            class="text-info text-underline fs-12 text-nowrap offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                    </div>
                    <div class="collapse mt-3" id="product_approval_guide">
                        <div class="card card-body">
                            <div class="">
                                <h5 class="mb-3">{{ translate('Product Approval') }}</h5>
                                <p class="fs-12 mb-0">
                                    {{ translate('messages.This section manages which changes to products by vendors require admin approval before they are applied.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>


                <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
                    <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                        <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed"
                            type="button" data-toggle="collapse" data-target="#cash_in_hand_guide"
                            aria-expanded="true">
                            <div
                                class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                                <i class="tio-down-ui"></i>
                            </div>
                            <span
                                class="font-semibold text-left fs-14 text-title">{{ translate('messages.Cash in Hand Controls') }}</span>
                        </button>
                        <a href="#cash_in_hand_section"
                            class="text-info text-underline fs-12 text-nowrap offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
                    </div>
                    <div class="collapse mt-3" id="cash_in_hand_guide">
                        <div class="card card-body">
                            <div class="">
                                <h5 class="mb-3">{{ translate('Cash in Hand Controls') }}</h5>
                                <p class="fs-12 mb-3">
                                    {{ translate('messages.Limits how much COD cash a vendor can hold, reducing risk and keeping settlements on time.') }}
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
            if ($('#exinting_product').is(':checked')) {
                $('.update-exinting-product-box').removeClass('d-none');
            } else {
                $('.update-exinting-product-box').addClass('d-none');
            }
            $('#exinting_product').on('change', function() {
                if ($(this).is(':checked')) {
                    $('.update-exinting-product-box').removeClass('d-none');
                } else {
                    $('.update-exinting-product-box').addClass('d-none');
                }
            });

            $('form').on('submit', function(e) {
                if ($('#exinting_product').is(':checked')) {
                    let checked = 0;
                    if ($('#inlineCheckbox2').is(':checked')) {
                        checked++;
                    }
                    if ($('#inlineCheckbox3').is(':checked')) {
                        checked++;
                    }
                    if ($('#inlineCheckbox4').is(':checked')) {
                        checked++;
                    }
                    if (checked == 0) {
                        e.preventDefault();
                        toastr.error("{{ translate('Please select at least one option for update existing product') }}");
                    }
                }
            });

            $('#cash_in_hand_overflow_store').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#cash_in_hand_overflow_store_amount').removeAttr('readonly').attr('required', true);
                    $('#cash_in_hand_overflow_store_amount').attr('data-original-title', '').tooltip('hide');
                    $('#min_amount_to_pay_store').removeAttr('readonly').attr('required', true);
                } else {
                    $('#cash_in_hand_overflow_store_amount').attr('readonly', true).removeAttr('required');
                    $('#cash_in_hand_overflow_store_amount').attr('data-original-title', "{{ translate('This field is disabled as Cash-in-Hand Overflow suspension is turned OFF') }}").tooltip('show');
                    $('#min_amount_to_pay_store').attr('readonly', true).removeAttr('required');
                }
            });

            $('#cash_in_hand_overflow_store_amount, #min_amount_to_pay_store').on('change keyup', function() {
                let maxAmount = parseFloat($('#cash_in_hand_overflow_store_amount').val());
                let minAmount = parseFloat($('#min_amount_to_pay_store').val());
                if (maxAmount <= minAmount) {
                    $('#amount_warning').removeClass('d-none');
                } else {
                    $('#amount_warning').addClass('d-none');
                }
            });

            $('form').on('submit', function(e) {
                let maxAmount = parseFloat($('#cash_in_hand_overflow_store_amount').val());
                let minAmount = parseFloat($('#min_amount_to_pay_store').val());
                if ($('#cash_in_hand_overflow_store').is(':checked') && maxAmount <= minAmount) {
                    e.preventDefault();
                    toastr.error("{{ translate('Amount must be greater than the minimum payable amount') }}");
                }
            });

            $('.offcanvas-close-btn').on('click', function() {
                $('.offcanvas-close').trigger('click');
            });

            $('#inlineCheckbox4').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#inlineCheckbox2').prop('checked', true);
                    $('#inlineCheckbox3').prop('checked', true);
                }
            });

            $('#inlineCheckbox2, #inlineCheckbox3').on('change', function() {
                if ($('#inlineCheckbox2').is(':checked') && $('#inlineCheckbox3').is(':checked')) {
                    $('#inlineCheckbox4').prop('checked', true);
                } else {
                    $('#inlineCheckbox4').prop('checked', false);
                }
            });
        });

    </script>
@endpush
