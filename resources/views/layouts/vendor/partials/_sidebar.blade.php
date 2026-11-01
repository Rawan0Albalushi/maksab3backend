{{-- Vendor sidebar (v1). View data comes from
     App\Navigation\VendorSidebarViewModel via VendorViewComposerServiceProvider. --}}
<div id="sidebarMain" class="d-none">
    <aside
        class="js-navbar-vertical-aside navbar navbar-vertical-aside navbar-vertical navbar-vertical-fixed navbar-expand-xl navbar-bordered">
        <div class="navbar-vertical-container">
            <div class="navbar-brand-wrapper justify-content-between">

                <a class="navbar-brand" href="{{ route('vendor.dashboard') }}" aria-label="Front">
                    <img class="navbar-brand-logo initial--36  onerror-image"
                        data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                        src="{{ $sidebar->store->logo_full_url }}" alt="Logo">
                    <img class="navbar-brand-logo-mini initial--36 onerror-image"
                        data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                        src="{{ $sidebar->store->logo_full_url }}" alt="Logo">
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

            <div class="navbar-vertical-content text-capitalize bg--005555" id="navbar-vertical-content">
                <form class="sidebar--search-form">
                    <div class="search--form-group">
                        <button type="button" class="btn"><i class="tio-search"></i></button>
                        <input type="text" class="form-control form--control"
                            placeholder="{{ translate('Search menu') }}" id="search">
                    </div>
                </form>
                <ul class="navbar-nav navbar-nav-lg nav-tabs">
                    
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('vendor.dashboard') }}"
                            title="{{ translate('Dashboard') }}">
                            <i class="tio-home-vs-1-outlined nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                {{ translate('Dashboard') }}
                            </span>
                        </a>
                    </li>
                    
                    @if ($sidebar->can('pos'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/pos') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link  "
                                href="{{ route('vendor.pos.index') }}" title="{{ translate('messages.POS') }}">
                                <i class="tio-shopping-basket-outlined nav-icon"></i>
                                <span class="text-truncate">{{ translate('messages.POS') }}</span>
                            </a>
                        </li>
                    @endif
                    @if ($sidebar->sectionVisible('order_management'))
                        <li class="nav-item">
                            <small class="nav-subtitle"
                                title="{{ translate('Orders') }}">{{ translate('Orders') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>
                    @endif

                    @if ($sidebar->can('order'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/order*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                                title="{{ translate('messages.Orders') }}">
                                <i class="tio-shopping-cart nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                    {{ translate('messages.Orders') }}
                                </span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display: {{ Request::is('vendor-panel/order*') ? 'block' : 'none' }}">
                                <li class="nav-item {{ Request::is('vendor-panel/order/list/all') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('vendor.order.list', ['all']) }}"
                                        title="{{ translate('All orders') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('All') }}
                                            <span class="badge badge-soft-info badge-pill ml-1">
                                                {{ $count_all }}
                                            </span>
                                        </span>
                                    </a>
                                </li>
                                <li
                                    class="nav-item {{ Request::is('vendor-panel/order/list/pending') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.order.list', ['pending']) }}"
                                        title="{{ translate('messages.pending_orders') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('Pending') }}
                                            {{ $sidebar->showTakeAwayLabel ? translate('Take away') : '' }}
                                            <span class="badge badge-soft-success badge-pill ml-1">
                                                {{ $count_pending }}
                                            </span>
                                        </span>
                                    </a>
                                </li>

                                <li
                                    class="nav-item {{ Request::is('vendor-panel/order/list/confirmed') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.order.list', ['confirmed']) }}"
                                        title="{{ translate('messages.Confirmed orders') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('messages.confirmed') }}
                                            <span class="badge badge-soft-success badge-pill ml-1">
                                                {{ $count_confirmed }}
                                            </span>
                                        </span>
                                    </a>
                                </li>

                                <li
                                    class="nav-item {{ Request::is('vendor-panel/order/list/cooking') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('vendor.order.list', ['cooking']) }}"
                                        title="{{ translate('messages.Processing orders') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            @if ($sidebar->store->module->module_type == 'food')
                                                {{ translate('Cooking') }}
                                            @else
                                                {{ translate('Processing') }}
                                            @endif
                                            <span class="badge badge-soft-info badge-pill ml-1">
                                                {{ $count_processing }}
                                            </span>
                                        </span>
                                    </a>
                                </li>
                                <li
                                    class="nav-item {{ Request::is('vendor-panel/order/list/ready_for_delivery') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('vendor.order.list', ['ready_for_delivery']) }}"
                                        title="{{ translate('Ready for delivery') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('Ready for delivery') }}
                                            <span class="badge badge-soft-info badge-pill ml-1">
                                                {{ $count_handover }}
                                            </span>
                                        </span>
                                    </a>
                                </li>
                                <li
                                    class="nav-item {{ Request::is('vendor-panel/order/list/item_on_the_way') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('vendor.order.list', ['item_on_the_way']) }}"
                                        title="{{ translate('messages.Items on the way') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('Item on the way') }}
                                            <span class="badge badge-soft-info badge-pill ml-1">
                                                {{ $count_picked_up }}
                                            </span>
                                        </span>
                                    </a>
                                </li>
                                <li
                                    class="nav-item {{ Request::is('vendor-panel/order/list/delivered') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.order.list', ['delivered']) }}"
                                        title="{{ translate('messages.Delivered orders') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('Delivered') }}
                                            <span class="badge badge-soft-success badge-pill ml-1">
                                                {{ $count_delivered }}
                                            </span>
                                        </span>
                                    </a>
                                </li>
                                <li
                                    class="nav-item {{ Request::is('vendor-panel/order/list/refunded') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.order.list', ['refunded']) }}"
                                        title="{{ translate('messages.Refunded orders') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('Refunded') }}
                                            <span class="badge badge-soft-danger bg-light badge-pill ml-1">
                                                {{ $count_refunded }}
                                            </span>
                                        </span>
                                    </a>
                                </li>
                                <li
                                    class="nav-item {{ Request::is('vendor-panel/order/list/scheduled') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('vendor.order.list', ['scheduled']) }}"
                                        title="{{ translate('messages.scheduled_orders') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('Scheduled') }}
                                            <span class="badge badge-soft-info badge-pill ml-1">
                                                {{ $count_scheduled }}
                                            </span>
                                        </span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                    @endif

                    @if ($sidebar->can('flash_sale'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/item/flash-sale*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.item.flash_sale') }}"
                                title="{{ translate('Flash sales') }}">
                                <i class="tio-apps nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                    {{ translate('Flash sales') }}
                                </span>
                            </a>
                        </li>
                    @endif

                    @if ($sidebar->sectionVisible('item_management'))
                        <li class="nav-item">
                            <small class="nav-subtitle">{{ translate('Items') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>
                    @endif

                    @if ($sidebar->can('item'))

                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/item*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                                title="{{ translate('messages.Items') }}">
                                <i class="tio-premium-outlined nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.Items') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display: {{ Request::is('vendor-panel/item*') ? 'block' : 'none' }}">
                                <li class="nav-item {{ Request::is('vendor-panel/item/add-new') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.item.add-new') }}"
                                        title="{{ translate('messages.Add new item') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('Add new') }}</span>
                                    </a>
                                </li>
                                <li class="nav-item {{ Request::is('vendor-panel/item/list') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.item.list') }}"
                                        title="{{ translate('messages.Items list') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('messages.list') }}</span>
                                    </a>
                                </li>

                                @if ($sidebar->nav['product_approval'])
                                    <li
                                        class="nav-item {{ Request::is('vendor-panel/item/pending/item/list') || Request::is('vendor-panel/item/requested/item/view/*') ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('vendor.item.pending_item_list') }}"
                                            title="{{ translate('messages.pending_item_list') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span
                                                class="text-truncate">{{ translate('messages.pending_item_list') }}</span>
                                        </a>
                                    </li>
                                @endif
                                @if ($sidebar->nav['product_gallery'])
                                    <li
                                        class="nav-item {{ Request::is('vendor-panel/item/product-gallery') ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('vendor.item.product_gallery') }}"
                                            title="{{ translate('Product gallery') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span
                                                class="text-truncate">{{ translate('Product gallery') }}</span>
                                        </a>
                                    </li>
                                @endif

                                @if (!$sidebar->nav['is_food'])
                                    <li
                                        class="nav-item {{ Request::is('vendor-panel/item/stock-limit-list') ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('vendor.item.stock-limit-list') }}"
                                            title="{{ translate('Low stock list') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span
                                                class="text-truncate">{{ translate('Low stock list') }}</span>
                                        </a>
                                    </li>
                                @endif
                                @if ($sidebar->nav['item_section'])
                                    <li
                                        class="nav-item {{ Request::is('vendor-panel/item/bulk-import') ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('vendor.item.bulk-import') }}"
                                            title="{{ translate('Bulk import') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span
                                                class="text-truncate text-capitalize">{{ translate('Bulk import') }}</span>
                                        </a>
                                    </li>
                                    <li
                                        class="nav-item {{ Request::is('vendor-panel/item/bulk-export') ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('vendor.item.bulk-export-index') }}"
                                            title="{{ translate('Bulk export') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span
                                                class="text-truncate text-capitalize">{{ translate('Bulk export') }}</span>
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                        
                    @endif
                    
                    @if ($sidebar->can('addon'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/addon*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.addon.add-new') }}"
                                title="{{ translate('Addons') }}">
                                <i class="tio-add-circle-outlined nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                    {{ translate('Addons') }}
                                </span>
                            </a>
                        </li>
                    @endif
                    
                    @if ($sidebar->can('category'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/category*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                                title="{{ translate('Categories') }}">
                                <i class="tio-category nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Categories') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display: {{ Request::is('vendor-panel/category*') ? 'block' : 'none' }}">
                                <li class="nav-item {{ Request::is('vendor-panel/category/list') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.category.add') }}"
                                        title="{{ translate('Main category') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('Main category') }}</span>
                                    </a>
                                </li>

                                <li
                                    class="nav-item {{ Request::is('vendor-panel/category/sub-category-list') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.category.add-sub-category') }}"
                                        title="{{ translate('Main subcategory') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('Main subcategory') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endif

                    @if ($sidebar->can('my_category'))
                        <li class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/store-category*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.store-category.list') }}" title="{{ translate('My category') }}">
                                <i class="tio-folder-bookmarked nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('My category') }}</span>
                            </a>
                        </li>
                    @endif

                    @if ($sidebar->sectionVisible('marketing_section'))
                        <li class="nav-item">
                            <small class="nav-subtitle">{{ translate('Marketing section') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>
                    @endif
                    
                    @if ($sidebar->can('campaign'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/campaign*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                                title="{{ translate('messages.campaigns') }}">
                                <i class="tio-image nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.campaigns') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display: {{ Request::is('vendor-panel/campaign*') ? 'block' : 'none' }}">
                                <li class="nav-item {{ Request::is('vendor-panel/campaign/list') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.campaign.list') }}"
                                        title="{{ translate('messages.Basic campaigns') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span
                                            class="text-truncate">{{ translate('messages.Basic campaigns') }}</span>
                                    </a>
                                </li>
                                <li
                                    class="nav-item {{ Request::is('vendor-panel/campaign/item/list') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.campaign.itemlist') }}"
                                        title="{{ translate('Item campaigns') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('Item campaigns') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endif

                    
                    @if ($sidebar->can('coupon'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/coupon*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.coupon.add-new') }}"
                                title="{{ translate('messages.coupons') }}">
                                <i class="tio-ticket nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.coupons') }}</span>
                            </a>
                        </li>
                    @endif

                    @if ($sidebar->can('banner'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/banner*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.banner.list') }}"
                                title="{{ translate('messages.banners') }}">
                                <i class="tio-image nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.banners') }}</span>
                            </a>
                        </li>
                    @endif

                    @if ($sidebar->sectionVisible('advertisement_management'))
                        <li class="nav-item">
                            <small class="nav-subtitle">{{ translate('Advertisement') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>
                    @endif

                    @if ($sidebar->can('advertisement'))
                        <li class="navbar-vertical-aside-has-menu @yield('advertisement_create')">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.advertisement.create') }}"
                                title="{{ translate('New advertisement') }}">
                                <i class="tio-tv-old nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('New advertisement') }}</span>
                            </a>
                        </li>
                    @endif

                    @if ($sidebar->can('advertisement_list'))
                        <li class="navbar-vertical-aside-has-menu @yield('advertisement')">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                                title="{{ translate('Advertisement list') }}">
                                <i class="tio-format-bullets nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Advertisement list') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display: {{ !Request::is('vendor-panel/advertisement/create*') && Request::is('vendor-panel/advertisement*') ? 'block' : 'none' }}">
                                <li class="nav-item @yield('advertisement_pending_list')">
                                    <a class="nav-link "
                                        href="{{ route('vendor.advertisement.index', ['type' => 'pending']) }}"
                                        title="{{ translate('messages.Pending') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('messages.Pending') }}</span>
                                    </a>
                                </li>

                                <li class="nav-item @yield('advertisement_list')">
                                    <a class="nav-link " href="{{ route('vendor.advertisement.index') }}"
                                        title="{{ translate('Ad list') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('Ad list') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endif

                    @if ($sidebar->reelsEnabled)
                        <li class="nav-item">
                            <small class="nav-subtitle">{{ translate('Reels') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>

                        <li class="navbar-vertical-aside-has-menu @yield('vendor_reels_create')">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.reels.create') }}"
                                title="{{ translate('Create reels') }}">
                                <i class="tio-video-camera-outlined nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Create reels') }}</span>
                            </a>
                        </li>

                        <li class="navbar-vertical-aside-has-menu @yield('vendor_reels')">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.reels.index') }}"
                                title="{{ translate('Reels list') }}">
                                <i class="tio-format-bullets nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Reels list') }}</span>
                            </a>
                        </li>
                    @endif

                    @if ($sidebar->sectionVisible('deliveryman_section'))
                        <li class="nav-item">
                            <small class="nav-subtitle"
                                title="{{ translate('messages.deliveryman_section') }}">{{ translate('messages.deliveryman_section') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>
                    @endif

                    @if ($sidebar->can('deliveryman'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/delivery-man/add') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.delivery-man.add') }}"
                                title="{{ translate('Add deliveryman') }}">
                                <i class="tio-running nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                    {{ translate('Add deliveryman') }}
                                </span>
                            </a>
                        </li>
                    @endif

                    @if ($sidebar->can('deliveryman_list'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/delivery-man/list') || Request::is('vendor-panel/delivery-man/edit/*') || Request::is('vendor-panel/delivery-man/preview/*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.delivery-man.list') }}"
                                title="{{ translate('Deliveryman') }}">
                                <i class="tio-filter-list nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                    {{ translate('messages.deliverymen_list') }}
                                </span>
                            </a>
                        </li>
                    @endif

                    @if ($sidebar->sectionVisible('wallet_management'))

                        <li class="nav-item">
                            <small class="nav-subtitle"
                                title="{{ translate('Wallet') }}">{{ translate('Wallet') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>
                    @endif

                    @if ($sidebar->can('wallet'))

                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/wallet') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.wallet.index') }}"
                                title="{{ translate('messages.My wallet') }}">
                                <i class="tio-table nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.My wallet') }}</span>
                            </a>
                        </li>
                    @endif
                    @if ($sidebar->can('wallet_method'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/withdraw-method*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.wallet-method.index') }}"
                                title="{{ translate('messages.My wallet') }}">
                                <i class="tio-museum nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.Disbursement method') }}</span>
                            </a>
                        </li>
                    @endif

                    
                    @if ($sidebar->sectionVisible('employee_section'))
                        <li class="nav-item">
                            <small class="nav-subtitle"
                                title="{{ translate('Employee section') }}">{{ translate('Employee section') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>
                    @endif

                    @if ($sidebar->can('role'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/custom-role*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                                title="{{ translate('messages.Employee role') }}">
                                <i class="tio-incognito nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.Employee role') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display: {{ Request::is('vendor-panel/custom-role*') ? 'block' : 'none' }}">
                                <li class="nav-item {{ Request::is('vendor-panel/custom-role') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.custom-role.index') }}"
                                        title="{{ translate('Role list') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('messages.list') }}</span>
                                    </a>
                                </li>
                                <li class="nav-item {{ Request::is('vendor-panel/custom-role/create') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.custom-role.create') }}"
                                        title="{{ translate('Add new role') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('Add new') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endif

                    @if ($sidebar->can('employee'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/employee*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                                title="{{ translate('Employees') }}">
                                <i class="tio-user nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Employees') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display: {{ Request::is('vendor-panel/employee*') ? 'block' : 'none' }}">
                                <li
                                    class="nav-item {{ Request::is('vendor-panel/employee/add-new') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.employee.add-new') }}"
                                        title="{{ translate('messages.Add new employee') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('Add new') }}</span>
                                    </a>
                                </li>
                                <li class="nav-item {{ Request::is('vendor-panel/employee/list') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.employee.list') }}"
                                        title="{{ translate('messages.Employee list') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('messages.list') }}</span>
                                    </a>
                                </li>

                            </ul>
                        </li>
                    @endif

                    @if ($sidebar->sectionVisible('report_section'))
                        <li class="nav-item">
                            <small class="nav-subtitle"
                                title="{{ translate('Report section') }}">{{ translate('Report section') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>
                    @endif

                    @if ($sidebar->can('expense_report'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/report/expense-report') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('vendor.report.expense-report') }}"
                                title="{{ translate('Expense report') }}">
                                <span class="tio-money nav-icon"></span>
                                <span class="text-truncate">{{ translate('Expense report') }}</span>
                            </a>
                        </li>
                    @endif

                    @if ($sidebar->can('store_earning_report'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/report/store-earning-report') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('vendor.report.store-earning-report') }}"
                                title="{{ translate('Store earning report') }}">
                                <span class="tio-align-to-bottom nav-icon"></span>
                                <span class="text-truncate">{{ translate('Store earning report') }}</span>
                            </a>
                        </li>
                    @endif
                    
                    @if ($sidebar->can('disbursement_report'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/report/disbursement-report') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('vendor.report.disbursement-report') }}"
                                title="{{ translate('Disbursement report') }}">
                                <span class="tio-saving nav-icon"></span>
                                <span class="text-truncate">{{ translate('Disbursement report') }}</span>
                            </a>
                        </li>
                    @endif
                    
                    @if ($sidebar->can('vat_report'))
                        <li class="navbar-vertical-aside-has-menu @yield('vendor_tax_report')">
                            <a class="nav-link " href="{{ route('vendor.report.vendorTax') }}"
                                title="{{ translate('VAT report') }}">
                                <span class="tio-saving nav-icon"></span>
                                <span class="text-truncate">{{ translate('VAT report') }}</span>
                            </a>
                        </li>
                    @endif

                    @if ($sidebar->sectionVisible('business_section'))

                        <li class="nav-item">
                            <small class="nav-subtitle"
                                title="{{ translate('Business section') }}">{{ translate('Business section') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>
                    @endif

                    @if ($sidebar->can('store_setup'))
                        <li
                            class="nav-item {{ Request::is('vendor-panel/business-settings/store-setup') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('vendor.business-settings.store-setup') }}"
                                title="{{ translate('Store setup') }}">
                                <span class="tio-settings nav-icon"></span>
                                <span class="text-truncate">{{ translate('Store setup') }}</span>
                            </a>
                        </li>
                    @endif

                    @if ($sidebar->can('notification_setup'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/business-settings/notification-setup') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('vendor.business-settings.notification-setup') }}"
                                title="{{ translate('Notification setup') }}">
                                <span class="tio-notifications nav-icon"></span>
                                <span class="text-truncate">{{ translate('Notification setup') }}</span>
                            </a>
                        </li>
                    @endif

                    @if ($sidebar->can('my_shop'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/store/*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.shop.view') }}"
                                title="{{ translate('messages.My shop') }}">
                                <i class="tio-home nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                    {{ translate('messages.My shop') }}
                                </span>
                            </a>
                        </li>
                    @endif
                    @if ($sidebar->can('business_plan'))
                        <li class="navbar-vertical-aside-has-menu @yield('subscriberList')">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.subscriptionackage.subscriberDetail') }}"
                                title="{{ translate('My subscription') }}">
                                <i class="tio-crown nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                    {{ translate('My business plan') }}
                                </span>
                            </a>
                        </li>
                    @endif

                    @if ($sidebar->sectionVisible('customer_engagement'))
                        <li class="nav-item">
                            <small class="nav-subtitle"
                                title="{{ translate('Customer engagement') }}">{{ translate('Customer engagement') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>
                    @endif

                    @if ($sidebar->can('reviews'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/reviews') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.reviews') }}" title="{{ translate('messages.Reviews') }}">
                                <i class="tio-star-outlined nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                    {{ translate('messages.Reviews') }}
                                </span>
                            </a>
                        </li>
                    @endif
                    
                    @if ($sidebar->can('chat'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('vendor-panel/message*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.message.list') }}"
                                title="{{ translate('messages.Chat') }}">
                                <i class="tio-chat nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                    {{ translate('messages.Chat') }}
                                </span>
                            </a>
                        </li>
                    @endif

                    {{-- CTA links to advertisement.create, so it follows that permission, not coupon --}}
                    @if ($sidebar->can('advertisement'))
                        <li class="nav-item px-20 pb-5">
                            <div class="promo-card">
                                <div class="position-relative">
                                    <img src="{{ asset('public/assets/admin/img/promo-2.png') }}" class="mw-100"
                                        alt="">
                                    <h4 class="mb-2 mt-3">{{ translate('Want to get highlighted?') }}</h4>
                                    <p class="mb-4">
                                        {{ translate('Create ads to get highlighted on the app and web browser') }}
                                    </p>
                                    <a href="{{ route('vendor.advertisement.create') }}"
                                        class="btn btn--primary"><i class="tio-add-circle"></i> {{ translate('Create ads') }}</a>
                                </div>
                            </div>
                        </li>
                    @endif
                </ul>
            </div>
            
        </div>
    </aside>
</div>

<div id="sidebarCompact" class="d-none">

</div>

@push('script_2')
   <script src="{{ asset('public/assets/admin/js/view-pages/sidebar.js') }}"></script>
@endpush
