{{-- Vendor sidebar (v2). All view data comes from
     App\Navigation\VendorSidebarViewModel via VendorViewComposerServiceProvider;
     permission gating comes from App\Navigation\VendorNav. --}}
<aside id="v2-shell" class="v2-shell" data-workspace="vendor::{{ $sidebar->moduleType }}" data-active-section="{{ $sidebar->activeSection }}">
    <div id="v2-rail" class="v2-rail v2-rail--module" role="navigation" aria-label="Sections">
        <div class="v2-rail-scope d-none">{{ strtoupper($sidebar->moduleType ?: 'STORE') }}</div>
        <div class="v2-rail-btns">
            <button class="v2-rail-btn {{ $sidebar->activeSection==='dashboard' ? 'is-active' : '' }}" data-section="dashboard" data-label="{{ translate('Dashboard') }}" aria-label="{{ translate('Dashboard') }}">
                <i data-lucide="layout-dashboard"></i><span class="v2-pin-dot"></span>
            </button>
            @if($sidebar->sectionVisible('sales', 'v2'))
            <button class="v2-rail-btn {{ $sidebar->activeSection==='sales' ? 'is-active' : '' }}" data-section="sales" data-label="{{ translate('Sales') }}" aria-label="{{ translate('Sales') }}">
                <i data-lucide="shopping-cart"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($sidebar->sectionVisible('catalog', 'v2'))
            <button class="v2-rail-btn {{ $sidebar->activeSection==='catalog' ? 'is-active' : '' }}" data-section="catalog" data-label="{{ translate('Catalog') }}" aria-label="{{ translate('Catalog') }}">
                <i data-lucide="package"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($sidebar->marketingVisible)
            <button class="v2-rail-btn {{ $sidebar->activeSection==='marketing' ? 'is-active' : '' }}" data-section="marketing" data-label="{{ translate('Marketing') }}" aria-label="{{ translate('Marketing') }}">
                <i data-lucide="megaphone"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($sidebar->sectionVisible('ops', 'v2'))
            <button class="v2-rail-btn {{ $sidebar->activeSection==='ops' ? 'is-active' : '' }}" data-section="ops" data-label="{{ translate('messages.deliveryman_section') }}" aria-label="{{ translate('messages.deliveryman_section') }}">
                <i data-lucide="bike"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($sidebar->sectionVisible('finance', 'v2'))
            <button class="v2-rail-btn {{ $sidebar->activeSection==='finance' ? 'is-active' : '' }}" data-section="finance" data-label="{{ translate('Wallet') }}" aria-label="{{ translate('Wallet') }}">
                <i data-lucide="wallet"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($sidebar->sectionVisible('team', 'v2'))
            <button class="v2-rail-btn {{ $sidebar->activeSection==='team' ? 'is-active' : '' }}" data-section="team" data-label="{{ translate('Employee section') }}" aria-label="{{ translate('Employee section') }}">
                <i data-lucide="users"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($sidebar->sectionVisible('reports', 'v2'))
            <button class="v2-rail-btn {{ $sidebar->activeSection==='reports' ? 'is-active' : '' }}" data-section="reports" data-label="{{ translate('Report section') }}" aria-label="{{ translate('Report section') }}">
                <i data-lucide="bar-chart-3"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($sidebar->sectionVisible('settings', 'v2'))
            <button class="v2-rail-btn {{ $sidebar->activeSection==='settings' ? 'is-active' : '' }}" data-section="settings" data-label="{{ translate('Business section') }}" aria-label="{{ translate('Business section') }}">
                <i data-lucide="settings-2"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($sidebar->sectionVisible('engagement', 'v2'))
            <button class="v2-rail-btn {{ $sidebar->activeSection==='engagement' ? 'is-active' : '' }}" data-section="engagement" data-label="{{ translate('Customer engagement') }}" aria-label="{{ translate('Customer engagement') }}">
                <i data-lucide="message-circle"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
        </div>
        <div class="v2-rail-bottom">
            <button class="v2-rail-btn v2-rail-profile" id="v2-rail-profile" aria-haspopup="menu" aria-expanded="false" aria-label="{{ $sidebar->user->f_name ?? 'Vendor' }}">
                <span class="v2-avatar">{{ strtoupper(substr($sidebar->user->f_name ?? 'V', 0, 1) . substr($sidebar->user->l_name ?? '', 0, 1)) }}</span>
            </button>
        </div>
    </div>

    <aside id="v2-panel" class="v2-panel" aria-label="{{ translate('Section navigation') }}">

        <div class="v2-panel-content" data-panel="dashboard" @if($sidebar->activeSection!=='dashboard') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ $sidebar->store->name ?? translate('Dashboard') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Live overview of your store') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::dashboard'])
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="dh-over"><span>{{ translate('Overview') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel') ? 'is-active' : '' }}" href="{{ route('vendor.dashboard') }}" data-id="dh-home">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Dashboard') }}</span>
                            <button type="button" class="v2-pin" data-pin="dh-home" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @if($sidebar->sectionVisible('sales', 'v2'))
        <div class="v2-panel-content" data-panel="sales" @if($sidebar->activeSection!=='sales') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Sales') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('POS and orders') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::sales'])

                @if($sidebar->can('pos'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="sl-pos"><span>{{ translate('messages.POS') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/pos*') ? 'is-active' : '' }}" href="{{ route('vendor.pos.index') }}" data-id="sl-pos">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.POS') }}</span>
                            <button type="button" class="v2-pin" data-pin="sl-pos" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if($sidebar->can('order'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="sl-ord"><span>{{ translate('messages.Orders') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/order/list/all') ? 'is-active' : '' }}" href="{{ route('vendor.order.list', ['all']) }}" data-id="ord-all">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('All') }}</span>
                            <span class="v2-count">{{ $count_all }}</span>
                            <button type="button" class="v2-pin" data-pin="ord-all" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/order/list/pending') ? 'is-active' : '' }}" href="{{ route('vendor.order.list', ['pending']) }}" data-id="ord-pen">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('Pending') }}</span>
                            <span class="v2-count">{{ $count_pending }}</span>
                            <button type="button" class="v2-pin" data-pin="ord-pen" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/order/list/confirmed') ? 'is-active' : '' }}" href="{{ route('vendor.order.list', ['confirmed']) }}" data-id="ord-con">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.confirmed') }}</span>
                            <span class="v2-count">{{ $count_confirmed }}</span>
                            <button type="button" class="v2-pin" data-pin="ord-con" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/order/list/cooking') ? 'is-active' : '' }}" href="{{ route('vendor.order.list', ['cooking']) }}" data-id="ord-proc">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ $sidebar->isFood ? translate('Cooking') : translate('Processing') }}</span>
                            <span class="v2-count">{{ $count_processing }}</span>
                            <button type="button" class="v2-pin" data-pin="ord-proc" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/order/list/ready_for_delivery') ? 'is-active' : '' }}" href="{{ route('vendor.order.list', ['ready_for_delivery']) }}" data-id="ord-rfd">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Ready for delivery') }}</span>
                            <span class="v2-count">{{ $count_handover }}</span>
                            <button type="button" class="v2-pin" data-pin="ord-rfd" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/order/list/item_on_the_way') ? 'is-active' : '' }}" href="{{ route('vendor.order.list', ['item_on_the_way']) }}" data-id="ord-otw">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Item on the way') }}</span>
                            <span class="v2-count">{{ $count_picked_up }}</span>
                            <button type="button" class="v2-pin" data-pin="ord-otw" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/order/list/delivered') ? 'is-active' : '' }}" href="{{ route('vendor.order.list', ['delivered']) }}" data-id="ord-del">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Delivered') }}</span>
                            <span class="v2-count">{{ $count_delivered }}</span>
                            <button type="button" class="v2-pin" data-pin="ord-del" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/order/list/refunded') ? 'is-active' : '' }}" href="{{ route('vendor.order.list', ['refunded']) }}" data-id="ord-ref">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('Refunded') }}</span>
                            <span class="v2-count">{{ $count_refunded }}</span>
                            <button type="button" class="v2-pin" data-pin="ord-ref" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/order/list/scheduled') ? 'is-active' : '' }}" href="{{ route('vendor.order.list', ['scheduled']) }}" data-id="ord-sch">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('Scheduled') }}</span>
                            <span class="v2-count">{{ $count_scheduled }}</span>
                            <button type="button" class="v2-pin" data-pin="ord-sch" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($sidebar->sectionVisible('catalog', 'v2'))
        <div class="v2-panel-content" data-panel="catalog" @if($sidebar->activeSection!=='catalog') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Catalog') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Items') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::catalog'])

                @if($sidebar->can('item'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="ct-itm"><span>{{ translate('messages.Items') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/item/add-new') ? 'is-active' : '' }}" href="{{ route('vendor.item.add-new') }}" data-id="it-add">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Add new') }}</span>
                            <button type="button" class="v2-pin" data-pin="it-add" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ ($sidebar->is('vendor-panel/item/list*') || $sidebar->is('vendor-panel/item/edit/*') || $sidebar->is('vendor-panel/item/view/*')) ? 'is-active' : '' }}" href="{{ route('vendor.item.list') }}" data-id="it-list">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.list') }}</span>
                            <button type="button" class="v2-pin" data-pin="it-list" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @if($sidebar->nav['product_approval'])
                        <a class="v2-nav-item {{ ($sidebar->is('vendor-panel/item/pending/item/list*') || $sidebar->is('vendor-panel/item/requested/item/view/*')) ? 'is-active' : '' }}" href="{{ route('vendor.item.pending_item_list') }}" data-id="it-pen">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('messages.pending_item_list') }}</span>
                            <button type="button" class="v2-pin" data-pin="it-pen" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($sidebar->nav['product_gallery'])
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/item/product-gallery*') ? 'is-active' : '' }}" href="{{ route('vendor.item.product_gallery') }}" data-id="it-gal">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Product gallery') }}</span>
                            <button type="button" class="v2-pin" data-pin="it-gal" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if(!$sidebar->isFood)
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/item/stock-limit-list*') ? 'is-active' : '' }}" href="{{ route('vendor.item.stock-limit-list') }}" data-id="it-low">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('Low stock list') }}</span>
                            <button type="button" class="v2-pin" data-pin="it-low" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($sidebar->nav['item_section'])
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/item/bulk-import*') ? 'is-active' : '' }}" href="{{ route('vendor.item.bulk-import') }}" data-id="it-imp">
                            <span class="v2-dot v2-dot--gray"></span><span class="v2-label">{{ translate('Bulk import') }}</span>
                        </a>
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/item/bulk-export*') ? 'is-active' : '' }}" href="{{ route('vendor.item.bulk-export-index') }}" data-id="it-exp">
                            <span class="v2-dot v2-dot--gray"></span><span class="v2-label">{{ translate('Bulk export') }}</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                @if($sidebar->can('addon'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="ct-add"><span>{{ translate('Addons') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/addon*') ? 'is-active' : '' }}" href="{{ route('vendor.addon.add-new') }}" data-id="ad-list">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Addons') }}</span>
                            <button type="button" class="v2-pin" data-pin="ad-list" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if($sidebar->can('category'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="ct-cat"><span>{{ translate('Categories') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/category/list*') ? 'is-active' : '' }}" href="{{ route('vendor.category.add') }}" data-id="cat-list">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Main category') }}</span>
                            <button type="button" class="v2-pin" data-pin="cat-list" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/category/sub-category-list*') ? 'is-active' : '' }}" href="{{ route('vendor.category.add-sub-category') }}" data-id="cat-sub">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Main subcategory') }}</span>
                            <button type="button" class="v2-pin" data-pin="cat-sub" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if($sidebar->can('my_category'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="ct-mycat"><span>{{ translate('My category') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/store-category*') ? 'is-active' : '' }}" href="{{ route('vendor.store-category.list') }}" data-id="ct-mycat">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('My category') }}</span>
                            <button type="button" class="v2-pin" data-pin="ct-mycat" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($sidebar->marketingVisible)
        <div class="v2-panel-content" data-panel="marketing" @if($sidebar->activeSection!=='marketing') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Marketing') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Campaigns, coupons, banners, ads, reels & flash sales') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::marketing'])

                @if($sidebar->can('flash_sale'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="mk-flash"><span>{{ translate('Flash sales') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/item/flash-sale*') ? 'is-active' : '' }}" href="{{ route('vendor.item.flash_sale') }}" data-id="mk-flash">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('Flash sales') }}</span>
                            <button type="button" class="v2-pin" data-pin="mk-flash" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if($sidebar->can('campaign'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="mk-camp"><span>{{ translate('messages.campaigns') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/campaign/list') ? 'is-active' : '' }}" href="{{ route('vendor.campaign.list') }}" data-id="mk-camp">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.Basic campaigns') }}</span>
                            <button type="button" class="v2-pin" data-pin="mk-camp" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/campaign/item/list*') ? 'is-active' : '' }}" href="{{ route('vendor.campaign.itemlist') }}" data-id="mk-camp-item">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Item campaigns') }}</span>
                            <button type="button" class="v2-pin" data-pin="mk-camp-item" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if($sidebar->can('coupon'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="mk-coup"><span>{{ translate('messages.coupons') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/coupon*') ? 'is-active' : '' }}" href="{{ route('vendor.coupon.add-new') }}" data-id="mk-coup">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.coupons') }}</span>
                            <button type="button" class="v2-pin" data-pin="mk-coup" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        {{-- Gated on the module type as well as the permission: neither feature exists
                             outside grocery, food, pharmacy and ecommerce, and the route guard 404s
                             there, so an entry would lead nowhere. --}}
                        @if(config('module.'.$sidebar->moduleType.'.promotions'))
                            <a class="v2-nav-item {{ $sidebar->is('vendor-panel/bogo-offer*') ? 'is-active' : '' }}" href="{{ route('vendor.bogo-offer.list') }}" data-id="mk-bogo">
                                <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('BOGO offer') }}</span>
                                <button type="button" class="v2-pin" data-pin="mk-bogo" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                            </a>
                            <a class="v2-nav-item {{ $sidebar->is('vendor-panel/happy-hour*') ? 'is-active' : '' }}" href="{{ route('vendor.happy-hour.list') }}" data-id="mk-hh">
                                <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('Happy hour') }}</span>
                                <button type="button" class="v2-pin" data-pin="mk-hh" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                            </a>
                        @endif
                        @if(\App\Support\Promotion\BundleSettings::allowsModuleType($sidebar->moduleType))
                            <a class="v2-nav-item {{ $sidebar->is('vendor-panel/bundle*') ? 'is-active' : '' }}" href="{{ route('vendor.bundle.list') }}" data-id="mk-bundle">
                                <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Bundle package') }}</span>
                                <button type="button" class="v2-pin" data-pin="mk-bundle" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                            </a>
                        @endif
                    </div>
                </div>
                @endif

                @if($sidebar->can('banner'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="mk-ban"><span>{{ translate('messages.banners') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/banner*') ? 'is-active' : '' }}" href="{{ route('vendor.banner.list') }}" data-id="mk-bn">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.banners') }}</span>
                            <button type="button" class="v2-pin" data-pin="mk-bn" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                {{-- One permission (advertisement_management) covers all three leaves,
                     so this stays a single L2 group. --}}
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="mk-ad-all"><span>{{ translate('Advertisement') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @if($sidebar->can('advertisement'))
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/advertisement/create*') ? 'is-active' : '' }}" href="{{ route('vendor.advertisement.create') }}" data-id="mk-adc">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('New advertisement') }}</span>
                            <button type="button" class="v2-pin" data-pin="mk-adc" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($sidebar->can('advertisement_list'))
                        <a class="v2-nav-item {{ $sidebar->adPendingActive() ? 'is-active' : '' }}" href="{{ route('vendor.advertisement.index', ['type' => 'pending']) }}" data-id="mk-adp">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('Ad requests') }}</span>
                            <button type="button" class="v2-pin" data-pin="mk-adp" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $sidebar->adListActive() ? 'is-active' : '' }}" href="{{ route('vendor.advertisement.index') }}" data-id="mk-adl">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.Ads list') }}</span>
                            <button type="button" class="v2-pin" data-pin="mk-adl" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>

                @if($sidebar->reelsEnabled)
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="mk-reels"><span>{{ translate('Reels') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $sidebar->reelsCreateActive() ? 'is-active' : '' }}" href="{{ route('vendor.reels.create') }}" data-id="mk-rlc">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('Create reels') }}</span>
                            <button type="button" class="v2-pin" data-pin="mk-rlc" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $sidebar->reelsListActive() ? 'is-active' : '' }}" href="{{ route('vendor.reels.index') }}" data-id="mk-rll">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Reels list') }}</span>
                            <button type="button" class="v2-pin" data-pin="mk-rll" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($sidebar->sectionVisible('ops', 'v2'))
        <div class="v2-panel-content" data-panel="ops" @if($sidebar->activeSection!=='ops') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('messages.deliveryman_section') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Add and manage deliverymen') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::ops'])

                {{-- One permission (deliveryman_management) covers both leaves, so this
                     stays a single L2 group. Each leaf keeps its own display condition. --}}
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="op-all"><span>{{ translate('messages.deliveryman_section') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @if($sidebar->can('deliveryman'))
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/delivery-man/add*') ? 'is-active' : '' }}" href="{{ route('vendor.delivery-man.add') }}" data-id="op-dma">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Add deliveryman') }}</span>
                            <button type="button" class="v2-pin" data-pin="op-dma" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($sidebar->can('deliveryman_list'))
                        <a class="v2-nav-item {{ ($sidebar->is('vendor-panel/delivery-man/list*') || $sidebar->is('vendor-panel/delivery-man/edit/*') || $sidebar->is('vendor-panel/delivery-man/preview/*')) ? 'is-active' : '' }}" href="{{ route('vendor.delivery-man.list') }}" data-id="op-dml">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.deliverymen_list') }}</span>
                            <button type="button" class="v2-pin" data-pin="op-dml" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($sidebar->sectionVisible('finance', 'v2'))
        <div class="v2-panel-content" data-panel="finance" @if($sidebar->activeSection!=='finance') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Wallet') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Wallet balance, transactions and disbursement methods') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::finance'])

                {{-- One permission (wallet_management) covers both leaves, so this
                     stays a single L2 group. Each leaf keeps its own display condition. --}}
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="fn-all"><span>{{ translate('Wallet') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @if($sidebar->can('wallet'))
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/wallet') ? 'is-active' : '' }}" href="{{ route('vendor.wallet.index') }}" data-id="fn-wal">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.My wallet') }}</span>
                            <button type="button" class="v2-pin" data-pin="fn-wal" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($sidebar->can('wallet_method'))
                        <a class="v2-nav-item {{ ($sidebar->is('vendor-panel/withdraw-method*') || $sidebar->is('vendor-panel/wallet-method*')) ? 'is-active' : '' }}" href="{{ route('vendor.wallet-method.index') }}" data-id="fn-wmt">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.Disbursement method') }}</span>
                            <button type="button" class="v2-pin" data-pin="fn-wmt" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($sidebar->sectionVisible('team', 'v2'))
        <div class="v2-panel-content" data-panel="team" @if($sidebar->activeSection!=='team') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Employee section') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Roles and employees with permissions') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::team'])

                @if($sidebar->can('role'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="tm-roles"><span>{{ translate('messages.Employee role') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ ($sidebar->is('vendor-panel/custom-role') || $sidebar->is('vendor-panel/custom-role/edit/*')) ? 'is-active' : '' }}" href="{{ route('vendor.custom-role.index') }}" data-id="tm-role">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Role list') }}</span>
                            <button type="button" class="v2-pin" data-pin="tm-role" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/custom-role/create') ? 'is-active' : '' }}" href="{{ route('vendor.custom-role.create') }}" data-id="tm-role-add">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Add new role') }}</span>
                            <button type="button" class="v2-pin" data-pin="tm-role-add" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if($sidebar->can('employee'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="tm-mem"><span>{{ translate('Employees') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ ($sidebar->is('vendor-panel/employee/list*') || $sidebar->is('vendor-panel/employee/edit/*')) ? 'is-active' : '' }}" href="{{ route('vendor.employee.list') }}" data-id="tm-empl">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.Employee list') }}</span>
                            <button type="button" class="v2-pin" data-pin="tm-empl" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/employee/add-new*') ? 'is-active' : '' }}" href="{{ route('vendor.employee.add-new') }}" data-id="tm-empa">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.Add new employee') }}</span>
                            <button type="button" class="v2-pin" data-pin="tm-empa" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($sidebar->sectionVisible('reports', 'v2'))
        <div class="v2-panel-content" data-panel="reports" @if($sidebar->activeSection!=='reports') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Report section') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Earnings, expenses, disbursements and tax') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::reports'])

                {{-- One permission (report_section) covers all four leaves, so this
                     stays a single L2 group. Each leaf keeps its own display condition. --}}
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rp-all"><span>{{ translate('Report section') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @if($sidebar->can('expense_report'))
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/report/expense-report*') ? 'is-active' : '' }}" href="{{ route('vendor.report.expense-report') }}" data-id="rp-exp">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('Expense report') }}</span>
                            <button type="button" class="v2-pin" data-pin="rp-exp" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($sidebar->can('store_earning_report'))
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/report/store-earning-report*') ? 'is-active' : '' }}" href="{{ route('vendor.report.store-earning-report') }}" data-id="rp-ern">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Store earning report') }}</span>
                            <button type="button" class="v2-pin" data-pin="rp-ern" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($sidebar->can('disbursement_report'))
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/report/disbursement-report*') ? 'is-active' : '' }}" href="{{ route('vendor.report.disbursement-report') }}" data-id="rp-dis">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Disbursement report') }}</span>
                            <button type="button" class="v2-pin" data-pin="rp-dis" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($sidebar->can('vat_report'))
                        <a class="v2-nav-item {{ ($sidebar->is('vendor-panel/report/vendor-tax*') || $sidebar->is('vendor-panel/report/vendorTax*') || $sidebar->is('vendor-panel/report/tax*')) ? 'is-active' : '' }}" href="{{ route('vendor.report.vendorTax') }}" data-id="rp-vat">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('VAT report') }}</span>
                            <button type="button" class="v2-pin" data-pin="rp-vat" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($sidebar->sectionVisible('settings', 'v2'))
        <div class="v2-panel-content" data-panel="settings" @if($sidebar->activeSection!=='settings') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Business section') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Store profile, notifications and subscription') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::settings'])

                {{-- One permission (business_section) covers all four leaves, so this
                     stays a single L2 group. Each leaf keeps its own display condition. --}}
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="st-all"><span>{{ translate('Business section') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @if($sidebar->can('my_shop'))
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/store/*') ? 'is-active' : '' }}" href="{{ route('vendor.shop.view') }}" data-id="st-shop">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.My shop') }}</span>
                            <button type="button" class="v2-pin" data-pin="st-shop" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($sidebar->can('store_setup'))
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/business-settings/store-setup*') ? 'is-active' : '' }}" href="{{ route('vendor.business-settings.store-setup') }}" data-id="st-cfg">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Store setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="st-cfg" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($sidebar->can('notification_setup'))
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/business-settings/notification-setup*') ? 'is-active' : '' }}" href="{{ route('vendor.business-settings.notification-setup') }}" data-id="st-not">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Notification setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="st-not" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($sidebar->can('business_plan'))
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/subscription*') ? 'is-active' : '' }}" href="{{ route('vendor.subscriptionackage.subscriberDetail') }}" data-id="st-sub">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('My business plan') }}</span>
                            <button type="button" class="v2-pin" data-pin="st-sub" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($sidebar->sectionVisible('engagement', 'v2'))
        <div class="v2-panel-content" data-panel="engagement" @if($sidebar->activeSection!=='engagement') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Customer engagement') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Customer reviews and conversations') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::engagement'])

                {{-- One permission (customer_engagement) covers both leaves, so this
                     stays a single L2 group. Each leaf keeps its own display condition. --}}
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="ce-all"><span>{{ translate('Customer engagement') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @if($sidebar->can('reviews'))
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/reviews*') ? 'is-active' : '' }}" href="{{ route('vendor.reviews') }}" data-id="ce-rev">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('messages.Reviews') }}</span>
                            <button type="button" class="v2-pin" data-pin="ce-rev" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($sidebar->can('chat'))
                        <a class="v2-nav-item {{ $sidebar->is('vendor-panel/message*') ? 'is-active' : '' }}" href="{{ route('vendor.message.list') }}" data-id="ce-chat">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.Chat') }}</span>
                            <button type="button" class="v2-pin" data-pin="ce-chat" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif
    </aside>
</aside>

@include('layouts.vendor.partials._v2_profile_pop')
@include('layouts.admin.partials._v2_sidebar_script')
