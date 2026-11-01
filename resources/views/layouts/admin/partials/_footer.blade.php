<div class="footer">
    <div class="d-flex justify-content-between align-items-baseline flex-wrap gap-2">
        <div class="text-md-start">
            <p class="font-size-sm mb-0">
                &copy; {{\App\CentralLogics\Helpers::get_business_settings('business_name') }}. <span
                    class="d-none d-sm-inline-block">{{\App\CentralLogics\Helpers::get_business_settings('footer_text')}}</span>
            </p>
        </div>
        <div class="">
            <div class="d-flex justify-content-end">
                <ul class="list-inline list-separator list-separator-before text-left">
                    @if(\App\CentralLogics\Helpers::module_permission_check('settings'))
                    <li class="list-inline-item">
                        <a class="list-separator-link" href="{{route('admin.business-settings.business-setup')}}">{{translate('Business setup')}}</a>
                    </li>
                    @endif

                    @if(\App\CentralLogics\Helpers::module_permission_check('profile'))
                    <li class="list-inline-item">
                        <a class="list-separator-link" href="{{route('admin.settings')}}">{{translate('messages.Profile')}}</a>
                    </li>
                    @endif

                    <li class="list-inline-item">
                        <a class="list-separator-link" href="{{route('admin.dashboard')}}">{{translate('messages.home')}}</a>
                    </li>
                    <li class="list-inline-item d-inline-block">
                        <label class="badge badge-soft-primary m-0">
                            {{translate('messages.Software version')}} : {{env('SOFTWARE_VERSION')}}
                        </label>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
