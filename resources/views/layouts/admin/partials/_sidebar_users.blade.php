<div id="sidebarMain" class="d-none">
    <aside class="js-navbar-vertical-aside navbar navbar-vertical-aside navbar-vertical navbar-vertical-fixed navbar-expand-xl navbar-bordered  ">
        <div class="navbar-vertical-container">
            <div class="navbar-brand-wrapper justify-content-between">
                <a class="navbar-brand" href="{{ route('admin.users.dashboard') }}" aria-label="Front">
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
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/users') ? 'show active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.users.dashboard') }}" title="{{ translate('Dashboard') }}">
                            <i class="tio-home-vs-1-outlined nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                {{ translate('User overview') }}
                            </span>
                        </a>
                    </li>

                    @if (\App\CentralLogics\Helpers::module_permission_check('cashback'))
                    <li class="nav-item">
                        <small class="nav-subtitle" title="{{ translate('messages.Promotion section') }}">{{ translate('Promotion management') }}</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/users/cashback*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.users.cashback.add-new') }}" title="{{ translate('messages.CashBack') }}">
                            <i class="tio-settings-back nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.CashBack') }}</span>
                        </a>
                    </li>
                    @endif

                @if (\App\CentralLogics\Helpers::module_permission_check('deliveryman'))
                <li class="nav-item">
                    <small class="nav-subtitle" title="{{ translate('messages.Deliveryman section') }}">{{ translate('Deliveryman management') }}</small>
                    <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                </li>
                {{-- Vehicles Category moved to Settings › Delivery Management on 2026-09-08. --}}
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/users/delivery-man/add') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.users.delivery-man.add') }}" title="{{ translate('Add deliveryman') }}">
                        <i class="tio-running nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{ translate('Add deliveryman') }}
                        </span>
                    </a>
                </li>

                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/users/delivery-man/new') || Request::is('admin/users/delivery-man/deny')  ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.users.delivery-man.new') }}" title="{{ translate('New deliveryman') }}">
                        <i class="tio-man nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{ translate('New deliveryman') }}

                            <span class="badge badge-soft-info badge-pill ml-1">
                                {{ \App\Models\DeliveryMan::where('application_status','pending')->count() }}
                            </span>
                        </span>
                    </a>
                </li>


                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/users/delivery-man') ||  Request::is('admin/users/delivery-man/edit*') ||  Request::is('admin/users/delivery-man/preview*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.users.delivery-man.list') }}" title="{{ translate('Deliveryman list') }}">
                        <i class="tio-filter-list nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{ translate('Deliveryman list') }}
                        </span>
                    </a>
                </li>

                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/users/delivery-man/reviews') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.users.delivery-man.reviews.list') }}" title="{{ translate('messages.Reviews') }}">
                        <i class="tio-star-outlined nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{ translate('messages.Reviews') }}
                        </span>
                    </a>
                </li>
                @endif

                @if (addon_published_status('RideShare'))
                    @if(\App\CentralLogics\Helpers::module_permission_check('rider'))
                    <li class="nav-item">
                        <small class="nav-subtitle" title="{{ translate('messages.Rider handle') }}">{{ translate('Rider') }}
                            {{ translate('management') }}</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>

                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/users/rider*') && !Request::is('admin/users/rider/vehicle*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('Rider setup') }}">
                            <i class="tio-user nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Rider setup') }}</span>
                        </a>
                        <ul class="js-navbar-vertical-aside-submenu nav nav-sub"  style="display:{{ Request::is('admin/users/rider*') && !Request::is('admin/users/rider/vehicle*') ? 'block' : 'none' }}">
                            <li class="nav-item {{ Request::is('admin/users/rider') ||  Request::is('admin/users/rider/edit*') ||  Request::is('admin/users/rider/preview*') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.users.rider.list') }}" title="{{ translate('Rider list') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">{{ translate('Rider list') }}</span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/users/rider/add') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.users.rider.add') }}" title="{{ translate('Add new rider') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">{{ translate('Add new rider') }}</span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/users/rider/new') || Request::is('admin/users/rider/deny')  ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.users.rider.new') }}" title="{{ translate('New rider') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                        {{ translate('New rider') }}

                                        <span class="badge badge-soft-info badge-pill ml-1">
                                            {{ \App\Models\DeliveryMan::rider()->where('application_status','pending')->count() }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/users/rider/level') || Request::is('admin/users/rider/level/edit*') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.users.rider.level.index') }}" title="{{ translate('Rider level') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">{{ translate('Rider level') }}</span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/users/rider/level/create') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.users.rider.level.create') }}" title="{{ translate('Add rider level') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">{{ translate('Add rider level') }}</span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/users/rider/reviews') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.users.rider.reviews.list') }}" title="{{ translate('messages.Reviews') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">{{ translate('messages.Reviews') }}</span>
                                </a>
                            </li>

                        </ul>
                    </li>
                    @endif
                    @if(\App\CentralLogics\Helpers::module_permission_check('ride_vehicle'))
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/users/rider/vehicle*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('Vehicle setup') }}">
                            <i class="tio-car nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Vehicle setup') }}</span>
                        </a>
                        <ul class="js-navbar-vertical-aside-submenu nav nav-sub"  style="display:{{ Request::is('admin/users/rider/vehicle*') ? 'block' : 'none' }}">
                            <li class="nav-item {{ Request::is('admin/users/rider/vehicle/brand') || Request::is('admin/users/rider/vehicle/category') || Request::is('admin/users/rider/vehicle/model') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.users.rider.vehicle.brand.index') }}" title="{{ translate('Attribute setup') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">{{ translate('Attribute setup') }}</span>
                                </a>
                            </li>
                            <li class="nav-item @yield('rider_new_vehicle_edit') {{ Request::is('admin/users/rider/vehicle/create') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.users.rider.vehicle.create') }}" title="{{ translate('Add vehicle') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">{{ translate('Add vehicle') }}</span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/users/rider/vehicle') || Request::is('admin/users/rider/vehicle/show*') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.users.rider.vehicle.index') }}" title="{{ translate('Vehicle list') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">{{ translate('Vehicle list') }}</span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/users/rider/vehicle/request/list*') || Request::is('admin/users/rider/vehicle/request/details*') ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.users.rider.vehicle.request.list',['status'=>'pending']) }}" title="{{ translate('Vehicle request') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">{{ translate('Vehicle request') }}</span>
                                </a>
                            </li>

                        </ul>
                    </li>
                    @endif
                @endif

                @if (\App\CentralLogics\Helpers::module_permission_check('customer_management'))
                <li class="nav-item">
                    <small class="nav-subtitle" title="{{ translate('messages.Customer section') }}">{{ translate('Customer management') }}</small>
                    <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                </li>

                <li class="navbar-vertical-aside-has-menu @yield('customer') {{ (Request::is('admin/users/customer/list') || Request::is('admin/users/customer/view*')) ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.users.customer.list') }}" title="{{ translate('messages.customers') }}">
                        <i class="tio-poi-user nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{ translate('messages.customers') }}
                        </span>
                    </a>
                </li>

                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/users/customer/wallet*') ? 'active' : '' }}">

                    <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('Customer wallet') }}">
                        <i class="tio-wallet nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate  text-capitalize">
                            {{ translate('Customer wallet') }}
                        </span>
                    </a>

                    <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="display:{{ Request::is('admin/users/customer/wallet*') ? 'block' : 'none' }}">
                        <li class="nav-item {{ Request::is('admin/users/customer/wallet/add-fund') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.users.customer.wallet.add-fund') }}" title="{{ translate('Add fund') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate text-capitalize">{{ translate('Add fund') }}</span>
                            </a>
                        </li>

                        <li class="nav-item {{ Request::is('admin/users/customer/wallet/report*') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.users.customer.wallet.report') }}" title="{{ translate('messages.report') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate text-capitalize">{{ translate('messages.report') }}</span>
                            </a>
                        </li>

                        <li class="nav-item {{ Request::is('admin/users/customer/wallet/bonus*') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.users.customer.wallet.bonus.add-new') }}" title="{{ translate('messages.Bonus') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate text-capitalize">{{ translate('messages.Bonus') }}</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/users/customer/loyalty-point*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link  nav-link-toggle" href="javascript:" title="{{ translate('Customer loyalty point') }}">
                        <i class="tio-medal nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate  text-capitalize">
                            {{ translate('Customer loyalty point') }}
                        </span>
                    </a>

                    <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="display:{{ Request::is('admin/users/customer/loyalty-point*') ? 'block' : 'none' }}">
                        <li class="nav-item {{ Request::is('admin/users/customer/loyalty-point/report*') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.users.customer.loyalty-point.report') }}" title="{{ translate('messages.report') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate text-capitalize">{{ translate('messages.report') }}</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/users/customer/subscribed') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.users.customer.subscribed') }}" title="{{translate('Subscribed emails')}}">
                        <i class="tio-email-outlined nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{ translate('messages.Subscribed mail list') }}
                        </span>
                    </a>
                </li>
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/users/contact/contact-list') ? 'active' : '' }}">
                    <a class="nav-link " href="{{ route('admin.users.contact.contact-list') }}" title="{{ translate('Contact messages') }}">
                        <span class="tio-message nav-icon"></span>
                        <span class="text-truncate">{{ translate('Contact messages') }}</span>
                    </a>
                </li>

                @endif




                <li class="nav-item">
                    <small class="nav-subtitle" title="{{ translate('messages.Employee handle') }}">{{ translate('Employee') }}
                        {{ translate('management') }}</small>
                    <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                </li>

                @if (\App\CentralLogics\Helpers::module_permission_check('employee_role'))
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/users/custom-role*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.users.custom-role.list') }}" title="{{ translate('messages.Employee role') }}">
                        <i class="tio-incognito nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.Employee role') }}</span>
                    </a>
                </li>
                @endif

                @if (\App\CentralLogics\Helpers::module_permission_check('employee'))
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/users/employee*') ? 'active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('Employee') }}">
                        <i class="tio-user nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Employees') }}</span>
                    </a>
                    <ul class="js-navbar-vertical-aside-submenu nav nav-sub"  style="display:{{ Request::is('admin/users/employee*') ? 'block' : 'none' }}">
                        <li class="nav-item {{ Request::is('admin/users/employee/store') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.users.employee.add-new') }}" title="{{ translate('messages.Add new employee') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('Add new') }}</span>
                            </a>
                        </li>
                        <li class="nav-item @yield('employee_list')">
                            <a class="nav-link " href="{{ route('admin.users.employee.list') }}" title="{{ translate('messages.Employee list') }}">
                                <span class="tio-circle nav-indicator-icon"></span>
                                <span class="text-truncate">{{ translate('messages.list') }}</span>
                            </a>
                        </li>

                    </ul>
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
