@if($use_v2_chrome ?? false)
<div id="headerMain" class="d-none"></div>
<button type="button" id="modalOpener" class="d-none" data-toggle="modal" data-target="#staticBackdrop"></button>
@else
<div id="headerMain" class="d-none">
    <header id="header"
            class="navbar navbar-expand-lg navbar-fixed navbar-height navbar-flush navbar-container navbar-bordered pr-0">
        <div class="navbar-nav-wrap">

            <div class="navbar-nav-wrap-content-left d-xl-none">
                <button type="button" class="js-navbar-vertical-aside-toggle-invoker close mr-3">
                    <i class="tio-first-page navbar-vertical-aside-toggle-short-align" data-toggle="tooltip"
                       data-placement="right" title="Collapse"></i>
                    <i class="tio-last-page navbar-vertical-aside-toggle-full-align"
                       data-template='<div class="tooltip d-none d-sm-block" role="tooltip"><div class="arrow"></div><div class="tooltip-inner"></div></div>'
                       data-toggle="tooltip" data-placement="right" title="Expand"></i>
                </button>
            </div>

            <div class="navbar-nav-wrap-content-right flex-grow-1 w-0">
                <ul class="navbar-nav align-items-center flex-row flex-grow-1 __navbar-nav">

                    @if (\App\CentralLogics\Helpers::admin_can_access_workspace('users'))
                    <li class="nav-item __nav-item">
                        <a href="{{ route('admin.users.dashboard')}}" id="tourb-6"
                           class="__nav-link {{ Request::is('admin/users*') ? 'active' : '' }}">
                            <img src="{{asset('/public/assets/admin/img/new-img/user.svg')}}" alt="public/img">
                            <span>{{ translate('Users')}}</span>
                        </a>
                    </li>
                    @endif

                    @if (\App\CentralLogics\Helpers::admin_can_access_workspace('finance') || \App\CentralLogics\Helpers::admin_can_access_workspace('reports'))
                    <li class="nav-item __nav-item">
                        <a href="{{ route('admin.transactions.store.withdraw_list')}}" id="tourb-7"
                           class="__nav-link {{ Request::is('admin/transactions*') ? 'active' : '' }}">
                            <img src="{{asset('/public/assets/admin/img/new-img/transaction-and-report.svg')}}"
                                 alt="public/img">
                            <span>{{ translate('Transactions & reports')}}</span>
                        </a>
                    </li>
                    @endif

                    @if (\App\CentralLogics\Helpers::admin_can_access_workspace('settings'))
                    <li class="nav-item __nav-item">
                        <a href="{{ \App\CentralLogics\Helpers::settings_workspace_landing_url() }}" id="tourb-3"
                           class="__nav-link {{ Request::is('admin/business-settings*') ? 'active' : '' }}">
                            <img src="{{asset('/public/assets/admin/img/new-img/setting-icon.svg')}}" alt="public/img">
                            <span>{{ translate('Settings') }}</span>
                            <svg width="14" viewBox="0 0 14 14" fill="none">
                                <path d="M2.33325 5.25L6.99992 9.91667L11.6666 5.25" stroke="#006161" stroke-width="1.5"
                                      stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                        <div class="__nav-module" id="tourb-4">
                            <div class="__nav-module-header">
                                <div class="inner">
                                    <h4>{{translate('Settings')}}</h4>
                                    <p>
                                        {{translate('Monitor your business general settings from here')}}
                                    </p>
                                </div>
                            </div>
                            <div class="__nav-module-body">
                                <ul>
                                    @if (\App\CentralLogics\Helpers::module_permission_check('module'))
                                        <li>
                                            <a href="{{ route('admin.business-settings.module.index') }}"
                                               class="next-tour">
                                                <img
                                                    src="{{asset('/public/assets/admin/img/navbar-setting-icon/module.svg')}}"
                                                    alt="">
                                                <span>{{translate('System module setup')}}</span>
                                            </a>
                                        </li>
                                    @endif
                                    @if (\App\CentralLogics\Helpers::module_permission_check('settings'))
                                        <li>
                                            <a href="{{ route('admin.business-settings.zone.home') }}"
                                               class="next-tour">
                                                <img
                                                    src="{{asset('/public/assets/admin/img/navbar-setting-icon/location.svg')}}"
                                                    alt="">
                                                <span>{{translate('Zone setup')}}</span>
                                            </a>
                                        </li>
                                    @endif
                                    @if (\App\CentralLogics\Helpers::module_permission_check('settings'))
                                        <li>
                                            <a href="{{ route('admin.business-settings.business-setup') }}"
                                               class="next-tour">
                                                <img
                                                    src="{{asset('/public/assets/admin/img/navbar-setting-icon/business.svg')}}"
                                                    alt="">
                                                <span>{{translate('Business settings')}}</span>
                                            </a>
                                        </li>
                                    @endif

                                    @if (\App\CentralLogics\Helpers::module_permission_check('settings'))
                                        <li>
                                            <a href="{{ route('admin.business-settings.third-party.sms-module') }}"
                                               class="next-tour">
                                                <img
                                                    src="{{asset('/public/assets/admin/img/navbar-setting-icon/third-party.svg')}}"
                                                    alt="">
                                                <span>{{translate('Third-party')}}</span>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{route('admin.business-settings.social-media.index')}}"
                                               class="next-tour">
                                                <img
                                                    src="{{asset('/public/assets/admin/img/navbar-setting-icon/social.svg')}}"
                                                    alt="">
                                                <span>{{translate('Social media and page setup')}}</span>
                                            </a>
                                        </li>
                                    @endif
                                </ul>
                                <div class="text-center mt-2">
                                    <a href="{{ route('admin.business-settings.business-setup') }}"
                                       class="next-tour">{{translate('View all')}}</a>
                                </div>
                            </div>
                        </div>
                    </li>
                    @endif
                    @if (\App\CentralLogics\Helpers::module_permission_check('order'))
                        <li class="nav-item __nav-item">
                            <a href="{{ route('admin.dispatch.dashboard')}}" id="tourb-8"
                               class="__nav-link {{ Request::is('admin/dispatch*') ? 'active' : '' }}">
                                <img src="{{asset('/public/assets/admin/img/new-img/dispatch.svg')}}" alt="public/img">
                                <span>{{ translate('Dispatch management')}}</span>
                            </a>
                        </li>
                    @endif


                    <li class="nav-item max-sm-m-0 w-xxl-200px ml-auto flex-grow-0">
                        <button type="button" id="modalOpener" class="title-color bg--secondary border-0 rounded justify-content-between w-100 align-items-center py-2 px-2 px-md-3 d-flex gap-1" data-toggle="modal" data-target="#staticBackdrop">
                            <div class="align-items-center d-flex flex-grow-1 gap-1 justify-content-between">
                                <span class="align-items-center d-none d-xxl-flex gap-2 text-muted">{{translate('Search')}}

                                    <span class="bg-E7E6E8 border ctrlplusk d-md-block d-none font-bold fs-12 fw-bold lh-1 ms-1 px-1 rounded text-muted">Ctrl+K</span>

                                </span>
                                <img width="14" class="h-auto" src="{{asset('/public/assets/admin/img/new-img/search.svg')}}" class="svg" alt="">
                            </div>
                        </button>
                    </li>

                    @if(\App\CentralLogics\Helpers::module_permission_check('customer_management'))
                    <li class="nav-item max-sm-m-0  mr-lg-3">
                        <a class="btn btn-icon rounded-circle nav-msg-icon p-0 h-100 w-100 d-flex justify-content-center"
                           href="{{route('admin.message.list')}}">
                            <img src="{{asset('/public/assets/admin/img/new-img/message-icon.svg')}}" alt="public/img">
                            @php($message=\App\Models\Conversation::whereUserType('admin')->whereHas('last_message', function($query) {
                                $query->whereColumn('conversations.sender_id', 'messages.sender_id');
                            })->where('unread_message_count', '>', 0)->count())
                            @if($message!=0)
                                <span class="btn-status btn-status-danger">{{ $message }}</span>
                            @endif
                        </a>
                    </li>
                    @endif
                    @if(addon_published_status('RideShare') && \App\CentralLogics\Helpers::module_permission_check('heat_map'))
                        <li class="nav-item max-sm-m-0  mr-lg-3">
                            @php($safetyAlert=\Modules\RideShare\Entities\TripManagement\RideSafetyAlert::where('status', 'pending'))
                            @php($safety=$safetyAlert->count())
                            @php($latestSafetyAlert=$safetyAlert->latest()->first())
                            <a id="v1-safety-link" class="btn btn-icon rounded-circle nav-msg-icon p-0 h-100 w-100 d-flex justify-content-center @if($latestSafetyAlert) safety-alert-header-icon @endif"
                            @if($latestSafetyAlert) data-user-id="{{ $latestSafetyAlert->sent_by }}" @endif
                            href="{{route('admin.ride-share.safety-alerts',['module_id'=>\App\Models\Module::where('module_type','ride-share')->first()->id ?? 0])}}">
                                <img src="{{asset('/public/assets/admin/img/new-img/shield-check.svg')}}" class="d-block" alt="public/img">
                                @if($safety!=0)
                                    <span class="btn-status btn-status-danger" id="v1-safety-badge">{{ $safety }}</span>
                                @endif
                            </a>
                        </li>
                    @endif
                    <li class="nav-item max-sm-m-0">
                        <div class="hs-unfold">
                            <div>
                                @php( $local = session()->has('local')?session('local'): null)
                                @php($lang  = \App\CentralLogics\Helpers::get_business_settings('system_language'))
                                @if ($lang)
                                    <div
                                        class="topbar-text dropdown disable-autohide text-capitalize d-flex">
                                        <a class="topbar-link dropdown-toggle d-flex align-items-center title-color"
                                           href="#" data-toggle="dropdown">
                                            @foreach($lang  as $data)
                                                @if($data['code']==$local)
                                                    <i class="tio-globe"></i> {{$data['code']}}

                                                @elseif(!$local &&  $data['default'] == true)
                                                    <i class="tio-globe"></i> {{$data['code']}}
                                                @endif
                                            @endforeach
                                        </a>
                                        <ul class="dropdown-menu lang-menu">
                                            @foreach($lang as $key =>$data)
                                                @if($data['status']==1)
                                                    <li>
                                                        <a class="dropdown-item py-1"
                                                           href="{{route('admin.lang',[$data['code']])}}">
                                                            <span class="text-capitalize">{{$data['code']}}</span>
                                                        </a>
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </li>
                    @php($mod = \App\Models\Module::with('storage')->find(Config::get('module.current_module_id')))
                    <div class="nav-item __nav-item">
                        <a href="javascript:void(0)" class="__nav-link module--nav-icon" id="tourb-0">
                            @if ($mod)
                                <img src="{{ $mod->icon_full_url }}"
                                     class="onerror-image flex-shrink-0"
                                     data-onerror-image="{{asset('/public/assets/admin/img/new-img/module-icon.svg')}}"
                                     width="20px" alt="public/img">
                            @else
                                <img src="{{asset('/public/assets/admin/img/new-img/module-icon.svg')}}" class="flex-shrink-0"
                                     alt="public/img">
                            @endif
                            <span class="text-white">{{ $mod ? $mod->module_name : translate('Modules') }}</span>
                            <img src="{{asset('/public/assets/admin/img/new-img/angle-white.svg')}}"
                                 class="d-none d-lg-block ml-xl-2" alt="public/img">
                        </a>
                        <div class="__nav-module style-2" id="tourb-1">
                            @php($modules = app(\App\Services\System\ModuleService::class)->switcherModules(auth('admin')->user()->zone_id))
                            @if(isset($modules) && ($modules->count()>0))
                                <div class="__nav-module-header">
                                    <div class="inner">
                                        <div class="row g-3 align-items-center">
                                            <div class="col-6">
                                                <h5>{{translate('Modules section')}}</h5>
                                                <p class="m-0">
                                                    {{translate('Select module & monitor your business module wise')}}
                                                </p>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                                <div class="__nav-module-body">
                                    <div class="__nav-module-items">
                                        @foreach ($modules as $module)
                                        {{-- Rental was the only addon type this guarded, so a Service or
                                             RideShare module kept its tile on an install without that addon --
                                             and with no addon there is no icon either, so it drew as an empty
                                             square that switched the panel into a module with no screens behind
                                             it. module_type_addon_active() answers for all three. --}}
                                        @if(module_type_addon_active($module->module_type))
                                            <a href="javascript:"

                                               data-module-id="{{ $module->id }}"
                                               data-url="{{$module->module_type == 'rental' && addon_published_status('Rental') ? route('admin.rental.dashboard') : route('admin.dashboard')}}"
                                               data-filter="module_id"

                                               class="__nav-module-item set-module {{Config::get('module.current_module_id') == $module->id?'active':''}}">
                                                <div class="img w--70px ">
                                                    <img src="{{ $module?->icon_full_url }}"

                                                         data-onerror-image="{{asset('public/assets/admin/img/new-img/module/e-shop.svg')}}"
                                                         alt="new-img" class="mw-100 onerror-image">
                                                </div>
                                                <div>
                                                    {{ $module->module_name }}
                                                </div>
                                            </a>
                                        @endif
                                        @endforeach
                                        @if (\App\CentralLogics\Helpers::module_permission_check('module'))
                                            <a href="{{ route('admin.business-settings.module.create') }}"
                                               class="__nav-module-item" data-toggle="tooltip"
                                               data-placement="top" title="{{ translate('Add new module') }}">
                                                <i class="tio-add display-3"></i>
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="__nav-module-body text-center py-5">
                                    <img class="w--120px" src="{{ asset('/public/assets/admin/img/empty-box.png') }}"
                                         alt="">
                                    <h2 class="my-4">{{ translate('Please, enable or create module first') }}</h2>
                                    <a href="{{ route('admin.business-settings.module.index') }}"
                                       class="btn btn--primary"><i class="tio-settings-outlined"></i> {{ translate('Module setup') }}</a>
                                </div>
                            @endif
                        </div>
                        </li>
                </ul>
            </div>
        </div>
    </header>
</div>
@endif
<div id="headerFluid" class="d-none"></div>
<div id="headerDouble" class="d-none"></div>

@include('layouts.partials._global_search_modal', ['searchRoute' => route('admin.search.routing')])



<div class="toggle-tour">
    <button type="button" class="tour-guide_btn w-40px h-40px border-0 bg-white d-flex align-items-center justify-content-center ">
        <span class="w-32 h-32px  min-w-32 d-flex align-items-center justify-content-center  bg-primary rounded-8"><img src="{{ asset('public/assets/admin/img/solar_multiple-forward-right-line-duotone.svg') }}" alt=""></span>
    </button>
    <div class="d-flex flex-column">
 
        @if (Request::isAny([
            'taxvat*',
            'admin/business-settings/seo-settings/page-meta-data*',
            'admin/business-settings/pages/react-landing-page-settings/meta-data*',
            'admin/business-settings/pages/admin-landing-page-settings/meta-data*',
            'admin/business-settings/business-setup*',
            'admin/store/view/*/meta-data'
        ]))


            <div class="tour-guide-items offcanvas-trigger text-capitalize fs-14 text-title cursor-pointer"
                data-target="#global_guideline_offcanvas">{{ translate('Guideline') }}</div>
    @endif

        <div class="tour-guide-items">
            <a href="https://youtube.com/playlist?list=PLLFMbDpKMZBxgtX3n3rKJvO5tlU8-ae2Y" target="_blank"
               class="d-flex align-items-center gap-10px">
                <span class="text-capitalize fs-14 text-title">{{ translate('Tutorial') }}</span>
            </a>
        </div>
        <div class="tour-guide-items d-flex cursor-pointer align-items-center gap-10px restart-Tour">
            <span class="text-capitalize fs-14 text-title">{{ translate('Tour') }}</span>
        </div>
        @if ( getEnvMode() == 'demo')
        <div class="tour-guide-items text-capitalize d-flex align-items-center gap-3 fs-14 text-title">
            {{ translate('Toggle RTL') }}
            <label class="toggle-switch toggle-switch-sm" for="rtl_toggle">
                <input type="checkbox" class="toggle-switch-input" id="rtl_toggle">
                <span class="toggle-switch-label">
                    <span class="toggle-switch-indicator"></span>
                </span>
            </label>
        </div>
        @endif
    </div>
</div>



