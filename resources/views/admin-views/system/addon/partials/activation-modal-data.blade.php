<div class="modal-header border-0 pb-0 d-flex justify-content-end">
    <button
        type="button"
        class="btn-close border-0"
        data-dismiss="modal"
        aria-label="{{ translate('Close') }}"
    ><i class="tio-clear"></i></button>
</div>
<div class="modal-body px-4 px-sm-5">
    <div class="mb-4 text-center">
        @php($logo=\App\Models\BusinessSetting::where('key','logo')->first())
        <img
            width="200"
            src="{{\App\CentralLogics\Helpers::get_full_url('business', $logo?->value?? '', $logo?->storage[0]?->value ?? 'public','upload_image')}}"

            alt="image"
            class="dark-support onerror-image"  data-onerror-image="{{ asset('public/assets/admin/img/img1.jpg') }}" />
    </div>
    <h2 class="text-center mb-1">{{ $addon_name }}</h2>
    <p class="text-center fs-12 color-656565 mb-2">
        {{ translate('This add-on has not been licensed yet. Enter your CodeCanyon purchase details to license it and turn it on.') }}
    </p>
    @php($licensedDomain = str_replace(['http://', 'https://'], '', url('/')))
    <p class="text-center fs-12 color-656565 mb-4">
        {{ translate('Licensed domain') }}: <strong>{{ $licensedDomain }}</strong>
    </p>

    <form action="{{route('admin.business-settings.system-addon.activation')}}" method="post" id="addon_activation_form" autocomplete="off">
        @csrf
        <div class="form-group mb-4">
            <label for="name">{{ translate('Your name') }}</label>
            <input
                name="name" id="name"
                class="form-control"
                placeholder="{{ translate('Ex') . ': John Doe' }}" required
            />
        </div>
        <div class="form-group mb-4">
            <label for="email">{{ translate('Email address') }}</label>
            <input
                type="email" name="email" id="email"
                class="form-control"
                placeholder="{{translate('Ex') . ': john@example.com'}}" required
            />
            <small class="form-text fs-12 color-656565">
                {{ translate('Used to send you the license confirmation.') }}
            </small>
        </div>
        <div class="form-group mb-4">
            <label for="username">{{ translate('CodeCanyon Username') }}</label>
            <input
                name="username" id="username"
                class="form-control"
                placeholder="{{translate('Ex') . ': john_doe'}}" required
            />
            <small class="form-text fs-12 color-656565">
                {{ translate('The username you sign in to CodeCanyon with — not your email.') }}
            </small>
        </div>
        <div class="form-group mb-6">
            <label for="purchase_code">{{ translate('Purchase Code') }}</label>
            <input
                name="purchase_code" id="purchase_code"
                class="form-control"
                placeholder="{{translate('Ex') . ': 8f1c2a9e-3b47-4c21-9c8d-1f2b3a4d5e6f'}}" required
            />
            <small class="form-text fs-12 color-656565">
                CodeCanyon &rarr; Downloads &rarr; License certificate &amp; purchase code.
            </small>
            <input type="text" name="path" class="form-control" value="{{$path}}" hidden>
        </div>

        <div class="btn--container justify-content-center gap-3 mb-3">
            <button type="button" class="fs-16 btn btn-secondary flex-grow-1" data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{ translate('Cancel') }}</button>
            <button type="submit" class="fs-16 btn btn--primary flex-grow-1"><i class="tio-lock-open-outlined"></i> {{ translate('License & turn on') }}</button>
        </div>
    </form>
</div>
