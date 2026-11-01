<div id="sidebarMain" class="d-none">
    <aside class="js-navbar-vertical-aside navbar navbar-vertical-aside navbar-vertical navbar-vertical-fixed navbar-expand-xl navbar-bordered  ">
        <div class="navbar-vertical-container">
            <div class="navbar-brand-wrapper justify-content-between">
                <a class="navbar-brand" href="{{ route('admin.dashboard') }}" aria-label="Front">
                       <img class="navbar-brand-logo initial--36 onerror-image onerror-image" data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                    src="{{\App\CentralLogics\Helpers::logoFullUrl()}}"
                    alt="Logo">
                    <img class="navbar-brand-logo-mini initial--36 onerror-image onerror-image" data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                    src="{{\App\CentralLogics\Helpers::logoFullUrl()}}"
                    alt="Logo">
                </a>

                <button type="button" class="js-navbar-vertical-aside-toggle-invoker navbar-vertical-aside-toggle btn btn-icon btn-xs btn-ghost-dark">
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
                <form autocomplete="off"   class="sidebar--search-form">
                    <div class="search--form-group">
                        <button type="button" class="btn"><i class="tio-search"></i></button>
                        <input  autocomplete="false" name="qq" type="text" class="form-control form--control" placeholder="{{ translate('Search menu') }}" id="search">

                        <div id="search-suggestions" class="flex-wrap mt-1"></div>
                    </div>
                </form>

                <ul class="navbar-nav navbar-nav-lg nav-tabs">
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin') ? 'show active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.dashboard') }}?module_id={{Config::get('module.current_module_id')}}" title="{{ translate('Dashboard') }}">
                            <i class="tio-home-vs-1-outlined nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                {{ translate('Dashboard') }}
                            </span>
                        </a>
                    </li>
                    @if(\App\CentralLogics\Helpers::module_permission_check('pos'))
                    <li class="nav-item">
                        <small class="nav-subtitle" title="{{ translate('messages.Employee handle') }}">{{ translate('POS section') }}</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>
                    <li class="navbar-vertical-aside-has-menu {{Request::is('admin/pos*')?'active':''}}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link " href="{{route('admin.pos.index')}}" title="{{translate('New sale')}}">
                            <i class="tio-shopping-basket-outlined nav-icon"></i>
                            <span class="text-truncate">{{translate('New sale')}}</span>
                        </a>
                    </li>
                    @endif

                    @if (\App\CentralLogics\Helpers::module_permission_check('order'))
                    <li class="nav-item">
                        <small class="nav-subtitle">{{ translate('Order management') }}</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>

                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/order') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('messages.Orders') }}">
                            <i class="tio-shopping-cart nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                {{ translate('messages.Orders') }}
                            </span>
                        </a>
                        <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="display:{{ Request::is('admin/order*') ? 'block' : 'none' }}">
                            <li class="nav-item {{ Request::is('admin/order/list/all') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.order.list', ['all']) }}" title="{{ translate('All orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('All') }}
                                        <span class="badge badge-soft-info badge-pill ml-1">
                                            {{ \App\Models\Order::StoreOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/scheduled') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.order.list', ['scheduled']) }}" title="{{ translate('messages.Scheduled orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('Scheduled') }}
                                        <span class="badge badge-soft-info badge-pill ml-1">
                                            {{ \App\Models\Order::Scheduled()->StoreOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/pending') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.order.list', ['pending']) }}" title="{{ translate('messages.Pending orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('Pending') }}
                                        <span class="badge badge-soft-info badge-pill ml-1">
                                            {{ \App\Models\Order::Pending()->OrderScheduledIn(30)->StoreOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>

                            <li class="nav-item {{ Request::is('admin/order/list/accepted') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.order.list', ['accepted']) }}" title="{{ translate('messages.Accepted orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('Accepted') }}
                                        <span class="badge badge-soft-success badge-pill ml-1">
                                            {{ \App\Models\Order::AccepteByDeliveryman()->OrderScheduledIn(30)->StoreOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/processing') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.order.list', ['processing']) }}" title="{{ translate('messages.Processing orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('Processing') }}
                                        <span class="badge badge-soft-warning badge-pill ml-1">
                                            {{ \App\Models\Order::Preparing()->OrderScheduledIn(30)->StoreOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/item_on_the_way') ? 'active' : '' }}">
                                <a class="nav-link text-capitalize" href="{{ route('admin.order.list', ['item_on_the_way']) }}" title="{{ translate('messages.Order on the way') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('messages.Order on the way') }}
                                        <span class="badge badge-soft-warning badge-pill ml-1">
                                            {{ \App\Models\Order::ItemOnTheWay()->OrderScheduledIn(30)->StoreOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/delivered') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.order.list', ['delivered']) }}" title="{{ translate('messages.Delivered orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('Delivered') }}
                                        <span class="badge badge-soft-success badge-pill ml-1">
                                            {{ \App\Models\Order::Delivered()->StoreOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/canceled') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.order.list', ['canceled']) }}" title="{{ translate('messages.Canceled orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('Canceled') }}
                                        <span class="badge badge-soft-warning bg-light badge-pill ml-1">
                                            {{ \App\Models\Order::Canceled()->StoreOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/failed') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.order.list', ['failed']) }}" title="{{ translate('messages.Payment failed orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container text-capitalize">
                                        {{ translate('Payment failed') }}
                                        <span class="badge badge-soft-danger bg-light badge-pill ml-1">
                                            {{ \App\Models\Order::failed()->StoreOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/refunded') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.order.list', ['refunded']) }}" title="{{ translate('messages.Refunded orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('Refunded') }}
                                        <span class="badge badge-soft-danger bg-light badge-pill ml-1">
                                            {{ \App\Models\Order::Refunded()->StoreOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>

                            <li class="nav-item {{ Request::is('admin/order/offline/payment/list*') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.order.offline_verification_list', ['all']) }}" title="{{ translate('Offline payments') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('Offline payments') }}
                                        <span class="badge badge-soft-danger bg-light badge-pill ml-1">
                                            {{ \App\Models\Order::where('payment_method', 'offline_payment')->whereHas('offline_payments')->StoreOrder()->module(Config::get('module.current_module_id'))->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>

                        </ul>
                    </li>

                    <li
                    class="navbar-vertical-aside-has-menu {{ Request::is('admin/refund/*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                        title="{{ translate('Order refunds') }}">
                        <i class="tio-receipt nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{ translate('Order refunds') }}
                        </span>
                    </a>
                    <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                        style="display: {{ Request::is('admin/refund*') ? 'block' : 'none' }}">

                        <li class="nav-item {{ Request::is('admin/refund/requested') ||  Request::is('admin/refund/rejected') ||Request::is('admin/refund/refunded') ? 'active' : '' }}">
                            <a class="nav-link "
                                href="{{ route('admin.refund.refund_attr', ['requested']) }}"
                                title="{{ translate('Refund requests') }} ">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate sidebar--badge-container">
                                    {{ translate('Refund requests') }}
                                    <span class="badge badge-soft-danger badge-pill ml-1">
                                        {{ \App\Models\Order::Refund_requested()->StoreOrder()->module(Config::get('module.current_module_id'))->count() }}
                                    </span>
                                </span>
                            </a>
                        </li>

                    </ul>
                    </li>
                    @endif


                    @if(\App\CentralLogics\Helpers::module_permission_check('campaign')||
                        \App\CentralLogics\Helpers::module_permission_check('banner') ||
                        \App\CentralLogics\Helpers::module_permission_check('coupon')||
                        \App\CentralLogics\Helpers::module_permission_check('notification') ||
                        \App\CentralLogics\Helpers::module_permission_check('coupon')
                        )
                <li class="nav-item">
                    <small class="nav-subtitle" title="{{ translate('Promotion management') }}">{{ translate('Promotion management') }}</small>
                    <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                </li>
                @if (\App\CentralLogics\Helpers::module_permission_check('campaign'))
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/campaign') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('messages.campaigns') }}">
                        <i class="tio-layers-outlined nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.campaigns') }}</span>
                    </a>
                    <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="display:{{ Request::is('admin/campaign*') ? 'block' : 'none' }}">

                        <li class="nav-item {{ Request::is('admin/campaign/basic/*') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.campaign.list', 'basic') }}" title="{{ translate('messages.Basic campaigns') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('messages.Basic campaigns') }}</span>
                            </a>
                        </li>
                        <li class="nav-item {{ Request::is('admin/campaign/item/*') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.campaign.list', 'item') }}" title="{{ translate('messages.Food campaigns') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('messages.Food campaigns') }}</span>
                            </a>
                        </li>
                    </ul>
                </li>
                @endif
                @if (\App\CentralLogics\Helpers::module_permission_check('banner'))
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/banner*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.banner.add-new') }}" title="{{ translate('messages.banners') }}">
                        <i class="tio-image nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.banners') }}</span>
                    </a>
                </li>
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/promotional-banner*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.promotional-banner.add-new') }}" title="{{ translate('messages.Other banners') }}">
                        <i class="tio-image nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.Other banners') }}</span>
                    </a>
                </li>
                @endif
                @if (\App\CentralLogics\Helpers::module_permission_check('coupon'))
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/coupon*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.coupon.add-new') }}" title="{{ translate('messages.coupons') }}">
                        <i class="tio-gift nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.coupons') }}</span>
                    </a>
                </li>
                @endif
                @if (\App\CentralLogics\Helpers::module_permission_check('notification'))
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/notification*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.notification.add-new') }}" title="{{ translate('Push notification') }}">
                        <i class="tio-notifications nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{ translate('Push notification') }}
                        </span>
                    </a>
                </li>
                @endif


            @if (\App\CentralLogics\Helpers::module_permission_check('coupon'))
                <li
                    class="navbar-vertical-aside-has-menu  @yield('advertisement')">
                    <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                        title="{{ translate('Advertisement') }}">
                        <i class="tio-tv-old nav-icon"></i>
                        <span
                            class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Advertisement') }}</span>
                    </a>
                    <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                        style="display: {{ Request::is('admin/advertisement*') ? 'block' : 'none' }}">

                        <li class="nav-item @yield('advertisement_create')">
                            <a class="nav-link " href="{{ route('admin.advertisement.create') }}"
                                title="{{ translate('New advertisement') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('New advertisement') }}</span>
                            </a>
                        </li>
                        <li class="nav-item @yield('advertisement_request')">
                            <a class="nav-link " href="{{ route('admin.advertisement.requestList') }}"
                                title="{{ translate('Advertisement requests') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('Advertisement requests') }}</span>
                            </a>
                        </li>
                        <li class="nav-item @yield('advertisement_list')">
                            <a class="nav-link " href="{{ route('admin.advertisement.index') }}"
                                title="{{ translate('Advertisement list') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('Advertisement list') }}</span>
                            </a>
                        </li>
                    </ul>
                </li>
            @endif
            @if (
                addon_published_status('ReelsModule')
                && \App\CentralLogics\Helpers::module_permission_check('reels')
                && \Modules\ReelsModule\Support\ReelModuleConfig::isAllowedType(config('module.current_module_type'))
            )
                <li class="nav-item">
                    <small class="nav-subtitle">{{ translate('Reels management') }}</small>
                    <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                </li>
                <li class="navbar-vertical-aside-has-menu @yield('reels_create')">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.reels.create') }}"
                        title="{{ translate('Create reels') }}">
                        <i class="tio-video-camera-outlined nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Create reels') }}</span>
                    </a>
                </li>
                <li class="navbar-vertical-aside-has-menu @yield('reels_list')">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.reels.index') }}"
                        title="{{ translate('Reels list') }}">
                        <i class="tio-format-bullets nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Reels list') }}</span>
                    </a>
                </li>
            @endif
                    @endif

                    @if(\App\CentralLogics\Helpers::module_permission_check('category')||
                        \App\CentralLogics\Helpers::module_permission_check('addon')||
                        \App\CentralLogics\Helpers::module_permission_check('item')
                        )
                        <li class="nav-item">
                            <small class="nav-subtitle" title="{{ translate('messages.Item section') }}">{{ translate('messages.Food management') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>

                        @if (\App\CentralLogics\Helpers::module_permission_check('category'))
                            <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/category*') ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('Categories') }}">
                                    <i class="tio-category nav-icon"></i>
                                    <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Categories') }}</span>
                                </a>
                                <ul class="js-navbar-vertical-aside-submenu nav nav-sub"  style="display:{{ Request::is('admin/category*') ? 'block' : 'none' }}">
                                    <li class="nav-item  @yield('main_category') {{ request()->input('position') == 0 && Request::is('admin/category/add') ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('admin.category.add',['position'=>0]) }}" title="{{ translate('Main category') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Main category') }}</span>
                                        </a>
                                    </li>

                                    <li class="nav-item   @yield('sub_category') {{ request()->input('position') == 1 && Request::is('admin/category/add') ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('admin.category.add',['position'=>1]) }}" title="{{ translate('Main subcategory') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Main subcategory') }}</span>
                                        </a>
                                    </li>

                                    <li class="nav-item {{ Request::is('admin/category/bulk-import') ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('admin.category.bulk-import') }}" title="{{ translate('Bulk import') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate text-capitalize">{{ translate('Bulk import') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/category/bulk-export') ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('admin.category.bulk-export-index') }}" title="{{ translate('Bulk export') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate text-capitalize">{{ translate('Bulk export') }}</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        @endif

                        @if (\App\CentralLogics\Helpers::storeCategoryStatus() && \App\CentralLogics\Helpers::module_permission_check('category'))
                            <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/store-category*') ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link"
                                   href="{{ route('admin.store-category.list') }}" title="{{ translate('Store categories') }}">
                                    <i class="tio-folder-bookmarked nav-icon"></i>
                                    <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Store categories') }}</span>
                                </a>
                            </li>
                        @endif



                        @if (\App\CentralLogics\Helpers::module_permission_check('addon'))
                            <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/addon*') ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('Addons') }}">
                                    <i class="tio-add-circle-outlined nav-icon"></i>
                                    <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Addons') }}</span>
                                </a>
                                <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="display:{{ Request::is('admin/addon*') ? 'block' : 'none' }}">
                                    <li class="nav-item {{ Request::is('admin/addon/addon-category') ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('admin.addon.addon-category') }}" title="{{ translate('Addon category') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Addon category') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/addon/add-new') ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('admin.addon.add-new') }}" title="{{ translate('Addon list') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('messages.list') }}</span>
                                        </a>
                                    </li>

                                    <li class="nav-item {{ Request::is('admin/addon/bulk-import') ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('admin.addon.bulk-import') }}" title="{{ translate('Bulk import') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate text-capitalize">{{ translate('Bulk import') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/addon/bulk-export') ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('admin.addon.bulk-export-index') }}" title="{{ translate('Bulk export') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate text-capitalize">{{ translate('Bulk export') }}</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        @endif
                        @if (\App\CentralLogics\Helpers::module_permission_check('item'))
                            <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/item*') ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('Food setup') }}">
                                    <i class="tio-premium-outlined nav-icon"></i>
                                    <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate text-capitalize">{{ translate('Food setup') }}</span>
                                </a>
                                <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="display:{{ Request::is('admin/item*') ? 'block' : 'none' }}">
                                    <li class="nav-item {{ Request::is('admin/item/add-new') || (Request::is('admin/item/edit/*') && strpos(request()->fullUrl(), 'product_gellary=1') !== false  )  ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('admin.item.add-new') }}" title="{{ translate('Add new') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Add new') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/item/list') || (Request::is('admin/item/edit/*') && (strpos(request()->fullUrl(), 'temp_product=1') == false && strpos(request()->fullUrl(), 'product_gellary=1') == false  ) ) ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('admin.item.list') }}" title="{{ translate('Food list') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('messages.list') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{  Request::is('admin/item/product-gallery') ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('admin.item.product_gallery') }}" title="{{ translate('Product gallery') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Food gallery') }}</span>
                                        </a>
                                    </li>
                                    @if (\App\CentralLogics\Helpers::get_business_settings('product_approval'))
                                        <li class="nav-item {{  Request::is('admin/item/requested/item/view/*') || Request::is('admin/item/new/item/list') || (Request::is('admin/item/edit/*') && strpos(request()->fullUrl(), 'temp_product=1') !== false  ) ? 'active' : '' }}">
                                            <a class="nav-link " href="{{ route('admin.item.approval_list') }}" title="{{ translate('New item request') }}">
                                                <span class="tio-circle nav-indicator-icon"></span>
                                                <span class="text-truncate">{{ translate('New food request') }}
                                                     <span class="badge badge-soft-success bg-light badge-pill ml-1">
                                                        {{ \App\Models\TempProduct::withoutGlobalScope(StoreScope::class)->module(Config::get('module.current_module_id'))->count() }}
                                                    </span>
                                                </span>
                                            </a>
                                        </li>
                                    @endif
                                    <li class="nav-item {{ Request::is('admin/item/reviews') ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('admin.item.reviews') }}" title="{{ translate('Review list') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('messages.review') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/item/bulk-import') ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('admin.item.bulk-import') }}" title="{{ translate('Bulk import') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate text-capitalize">{{ translate('Bulk import') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/item/bulk-export') ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('admin.item.bulk-export-index') }}" title="{{ translate('Bulk export') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate text-capitalize">{{ translate('Bulk export') }}</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        @endif
                    @endif


                    @if (\App\CentralLogics\Helpers::module_permission_check('store'))

                <li class="nav-item">
                    <small class="nav-subtitle" title="{{ translate('messages.Restaurant section') }}">{{ translate('messages.Restaurant management') }}</small>
                    <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                </li>

                <li class="navbar-vertical-aside-has-menu @yield('new_store_request') {{ Request::is('admin/store/pending-requests') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.store.pending-requests') }}" title="{{ translate('messages.New restaurants') }}">
                        <span class="tio-calendar-note nav-icon"></span>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate text-capitalize">
                            {{ translate('messages.New restaurants') }}
                            @php($new_str = \App\Models\Store::whereHas('vendor', function($query){
                                return $query->where('status', null);
                            })->module(Config::get('module.current_module_id'))->get())
                            @if (count($new_str)>0)

                            <span class="btn-status btn-status-danger border-0 size-8px"></span>
                            @endif
                        </span>
                    </a>
                </li>
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/store/add') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.store.add') }}" title="{{ translate('Add new restaurant') }}">
                        <span class="tio-add-circle nav-icon"></span>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate text-capitalize">
                            {{ translate('Add new restaurant') }}
                        </span>
                    </a>
                </li>
                <li class="navbar-vertical-aside-has-menu @yield('store_update') {{ Request::is('admin/store/list') ||  Request::is('admin/store/view/*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.store.list') }}" title="{{ translate('messages.Restaurants list') }}">
                        <span class="tio-layout nav-icon"></span>
                        <span class="text-truncate">{{ translate('messages.restaurants') }}
                            {{ translate('list') }}</span>
                    </a>
                </li>

                <li class="navbar-item {{ Request::is('admin/store/recommended-store') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.store.recommended_store') }}" title="{{ translate('Pending requests') }}">
                        <span class="tio-hot  nav-icon"></span>
                        <span class="text-truncate text-capitalize">{{ translate('Recommended restaurants') }}</span>
                    </a>
                </li>
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/store/bulk-import') ? 'active' : '' }}">
                    <a class="nav-link " href="{{ route('admin.store.bulk-import') }}" title="{{ translate('Bulk import') }}">
                        <span class="tio-publish nav-icon"></span>
                        <span class="text-truncate text-capitalize">{{ translate('Bulk import') }}</span>
                    </a>
                </li>
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/store/bulk-export') ? 'active' : '' }}">
                    <a class="nav-link " href="{{ route('admin.store.bulk-export-index') }}" title="{{ translate('Bulk export') }}">
                        <span class="tio-download-to nav-icon"></span>
                        <span class="text-truncate text-capitalize">{{ translate('Bulk export') }}</span>
                    </a>
                </li>
                @endif

                <li class="nav-item py-5">

                </li>


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
