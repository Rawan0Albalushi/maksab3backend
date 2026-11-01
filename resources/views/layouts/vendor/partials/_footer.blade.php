@php
    $vendorData = \App\CentralLogics\Helpers::get_store_data();
    $title = $vendorData?->module_type == 'rental' && addon_published_status('Rental') ? 'Provider' : 'Store';
@endphp
<div class="footer">
    <div class="row justify-content-between align-items-center">
        <div class="col">
            <p class="font-size-sm mb-0">
                &copy; {{Str::limit(\App\CentralLogics\Helpers::get_store_data()->name, 50, '...')}}. <span
                    class="d-none d-sm-inline-block"></span>
            </p>
        </div>
        <div class="col-auto">
            <div class="d-flex justify-content-end">
                <ul class="list-inline list-separator">
                    <li class="list-inline-item">
                        <a class="list-separator-link" href="{{route('vendor.business-settings.store-setup')}}">{{translate('messages.'.$title.'_settings')}}</a>
                    </li>

                    <li class="list-inline-item">
                        <a class="list-separator-link" href="{{route('vendor.shop.view')}}">{{translate('messages.Profile')}}</a>
                    </li>

                    <li class="list-inline-item">
                        <div class="hs-unfold">
                            <a class="js-hs-unfold-invoker btn btn-icon btn-ghost-secondary rounded-circle"
                               href="{{route('vendor.dashboard')}}">
                                <i class="tio-home-outlined"></i>
                            </a>
                        </div>
                    </li>
                    <li class="list-inline-item">
                        <label class="badge badge-soft-primary m-0">
                            {{translate('messages.Software version')}} : {{env('SOFTWARE_VERSION')}}
                        </label>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
