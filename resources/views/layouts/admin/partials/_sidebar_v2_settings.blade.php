@php
    use App\CentralLogics\Helpers;

    $req = request()->path();
    $is = function($pat) use ($req) { return \Illuminate\Support\Str::is($pat, $req); };
    $admin_user = auth('admin')->user();

    $can_settings = Helpers::module_permission_check('settings');
    $can_zone     = Helpers::module_permission_check('settings');
    $can_module   = Helpers::module_permission_check('module');
    $can_sub      = Helpers::module_permission_check('subscription');
    $can_pro      = Helpers::module_permission_check('pro_customer_subscription');
    $can_customer = Helpers::module_permission_check('customer_management');
    $can_sys_tax  = Helpers::module_permission_check('system_tax');
    $can_pages    = (Helpers::module_permission_check('social_media') || Helpers::module_permission_check('landing_pages') || Helpers::module_permission_check('business_pages') || Helpers::module_permission_check('seo'));
    $can_sys_cfg  = Helpers::module_permission_check('system_config');
    $can_apps     = Helpers::module_permission_check('system_config');
    $can_sys_addons = Helpers::module_permission_check('system_config');
    $can_login    = Helpers::module_permission_check('login_setup');
    $can_email    = (Helpers::module_permission_check('email_setups') || Helpers::module_permission_check('notification_setup'));
    $can_3rd_party = Helpers::module_permission_check('third_party-ms');
    $can_ride_settings = Helpers::module_permission_check('ride_settings');
    $can_service_mgmt = Helpers::module_permission_check('service_settings');
    $can_gallery  = Helpers::module_permission_check('gallery');
    $can_clean_db = Helpers::module_permission_check('clean_database');
    $can_db_backup = Helpers::module_permission_check('database_backup');
    $can_maint    = ($can_clean_db || $can_db_backup);
    $rental_on    = addon_published_status('Rental');
    $ride_on      = addon_published_status('RideShare');
    $service_on   = addon_published_status('Service');
    $tax_on       = addon_published_status('TaxModule');
    // Weight and Dimension Setup only mean something when something is being parcelled.
    // Capability-driven, never a module-name comparison — see ModuleService::hasParcelCapability().
    $parcel_on    = app(\App\Services\System\ModuleService::class)->hasParcelCapability();

    $active_section = 'biz';
    // Delivery Management is its own section, not a group under Business Setup. The URLs
    // still live beneath /zone/ because they share its permission, so this branch runs
    // first — otherwise the generic zone check below would claim them for 'biz'.
    if ($is(['admin/delivery-management/delivery-rule*', 'admin/delivery-management/area*', 'admin/delivery-management/zip-code*', 'admin/delivery-management/weight*', 'admin/delivery-management/dimension*', 'admin/delivery-management/free-delivery*', 'admin/delivery-management/eta-configuration*', 'admin/delivery-management/surge-price*', 'admin/delivery-management/vehicle-category*', 'admin/delivery-management/additional-delivery-charge*', 'admin/business-settings/zone/module-setup*'])) $active_section = 'delivery';
    elseif ($is('admin/business-settings/module*'))                   $active_section = 'mods';
    elseif ($is('admin/business-settings/subscription*') || $is('admin/pro-customer*'))         $active_section = 'subs';
    elseif ($is('taxvat/*'))                                      $active_section = 'fin';
    elseif ($is('admin/business-settings/pages/*') || $is('admin/business-settings/seo-settings*')) $active_section = 'pages';
    elseif ($is('admin/business-settings/file-manager*'))         $active_section = 'media';
    elseif ($is('admin/business-settings/login-settings*') || $is('admin/business-settings/login-url-setup*'))       $active_section = 'auth';
    elseif ($is('admin/business-settings/email-setup*') || $is('admin/business-settings/rental-email-setup*') || $is('admin/business-settings/service-email-setup*') || $is('admin/business-settings/notification-setup*') || $is('admin/business-settings/fcm*')) $active_section = 'comm';
    elseif ($is('admin/business-settings/third-party*') || $is('admin/business-settings/offline-payment*') || $is('admin/business-settings/marketing*') || $is('admin/business-settings/open-ai*') || $is('admin/payment/configuration*') || $is('admin/sms/configuration*')) $active_section = 'int';
    elseif ($is('admin/business-settings/safety-precaution*') || $is('admin/business-settings/ride-fare*') || $is('admin/business-settings/ride-share*')) $active_section = 'safety';
    elseif ($is('admin/business-settings/service*'))             $active_section = 'service';
    elseif ($is('admin/business-settings/db-index*') || $is('admin/business-settings/database/*')) $active_section = 'maint';
    elseif ($is('admin/business-settings/language*') || $is('admin/business-settings/app-settings*') || $is('admin/business-settings/websocket*') || $is('admin/business-settings/addon-activation*') || $is('admin/business-settings/system-addon*')) $active_section = 'sys';
@endphp

<aside id="v2-shell" class="v2-shell" data-workspace="settings" data-active-section="{{ $active_section }}">
    <div id="v2-rail" class="v2-rail" role="navigation" aria-label="Sections">
        <div class="v2-rail-scope d-none">SETTINGS</div>
        <div class="v2-rail-btns">
            @if($can_settings || $can_zone)
            <button class="v2-rail-btn {{ $active_section==='biz' ? 'is-active' : '' }}" data-section="biz" data-label="{{ translate('Business setup') }}" aria-label="{{ translate('Business setup') }}">
                <i data-lucide="briefcase"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_zone)
            <button class="v2-rail-btn {{ $active_section==='delivery' ? 'is-active' : '' }}" data-section="delivery" data-label="{{ translate('Delivery management') }}" aria-label="{{ translate('Delivery management') }}">
                <i data-lucide="truck"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_module)
            <button class="v2-rail-btn {{ $active_section==='mods' ? 'is-active' : '' }}" data-section="mods" data-label="{{ translate('Business modules') }}" aria-label="{{ translate('Business modules') }}">
                <i data-lucide="boxes"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_sub || $can_pro || $can_customer)
            <button class="v2-rail-btn {{ $active_section==='subs' ? 'is-active' : '' }}" data-section="subs" data-label="{{ translate('Subscription management') }}" aria-label="{{ translate('Subscription management') }}">
                <i data-lucide="credit-card"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_sys_tax && $tax_on)
            <button class="v2-rail-btn {{ $active_section==='fin' ? 'is-active' : '' }}" data-section="fin" data-label="{{ translate('Finance & tax') }}" aria-label="{{ translate('Finance & tax') }}">
                <i data-lucide="receipt"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_pages)
            <button class="v2-rail-btn {{ $active_section==='pages' ? 'is-active' : '' }}" data-section="pages" data-label="{{ translate('Website, pages & content') }}" aria-label="{{ translate('Website, pages & content') }}">
                <i data-lucide="file-text"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_sys_cfg || $can_apps || $can_sys_addons)
            <button class="v2-rail-btn {{ $active_section==='sys' ? 'is-active' : '' }}" data-section="sys" data-label="{{ translate('System configuration') }}" aria-label="{{ translate('System configuration') }}">
                <i data-lucide="cog"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_login)
            <button class="v2-rail-btn {{ $active_section==='auth' ? 'is-active' : '' }}" data-section="auth" data-label="{{ translate('Authentication & access') }}" aria-label="{{ translate('Authentication & access') }}">
                <i data-lucide="lock"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_email)
            <button class="v2-rail-btn {{ $active_section==='comm' ? 'is-active' : '' }}" data-section="comm" data-label="{{ translate('Communication setup') }}" aria-label="{{ translate('Communication setup') }}">
                <i data-lucide="mail"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_3rd_party)
            <button class="v2-rail-btn {{ $active_section==='int' ? 'is-active' : '' }}" data-section="int" data-label="{{ translate('Integrations & Third-Party') }}" aria-label="{{ translate('Integrations & Third-Party') }}">
                <i data-lucide="plug"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($ride_on && $can_ride_settings)
            <button class="v2-rail-btn {{ $active_section==='safety' ? 'is-active' : '' }}" data-section="safety" data-label="{{ translate('Ride share settings') }}" aria-label="{{ translate('Ride share settings') }}">
                <i data-lucide="car-front"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($service_on && $can_service_mgmt)
            <button class="v2-rail-btn {{ $active_section==='service' ? 'is-active' : '' }}" data-section="service" data-label="{{ translate('Service module settings') }}" aria-label="{{ translate('Service module settings') }}">
                <i data-lucide="wrench"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_gallery)
            <button class="v2-rail-btn {{ $active_section==='media' ? 'is-active' : '' }}" data-section="media" data-label="{{ translate('Media & file management') }}" aria-label="{{ translate('Media & file management') }}">
                <i data-lucide="image"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_maint)
            <button class="v2-rail-btn {{ $active_section==='maint' ? 'is-active' : '' }}" data-section="maint" data-label="{{ translate('Maintenance & database') }}" aria-label="{{ translate('Maintenance & database') }}">
                <i data-lucide="database"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
        </div>
        <div class="v2-rail-bottom">
            <button class="v2-rail-btn v2-rail-profile" id="v2-rail-profile" aria-haspopup="menu" aria-expanded="false" aria-label="{{ $admin_user->f_name ?? 'Admin' }}">
                <span class="v2-avatar">{{ strtoupper(substr($admin_user->f_name ?? 'A', 0, 1) . substr($admin_user->l_name ?? '', 0, 1)) }}</span>
            </button>
        </div>
    </div>

    <aside id="v2-panel" class="v2-panel" aria-label="{{ translate('Section navigation') }}">
        @if($can_settings || $can_zone)
        <div class="v2-panel-content" data-panel="biz" @if($active_section!=='biz') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Business setup') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Core business configuration and zones') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::biz'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        @if($can_settings)
                        <a class="v2-nav-item {{ $is('admin/business-settings/business-setup*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.business-setup') }}" data-id="biz-info">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Business settings') }}</span>
                            <button type="button" class="v2-pin" data-pin="biz-info" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_zone)
                        {{-- Delivery Management moved to its own prefix, so `zone*` no longer
                             reaches it and the nine sub-path exclusions that used to sit here are
                             gone. Module Setup is the one that still needs excluding: it lives
                             under /zone/ but belongs to the Delivery section above. --}}
                        <a class="v2-nav-item {{ $is('admin/business-settings/zone*') && !$is('admin/business-settings/zone/module-setup*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.zone.home') }}" data-id="biz-zone">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Zone setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="biz-zone" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>

            </div>
        </div>
        @endif

        @if($can_module)
        {{--
            Delivery Management — its own section, matching the design: a rail entry and a panel
            of its own, NOT a group inside Business Setup. The routes still sit beneath /zone/
            because they share its permission, which is why $active_section tests them first.

            Weight and Dimension Setup are live — their CRUD settings ship ahead of the parcel
            pricing that consumes them — and appear only where a parcel-capable module is active.
            Vehicle Category is still absent rather than disabled: it is deferred with the rest of
            the parcel tier, and a menu entry that leads nowhere is worse than one that is not
            there yet.
        --}}
        <div class="v2-panel-content" data-panel="delivery" @if($active_section!=='delivery') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Delivery management') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Manage deliveries and track delivery performance') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::delivery'])
                @if($can_zone)
                <div class="v2-group">
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/delivery-management/delivery-rule*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.zone.delivery-rule.list') }}" data-id="dm-rule">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Delivery rule setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="dm-rule" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/delivery-management/area*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.zone.area.list') }}" data-id="dm-area">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Area setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="dm-area" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/delivery-management/zip-code*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.zone.zip-code.list') }}" data-id="dm-zip">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Zip code setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="dm-zip" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @if($parcel_on)
                        {{-- Weight before Dimension, matching the delivery-rule wizard's own step
                             order (General Information -> Weight Rules -> Dimension Rules). --}}
                        <a class="v2-nav-item {{ $is('admin/delivery-management/weight*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.zone.weight.list') }}" data-id="dm-weight">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Weight setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="dm-weight" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/delivery-management/dimension*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.zone.dimension.list') }}" data-id="dm-dimension">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Dimension setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="dm-dimension" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        {{-- Vehicles Category — moved out of the Users section on 2026-09-08. It
                             is a delivery SETUP, not a person, so it belongs beside the rules that
                             read it. --}}
                        <a class="v2-nav-item {{ $is('admin/delivery-management/vehicle-category*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.zone.vehicle-category.list') }}" data-id="dm-vehicle">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Vehicles category') }}</span>
                            <button type="button" class="v2-pin" data-pin="dm-vehicle" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/delivery-management/free-delivery*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.zone.free-delivery.list') }}" data-id="dm-free">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Free delivery setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="dm-free" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/delivery-management/eta-configuration*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.zone.eta-configuration.list') }}" data-id="dm-eta">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('ETA configuration') }}</span>
                            <button type="button" class="v2-pin" data-pin="dm-eta" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/delivery-management/surge-price*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.zone.surge-price.list') }}" data-id="dm-surge">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Surge price setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="dm-surge" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/delivery-management/additional-delivery-charge*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.zone.additional-delivery-charge.list') }}" data-id="dm-charge">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Additional charge') }}</span>
                            <button type="button" class="v2-pin" data-pin="dm-charge" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <div class="v2-panel-content" data-panel="mods" @if($active_section!=='mods') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Business modules') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Module creation and management') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::mods'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/business-settings/module/store*') || $is('admin/business-settings/module/create*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.module.create') }}" data-id="mod-add">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Add new module') }}</span>
                            <button type="button" class="v2-pin" data-pin="mod-add" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ ($is('admin/business-settings/module') || $is('admin/business-settings/module/edit/*')) ? 'is-active' : '' }}" href="{{ route('admin.business-settings.module.index') }}" data-id="mod-list">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Manage modules') }}</span>
                            <button type="button" class="v2-pin" data-pin="mod-list" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can_sub || $can_pro || $can_customer)
        <div class="v2-panel-content" data-panel="subs" @if($active_section!=='subs') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Subscription management') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Subscription packages, subscribers, and settings') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::subs'])
                @if($can_sub)
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="sub-vendor"><span>{{ translate('Vendor subscription') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @if($can_sub)
                        <a class="v2-nav-item {{ $is('admin/business-settings/subscription/subscriptionackage*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.subscriptionackage.index') }}" data-id="sub-pkg">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Subscription packages') }}</span>
                            <button type="button" class="v2-pin" data-pin="sub-pkg" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/business-settings/subscription/subscriber*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.subscriptionackage.subscriberList') }}" data-id="sub-list">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Subscribers') }}</span>
                            <button type="button" class="v2-pin" data-pin="sub-list" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_sub)
                        <a class="v2-nav-item {{ $is('admin/business-settings/subscription/settings*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.subscriptionackage.settings') }}" data-id="sub-set">
                            <span class="v2-dot v2-dot--gray"></span><span class="v2-label">{{ translate('Subscription settings') }}</span>
                            <button type="button" class="v2-pin" data-pin="sub-set" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                @if (Helpers::get_business_settings('pro_member_status') == 1 && ($can_pro || $can_customer))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="sub-pro"><span>{{ translate('Pro customer management') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @if ($can_customer)
                        <a class="v2-nav-item {{ $is('admin/pro-customer/list*') ? 'is-active' : '' }}" href="{{ route('admin.pro-customer.list') }}" data-id="pro-list">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Pro customer list') }}</span>
                            <button type="button" class="v2-pin" data-pin="pro-list" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if ($can_pro)
                        <a class="v2-nav-item {{ $is('admin/pro-customer/benefits-setup*') ? 'is-active' : '' }}" href="{{ route('admin.pro-customer.benefits-setup') }}" data-id="pro-ben">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Pro customer benefits setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="pro-ben" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/pro-customer/price-setup*') ? 'is-active' : '' }}" href="{{ route('admin.pro-customer.price-setup') }}" data-id="pro-price">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('Price setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="pro-price" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/pro-customer/additional-setup*') ? 'is-active' : '' }}" href="{{ route('admin.pro-customer.additional-setup') }}" data-id="pro-add">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Additional setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="pro-add" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/pro-customer/transactions*') ? 'is-active' : '' }}" href="{{ route('admin.pro-customer.transactions') }}" data-id="pro-tx">
                            <span class="v2-dot v2-dot--gray"></span><span class="v2-label">{{ translate('messages.Transactions') }}</span>
                            <button type="button" class="v2-pin" data-pin="pro-tx" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($can_sys_tax && $tax_on)
        <div class="v2-panel-content" data-panel="fin" @if($active_section!=='fin') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Finance & tax') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Charges, penalties, and financial configurations') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::fin'])
                @if($tax_on)
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="fin-tax"><span>{{ translate('Tax configuration') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ \Illuminate\Support\Str::is(['taxvat/get-taxvat-data*', 'taxvat/add-taxvat-data*', 'taxvat/update-taxvat-data*', 'taxvat/export-taxvat*'], $req) ? 'is-active' : '' }}" href="{{ route('taxvat.index') }}" data-id="tax-create">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('Create taxes') }}</span>
                            <button type="button" class="v2-pin" data-pin="tax-create" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('taxvat/system-taxvat*') ? 'is-active' : '' }}" href="{{ route('taxvat.systemTaxvat', ['type' => 'vendor']) }}" data-id="tax-setup">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('Setup taxes') }}</span>
                            <button type="button" class="v2-pin" data-pin="tax-setup" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($can_pages)
        <div class="v2-panel-content" data-panel="pages" @if($active_section!=='pages') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Website, pages & content') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Public-facing pages, policies, and branding') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::pages'])

                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="pg-soc"><span>{{ translate('Social & branding') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/business-settings/pages/social-media*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.social-media.index') }}" data-id="pg-soc-link">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Social media links') }}</span>
                            <button type="button" class="v2-pin" data-pin="pg-soc-link" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>

                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="pg-land"><span>{{ translate('Landing pages') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/business-settings/pages/admin-landing-page-settings*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.admin-landing-page-settings', 'setup') }}" data-id="pg-adm">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Admin landing page') }}</span>
                            <button type="button" class="v2-pin" data-pin="pg-adm" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/business-settings/pages/react-landing-page-settings*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.react-landing-page-settings', 'header') }}" data-id="pg-rea">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('React landing page') }}</span>
                            <button type="button" class="v2-pin" data-pin="pg-rea" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @if (addon_published_status('RideShare') == 1)
                        <a class="v2-nav-item {{ $is('admin/business-settings/pages/react-ride-share-page-settings*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.react-ride-share-page-settings', 'hero') }}" data-id="pg-rea-ride">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.React ride share page') }}</span>
                            <button type="button" class="v2-pin" data-pin="pg-rea-ride" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>

                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="pg-leg"><span>{{ translate('Business pages') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/business-settings/pages/business-page/terms-and-conditions*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.terms-and-conditions') }}" data-id="bp-tc">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Terms & conditions') }}</span>
                            <button type="button" class="v2-pin" data-pin="bp-tc" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/business-settings/pages/business-page/privacy-policy*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.privacy-policy') }}" data-id="bp-pp">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Privacy policy') }}</span>
                            <button type="button" class="v2-pin" data-pin="bp-pp" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/business-settings/pages/business-page/about-us*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.about-us') }}" data-id="bp-ab">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('About us') }}</span>
                            <button type="button" class="v2-pin" data-pin="bp-ab" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/business-settings/pages/business-page/refund*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.refund') }}" data-id="bp-rf">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Refund policy') }}</span>
                            <button type="button" class="v2-pin" data-pin="bp-rf" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/business-settings/pages/business-page/cancelation*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.cancelation') }}" data-id="bp-cn">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Cancellation policy') }}</span>
                            <button type="button" class="v2-pin" data-pin="bp-cn" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/business-settings/pages/business-page/shipping-policy*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.shipping-policy') }}" data-id="bp-sh">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('Shipping policy') }}</span>
                            <button type="button" class="v2-pin" data-pin="bp-sh" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>

                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="pg-seo"><span>{{ translate('SEO & metadata') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/business-settings/seo-settings*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.seo-settings.pageMetaData') }}" data-id="pg-meta">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('Page meta data (SEO)') }}</span>
                            <button type="button" class="v2-pin" data-pin="pg-meta" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can_sys_cfg || $can_apps || $can_sys_addons)
        <div class="v2-panel-content" data-panel="sys" @if($active_section!=='sys') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('System configuration') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Platform-wide technical settings') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::sys'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        @if($can_sys_cfg)
                        <a class="v2-nav-item {{ $is('admin/business-settings/language*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.language.index') }}" data-id="sys-lang">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Language management') }}</span>
                            <button type="button" class="v2-pin" data-pin="sys-lang" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_apps)
                        <a class="v2-nav-item {{ $is('admin/business-settings/app-settings*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.app-settings') }}" data-id="sys-app">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('App settings') }}</span>
                            <button type="button" class="v2-pin" data-pin="sys-app" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_sys_cfg)
                        <a class="v2-nav-item {{ $is('admin/business-settings/websocket*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.websocket') }}" data-id="sys-ws">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('WebSocket configuration') }}</span>
                            <button type="button" class="v2-pin" data-pin="sys-ws" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/business-settings/addon-activation*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.addon-activation.index') }}" data-id="sys-add">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('Add-on activation') }}</span>
                            <button type="button" class="v2-pin" data-pin="sys-add" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_sys_addons)
                        <a class="v2-nav-item {{ $is('admin/business-settings/system-addon*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.system-addon.index') }}" data-id="sys-sa">
                            <span class="v2-dot v2-dot--gray"></span><span class="v2-label">{{ translate('System addons') }}</span>
                            <button type="button" class="v2-pin" data-pin="sys-sa" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can_login)
        <div class="v2-panel-content" data-panel="auth" @if($active_section!=='auth') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Authentication & access') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Login systems and access settings') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::auth'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ ($is('admin/business-settings/login-settings*') || $is('admin/business-settings/login-url-setup*')) ? 'is-active' : '' }}" href="{{ route('admin.business-settings.login-settings.index') }}" data-id="auth-login">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Login & authentication setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="auth-login" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can_email)
        <div class="v2-panel-content" data-panel="comm" @if($active_section!=='comm') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Communication setup') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Email, notifications, and push') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::comm'])

                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="comm-email"><span>{{ translate('Email configuration') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/business-settings/email-setup*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.email-setup', ['admin', 'forgot-password']) }}" data-id="em-all">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('All modules email setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="em-all" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @if($rental_on)
                        <a class="v2-nav-item {{ $is('admin/business-settings/rental-email-setup*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.rental-email-setup', ['admin', 'provider-registration']) }}" data-id="em-ren">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Rental module email setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="em-ren" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($service_on)
                        <a class="v2-nav-item {{ $is('admin/business-settings/service-email-setup*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.service-email-setup', ['admin', 'provider-registration']) }}" data-id="em-serv">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Service module email setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="em-serv" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>

                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="comm-notif"><span>{{ translate('Notifications') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ ($is('admin/business-settings/notification-setup*') && !str_contains(request()->fullUrl(), 'module=rental') && !str_contains(request()->fullUrl(), 'module=service')) ? 'is-active' : '' }}" href="{{ route('admin.business-settings.notification_setup') }}" data-id="sn-all">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('All modules notifications') }}</span>
                            <button type="button" class="v2-pin" data-pin="sn-all" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @if($rental_on)
                        <a class="v2-nav-item {{ ($is('admin/business-settings/notification-setup*') && str_contains(request()->fullUrl(), 'module=rental')) ? 'is-active' : '' }}" href="{{ route('admin.business-settings.notification_setup', ['module' => 'rental']) }}" data-id="sn-rental">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Rental module notifications') }}</span>
                            <button type="button" class="v2-pin" data-pin="sn-rental" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($service_on)
                        <a class="v2-nav-item {{ ($is('admin/business-settings/notification-setup*') && str_contains(request()->fullUrl(), 'module=service')) ? 'is-active' : '' }}" href="{{ route('admin.business-settings.notification_setup', ['module' => 'service']) }}" data-id="sn-service">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Service module notifications') }}</span>
                            <button type="button" class="v2-pin" data-pin="sn-service" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        <a class="v2-nav-item {{ $is('admin/business-settings/fcm*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.fcm-index') }}" data-id="fcm">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('Firebase notifications') }}</span>
                            <button type="button" class="v2-pin" data-pin="fcm" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can_3rd_party)
        <div class="v2-panel-content" data-panel="int" @if($active_section!=='int') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Integrations & Third-Party') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('External tools, payment, AI, and analytics') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::int'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        @php
                            $int_third_party_active = $is('admin/business-settings/third-party/sms-module*')
                                || $is('admin/business-settings/third-party/mail-config*')
                                || $is('admin/business-settings/third-party/test-mail*')
                                || $is('admin/business-settings/third-party/config-setup*')
                                || $is('admin/business-settings/third-party/social-login*')
                                || $is('admin/business-settings/third-party/recaptcha*')
                                || $is('admin/business-settings/third-party/firebase-otp*')
                                || $is('admin/business-settings/third-party/storage-connection*');
                            $addon_admin_routes = collect(config('addon_admin_routes'))->collapse();
                        @endphp
                        <a class="v2-nav-item {{ $int_third_party_active ? 'is-active' : '' }}" href="{{ route('admin.business-settings.third-party.sms-module') }}" data-id="int-sms">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Third-party & external services') }}</span>
                            <button type="button" class="v2-pin" data-pin="int-sms" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ ($is('admin/business-settings/third-party/payment-method*') || $is('admin/business-settings/offline-payment*')) ? 'is-active' : '' }}" href="{{ route('admin.business-settings.third-party.payment-method') }}" data-id="int-pay">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Payment methods') }}</span>
                            <button type="button" class="v2-pin" data-pin="int-pay" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/business-settings/marketing*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.marketing.analytic') }}" data-id="int-an">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('Analytics & tracking scripts') }}</span>
                            <button type="button" class="v2-pin" data-pin="int-an" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @if(Route::has('admin.business-settings.openAI'))
                        <a class="v2-nav-item {{ $is('admin/business-settings/open-ai*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.openAI') }}" data-id="int-ai">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('AI configuration') }}</span>
                            <button type="button" class="v2-pin" data-pin="int-ai" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
                @if($addon_admin_routes->isNotEmpty())
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="int-addon"><span>{{ translate('Addon menu') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @foreach($addon_admin_routes->where('name', 'sms_setup') as $sms_setup_route)
                        <a class="v2-nav-item {{ $is('admin/sms/configuration*') ? 'is-active' : '' }}" href="{{ $sms_setup_route['url'] }}" data-id="int-addon-sms">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('SMS setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="int-addon-sms" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endforeach
                        @foreach($addon_admin_routes->where('name', 'payment_setup') as $payment_setup_route)
                        <a class="v2-nav-item {{ $is('admin/payment/configuration*') ? 'is-active' : '' }}" href="{{ $payment_setup_route['url'] }}" data-id="int-addon-pay">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Payment setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="int-addon-pay" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($ride_on && $can_ride_settings)
        <div class="v2-panel-content" data-panel="safety" @if($active_section!=='safety') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Ride share settings') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Fare, penalties, and safety configurations') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::safety'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ ($is('admin/business-settings/ride-fare*') || $is('admin/business-settings/ride-share*')) ? 'is-active' : '' }}" href="{{ route('admin.business-settings.ride-fare.penalty') }}" data-id="fin-fare">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('Ride fare penalty & charges') }}</span>
                            <button type="button" class="v2-pin" data-pin="fin-fare" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @if(Route::has('admin.business-settings.safety-precaution.index') && defined('SAFETY_ALERT'))
                        <a class="v2-nav-item {{ $is('admin/business-settings/safety-precaution*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.safety-precaution.index', SAFETY_ALERT) }}" data-id="safety-alerts">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('Safety alerts & precautions') }}</span>
                            <button type="button" class="v2-pin" data-pin="safety-alerts" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($service_on && $can_service_mgmt)
        <div class="v2-panel-content" data-panel="service" @if($active_section!=='service') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Service module settings') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Booking, provider and serviceman configurations') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::service'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/business-settings/service/booking*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.service.booking') }}" data-id="svc-booking">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Bookings') }}</span>
                            <button type="button" class="v2-pin" data-pin="svc-booking" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/business-settings/service/provider-serviceman*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.service.provider-serviceman') }}" data-id="svc-provider">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Providers & servicemen') }}</span>
                            <button type="button" class="v2-pin" data-pin="svc-provider" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can_gallery)
        <div class="v2-panel-content" data-panel="media" @if($active_section!=='media') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Media & file management') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Files, assets, and gallery') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::media'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/business-settings/file-manager*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.file-manager.index') }}" data-id="media-gal">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('Gallery / file manager') }}</span>
                            <button type="button" class="v2-pin" data-pin="media-gal" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can_maint)
        <div class="v2-panel-content" data-panel="maint" @if($active_section!=='maint') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Maintenance & database') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('System cleanup and maintenance') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'settings::maint'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        @if($can_db_backup)
                        <a class="v2-nav-item {{ $is('admin/business-settings/database/backup*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.database.backup') }}" data-id="maint-backup">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Backup database') }}</span>
                            <button type="button" class="v2-pin" data-pin="maint-backup" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/business-settings/database/restore*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.database.restore') }}" data-id="maint-restore">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('Restore database') }}</span>
                            <button type="button" class="v2-pin" data-pin="maint-restore" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_clean_db)
                        <a class="v2-nav-item {{ $is('admin/business-settings/db-index*') ? 'is-active' : '' }}" href="{{ route('admin.business-settings.db-index') }}" data-id="maint-clean">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('Clean database') }}</span>
                            <button type="button" class="v2-pin" data-pin="maint-clean" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif
    </aside>
</aside>

@include('layouts.admin.partials._v2_profile_pop')
@include('layouts.admin.partials._v2_sidebar_script')
