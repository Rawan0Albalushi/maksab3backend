<div id="sidebarMain" class="d-none">
    <aside class="js-navbar-vertical-aside navbar navbar-vertical-aside navbar-vertical navbar-vertical-fixed navbar-expand-xl navbar-bordered  ">
        <div class="navbar-vertical-container">
            <div class="navbar-brand-wrapper justify-content-between">
                <a class="navbar-brand" href="#" aria-label="Front">
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
                <form autocomplete="off" class="sidebar--search-form">
                    <div class="search--form-group">
                        <button type="button" class="btn"><i class="tio-search"></i></button>
                        <input autocomplete="false" type="text" name="qq" class="form-control form--control" placeholder="{{ translate('Search menu') }}" id="search">
                        <div id="search-suggestions" class="flex-wrap mt-1"></div>
                    </div>
                </form>
                <ul class="navbar-nav navbar-nav-lg nav-tabs">
                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/dispatch') ? 'show active' : '' }}">
                    <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.dispatch.dashboard') }}" title="{{ translate('Dashboard') }}">
                        <i class="tio-home-vs-1-outlined nav-icon"></i>
                        <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{ translate('Dashboard') }}
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <small class="nav-subtitle" title="{{ translate('messages.Dispatch section') }}">{{ translate('Dispatch management') }}</small>
                    <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                </li>

                @if (\App\CentralLogics\Helpers::module_permission_check('order'))
                    @php($modules = \App\Models\Module::whereNotIn('module_type', ['rental','ride-share'])->when(auth('admin')->user()->zone_id, function($query){
                                $query->whereHas('zones',function($query){
                                    $query->where('zone_id',auth('admin')->user()->zone_id);
                                });
                            })->Active()->get())
                    @foreach ($modules as $module)
                    @if ($module->module_type != 'parcel')
                    @php($unassigned = \App\Models\Order::whereHas('module', function($query) use($module){
                                        $query->where('module_id', $module->id);
                                    })->SearchingForDeliveryman()->OrderScheduledIn(30)->StoreOrder()->count())
                    @php($ongoing = \App\Models\Order::whereHas('module', function($query) use($module){
                                        $query->where('module_id', $module->id);
                                    })->Ongoing()->OrderScheduledIn(30)->StoreOrder()->count() )
                    <li class="navbar-vertical-aside-has-menu {{ Request::is("admin/dispatch/list/{$module->id}*") ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ $module->module_name }}">
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                {{ $module->module_name }}
                                <span class="badge badge-soft-info badge-pill ml-1">
                                    {{ $unassigned + $ongoing }}

                                </span>
                            </span>
                        </a>
                        <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="{{ Request::is('admin/dispatch*') ? 'display-block' : 'display-none' }}">
                            <li class="nav-item {{ Request::is("admin/dispatch/list/{$module->id}/searching_for_deliverymen") ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.dispatch.list', [$module->id,'searching_for_deliverymen']) }}" title="{{ translate('messages.Unassigned orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{translate('messages.Unassigned orders')}}
                                        <span class="badge badge-soft-info badge-pill ml-1">
                                            {{ $unassigned }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is("admin/dispatch/list/{$module->id}/on_going") ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.dispatch.list', [$module->id,'on_going']) }}" title="{{ translate('Ongoing orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('Ongoing orders') }}
                                        <span class="badge badge-soft-light badge-pill ml-1">
                                            {{ $ongoing }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @else
                    @php($unassigned = \App\Models\Order::whereHas('module', function($query) use($module){
                                        $query->where('module_id', $module->id);
                                    })->SearchingForDeliveryman()->OrderScheduledIn(30)->ParcelOrder()->count())
                    @php($ongoing = \App\Models\Order::whereHas('module', function($query) use($module){
                                        $query->where('module_id', $module->id);
                                    })->Ongoing()->OrderScheduledIn(30)->ParcelOrder()->count() )
                    <li class="navbar-vertical-aside-has-menu {{ Request::is("admin/dispatch/parcel/list/{$module->id}*") ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ $module->module_name }}">
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                {{ $module->module_name }}
                                <span class="badge badge-soft-light badge-pill ml-1">
                                    {{ $unassigned + $ongoing }}
                                </span>
                            </span>
                        </a>
                        <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="{{ Request::is('admin/dispatch*') ? 'display-block' : 'display-none' }}">
                            <li class="nav-item {{ Request::is("admin/dispatch/parcel/list/{$module->id}/searching_for_deliverymen") ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.dispatch.parcel.list', [$module->id,'searching_for_deliverymen']) }}" title="{{ translate('messages.Unassigned orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{translate('messages.Unassigned orders')}}
                                        <span class="badge badge-soft-info badge-pill ml-1">
                                            {{ $unassigned }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is("admin/dispatch/parcel/list/{$module->id}/on_going") ? 'active' : '' }}">
                                <a class="nav-link " href="{{ route('admin.dispatch.parcel.list', [$module->id,'on_going']) }}" title="{{ translate('Ongoing orders') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('Ongoing orders') }}
                                        <span class="badge badge-soft-light badge-pill ml-1">
                                            {{ $ongoing }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif
                    @endforeach
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
