<div id="sidebarMain" class="d-none">
    <aside
        class="js-navbar-vertical-aside navbar navbar-vertical-aside navbar-vertical navbar-vertical-fixed navbar-expand-xl navbar-bordered  ">
        <div class="navbar-vertical-container">
            <div class="navbar-brand-wrapper justify-content-between">
                <a class="navbar-brand" href="{{ route('admin.dashboard') }}" aria-label="Front">
                    <img class="navbar-brand-logo initial--36 onerror-image onerror-image"
                         data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                         src="{{\App\CentralLogics\Helpers::logoFullUrl()}}"
                         alt="Logo">
                    <img class="navbar-brand-logo-mini initial--36 onerror-image onerror-image"
                         data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                         src="{{\App\CentralLogics\Helpers::logoFullUrl()}}"
                         alt="Logo">
                </a>

                <button type="button"
                        class="js-navbar-vertical-aside-toggle-invoker navbar-vertical-aside-toggle btn btn-icon btn-xs btn-ghost-dark">
                    <i class="tio-clear tio-lg"></i>
                </button>

                <div class="navbar-nav-wrap-content-left">
                    <button type="button" class="js-navbar-vertical-aside-toggle-invoker close">
                        <i class="tio-first-page navbar-vertical-aside-toggle-short-align" data-toggle="tooltip"
                           data-placement="right" title="Collapse"></i>
                        <i class="tio-last-page navbar-vertical-aside-toggle-full-align"
                           data-template='<div class="tooltip d-none d-sm-block" role="tooltip"><div class="arrow"></div><div class="tooltip-inner"></div></div>'></i>
                    </button>
                </div>

            </div>

            <div class="navbar-vertical-content bg--005555" id="navbar-vertical-content">
                <form autocomplete="off" class="sidebar--search-form">
                    <div class="search--form-group">
                        <button type="button" class="btn"><i class="tio-search"></i></button>
                        <input autocomplete="false" name="qq" type="text" class="form-control form--control"
                               placeholder="{{ translate('Search menu') }}" id="search">

                        <div id="search-suggestions" class="flex-wrap mt-1"></div>
                    </div>
                </form>
                <ul class="navbar-nav navbar-nav-lg nav-tabs">
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin') ? 'show active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link"
                           href="{{ route('admin.dashboard') }}?module_id={{Config::get('module.current_module_id')}}"
                           title="{{ translate('Dashboard') }}">
                            <i class="tio-home-vs-1-outlined nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                {{ translate('Dashboard') }}
                            </span>
                        </a>
                    </li>


                    @if (\App\CentralLogics\Helpers::module_permission_check('order'))
                        <li class="nav-item">
                            <small class="nav-subtitle">{{ translate('Order management') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>

                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/parcel/orders/*') ||Request::is('admin/parcel/details/*')   ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                               title="{{ translate('messages.Orders') }}">
                                <i class="tio-shopping-cart nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                {{ translate('messages.Orders') }}
                            </span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display:{{ Request::is('admin/parcel/orders/*') ||Request::is('admin/parcel/details/*')  || Request::is('admin/order/offline/payment/list*')? 'block' : 'none' }}">
                                <li class="nav-item {{ Request::is('admin/parcel/orders/all') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('admin.parcel.orders', ['all']) }}"
                                       title="{{ translate('All orders') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                        {{ translate('All') }}
                                        <span class="badge badge-soft-info badge-pill ml-1">
                                            {{ \App\Models\Order::ParcelOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                    </a>
                                </li>

                                <li class="nav-item {{ Request::is('admin/parcel/orders/pending') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('admin.parcel.orders', ['pending']) }}"
                                       title="{{ translate('messages.Pending orders') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                        {{ translate('Pending') }}
                                        <span class="badge badge-soft-info badge-pill ml-1">
                                            {{ \App\Models\Order::Pending()->OrderScheduledIn(30)->ParcelOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                    </a>
                                </li>

                                <li class="nav-item {{ Request::is('admin/parcel/orders/accepted') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('admin.parcel.orders', ['accepted']) }}"
                                       title="{{ translate('messages.Accepted orders') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                        {{ translate('Accepted') }}
                                        <span class="badge badge-soft-success badge-pill ml-1">
                                            {{ \App\Models\Order::AccepteByDeliveryman()->OrderScheduledIn(30)->ParcelOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                    </a>
                                </li>
                                <li class="nav-item {{ Request::is('admin/parcel/orders/processing') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('admin.parcel.orders', ['processing']) }}"
                                       title="{{ translate('messages.Processing orders') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                        {{ translate('Processing') }}
                                        <span class="badge badge-soft-warning badge-pill ml-1">
                                            {{ \App\Models\Order::Preparing()->OrderScheduledIn(30)->ParcelOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                    </a>
                                </li>
                                <li class="nav-item {{ Request::is('admin/parcel/orders/item_on_the_way') ? 'active' : '' }}">
                                    <a class="nav-link text-capitalize"
                                       href="{{ route('admin.parcel.orders', ['item_on_the_way']) }}"
                                       title="{{ translate('messages.Order on the way') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                        {{ translate('messages.Order on the way') }}
                                        <span class="badge badge-soft-warning badge-pill ml-1">
                                            {{ \App\Models\Order::ItemOnTheWay()->OrderScheduledIn(30)->ParcelOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                    </a>
                                </li>
                                <li class="nav-item {{ Request::is('admin/parcel/orders/delivered') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('admin.parcel.orders', ['delivered']) }}"
                                       title="{{ translate('messages.Delivered orders') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                        {{ translate('Delivered') }}
                                        <span class="badge badge-soft-success badge-pill ml-1">
                                            {{ \App\Models\Order::Delivered()->ParcelOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                    </a>
                                </li>
                                <li class="nav-item {{ Request::is('admin/parcel/orders/canceled') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('admin.parcel.orders', ['canceled']) }}"
                                       title="{{ translate('messages.Canceled orders') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                        {{ translate('Canceled') }}
                                        <span class="badge badge-soft-warning bg-light badge-pill ml-1">
                                            {{ \App\Models\Order::Canceled()->ParcelOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                    </a>
                                </li>
                                <li class="nav-item {{ Request::is('admin/parcel/orders/failed') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('admin.parcel.orders', ['failed']) }}"
                                       title="{{ translate('messages.Payment failed orders') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container text-capitalize">
                                        {{ translate('Payment failed') }}
                                        <span class="badge badge-soft-danger bg-light badge-pill ml-1">
                                            {{ \App\Models\Order::failed()->ParcelOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                    </a>
                                </li>


                                <li class="nav-item {{ Request::is('admin/order/offline/payment/list*') ? 'active' : '' }}">
                                    <a class="nav-link "
                                       href="{{ route('admin.order.offline_verification_list', ['all']) }}"
                                       title="{{ translate('Offline payments') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                        {{ translate('Offline payments') }}
                                        <span class="badge badge-soft-danger bg-light badge-pill ml-1">
                                            {{ \App\Models\Order::where('payment_method', 'offline_payment')->whereHas('offline_payments')->ParcelOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/parcel/dispatch/*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                               title="{{ translate('messages.dispatch') }}">
                                <i class="tio-clock nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                {{ translate('messages.dispatch') }}
                            </span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="{{ Request::is('admin/parcel*') ? 'display-block' : 'display-none' }}">
                                <li class="nav-item {{ Request::is('admin/parcel/dispatch/searching_for_deliverymen') ? 'active' : '' }}">
                                    <a class="nav-link "
                                       href="{{ route('admin.parcel.list', ['searching_for_deliverymen']) }}"
                                       title="{{ translate('messages.Unassigned orders') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                        {{translate('messages.Unassigned orders')}}
                                        <span class="badge badge-soft-info badge-pill ml-1">
                                            {{ \App\Models\Order::SearchingForDeliveryman()->OrderScheduledIn(30)->ParcelOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                    </a>
                                </li>
                                <li class="nav-item {{ Request::is('admin/parcel/dispatch/on_going') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('admin.parcel.list', ['on_going']) }}"
                                       title="{{ translate('Ongoing orders') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                        {{ translate('Ongoing orders') }}
                                        <span class="badge badge-soft-light badge-pill ml-1">
                                            {{ \App\Models\Order::Ongoing()->OrderScheduledIn(30)->ParcelOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endif



                    @if (\App\CentralLogics\Helpers::module_permission_check('banner'))

                        <li class="nav-item">
                            <small class="nav-subtitle"
                                   title="{{ translate('Promotion management') }}">{{ translate('Promotion management') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>

                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/promotional-banner*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                               href="{{ route('admin.promotional-banner.add-new') }}"
                               title="{{ translate('Promotional banners') }}">
                                <i class="tio-image nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Promotional banners') }}</span>
                            </a>
                        </li>
                    @endif


                    @if (\App\CentralLogics\Helpers::module_permission_check('parcel'))

                        <li class="nav-item">
                            <small class="nav-subtitle"
                                   title="{{ translate('messages.Product section') }}">{{ translate('Product management') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>

                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/parcel/category') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                               href="{{ route('admin.parcel.category.index') }}"
                               title="{{ translate('Category setup') }}">
                                <i class="tio-category nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                {{ translate('Category setup') }}
                            </span>
                            </a>
                        </li>
                    @endif

                    @if (\App\CentralLogics\Helpers::module_permission_check('parcel'))

                        <li class="nav-item">
                            <small class="nav-subtitle"
                                   title="{{ translate('messages.Delivery section') }}">{{ translate('Delivery management') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>
                        <li class="navbar-vertical-aside-has-menu @yield('parcel_settings') @yield('parcel_cancellation')">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                               title="{{ translate('Delivery settings') }}">
                                <i class="tio-settings nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                {{ translate('Delivery settings') }}
                            </span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="{{ Request::is('admin/parcel/settings*') || Request::is('admin/parcel/cancellation-settings') ? 'display-block' : 'display-none' }}">

                                <li class="nav-item @yield('parcel_settings')">
                                    <a class="nav-link " href="{{ route('admin.parcel.settings') }}"
                                       title="{{ translate('Parcel settings') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                        {{translate('Parcel settings')}}

                                    </span>
                                    </a>
                                </li>
                                <li class="nav-item @yield('parcel_cancellation')">
                                    <a class="nav-link " href="{{ route('admin.parcel.cancellationSettings') }}"
                                       title="{{ translate('Cancellation setup') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                        {{ translate('Cancellation setup') }}

                                    </span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endif





                    @includeIf('layouts.admin.partials._logout_modal')
                </ul>
            </div>
        </div>
    </aside>
</div>

<div id="sidebarCompact" class="d-none">

</div>


@push('script_2')

@if(addon_published_status('Rental'))
<script src="{{ asset('Modules/Rental/public/assets/js/admin/view-pages/rental-sidebar.js') }}"></script>
@endif


@endpush
