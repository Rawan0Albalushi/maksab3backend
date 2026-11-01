@extends('layouts.admin.app')

@section('title', translate('AI configuration'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/openai-config.css') }}">
@endpush

@section('3rd_party')
    active
@endsection
@section('openAI')
    active
@endsection

@section('content')
    @php
        /*
         * openai_config is one JSON row holding the two credentials and the master
         * status. Read through the cached helper rather than the table — every
         * write clears that cache, so it is never behind the form.
         */
        $openai = \App\CentralLogics\Helpers::get_business_settings('openai_config');
        $openai = is_string($openai) ? json_decode($openai, true) : $openai;
        $openai = is_array($openai) ? $openai : [];

        $api_key = trim((string) ($openai['OPENAI_API_KEY'] ?? ''));
        $organization = trim((string) ($openai['OPENAI_ORGANIZATION'] ?? ''));
        $is_configured = $api_key !== '';
        $is_on = (int) ($openai['status'] ?? 0) === 1;

        /*
         * Enough of the key to tell two of them apart, never enough to use one.
         * Demo mode blanks the stored values, so it only ever gets the mask.
         */
        $key_preview = ! $is_configured
            ? ''
            : (getEnvMode() === 'demo'
                ? 'sk-••••••••••••'
                : substr($api_key, 0, 7).'••••••'.substr($api_key, -4));

        $ai_addon_on = addon_published_status('AI');
    @endphp

    <div class="content container-fluid tps oai">
        <div class="tps-head">
            <div class="tps-head__title">
                <span class="tps-head__icon"><i class="tio-robot"></i></span>
                <span class="tps-head__text">
                    <h1>{{ translate('AI configuration') }}</h1>
                    <p>{{ translate('Connect your own OpenAI account so the panel and apps can generate content.') }}</p>
                </span>
            </div>

            <button type="button" class="tps-help" data-toggle="modal" data-target="#openai-help-modal">
                <i class="tio-help-outlined"></i>
                <span>{{ translate('How it works') }}</span>
            </button>
        </div>

        @include('admin-views.business-settings.partials.ai-tabs', ['current' => 'config', 'isOn' => $is_on])

        <div class="oai-status {{ $is_on ? 'is-on' : '' }}">
            <span class="oai-status__brand">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M10.5 5.5l1.45 4.05a4 4 0 0 0 2.5 2.5l4.05 1.45-4.05 1.45a4 4 0 0 0-2.5 2.5L10.5 21.5l-1.45-4.05a4 4 0 0 0-2.5-2.5L2.5 13.5l4.05-1.45a4 4 0 0 0 2.5-2.5L10.5 5.5z"
                          fill="currentColor"/>
                    <path d="M18.5 2l.72 2.03a2 2 0 0 0 1.25 1.25L22.5 6l-2.03.72a2 2 0 0 0-1.25 1.25L18.5 10l-.72-2.03a2 2 0 0 0-1.25-1.25L14.5 6l2.03-.72a2 2 0 0 0 1.25-1.25L18.5 2z"
                          fill="currentColor" opacity=".5"/>
                </svg>
            </span>

            <div class="oai-status__body">
                <div class="oai-status__title-row">
                    <h2 class="oai-status__title">OpenAI</h2>
                    @if (! $is_configured)
                        <span class="tps-pill tps-pill--warn">{{ translate('Not configured') }}</span>
                    @elseif ($is_on)
                        <span class="tps-pill tps-pill--on">{{ translate('Active') }}</span>
                    @else
                        <span class="tps-pill tps-pill--off">{{ translate('Turned off') }}</span>
                    @endif
                </div>

                <div class="oai-status__meta">
                    @if ($is_configured)
                        <span class="oai-meta">
                            <i class="tio-key"></i>
                            <span class="oai-meta__value">{{ $key_preview }}</span>
                        </span>
                        @if ($organization !== '')
                            <span class="oai-meta">
                                <i class="tio-briefcase-outlined"></i>
                                <span class="oai-meta__value">{{ getEnvMode() === 'demo' ? 'org-••••••••' : $organization }}</span>
                            </span>
                        @endif
                    @else
                        <span>{{ translate('No API key saved yet — add one below to connect this platform to OpenAI.') }}</span>
                    @endif
                </div>
            </div>

            {{-- The switch submits this form straight away; the credentials form below is separate. --}}
            <form action="{{ route('admin.business-settings.openAIConfigStatus') }}" method="get"
                  id="openai_status_form" class="oai-status__switch">
                <span class="oai-status__switch-label">
                    {{ $is_on ? translate('Switched on') : translate('Switched off') }}
                </span>
                <label class="toggle-switch toggle-switch-sm m-0 p-0">
                    <input type="checkbox"
                           id="openai_status"
                           data-id="openai_status"
                           data-type="status"
                           data-image-on="{{ asset('public/assets/admin/img/modal/mail-success.png') }}"
                           data-image-off="{{ asset('public/assets/admin/img/modal/mail-warning.png') }}"
                           data-title-on="<strong>{{ translate('Turn AI on?') }}</strong>"
                           data-title-off="<strong>{{ translate('Turn AI off?') }}</strong>"
                           data-text-on="<p>{{ translate('AI generation runs across the admin panel, vendor panel and apps, billed to this key OpenAI account.') }}</p>"
                           data-text-off="<p>{{ translate('AI features stop and billing ends. Your API key stays saved for switching back on.') }}</p>"
                           class="status toggle-switch-input dynamic-checkbox"
                           name="status" value="1" {{ $is_on ? 'checked' : '' }}>
                    <span class="toggle-switch-label text p-0">
                        <span class="toggle-switch-indicator"></span>
                    </span>
                </label>
            </form>
        </div>

        @if (! $is_configured)
            <div class="tps-note tps-note--warn mb-3">
                <i class="tio-info-outined"></i>
                <div>
                    {{ translate('Save your API key first. Switching AI on without one leaves every AI request failing.') }}
                </div>
            </div>
        @elseif (! $is_on)
            <div class="tps-note tps-note--info mb-3">
                <i class="tio-info"></i>
                <div>
                    {{ translate('Credentials are saved but AI is off, so nothing is sent or billed. Use the switch above.') }}
                </div>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-xl-7">
                <div class="tps-card oai-card">
                    <div class="tps-card__head">
                        <span class="tps-card__brand oai-card__icon"><i class="tio-lock-outlined"></i></span>
                        <div class="tps-card__titles">
                            <h2 class="tps-card__title">{{ translate('API credentials') }}</h2>
                            <p class="tps-card__subtitle">
                                {{ translate('Taken from your own OpenAI account. They are stored on this server and never shown to vendors or customers.') }}
                            </p>
                        </div>
                    </div>

                    <form action="{{ route('admin.business-settings.openAIConfigUpdate') }}" method="post"
                          class="oai-card__form">
                        @csrf

                        <div class="tps-card__body">
                            <div class="tps-field mb-4">
                                <label class="tps-field__label" for="openai_api_key">
                                    {{ translate('OpenAI API Key') }} <span class="tps-req">*</span>
                                </label>
                                <div class="tps-input-wrap has-two-actions">
                                    <input type="password" id="openai_api_key" name="OPENAI_API_KEY"
                                           class="form-control" required autocomplete="off" spellcheck="false"
                                           placeholder="{{ translate('messages.Ex') }}: sk-proj-K0LhsdcbHJ......."
                                           value="{{ showDemoModeInputValue(value: $api_key) }}">
                                    <button type="button" class="tps-input-action tps-copy"
                                            data-target="#openai_api_key"
                                            aria-label="{{ translate('messages.Copy') }}">
                                        <i class="tio-copy"></i>
                                    </button>
                                    <button type="button" class="tps-input-action tps-toggle-secret"
                                            data-target="#openai_api_key"
                                            aria-label="{{ translate('Show value') }}">
                                        <i class="tio-visible"></i>
                                    </button>
                                </div>
                                <small class="tps-field__hint">
                                    {{ translate('Created under Dashboard → API keys. OpenAI shows the full key once, so copy it before leaving.') }}
                                </small>
                            </div>

                            <div class="tps-field">
                                <label class="tps-field__label" for="openai_organization">
                                    {{ translate('OpenAI Organization') }} <span class="tps-req">*</span>
                                </label>
                                <div class="tps-input-wrap">
                                    <input type="text" id="openai_organization" name="OPENAI_ORGANIZATION"
                                           class="form-control" required autocomplete="off" spellcheck="false"
                                           placeholder="{{ translate('messages.Ex') }}: org-xxxxxxxxxxx"
                                           value="{{ showDemoModeInputValue(value: $organization) }}">
                                    <button type="button" class="tps-input-action tps-copy"
                                            data-target="#openai_organization"
                                            aria-label="{{ translate('messages.Copy') }}">
                                        <i class="tio-copy"></i>
                                    </button>
                                </div>
                                <small class="tps-field__hint">
                                    {{ translate('Found under Settings → Organization on the OpenAI platform. It decides which organization the usage is billed to.') }}
                                </small>
                            </div>
                        </div>

                        <div class="tps-card__foot">
                            <span class="tps-foot-note">
                                {{ translate('Saving replaces the stored credentials.') }}
                            </span>
                            <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                            <button type="{{ getDemoModeFormButton(type: 'button') }}"
                                    class="btn btn--primary {{ getDemoModeFormButton(type: 'class') }}">
                                <i class="tio-save"></i> {{ translate('messages.Save') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="tps-card oai-card">
                    <div class="tps-card__head">
                        <span class="tps-card__brand oai-card__icon"><i class="tio-apps"></i></span>
                        <div class="tps-card__titles">
                            <h2 class="tps-card__title">{{ translate('Where AI is used') }}</h2>
                            <p class="tps-card__subtitle">
                                {{ translate('What the switch above turns on across the platform.') }}
                            </p>
                        </div>
                    </div>

                    <div class="tps-card__body">
                        <ul class="oai-uses">
                            <li class="oai-use">
                                <span class="oai-use__icon"><i class="tio-magic-wand"></i></span>
                                <div class="oai-use__body">
                                    <h3 class="oai-use__title">{{ translate('Content generation') }}</h3>
                                    <p class="oai-use__desc">
                                        {{ translate('Titles, descriptions, SEO fields and variations filled in from the admin and vendor panels.') }}
                                        <a class="oai-link" href="{{ route('admin.business-settings.openAISettings') }}">
                                            {{ translate('Set the vendor limit') }} <i class="tio-chevron-right"></i>
                                        </a>
                                    </p>
                                </div>
                            </li>

                            <li class="oai-use">
                                <span class="oai-use__icon"><i class="tio-image"></i></span>
                                <div class="oai-use__body">
                                    <h3 class="oai-use__title">{{ translate('Generate from an image') }}</h3>
                                    <p class="oai-use__desc">
                                        {{ translate('An uploaded photo is read back as ready-to-edit product details.') }}
                                    </p>
                                </div>
                            </li>

                            @if ($ai_addon_on)
                                <li class="oai-use">
                                    <span class="oai-use__icon"><i class="tio-comment-text-outlined"></i></span>
                                    <div class="oai-use__body">
                                        <h3 class="oai-use__title">
                                            {{ translate('AI Chat Agent') }}
                                            <span class="oai-use__badge">{{ translate('Add-on') }}</span>
                                        </h3>
                                        <p class="oai-use__desc">
                                            {{ translate('A conversational assistant for customers. It stays offline until this key and the agent switch are both on.') }}
                                        </p>
                                    </div>
                                </li>
                            @endif
                        </ul>

                        <div class="tps-note tps-note--muted mt-3">
                            <i class="tio-credit-card-outlined"></i>
                            <div>
                                {{ translate('Requests are billed to your own OpenAI account. Set a monthly spend limit there before switching AI on.') }}
                                <a class="oai-link d-block mt-1" href="https://platform.openai.com/usage" target="_blank"
                                   rel="noopener noreferrer">
                                    {{ translate('Open the OpenAI usage dashboard') }} <i class="tio-open-in-new"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="openai-help-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Connecting your OpenAI account') }}</h5>
                    <button type="button" class="close btn btn--reset btn-circle" data-dismiss="modal"
                            aria-label="{{ translate('Close') }}">
                        <span aria-hidden="true" class="tio-clear fs-20 opacity-70"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <ol class="tps-steps mb-3">
                        <li>
                            {{ translate('Sign in at') }}
                            <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">platform.openai.com</a>
                            and open Dashboard → API keys.
                        </li>
                        <li>{{ translate('Create a new secret key and copy it — the full value is shown only once.') }}</li>
                        <li>{{ translate('Copy your organization ID from Settings → Organization.') }}</li>
                        <li>{{ translate('Paste both into the credentials card and press Save.') }}</li>
                        <li>{{ translate('Switch AI on. Generation buttons then appear across the admin panel, the vendor panel and the apps.') }}</li>
                    </ol>
                    <div class="tps-note tps-note--warn">
                        <i class="tio-info-outined"></i>
                        <div>
                            {{ translate('Usage bills to the OpenAI account that owns the key. Set a payment method and monthly limit there.') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    @include('admin-views.business-settings.partials.third-party-scripts')
@endpush
