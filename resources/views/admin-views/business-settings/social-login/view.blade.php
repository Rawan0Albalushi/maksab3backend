@extends('layouts.admin.app')

@section('title', translate('Social Login Setup'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
@endpush

@section('content')
    @php
        $provider_names = ['google' => 'Google', 'facebook' => 'Facebook', 'apple' => 'Apple'];
        $provider_copy = [
            'google' => translate('Lets customers sign in with their Google account.'),
            'facebook' => translate('Lets customers sign in with their Facebook account.'),
            'apple' => translate('Required by Apple if your iOS app offers any other social login.'),
        ];
    @endphp

    <div class="content container-fluid tps">
        @include('admin-views.business-settings.partials.third-party-header', [
            'icon' => 'tio-users-switch',
            'title' => translate('Social Login Setup'),
            'summary' => translate('Let customers sign up and log in with an account they already have.'),
        ])

        <div class="tps-masonry">
            @if (isset($socialLoginServices))
                @foreach ($socialLoginServices as $socialLoginService)
                    @php
                        $medium = $socialLoginService['login_medium'];
                        $is_active = (int) ($socialLoginService['status'] ?? 0) === 1;
                        $callback_id = 'id_' . $medium;
                    @endphp

                    <div>
                        <form action="{{ route('admin.social-login.update', [$medium]) }}" method="post">
                            @csrf
                            <div class="tps-card">
                                <div class="tps-card__head">
                                    <span class="tps-card__brand">
                                        <img src="{{ asset('/public/assets/admin/img') }}/{{ $medium }}.svg" alt="">
                                    </span>
                                    <div class="tps-card__titles">
                                        <h2 class="tps-card__title">{{ $provider_names[$medium] ?? ucfirst($medium) }}</h2>
                                        @if (! empty($provider_copy[$medium]))
                                            <p class="tps-card__subtitle">{{ $provider_copy[$medium] }}</p>
                                        @endif
                                    </div>
                                    <div class="tps-card__aside">
                                        <span class="tps-pill {{ $is_active ? 'tps-pill--on' : 'tps-pill--off' }}">
                                            {{ $is_active ? translate('messages.Active') : translate('messages.Inactive') }}
                                        </span>
                                        <label class="toggle-switch toggle-switch-sm p-0 m-0"
                                               data-toggle="tooltip" data-placement="left"
                                               title="{{ translate('messages.Toggle this option to enable or disable login through this provider.') }}">
                                            <input id="{{ $medium }}_status"
                                                   data-id="{{ $medium }}_status"
                                                   data-type="toggle"
                                                   data-image-on="{{ asset('/public/assets/admin/img/modal') }}/{{ $medium }}-on.png"
                                                   data-image-off="{{ asset('/public/assets/admin/img/modal') }}/{{ $medium }}-off.png"
                                                   data-title-on="{{ $provider_names[$medium] ?? ucfirst($medium) }} {{ translate('Login Turned ON') }}"
                                                   data-title-off="{{ $provider_names[$medium] ?? ucfirst($medium) }} {{ translate('Login Turned OFF') }}"
                                                   data-text-on="<p>{{ $provider_names[$medium] ?? ucfirst($medium) }} {{ translate('Login is now enabled. Customers can sign up or log in using their social media accounts.') }}</p>"
                                                   data-text-off="<p>{{ $provider_names[$medium] ?? ucfirst($medium) }} {{ translate('Social login is off. Customers cannot sign up or log in with social accounts.') }}</p>"
                                                   class="status toggle-switch-input dynamic-checkbox-toggle"
                                                   type="checkbox" name="status" value="1" {{ $is_active ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text p-0">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <div class="tps-card__body">
                                    <div class="tps-field mb-3">
                                        <label class="tps-field__label">{{ translate('messages.Callback uri') }}</label>
                                        <div class="tps-readonly">
                                            <span class="tps-readonly__value" id="{{ $callback_id }}">{{ url('/') }}/customer/auth/login/{{ $medium }}/callback</span>
                                            <button type="button" class="tps-readonly__copy tps-copy" data-target="#{{ $callback_id }}">
                                                <i class="tio-copy"></i> {{ translate('Copy') }}
                                            </button>
                                        </div>
                                        <small class="tps-field__hint">
                                            {{ translate('Paste this exact URL into the provider console as an authorised redirect URI.') }}
                                        </small>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="tps-field">
                                                <label for="{{ $medium }}_client_id" class="tps-field__label">{{ translate('messages.Client ID') }}</label>
                                                <div class="tps-input-wrap">
                                                    <input id="{{ $medium }}_client_id" type="text" class="form-control" name="client_id"
                                                           autocomplete="off" value="{{ $socialLoginService['client_id'] }}">
                                                    <button type="button" class="tps-input-action tps-copy"
                                                            data-target="#{{ $medium }}_client_id" aria-label="{{ translate('Copy') }}">
                                                        <i class="tio-copy"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="tps-field">
                                                <label for="{{ $medium }}_client_secret" class="tps-field__label">{{ translate('messages.Client secret') }}</label>
                                                <div class="tps-input-wrap has-two-actions">
                                                    <input id="{{ $medium }}_client_secret" type="password" class="form-control" name="client_secret"
                                                           autocomplete="off" value="{{ $socialLoginService['client_secret'] }}">
                                                    <button type="button" class="tps-input-action tps-copy"
                                                            data-target="#{{ $medium }}_client_secret" aria-label="{{ translate('Copy') }}">
                                                        <i class="tio-copy"></i>
                                                    </button>
                                                    <button type="button" class="tps-input-action tps-toggle-secret"
                                                            data-target="#{{ $medium }}_client_secret" aria-label="{{ translate('Show value') }}">
                                                        <i class="tio-visible"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tps-card__foot">
                                    <button type="button" class="tps-foot-note btn btn-link p-0 text--primary"
                                            data-toggle="modal" data-target="#{{ $medium }}-modal">
                                        <i class="tio-help-outlined"></i> {{ translate('Credential Setup') }}
                                    </button>
                                    <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('Reset') }}</button>
                                    <button type="submit" class="btn btn--primary call-demo"><i class="tio-save"></i> {{ translate('messages.Save') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                @endforeach
            @endif

            @if (isset($appleLoginServices))
                @foreach ($appleLoginServices as $appleLoginService)
                    @php
                        $medium = $appleLoginService['login_medium'];
                        $is_active = (int) ($appleLoginService['status'] ?? 0) === 1;
                        $has_service_file = ! empty($appleLoginService['service_file']);
                    @endphp

                    <div>
                        <form action="{{ route('admin.apple-login.update', [$medium]) }}" method="post" enctype="multipart/form-data">
                            @csrf
                            <div class="tps-card">
                                <div class="tps-card__head">
                                    <span class="tps-card__brand">
                                        <img src="{{ asset('/public/assets/admin/img/apple.svg') }}" alt="">
                                    </span>
                                    <div class="tps-card__titles">
                                        <h2 class="tps-card__title">{{ $provider_names[$medium] ?? ucfirst($medium) }}</h2>
                                        <p class="tps-card__subtitle">{{ $provider_copy['apple'] }}</p>
                                    </div>
                                    <div class="tps-card__aside">
                                        <span class="tps-pill {{ $is_active ? 'tps-pill--on' : 'tps-pill--off' }}">
                                            {{ $is_active ? translate('messages.Active') : translate('messages.Inactive') }}
                                        </span>
                                        <label class="toggle-switch toggle-switch-sm p-0 m-0"
                                               data-toggle="tooltip" data-placement="left"
                                               title="{{ translate('messages.Toggle this option to enable or disable login through this provider.') }}">
                                            <input id="{{ $medium }}_status"
                                                   data-id="{{ $medium }}_status"
                                                   data-type="toggle"
                                                   data-image-on="{{ asset('/public/assets/admin/img/modal') }}/{{ $medium }}-on.png"
                                                   data-image-off="{{ asset('/public/assets/admin/img/modal') }}/{{ $medium }}-off.png"
                                                   data-title-on="{{ $provider_names[$medium] ?? ucfirst($medium) }} {{ translate('Login Turned ON') }}"
                                                   data-title-off="{{ $provider_names[$medium] ?? ucfirst($medium) }} {{ translate('Login Turned OFF') }}"
                                                   data-text-on="<p>{{ $provider_names[$medium] ?? ucfirst($medium) }} {{ translate('Login is now enabled. Customers can sign up or log in using their social media accounts.') }}</p>"
                                                   data-text-off="<p>{{ $provider_names[$medium] ?? ucfirst($medium) }} {{ translate('Social login is off. Customers cannot sign up or log in with social accounts.') }}</p>"
                                                   class="status toggle-switch-input dynamic-checkbox-toggle"
                                                   type="checkbox" name="status" value="1" {{ $is_active ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text p-0">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <div class="tps-card__body">
                                    <div class="tps-group">
                                        <h3 class="tps-group__label">{{ translate('Identifiers') }}</h3>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="tps-field">
                                                    <label for="apple_client_id" class="tps-field__label">{{ translate('messages.Client id for web') }}</label>
                                                    <input id="apple_client_id" type="text" class="form-control" name="client_id"
                                                           autocomplete="off" value="{{ $appleLoginService['client_id'] }}">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="tps-field">
                                                    <label for="apple_client_id_app" class="tps-field__label">{{ translate('messages.Client id for app') }}</label>
                                                    <input id="apple_client_id_app" type="text" class="form-control" name="client_id_app"
                                                           autocomplete="off" value="{{ $appleLoginService['client_id_app'] ?? '' }}">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="tps-field">
                                                    <label for="apple_team_id" class="tps-field__label">{{ translate('messages.Team ID') }}</label>
                                                    <input id="apple_team_id" type="text" class="form-control" name="team_id"
                                                           autocomplete="off" value="{{ $appleLoginService['team_id'] }}">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="tps-field">
                                                    <label for="apple_key_id" class="tps-field__label">{{ translate('messages.Key id') }}</label>
                                                    <input id="apple_key_id" type="text" class="form-control" name="key_id"
                                                           autocomplete="off" value="{{ $appleLoginService['key_id'] }}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tps-group">
                                        <h3 class="tps-group__label">{{ translate('Redirect URLs') }}</h3>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="tps-field">
                                                    <label for="redirect_url_flutter" class="tps-field__label">{{ translate('messages.Redirect url for flutter web') }}</label>
                                                    <input id="redirect_url_flutter" type="url" class="form-control" name="redirect_url_flutter"
                                                           value="{{ $appleLoginService['redirect_url_flutter'] ?? '' }}">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="tps-field">
                                                    <label for="redirect_url_react" class="tps-field__label">{{ translate('messages.Redirect url for react web') }}</label>
                                                    <input id="redirect_url_react" type="url" class="form-control" name="redirect_url_react"
                                                           value="{{ $appleLoginService['redirect_url_react'] ?? '' }}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tps-group">
                                        <h3 class="tps-group__label">{{ translate('messages.Service file') }}</h3>
                                        <div class="tps-field">
                                            <label for="service_file" class="tps-field__label">
                                                {{ translate('messages.Service file') }}
                                                @if ($has_service_file)
                                                    <span class="tps-pill tps-pill--on">{{ translate('Already exists') }}</span>
                                                @endif
                                            </label>
                                            <input id="service_file" type="file" accept=".p8" class="form-control" name="service_file">
                                            <small class="tps-field__hint">
                                                {{ translate('The private key downloaded from Apple') }} (.p8).
                                                @if ($has_service_file)
                                                    {{ translate('Uploading a new file replaces the current one.') }}
                                                @endif
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="tps-card__foot">
                                    <button type="button" class="tps-foot-note btn btn-link p-0 text--primary"
                                            data-toggle="modal" data-target="#{{ $medium }}-modal">
                                        <i class="tio-help-outlined"></i> {{ translate('Credential Setup') }}
                                    </button>
                                    <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('Reset') }}</button>
                                    <button type="submit" class="btn btn--primary call-demo"><i class="tio-save"></i> {{ translate('messages.Save') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    <div class="modal fade" id="google-modal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog status-warning-modal modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title">{{ translate('messages.Google api setup instructions') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-0">
                    <ol class="tps-steps">
                        <li>{{ translate('messages.Go to the Credentials page') }} ({{ translate('messages.click') }} <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">{{ translate('Here.') }}</a>)</li>
                        <li>{{ translate('messages.click') }} <b>{{ translate('messages.Create credentials') }}</b> > <b>{{ translate('messages.Auth client id') }}</b>.</li>
                        <li>{{ translate('messages.Select the') }} <b>{{ translate('messages.Web application') }}</b> {{ translate('Type') }}.</li>
                        <li>{{ translate('messages.Name your auth client') }}</li>
                        <li>{{ translate('messages.click') }} <b>{{ translate('messages.Add uri') }}</b> {{ translate('messages.from') }} <b>{{ translate('messages.Authorized redirect uris') }}</b>, {{ translate('messages.Provide the') }} <code>{{ translate('messages.Callback uri') }}</code> {{ translate('messages.From below and click') }} <b>{{ translate('messages.created') }}</b></li>
                        <li>{{ translate('messages.Copy') }} <b>{{ translate('messages.Client ID') }}</b> {{ translate('messages.and') }} <b>{{ translate('messages.Client secret') }}</b>, {{ translate('messages.Past in the input field below and') }} <b>Save</b>.</li>
                    </ol>
                </div>
                <div class="modal-footer justify-content-center border-0">
                    <button type="button" class="btn btn--primary w-100 mw-300px" data-dismiss="modal"><i class="tio-checkmark-circle-outlined"></i> {{ translate('Got it') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="facebook-modal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog status-warning-modal modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title">{{ translate('messages.Facebook api set instruction') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-0">
                    <ol class="tps-steps">
                        <li>{{ translate('messages.Go to the Facebook Developer page') }} (<a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener">{{ translate('messages.Click Here') }}</a>)</li>
                        <li>{{ translate('messages.Go to') }} <b>{{ translate('messages.Get started') }}</b> {{ translate('messages.From navbar') }}</li>
                        <li>{{ translate('messages.From register tab press') }} <b>{{ translate('messages.Continue') }}</b> <small>({{ translate('messages.If needed') }})</small></li>
                        <li>{{ translate('messages.Provide primary email and press') }} <b>{{ translate('messages.Confirm email') }}</b> <small>({{ translate('messages.If needed') }})</small></li>
                        <li>{{ translate('messages.In about section select') }} <b>{{ translate('messages.other') }}</b> {{ translate('messages.And press') }} <b>{{ translate('messages.Complete registration') }}</b></li>
                        <li><b>{{ translate('messages.Create app') }}</b> > {{ translate('messages.Select an app type and press') }} <b>{{ translate('messages.Next') }}</b></li>
                        <li>{{ translate('messages.Complete the details form and press') }} <b>{{ translate('messages.Create app') }}</b></li>
                        <li>{{ translate('messages.Press Set up on this product') }}: <b>{{ translate('messages.Facebook login') }}</b></li>
                        <li>{{ translate('Select') }} <b>{{ translate('messages.web') }}</b></li>
                        <li>{{ translate('messages.provide') }} <b>{{ translate('messages.Site url') }}</b> <small>({{ translate('messages.Base url of the site') }}: https://example.com)</small> > <b>{{ translate('messages.Save') }}</b></li>
                        <li>{{ translate('messages.Now go to') }} <b>{{ translate('messages.setting') }}</b> {{ translate('messages.form') }} <b>{{ translate('messages.Facebook login') }}</b> ({{ translate('messages.Left sidebar') }})</li>
                        <li>{{ translate('messages.Make sure to check') }} <b>{{ translate('messages.Client auth login') }}</b> <small>({{ translate('messages.Must on') }})</small></li>
                        <li>{{ translate('messages.provide') }} <code>{{ translate('messages.Valid auth redirect uris') }}</code> {{ translate('messages.From below and click') }} <b>{{ translate('messages.Save changes') }}</b></li>
                        <li>{{ translate('messages.Now go to') }} <b>{{ translate('messages.setting') }}</b> ({{ translate('messages.From left sidebar') }}) > <b>{{ translate('messages.basic') }}</b></li>
                        <li>{{ translate('messages.Fill the form and press') }} <b>{{ translate('messages.Save changes') }}</b></li>
                        <li>{{ translate('messages.Now copy') }} <b>{{ translate('messages.Client ID') }}</b> & <b>{{ translate('messages.Client secret') }}</b>, {{ translate('messages.Past in the input field below and') }} <b>{{ translate('messages.Save') }}</b>.</li>
                    </ol>
                </div>
                <div class="modal-footer justify-content-center border-0">
                    <button type="button" class="btn btn--primary w-100 mw-300px" data-dismiss="modal"><i class="tio-checkmark-circle-outlined"></i> {{ translate('Got it') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="apple-modal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog status-warning-modal modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title">{{ translate('messages.Apple api set instruction') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-0">
                    <ol class="tps-steps">
                        <li>{{ translate('Go to Apple Developer page') }} (<a href="https://developer.apple.com/account/resources/identifiers/list" target="_blank" rel="noopener">{{ translate('messages.Click Here') }}</a>)</li>
                        <li>{{ translate('Note the value in the top left corner') }}: <b>{{ translate('Team ID') }}</b> {{ translate('[Apple Developer account name - team ID]') }}</li>
                        <li>Click Plus icon -> select App IDs -> click on Continue</li>
                        <li>{{ translate('Put a description and an identifier (the identifier used for the app)') }} <b>{{ translate('Client ID') }}</b></li>
                        <li>{{ translate('Download the key file and store it safely — it is used for push notifications.') }} <code>AuthKey_ID.p8</code></li>
                        <li>{{ translate('Click the Plus icon again, select Service IDs, then click Continue') }}</li>
                        <li>{{ translate('Put a description and an identifier, then click Continue') }}</li>
                        <li>{{ translate('Download the file in device named') }} <b>AuthKey_KeyID.p8</b> ({{ translate('This is the service key ID file, and the part after AuthKey is the key ID') }})</li>
                    </ol>
                </div>
                <div class="modal-footer justify-content-center border-0">
                    <button type="button" class="btn btn--primary w-100 mw-300px" data-dismiss="modal"><i class="tio-checkmark-circle-outlined"></i> {{ translate('Got it') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="twitter-modal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title">{{ translate('messages.Twitter api set up instructions') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">{{ translate('messages.Instruction will be available very soon') }}</div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn--primary" data-dismiss="modal"><i class="tio-clear"></i> {{ translate('messages.Close') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    @include('admin-views.business-settings.partials.third-party-scripts')
@endpush
