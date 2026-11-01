@extends('layouts.admin.app')

@section('title', translate('messages.reCaptcha Setup'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
@endpush

@section('content')
    @php
        $config = \App\CentralLogics\Helpers::get_business_settings('recaptcha');
        $config = is_array($config) ? $config : [];
        $is_demo = getEnvMode() == 'demo';
        $is_active = (int) ($config['status'] ?? 0) === 1;
    @endphp

    <div class="content container-fluid tps">
        @include('admin-views.business-settings.partials.third-party-header', [
            'icon' => 'tio-robot',
            'title' => translate('messages.reCaptcha credentials setup'),
            'summary' => translate('Protect sign up, login and checkout forms from bots and automated abuse.'),
            'helpTarget' => '#setup-information',
            'helpLabel' => translate('Credential Setup Information'),
        ])

        <form action="{{ ! $is_demo ? route('admin.business-settings.third-party.recaptcha_update', ['recaptcha']) : 'javascript:' }}"
              method="post">
            @csrf
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="tps-card">
                        <div class="tps-card__head">
                            <span class="tps-card__brand">
                                <img src="{{ asset('public/assets/admin/img/captcha.png') }}" alt="">
                            </span>
                            <div class="tps-card__titles">
                                <h2 class="tps-card__title">{{ translate('Google Recaptcha Information') }}</h2>
                                <p class="tps-card__subtitle">
                                    {{ translate('Keys are issued per domain from the Google reCAPTCHA admin console.') }}
                                </p>
                            </div>
                            <div class="tps-card__aside">
                                <span class="tps-pill {{ $is_active ? 'tps-pill--on' : 'tps-pill--off' }}">
                                    {{ $is_active ? translate('messages.Active') : translate('messages.Inactive') }}
                                </span>
                                <label class="toggle-switch toggle-switch-sm p-0 m-0">
                                    <input type="checkbox"
                                           data-id="recaptcha_status"
                                           data-type="toggle"
                                           data-image-on="{{ asset('/public/assets/admin/img/modal/important-recapcha.png') }}"
                                           data-image-off="{{ asset('/public/assets/admin/img/modal/warning-recapcha.png') }}"
                                           data-title-on="{{ translate('Important!') }}"
                                           data-title-off="{{ translate('warning') }}"
                                           data-text-on="<p>{{ translate('ReCAPTCHA is on. Users may be asked to complete a challenge to prove they are human.') }}</p>"
                                           data-text-off="<p>{{ translate('Disabling reCAPTCHA leaves your site open to spam and bots. Keeping it on is strongly recommended.') }}</p>"
                                           class="status toggle-switch-input dynamic-checkbox-toggle"
                                           name="status" id="recaptcha_status" value="1" {{ $is_active ? 'checked' : '' }}>
                                    <span class="toggle-switch-label text p-0">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="tps-card__body">
                            <div class="tps-note tps-note--info mb-4">
                                <i class="tio-info"></i>
                                <div>
                                    <strong>{{ translate('New version available') }}: reCAPTCHA v3</strong>
                                    <div>{{ translate('You must set up this version. Otherwise the default reCAPTCHA will be displayed automatically') }}</div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="tps-field">
                                        <label for="site_key" class="tps-field__label">{{ translate('messages.Site Key') }}</label>
                                        <div class="tps-input-wrap">
                                            <input id="site_key" type="text" class="form-control" name="site_key"
                                                   autocomplete="off" placeholder="6Lc…"
                                                   value="{{ ! $is_demo ? $config['site_key'] ?? '' : '' }}">
                                            <button type="button" class="tps-input-action tps-copy" data-target="#site_key"
                                                    aria-label="{{ translate('Copy') }}">
                                                <i class="tio-copy"></i>
                                            </button>
                                        </div>
                                        <small class="tps-field__hint">
                                            {{ translate('Public key rendered inside the reCAPTCHA widget on your site.') }}
                                        </small>
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="tps-field">
                                        <label for="secret_key" class="tps-field__label">{{ translate('messages.Secret Key') }}</label>
                                        <div class="tps-input-wrap has-two-actions">
                                            <input id="secret_key" type="password" class="form-control" name="secret_key"
                                                   autocomplete="off" placeholder="6Lc…"
                                                   value="{{ ! $is_demo ? $config['secret_key'] ?? '' : '' }}">
                                            <button type="button" class="tps-input-action tps-copy" data-target="#secret_key"
                                                    aria-label="{{ translate('Copy') }}">
                                                <i class="tio-copy"></i>
                                            </button>
                                            <button type="button" class="tps-input-action tps-toggle-secret" data-target="#secret_key"
                                                    aria-label="{{ translate('Show value') }}">
                                                <i class="tio-visible"></i>
                                            </button>
                                        </div>
                                        <small class="tps-field__hint">
                                            {{ translate('Server side key. Never expose it in any frontend code.') }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tps-card__foot">
                            <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                            <button type="{{ ! $is_demo ? 'submit' : 'button' }}" class="btn btn--primary call-demo">
                                <i class="tio-save"></i> {{ translate('messages.Save') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="tps-card">
                        <div class="tps-card__head">
                            <span class="tps-card__brand"><i class="tio-security-on"></i></span>
                            <div class="tps-card__titles">
                                <h2 class="tps-card__title">{{ translate('Where reCAPTCHA applies') }}</h2>
                            </div>
                        </div>
                        <div class="tps-card__body">
                            <ul class="list-unstyled m-0 d-flex flex-column __gap-12px">
                                @foreach ([
                                    translate('Customer registration and login'),
                                    translate('Store and delivery man sign up'),
                                    translate('Password reset requests'),
                                    translate('Admin panel login'),
                                ] as $recaptcha_scope)
                                    <li class="d-flex align-items-center gap-2 fs-12">
                                        <i class="tio-checkmark-circle text-success"></i>
                                        <span>{{ $recaptcha_scope }}</span>
                                    </li>
                                @endforeach
                            </ul>

                            <a href="https://www.google.com/recaptcha/admin/create" target="_blank" rel="noopener"
                               class="btn btn-outline-primary btn-sm w-100 mt-4">
                                <i class="tio-launch"></i> {{ translate('Create reCAPTCHA Keys') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="modal fade" id="setup-information" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title">{{ translate('Instructions') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pt-0">
                    <ol class="tps-steps">
                        <li>{{ translate('messages.Go to the Credentials page') }}
                            ({{ translate('messages.click') }}
                            <a href="https://www.google.com/recaptcha/admin/create" target="_blank" rel="noopener">{{ translate('messages.Here.') }}</a>)
                        </li>
                        <li>{{ translate('messages.Add a') }} <b>{{ translate('messages.label') }}</b> ({{ translate('Ex') }}: Test Label)</li>
                        <li>{{ translate('Select the reCAPTCHA type') }}: <b>reCAPTCHA v3</b>
                            ({{ translate("Sub type: I'm not a robot Checkbox") }})
                        </li>
                        <li>{{ translate('Add') }} <b>{{ translate('messages.domain') }}</b> ({{ translate('For ex') }}: demo.6amtech.com)</li>
                        <li>{{ translate('messages.Check in') }} <b>{{ translate('messages.Accept the reCAPTCHA Terms of Service') }}</b></li>
                        <li>{{ translate('Press the button') }}: <b>{{ translate('messages.Submit') }}</b></li>
                        <li>{{ translate('messages.Copy') }} <b>{{ translate('Site') }} {{ translate('Key') }}</b>
                            {{ translate('messages.and') }} <b>{{ translate('Secret') }} {{ translate('Key') }}</b>,
                            {{ translate('messages.paste in the input field below and') }} <b>{{ translate('Save') }}</b>.
                        </li>
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
