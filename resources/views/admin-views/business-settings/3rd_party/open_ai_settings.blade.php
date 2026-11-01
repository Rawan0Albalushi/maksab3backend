@extends('layouts.admin.app')

@section('title', translate('AI Settings'))

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
        /* Only the master status is needed here — the credentials live on the sibling tab. */
        $openai = \App\CentralLogics\Helpers::get_business_settings('openai_config');
        $openai = is_string($openai) ? json_decode($openai, true) : $openai;
        $ai_is_on = (int) data_get($openai, 'status') === 1;

        $ai_addon_on = addon_published_status('AI');
        $ai_chat_status = (int) ($data['ai_chat_status'] ?? 0) === 1;

        /*
         * AiModule::isChatActive() needs both switches, so a chat agent left on
         * while AI is off is neither active nor off — it is waiting on the other
         * tab, and the pill says so rather than claiming customers can use it.
         */
        $chat_is_live = $ai_chat_status && $ai_is_on;

        /*
         * Personalization is the other AI-gated customer feature, but it is saved
         * from Customer Settings. Shown read-only here so this page is a complete
         * answer to "what is AI doing on my platform".
         */
        $personalization_on = (int) (\App\CentralLogics\Helpers::get_business_settings('customer_personalization_status') ?? 0) === 1;
        $customer_settings_url = route('admin.business-settings.business-setup', ['tab' => 'customer']).'#customer-personalization';
    @endphp

    <div class="content container-fluid tps oai">
        <div class="tps-head">
            <div class="tps-head__title">
                <span class="tps-head__icon"><i class="tio-tune-horizontal"></i></span>
                <span class="tps-head__text">
                    <h1>{{ translate('AI Settings') }}</h1>
                    <p>{{ translate('Choose which AI features are available and how often vendors can use them.') }}</p>
                </span>
            </div>

            <button type="button" class="tps-help" data-toggle="modal" data-target="#ai-settings-help-modal">
                <i class="tio-help-outlined"></i>
                <span>{{ translate('How it works') }}</span>
            </button>
        </div>

        @include('admin-views.business-settings.partials.ai-tabs', ['current' => 'settings', 'isOn' => $ai_is_on])

        @if (! $ai_is_on)
            <div class="tps-note tps-note--warn mb-3">
                <i class="tio-info-outined"></i>
                <div>
                    {{ translate('AI is off, so nothing here takes effect yet. Settings are saved and apply once AI is on.') }}
                    <a class="oai-link" href="{{ route('admin.business-settings.openAI') }}">
                        {{ translate('Go to AI Configuration') }} <i class="tio-chevron-right"></i>
                    </a>
                </div>
            </div>
        @endif

        <form action="{{ route('admin.business-settings.openAISettingsUpdate') }}" method="post">
            @csrf
            @method('put')

            @if ($ai_addon_on)
                <div class="oai-section">
                    <div class="oai-section__head">
                        <h2 class="oai-section__title">{{ translate('Customer experience') }}</h2>
                        <p class="oai-section__desc">{{ translate('AI features your customers see in the app and on the website.') }}</p>
                    </div>

                    <div class="tps-card mb-3">
                        <div class="tps-card__head">
                            <span class="tps-card__brand oai-card__icon"><i class="tio-comment-text-outlined"></i></span>

                            <div class="tps-card__titles">
                                <div class="oai-status__title-row">
                                    <h3 class="tps-card__title">{{ translate('AI Chat Agent') }}</h3>
                                    @if ($ai_chat_status && ! $ai_is_on)
                                        <span class="tps-pill tps-pill--warn">{{ translate('Waiting on AI configuration') }}</span>
                                    @elseif ($chat_is_live)
                                        <span class="tps-pill tps-pill--on">{{ translate('Active') }}</span>
                                    @else
                                        <span class="tps-pill tps-pill--off">{{ translate('Turned off') }}</span>
                                    @endif
                                </div>
                                <p class="tps-card__subtitle">
                                    {{ translate('Let customers ask questions and get conversational replies in the app and on the website.') }}
                                </p>
                            </div>

                            <div class="tps-card__aside">
                                {{-- dynamic-checkbox-toggle only stages the flip; the sticky Save bar applies it. --}}
                                <label class="toggle-switch toggle-switch-sm m-0 p-0">
                                    <input type="checkbox"
                                           id="ai_chat_status"
                                           data-id="ai_chat_status"
                                           data-type="toggle"
                                           data-image-on="{{ asset('public/assets/admin/img/modal/mail-success.png') }}"
                                           data-image-off="{{ asset('public/assets/admin/img/modal/mail-warning.png') }}"
                                           data-title-on="<strong>{{ translate('Turn on the AI Chat Agent?') }}</strong>"
                                           data-title-off="<strong>{{ translate('Turn off the AI Chat Agent?') }}</strong>"
                                           data-text-on="<p>{{ translate('Lets customers chat with the AI assistant in the app and on the website. Requires a valid OpenAI key.') }}</p>"
                                           data-text-off="<p>{{ translate('The assistant stops responding in the app and website. Saved conversations return when you switch it back on.') }}</p>"
                                           class="status toggle-switch-input dynamic-checkbox-toggle"
                                           name="ai_chat_status" value="1" {{ $ai_chat_status ? 'checked' : '' }}>
                                    <span class="toggle-switch-label text p-0">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="tps-card__body">
                            <div class="tps-note tps-note--muted">
                                <i class="tio-info"></i>
                                <div>
                                    {{ translate('Flipping this switch does not save — press Save below. Existing conversations return when the agent is back on.') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tps-card">
                        <div class="tps-card__head">
                            <span class="tps-card__brand oai-card__icon"><i class="tio-user-outlined"></i></span>

                            <div class="tps-card__titles">
                                <div class="oai-status__title-row">
                                    <h3 class="tps-card__title">{{ translate('AI Personalization') }}</h3>
                                    @if ($personalization_on)
                                        <span class="tps-pill tps-pill--on">{{ translate('Active') }}</span>
                                    @else
                                        <span class="tps-pill tps-pill--off">{{ translate('Turned off') }}</span>
                                    @endif
                                </div>
                                <p class="tps-card__subtitle">
                                    {{ translate('Orders items, stores and categories by relevance for each customer. Saved from Customer Settings.') }}
                                </p>
                            </div>

                            <div class="tps-card__aside">
                                <a class="tps-help" href="{{ $customer_settings_url }}">
                                    <span>{{ translate('Open Customer Settings') }}</span>
                                    <i class="tio-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="oai-section">
                <div class="oai-section__head">
                    <h2 class="oai-section__title">{{ translate('Vendor usage limits') }}</h2>
                    <p class="oai-section__desc">{{ translate('How much AI each store may spend on your OpenAI account.') }}</p>
                </div>

                <div class="tps-card">
                    <div class="tps-card__head">
                        <span class="tps-card__brand oai-card__icon"><i class="tio-shop-outlined"></i></span>
                        <div class="tps-card__titles">
                            <h3 class="tps-card__title">{{ translate('Vendor limits on using AI') }}</h3>
                            <p class="tps-card__subtitle">
                                {{ translate('Each store gets a lifetime allowance that never resets. Generating from the admin panel is not counted against it.') }}
                            </p>
                        </div>
                    </div>

                    <div class="tps-card__body">
                        <div class="oai-limit">
                            <div class="row g-3 align-items-center">
                                <div class="col-lg-7">
                                    <h4 class="oai-limit__label">
                                        <i class="tio-magic-wand"></i>
                                        {{ translate('Section wise data generation') }}
                                    </h4>
                                    <p class="oai-limit__desc">
                                        {{ translate('Set how many times AI can generate data for each element of the vendor panel or app') }}
                                    </p>
                                </div>
                                <div class="col-lg-5">
                                    <div class="tps-field oai-limit__field">
                                        <label class="tps-field__label" for="section_wise_ai_limit">
                                            {{ translate('Total generations per store') }} <span class="tps-req">*</span>
                                        </label>
                                        <input id="section_wise_ai_limit" type="number" min="0" max="99999999999" required
                                               class="form-control" name="section_wise_ai_limit"
                                               value="{{ $data['section_wise_ai_limit'] ?? '' }}">
                                        <small class="tps-field__hint">
                                            {{ translate('Enter zero to block text generation for vendors entirely.') }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="oai-limit">
                            <div class="row g-3 align-items-center">
                                <div class="col-lg-7">
                                    <h4 class="oai-limit__label">
                                        <i class="tio-image"></i>
                                        {{ translate('Image based data generation') }}
                                    </h4>
                                    <p class="oai-limit__desc">
                                        {{ translate('Set how many times AI can generate data from an image upload') }}
                                    </p>
                                </div>
                                <div class="col-lg-5">
                                    <div class="tps-field oai-limit__field">
                                        <label class="tps-field__label" for="image_upload_limit_for_ai">
                                            {{ translate('Total image reads per store') }} <span class="tps-req">*</span>
                                        </label>
                                        <input id="image_upload_limit_for_ai" type="number" min="0" max="99999999999" required
                                               class="form-control" name="image_upload_limit_for_ai"
                                               value="{{ $data['image_upload_limit_for_ai'] ?? '' }}">
                                        <small class="tps-field__hint">
                                            {{ translate('Counted separately from text. Enter zero to block image generation entirely.') }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tps-card__foot">
                        <span class="tps-foot-note">
                            {{ translate('A store that reaches its allowance loses the generate button until you raise the number here.') }}
                        </span>
                    </div>
                </div>
            </div>

            @include('admin-views.partials._floating-submit-button', [
                'submitButtonText' => translate('messages.Save'),
                'resetButtonText' => translate('messages.Reset'),
            ])
        </form>
    </div>

    <div class="modal fade" id="ai-settings-help-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Setting up AI for your platform') }}</h5>
                    <button type="button" class="close btn btn--reset btn-circle" data-dismiss="modal"
                            aria-label="{{ translate('Close') }}">
                        <span aria-hidden="true" class="tio-clear fs-20 opacity-70"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <ol class="tps-steps mb-3">
                        <li>{{ translate('Connect your OpenAI account on the AI Configuration tab and switch AI on.') }}</li>
                        <li>{{ translate('Choose which customer-facing AI features run on your platform.') }}</li>
                        <li>{{ translate('Set how many generations each store may run before the button stops working for them.') }}</li>
                        <li>{{ translate('Press Save. Switches on this page are only staged until you do.') }}</li>
                    </ol>
                    <div class="tps-note tps-note--warn">
                        <i class="tio-info-outined"></i>
                        <div>
                            {{ translate('Allowances are a running per-store total that never resets, billed to your OpenAI account. Start low.') }}
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
