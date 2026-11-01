@extends('layouts.admin.app')

@section('title', translate('Firebase OTP Verification'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
@endpush

@section('content')
    @php
        $firebase_otp_verification = (int) (\App\CentralLogics\Helpers::get_business_settings('firebase_otp_verification', false) ?? 0);
        $firebase_web_api_key = \App\CentralLogics\Helpers::get_business_settings('firebase_web_api_key', false);
        $is_demo = getEnvMode() == 'demo';
        $is_active = $firebase_otp_verification === 1;
        $has_fallback = $is_sms_active || $is_mail_active;
    @endphp

    <div class="content container-fluid tps">
        @include('admin-views.business-settings.partials.third-party-header', [
            'icon' => 'tio-fingerprint',
            'title' => translate('Firebase OTP Verification'),
            'summary' => translate('Deliver one time passcodes through Firebase Phone Authentication instead of your SMS gateway.'),
            'helpTarget' => '#instructionsModal',
        ])

        <form action="{{ ! $is_demo ? route('admin.business-settings.third-party.firebase_otp_update', ['recaptcha']) : 'javascript:' }}"
              method="post">
            @csrf
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="tps-card">
                        <div class="tps-card__head">
                            <span class="tps-card__brand">
                                <img src="{{ asset('public/assets/admin/img/firebase_auth.png') }}" alt="">
                            </span>
                            <div class="tps-card__titles">
                                <h2 class="tps-card__title">{{ translate('Firebase Authentication') }}</h2>
                                <p class="tps-card__subtitle">
                                    {{ translate('To work the Firebase OTP properly need to use exact API key.') }}
                                </p>
                            </div>
                            <div class="tps-card__aside">
                                <span class="tps-pill {{ $is_active ? 'tps-pill--on' : 'tps-pill--off' }}">
                                    {{ $is_active ? translate('messages.Active') : translate('messages.Inactive') }}
                                </span>
                                <label class="toggle-switch toggle-switch-sm p-0 m-0">
                                    <input type="checkbox"
                                           data-id="firebase_otp_verification"
                                           data-type="toggle"
                                           data-image-on="{{ asset('public/assets/admin/img/modal/order-delivery-verification-on.png') }}"
                                           data-image-off="{{ asset('public/assets/admin/img/modal/order-delivery-verification-off.png') }}"
                                           data-title-on="<strong>{{ translate('Want to enable Firebase OTP Verification?') }}</strong>"
                                           data-title-off="<strong>{{ translate('Want to disable Firebase OTP Verification?') }}</strong>"
                                           data-text-on="<p>{{ translate('With Firebase OTP enabled, verification codes will be sent through Firebase.') . ' </p>' . ' <p> <strong>
                                           Note: ' . translate('Enable Firebase OTP means users will not receive verification codes through Email or SMS Although those methods are activated.') . '</strong>' }}</p>"
                                           data-text-off="<p>{{ translate('Firebase stops sending verification codes. Activate email or SMS verification instead.') }}</p>"
                                           class="status toggle-switch-input dynamic-checkbox-toggle"
                                           value="1" name="firebase_otp_verification" id="firebase_otp_verification"
                                           {{ $is_active ? 'checked' : '' }}>
                                    <span class="toggle-switch-label text p-0">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="tps-card__body">
                            <div class="tps-note tps-note--warn mb-4">
                                <i class="tio-warning"></i>
                                <div>
                                    {{ translate('messages.Web Api Key field need to fill properly otherwise OTP authentication can\'t work.') }}
                                </div>
                            </div>

                            @if ($is_active)
                                <div class="tps-note tps-note--info mb-4">
                                    <i class="tio-info"></i>
                                    <div>
                                        {{ translate('Firebase is currently handling every OTP. Users will not receive verification codes through Email or SMS even if those channels are configured.') }}
                                    </div>
                                </div>
                            @elseif (! $has_fallback)
                                <div class="tps-note tps-note--danger mb-4">
                                    <i class="tio-error"></i>
                                    <div>
                                        {{ translate('Firebase OTP is off and neither an SMS gateway nor mail configuration is active, so no verification code can reach your users right now.') }}
                                    </div>
                                </div>
                            @endif

                            <div class="tps-field">
                                <label class="tps-field__label" for="firebase_web_api_key">
                                    {{ translate('Web API key') }}
                                    <span class="tps-req">*</span>
                                    <span class="form-label-secondary m-0" data-toggle="tooltip" data-placement="right"
                                          data-original-title="{{ translate('Found in your Firebase project settings under General, listed as Web API Key.') }}">
                                        <i class="tio-info text-gray1 fs-16"></i>
                                    </span>
                                </label>
                                <div class="tps-input-wrap has-two-actions">
                                    <input type="password" name="firebase_web_api_key" class="form-control"
                                           id="firebase_web_api_key" autocomplete="off" placeholder="AIzaSy…"
                                           value="{{ ! $is_demo ? $firebase_web_api_key ?? '' : '' }}" required>
                                    <button type="button" class="tps-input-action tps-copy" data-target="#firebase_web_api_key"
                                            aria-label="{{ translate('Copy') }}">
                                        <i class="tio-copy"></i>
                                    </button>
                                    <button type="button" class="tps-input-action tps-toggle-secret" data-target="#firebase_web_api_key"
                                            aria-label="{{ translate('Show value') }}">
                                        <i class="tio-visible"></i>
                                    </button>
                                </div>
                                <small class="tps-field__hint">
                                    Firebase Console &rarr; Project settings &rarr; General &rarr; Web API Key.
                                </small>
                            </div>
                        </div>

                        <div class="tps-card__foot">
                            <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                            <button type="{{ ! $is_demo ? 'submit' : 'button' }}" class="btn btn--primary call-demo">
                                <i class="tio-save"></i> {{ translate('Save information') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="tps-card">
                        <div class="tps-card__head">
                            <span class="tps-card__brand"><i class="tio-traffic-light"></i></span>
                            <div class="tps-card__titles">
                                <h2 class="tps-card__title">{{ translate('OTP Delivery Channels') }}</h2>
                                <p class="tps-card__subtitle">{{ translate('Only one channel delivers the code at a time.') }}</p>
                            </div>
                        </div>
                        <div class="tps-card__body">
                            <ul class="list-unstyled m-0 d-flex flex-column __gap-12px">
                                <li class="d-flex align-items-center justify-content-between gap-2">
                                    <span class="d-flex align-items-center gap-2 fs-12">
                                        <i class="tio-fingerprint"></i> Firebase
                                    </span>
                                    <span class="tps-pill {{ $is_active ? 'tps-pill--on' : 'tps-pill--off' }}">
                                        {{ $is_active ? translate('messages.Active') : translate('messages.Inactive') }}
                                    </span>
                                </li>
                                <li class="d-flex align-items-center justify-content-between gap-2">
                                    <span class="d-flex align-items-center gap-2 fs-12">
                                        <i class="tio-message"></i> {{ translate('SMS Gateway') }}
                                    </span>
                                    <span class="tps-pill {{ $is_sms_active ? 'tps-pill--on' : 'tps-pill--off' }}">
                                        {{ $is_sms_active ? translate('messages.Active') : translate('messages.Inactive') }}
                                    </span>
                                </li>
                                <li class="d-flex align-items-center justify-content-between gap-2">
                                    <span class="d-flex align-items-center gap-2 fs-12">
                                        <i class="tio-email"></i> {{ translate('Mail Config') }}
                                    </span>
                                    <span class="tps-pill {{ $is_mail_active ? 'tps-pill--on' : 'tps-pill--off' }}">
                                        {{ $is_mail_active ? translate('messages.Active') : translate('messages.Inactive') }}
                                    </span>
                                </li>
                            </ul>

                            <a href="https://console.firebase.google.com/" target="_blank" rel="noopener"
                               class="btn btn-outline-primary btn-sm w-100 mt-4">
                                <i class="tio-launch"></i> {{ translate('Open Firebase Console') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="modal fade" id="instructionsModal" tabindex="-1" aria-labelledby="instructionsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="instructionsModalLabel">{{ translate('Instructions') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pt-0">
                    <p class="fs-12">
                        {{ translate('To configure OTP you need a Firebase project. Create one first if you do not have one yet.') }}
                    </p>
                    <p class="fs-12">
                        {{ translate('Now go to the Firebase console and follow the instructions below') }}: <a href="https://console.firebase.google.com/" target="_blank" rel="noopener" class="text-underline text-info">{{ translate('Firebase console') }}</a>
                    </p>
                    <ol class="tps-steps">
                        <li>{{ translate('Go to your Firebase project.') }}</li>
                        <li>{{ translate('Navigate to the Build menu from the left sidebar and select Authentication.') }}</li>
                        <li>{{ translate('Get started with the project and go to the Sign-in method tab.') }}</li>
                        <li>{{ translate('From the Sign-in providers section, select the Phone option.') }}</li>
                        <li>{{ translate('Ensure to enable the method Phone and press save.') }}</li>
                    </ol>
                </div>
                <div class="modal-footer justify-content-center border-0">
                    <button type="button" class="btn btn--primary w-100" data-dismiss="modal"><i class="tio-checkmark-circle-outlined"></i> {{ translate('Got it') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    @include('admin-views.business-settings.partials.third-party-scripts')
@endpush
